<?php
/**
 * WHMCS Mail Provider — SMTP + ký S/MIME
 *
 * @package     HiTechCloud S/MIME Mail Signing
 * @author      Pho Tue Software Solutions JSC <info@photuesoftware.com>
 * @copyright   Copyright (c) 2026 Pho Tue Software Solutions Joint Stock Company
 * @license     HiTechCloud Source-Available License (Non-Commercial) — see LICENSE
 * @link        https://github.com/hitechcloud-vietnam/smime-mail-signing
 * @version     1.0.0
 *
 * CẤM sử dụng thương mại, bán, bán lại hoặc phân phối lại phần mềm này.
 * Commercial use, resale, and redistribution are PROHIBITED.
 * Liên hệ info@photuesoftware.com để xin giấy phép thương mại.
 */

/**
 * Cài đặt:
 *   1. Tạo thư mục: modules/mail/smimesmtp/
 *   2. Đặt file này tại: modules/mail/smimesmtp/smimesmtp.php
 *   3. WHMCS Admin → Settings → General Settings → Mail → chọn "SMTP + S/MIME Sign"
 * Yêu cầu: WHMCS 8.0+, PHP có extension openssl
 */

namespace WHMCS\Module\Mail;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use WHMCS\Authentication\CurrentUser;
use WHMCS\Exception\Mail\SendFailure;
use WHMCS\Mail\Message;
use WHMCS\Module\Contracts\SenderModuleInterface;
use WHMCS\Module\MailSender\DescriptionTrait;

class Smimesmtp implements SenderModuleInterface
{
    use DescriptionTrait;

    public function getName()
    {
        return 'Smimesmtp';
    }

    public function getDisplayName()
    {
        return 'SMTP + S/MIME Sign';
    }

    public function settings()
    {
        return [
            'host' => [
                'FriendlyName' => 'SMTP Host',
                'Type' => 'text',
                'Description' => 'Ví dụ: mail.bnix.vn',
            ],
            'port' => [
                'FriendlyName' => 'SMTP Port',
                'Type' => 'text',
                'Default' => '587',
                'Description' => '587 (STARTTLS) hoặc 465 (SMTPS)',
            ],
            'encryption' => [
                'FriendlyName' => 'Mã hoá',
                'Type' => 'dropdown',
                'Options' => 'tls,ssl,none',
                'Default' => 'tls',
            ],
            'username' => [
                'FriendlyName' => 'SMTP Username',
                'Type' => 'text',
            ],
            'password' => [
                'FriendlyName' => 'SMTP Password',
                'Type' => 'password',
            ],
            'cert_path' => [
                'FriendlyName' => 'Đường dẫn Certificate',
                'Type' => 'text',
                'Description' => 'Tuyệt đối, ví dụ: /home/admin/smime/smime.crt',
            ],
            'key_path' => [
                'FriendlyName' => 'Đường dẫn Private Key',
                'Type' => 'text',
                'Description' => 'Tuyệt đối, ví dụ: /home/admin/smime/smime-hb.key',
            ],
            'key_pass' => [
                'FriendlyName' => 'Passphrase của Key',
                'Type' => 'password',
                'Description' => 'Để trống nếu key không mã hoá',
            ],
            'ca_path' => [
                'FriendlyName' => 'Đường dẫn CA Chain',
                'Type' => 'text',
                'Description' => 'Chỉ chứa cert CA, không có cert leaf',
            ],
            'sign_from' => [
                'FriendlyName' => 'Chỉ ký khi From là',
                'Type' => 'text',
                'Description' => 'Địa chỉ khớp SAN trong cert. Để trống = ký mọi mail.',
            ],
            'from_name' => [
                'FriendlyName' => 'Tên người gửi',
                'Type' => 'text',
                'Description' => 'Ví dụ: BNIX Support. Ghi đè tên mặc định của WHMCS. '
                    . 'Để trống = giữ nguyên tên WHMCS gửi sang.',
            ],
            'from_email' => [
                'FriendlyName' => 'Ghi đè địa chỉ gửi',
                'Type' => 'text',
                'Description' => 'Để trống = dùng địa chỉ WHMCS gửi sang. '
                    . 'Chỉ điền khi SMTP bắt buộc gửi từ một địa chỉ cố định.',
            ],
            'test_to' => [
                'FriendlyName' => 'Địa chỉ nhận mail test',
                'Type' => 'text',
                'Description' => 'Hộp thư có thật để nhận mail kiểm tra. '
                    . 'Để trống = dùng email của admin đang đăng nhập.',
            ],
        ];
    }

    /**
     * Kiểm tra cấu hình và gửi mail test tới admin đang đăng nhập.
     */
    public function testConnection(array $settings)
    {
        // Bước 1: kiểm tra chứng chỉ trước, lỗi ở đây không liên quan SMTP.
        $this->validateCertificate($settings);

        // Bước 2: xác định địa chỉ nhận.
        $recipient = trim((string) ($settings['test_to'] ?? ''));
        if ($recipient === '') {
            $admin = (new CurrentUser)->admin();
            $recipient = $admin ? $admin->email : '';
        }
        if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new SendFailure(
                'Chưa có địa chỉ nhận mail test hợp lệ. Điền vào ô "Địa chỉ nhận mail test", '
                . 'hoặc sửa email trong hồ sơ admin tại Configuration → Administrator Users.'
            );
        }

        // Bước 3: xác định địa chỉ gửi.
        $sender = trim((string) ($settings['sign_from'] ?? ''));
        if ($sender === '') {
            $sender = trim((string) ($settings['username'] ?? ''));
        }
        if (!filter_var($sender, FILTER_VALIDATE_EMAIL)) {
            throw new SendFailure(
                'Không xác định được địa chỉ gửi. Điền ô "Chỉ ký khi From là" '
                . 'bằng địa chỉ khớp SAN trong certificate.'
            );
        }

        try {
            $mail = $this->buildMailer($settings);
            $mail->setFrom($sender, 'WHMCS S/MIME Test');
            $mail->addAddress($recipient);
            $mail->Subject = 'Test S/MIME — ' . date('Y-m-d H:i:s');
            $mail->isHTML(true);
            $mail->Body = '<p>Mail này được ký bằng S/MIME. '
                . 'Nếu client hiển thị biểu tượng chữ ký hợp lệ thì cấu hình đã đúng.</p>';
            $mail->AltBody = 'Mail nay duoc ky bang S/MIME.';

            $this->applySignature($mail, $settings, $sender);

            if (!$mail->send()) {
                throw new SendFailure($this->explainSmtpError($mail->ErrorInfo, $recipient, $sender));
            }
        } catch (PHPMailerException $e) {
            throw new SendFailure($this->explainSmtpError($e->getMessage(), $recipient, $sender));
        }
    }

    /**
     * Diễn giải lỗi SMTP thành thông báo có hướng xử lý cụ thể.
     */
    private function explainSmtpError($error, $recipient, $sender)
    {
        $error = (string) $error;

        if (stripos($error, 'No such recipient') !== false
            || stripos($error, 'recipients failed') !== false
            || stripos($error, 'User unknown') !== false
            || stripos($error, '550') !== false
        ) {
            return "Mail server từ chối địa chỉ nhận [{$recipient}] — hộp thư này không tồn tại. "
                . 'Kiểm tra chính tả, hoặc điền một hộp thư có thật vào ô "Địa chỉ nhận mail test". '
                . "Chi tiết: {$error}";
        }

        if (stripos($error, 'authenticate') !== false || stripos($error, '535') !== false) {
            return "Sai username hoặc password SMTP. Chi tiết: {$error}";
        }

        if (stripos($error, 'connect') !== false || stripos($error, 'Connection refused') !== false) {
            return 'Không kết nối được tới SMTP host — kiểm tra host, port và firewall. '
                . "Chi tiết: {$error}";
        }

        if (stripos($error, 'sender') !== false || stripos($error, '553') !== false) {
            return "Mail server từ chối địa chỉ gửi [{$sender}] — tài khoản SMTP có thể không được "
                . "phép gửi dưới địa chỉ này. Chi tiết: {$error}";
        }

        return "Gửi thất bại: {$error}";
    }

    /**
     * Gửi mail thật.
     */
    public function send(array $settings, Message $message)
    {
        try {
            $mail = $this->buildMailer($settings);

            $fromEmail = $message->getFromEmail();
            $fromName = $message->getFromName();

            if (!empty($settings['from_email'])
                && filter_var($settings['from_email'], FILTER_VALIDATE_EMAIL)
            ) {
                // Giữ địa chỉ gốc làm Reply-To để khách trả lời đúng chỗ.
                if ($fromEmail && strcasecmp($fromEmail, $settings['from_email']) !== 0) {
                    $mail->addReplyTo($fromEmail, $fromName);
                }
                $fromEmail = $settings['from_email'];
            }

            if (!empty($settings['from_name'])) {
                $fromName = $settings['from_name'];
            }

            $mail->setFrom($fromEmail, $fromName);

            foreach ($message->getRecipients('to') as $to) {
                $mail->addAddress($to[0], isset($to[1]) ? $to[1] : '');
            }
            foreach ($message->getRecipients('cc') as $cc) {
                $mail->addCC($cc[0], isset($cc[1]) ? $cc[1] : '');
            }
            foreach ($message->getRecipients('bcc') as $bcc) {
                $mail->addBCC($bcc[0], isset($bcc[1]) ? $bcc[1] : '');
            }

            $replyTo = $message->getReplyTo();
            if (!empty($replyTo['email'])) {
                $mail->addReplyTo($replyTo['email'], isset($replyTo['name']) ? $replyTo['name'] : '');
            }

            $mail->Subject = $message->getSubject();

            $body = $message->getBody();
            $plain = $message->getPlainText();
            if ($body) {
                $mail->isHTML(true);
                $mail->Body = $body;
                $mail->AltBody = $plain ?: strip_tags($body);
            } else {
                $mail->isHTML(false);
                $mail->Body = $plain;
            }

            foreach ($message->getAttachments() as $attachment) {
                if (array_key_exists('data', $attachment)) {
                    $mail->addStringAttachment($attachment['data'], $attachment['filename']);
                } else {
                    $mail->addAttachment($attachment['filepath'], $attachment['filename']);
                }
            }

            $signed = $this->applySignature($mail, $settings, $fromEmail);

            if (!$mail->send()) {
                $first = $message->getRecipients('to');
                $to = isset($first[0][0]) ? $first[0][0] : '';
                $reason = $this->explainSmtpError($mail->ErrorInfo, $to, $fromEmail);
                $this->log($fromEmail, $to, $message->getSubject(), $signed, 'failed', $reason);
                throw new SendFailure($reason);
            }

            $first = $message->getRecipients('to');
            $this->log(
                $fromEmail,
                isset($first[0][0]) ? $first[0][0] : '',
                $message->getSubject(),
                $signed,
                'sent',
                ''
            );
        } catch (PHPMailerException $e) {
            $this->log($fromEmail ?? '', '', $message->getSubject(), false, 'failed', $e->getMessage());
            throw new SendFailure('Lỗi gửi mail: ' . $e->getMessage());
        }
    }

    /**
     * Khởi tạo PHPMailer với cấu hình SMTP.
     */
    private function buildMailer(array $settings)
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $settings['host'];
        $mail->Port = (int) ($settings['port'] ?: 587);
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->Encoding = PHPMailer::ENCODING_BASE64;

        if (!empty($settings['username'])) {
            $mail->SMTPAuth = true;
            $mail->Username = $settings['username'];
            $mail->Password = $settings['password'];
        }

        $enc = isset($settings['encryption']) ? $settings['encryption'] : 'tls';
        if ($enc === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($enc === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        }

        return $mail;
    }

    /**
     * Gắn chữ ký S/MIME nếu địa chỉ gửi khớp điều kiện.
     */
    private function applySignature(PHPMailer $mail, array $settings, $fromEmail)
    {
        $restrict = trim((string) ($settings['sign_from'] ?? ''));
        if ($restrict !== '' && strcasecmp($restrict, (string) $fromEmail) !== 0) {
            // From không khớp cert — gửi bình thường, không ký.
            return false;
        }

        $this->validateCertificate($settings);

        // PHPMailer::sign($cert, $key, $keyPass, $extraCerts)
        $mail->sign(
            $settings['cert_path'],
            $settings['key_path'],
            (string) $settings['key_pass'],
            $settings['ca_path'] ?: ''
        );

        return true;
    }

    /**
     * Ghi log vào bảng của addon SMIME Monitor, nếu addon đã cài.
     * Không bao giờ làm hỏng luồng gửi mail nếu ghi log thất bại.
     */
    private function log($from, $to, $subject, $signed, $status, $error)
    {
        try {
            if (!class_exists('\WHMCS\Database\Capsule')) {
                return;
            }
            $schema = \WHMCS\Database\Capsule::schema();
            if (!$schema->hasTable('mod_smime_log')) {
                return;
            }
            \WHMCS\Database\Capsule::table('mod_smime_log')->insert([
                'sent_at' => date('Y-m-d H:i:s'),
                'from_email' => mb_substr((string) $from, 0, 190),
                'to_email' => mb_substr((string) $to, 0, 190),
                'subject' => mb_substr((string) $subject, 0, 250),
                'signed' => $signed ? 1 : 0,
                'status' => $status,
                'error' => mb_substr((string) $error, 0, 500),
            ]);
        } catch (\Exception $e) {
            // Bỏ qua: log hỏng không được phép chặn mail.
        }
    }

    /**
     * Kiểm tra file cert/key đọc được và passphrase đúng.
     * Ném SendFailure kèm lỗi OpenSSL cụ thể nếu sai.
     */
    private function validateCertificate(array $settings)
    {
        foreach (['cert_path' => 'Certificate', 'key_path' => 'Private key'] as $field => $label) {
            if (empty($settings[$field])) {
                throw new SendFailure("Chưa khai {$label}.");
            }
            if (!is_readable($settings[$field])) {
                throw new SendFailure(
                    "{$label} không đọc được: {$settings[$field]} — "
                    . 'kiểm tra quyền file và quyền thư mục cha (user PHP cần bit x trên thư mục).'
                );
            }
        }

        if (!empty($settings['ca_path']) && !is_readable($settings['ca_path'])) {
            throw new SendFailure("CA chain không đọc được: {$settings['ca_path']}");
        }

        $key = openssl_pkey_get_private(
            'file://' . $settings['key_path'],
            (string) $settings['key_pass']
        );
        if ($key === false) {
            $errors = [];
            while ($e = openssl_error_string()) {
                $errors[] = $e;
            }
            throw new SendFailure(
                'Không mở được private key. Thường do sai passphrase. OpenSSL: '
                . implode(' | ', $errors)
            );
        }

        $cert = openssl_x509_read(file_get_contents($settings['cert_path']));
        if ($cert === false) {
            throw new SendFailure('File certificate không hợp lệ.');
        }

        if (!openssl_x509_check_private_key($cert, $key)) {
            throw new SendFailure('Private key không khớp với certificate.');
        }

        $info = openssl_x509_parse($cert);
        if (isset($info['validTo_time_t']) && $info['validTo_time_t'] < time()) {
            throw new SendFailure(
                'Certificate đã hết hạn ngày ' . date('d/m/Y', $info['validTo_time_t'])
            );
        }
    }
}
