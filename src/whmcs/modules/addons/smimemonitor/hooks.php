<?php
/**
 * WHMCS Hooks — cảnh báo hạn chứng chỉ và widget trạng thái / WHMCS Hooks — certificate-expiry alerts and status widget
 *
 * @package     HiTechCloud S/MIME Mail Signing
 * @author      Pho Tue Software Solutions JSC <info@photuesoftware.com>
 * @copyright   Copyright (c) 2026 Pho Tue Software Solutions Joint Stock Company
 * @license     HiTechCloud Source-Available License (Non-Commercial) — see LICENSE
 * @link        https://github.com/hitechcloudvn/smime-mail-signing
 * @version     1.0.0
 *
 * CẤM sử dụng thương mại, bán, bán lại hoặc phân phối lại phần mềm này.
 * Commercial use, resale, and redistribution are PROHIBITED.
 * Liên hệ info@photuesoftware.com để xin giấy phép thương mại. / Contact info@photuesoftware.com for a commercial license.
 */

/**
 * Đặt tại: modules/addons/smimemonitor/hooks.php / Place at: modules/addons/smimemonitor/hooks.php
 * Chức năng: / Purpose:
 */

if (!defined('WHMCS')) {
    die('Không thể truy cập file này trực tiếp / This file cannot be accessed directly');
}

use WHMCS\Database\Capsule;

/**
 * Lấy cấu hình addon. / Get the addon configuration.
 */
function smimemonitor_hook_settings()
{
    try {
        $rows = Capsule::table('tbladdonmodules')
            ->where('module', 'smimemonitor')
            ->pluck('value', 'setting');
        return is_object($rows) ? $rows->toArray() : (array) $rows;
    } catch (\Exception $e) {
        return [];
    }
}

/**
 * Số ngày còn lại của certificate. Trả về null nếu không đọc được. / Days left on the certificate. Returns null if it cannot be read.
 */
function smimemonitor_hook_daysLeft(array $cfg)
{
    $path = trim((string) ($cfg['cert_path'] ?? ''));
    if ($path === '' || !is_readable($path)) {
        return null;
    }
    $cert = @openssl_x509_read(file_get_contents($path));
    if ($cert === false) {
        return null;
    }
    $info = openssl_x509_parse($cert);
    if (empty($info['validTo_time_t'])) {
        return null;
    }
    return (int) floor(($info['validTo_time_t'] - time()) / 86400);
}

/**
 * Chạy hằng ngày: cảnh báo hết hạn + dọn log. / Runs daily: expiry warnings + log cleanup.
 */
add_hook('DailyCronJob', 1, function ($vars) {
    $cfg = smimemonitor_hook_settings();
    if (empty($cfg)) {
        return;
    }

    // --- Dọn log cũ --- / --- Purge old logs ---
    $keepDays = (int) ($cfg['log_days'] ?? 90);
    if ($keepDays > 0) {
        try {
            if (Capsule::schema()->hasTable('mod_smime_log')) {
                Capsule::table('mod_smime_log')
                    ->where('sent_at', '<', date('Y-m-d H:i:s', strtotime("-{$keepDays} days")))
                    ->delete();
            }
        } catch (\Exception $e) {
            // bỏ qua / ignore
        }
    }

    // --- Cảnh báo hạn cert --- / --- Cert expiry warning ---
    $days = smimemonitor_hook_daysLeft($cfg);
    if ($days === null) {
        logActivity('S/MIME Monitor: không đọc được certificate để kiểm tra hạn. / cannot read the certificate to check expiry.');
        return;
    }

    $warnAt = (int) ($cfg['warn_days'] ?? 30);
    if ($days > $warnAt) {
        return;
    }

    // Chỉ gửi ở các mốc cụ thể, tránh spam mỗi ngày. / Send only at specific milestones to avoid daily spam.
    $milestones = [30, 14, 7, 3, 1, 0];
    if ($days > 0 && !in_array($days, $milestones, true)) {
        return;
    }

    $to = trim((string) ($cfg['alert_email'] ?? ''));
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        logActivity('S/MIME Monitor: cert còn ' . $days . ' ngày nhưng chưa khai email nhận cảnh báo. / cert has ' . $days . ' days left but no alert email is configured.');
        return;
    }

    if ($days < 0) {
        $subject = '[KHẨN] Chứng chỉ S/MIME đã hết hạn / [URGENT] S/MIME certificate has expired';
        $body = 'Chứng chỉ S/MIME đã hết hạn ' . abs($days) . ' ngày trước. '
            . 'Mail gửi đi hiện không còn chữ ký hợp lệ. Cần gia hạn ngay. '
            . '/ The S/MIME certificate expired ' . abs($days) . ' days ago. '
            . 'Outgoing mail no longer has a valid signature. Renew immediately.';
    } else {
        $subject = '[Cảnh báo] Chứng chỉ S/MIME còn ' . $days . ' ngày / [Warning] S/MIME certificate expires in ' . $days . ' days';
        $body = 'Chứng chỉ S/MIME sẽ hết hạn sau ' . $days . ' ngày. '
            . 'Vui lòng làm thủ tục gia hạn tại portal Sectigo. '
            . '/ The S/MIME certificate will expire in ' . $days . ' days. '
            . 'Please renew via the Sectigo portal.';
    }

    $body .= "\n\nFile: " . ($cfg['cert_path'] ?? '');
    $body .= "\nKiểm tra: Admin → Addons → S/MIME Monitor / Check: Admin → Addons → S/MIME Monitor";

    try {
        $mail = new \WHMCS\Mail\Message();
        $mail->setSubject($subject)
            ->setBodyAndPlainText(nl2br(htmlspecialchars($body)), $body)
            ->addRecipient('to', $to);
        $mail->send();
    } catch (\Exception $e) {
        logActivity('S/MIME Monitor: gửi cảnh báo thất bại — ' . $e->getMessage() . ' / failed to send the alert — ' . $e->getMessage());
    }

    logActivity('S/MIME Monitor: cert còn ' . $days . ' ngày, đã gửi cảnh báo tới ' . $to . ' / cert has ' . $days . ' days left, alert sent to ' . $to);
});

/**
 * Banner cảnh báo trên trang chủ admin. / Warning banner on the admin home page.
 */
add_hook('AdminHomeWidgets', 1, function () {
    return new SmimeMonitorWidget();
});

class SmimeMonitorWidget extends \WHMCS\Module\AbstractWidget
{
    protected $title = 'S/MIME';
    protected $description = 'Trạng thái chứng chỉ ký mail / Mail-signing certificate status';
    protected $weight = 60;
    protected $columns = 1;
    protected $cache = true;
    protected $cacheExpiry = 3600;
    protected $requiredPermission = 'Configure Mail Providers';

    public function getData()
    {
        $cfg = smimemonitor_hook_settings();
        $days = smimemonitor_hook_daysLeft($cfg);

        $signed = 0;
        $unsigned = 0;
        try {
            if (Capsule::schema()->hasTable('mod_smime_log')) {
                $since = date('Y-m-d H:i:s', strtotime('-7 days'));
                $signed = Capsule::table('mod_smime_log')
                    ->where('sent_at', '>=', $since)->where('signed', 1)->count();
                $unsigned = Capsule::table('mod_smime_log')
                    ->where('sent_at', '>=', $since)->where('signed', 0)->count();
            }
        } catch (\Exception $e) {
            // bỏ qua / ignore
        }

        return ['days' => $days, 'signed' => $signed, 'unsigned' => $unsigned];
    }

    public function generateOutput($data)
    {
        $days = $data['days'];

        if ($days === null) {
            return '<div class="widget-content-padded">'
                . '<span class="label label-danger">Lỗi / Error</span> '
                . 'Không đọc được certificate. Kiểm tra cấu hình addon. '
                . '/ Cannot read the certificate. Check the addon configuration.</div>';
        }

        if ($days < 0) {
            $badge = '<span class="label label-danger">Hết hạn / Expired</span>';
            $text = 'Đã hết hạn ' . abs($days) . ' ngày trước. / Expired ' . abs($days) . ' days ago.';
        } elseif ($days <= 30) {
            $badge = '<span class="label label-warning">Sắp hết hạn / Expiring soon</span>';
            $text = 'Còn ' . $days . ' ngày. / ' . $days . ' days left.';
        } else {
            $badge = '<span class="label label-success">Bình thường / Normal</span>';
            $text = 'Còn ' . $days . ' ngày. / ' . $days . ' days left.';
        }

        return '<div class="widget-content-padded">'
            . '<p>' . $badge . ' ' . $text . '</p>'
            . '<p style="margin:0;color:#888;font-size:12px;">7 ngày qua: '
            . (int) $data['signed'] . ' mail đã ký, '
            . (int) $data['unsigned'] . ' không ký'
            . ' / Last 7 days: '
            . (int) $data['signed'] . ' signed, '
            . (int) $data['unsigned'] . ' unsigned</p>'
            . '</div>';
    }
}
