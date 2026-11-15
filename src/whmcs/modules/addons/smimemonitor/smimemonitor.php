<?php
/**
 * WHMCS Addon — S/MIME Monitor
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
 *   1. Tạo thư mục: modules/addons/smimemonitor/
 *   2. Đặt file này tại: modules/addons/smimemonitor/smimemonitor.php
 *   3. Đặt file hooks.php cùng thư mục (cảnh báo cert sắp hết hạn)
 *   4. Admin → System Settings → Addon Modules → Activate
 * Yêu cầu: WHMCS 8.0+
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use WHMCS\Database\Capsule;

function smimemonitor_config()
{
    return [
        'name' => 'S/MIME Monitor',
        'description' => 'Theo dõi hạn chứng chỉ và nhật ký mail ký S/MIME.',
        'version' => '1.0',
        'author' => 'HiTechCloud',
        'fields' => [
            'cert_path' => [
                'FriendlyName' => 'Đường dẫn Certificate',
                'Type' => 'text',
                'Size' => '60',
                'Description' => 'Trùng với cấu hình trong Mail Provider',
            ],
            'key_path' => [
                'FriendlyName' => 'Đường dẫn Private Key',
                'Type' => 'text',
                'Size' => '60',
            ],
            'ca_path' => [
                'FriendlyName' => 'Đường dẫn CA Chain',
                'Type' => 'text',
                'Size' => '60',
            ],
            'warn_days' => [
                'FriendlyName' => 'Cảnh báo trước (ngày)',
                'Type' => 'text',
                'Size' => '5',
                'Default' => '30',
                'Description' => 'Gửi cảnh báo khi cert còn ít hơn số ngày này',
            ],
            'alert_email' => [
                'FriendlyName' => 'Email nhận cảnh báo',
                'Type' => 'text',
                'Size' => '40',
            ],
            'log_days' => [
                'FriendlyName' => 'Giữ log (ngày)',
                'Type' => 'text',
                'Size' => '5',
                'Default' => '90',
                'Description' => 'Log cũ hơn sẽ bị xoá tự động',
            ],
        ],
    ];
}

function smimemonitor_activate()
{
    try {
        if (!Capsule::schema()->hasTable('mod_smime_log')) {
            Capsule::schema()->create('mod_smime_log', function ($t) {
                $t->increments('id');
                $t->dateTime('sent_at')->index();
                $t->string('from_email', 190)->nullable();
                $t->string('to_email', 190)->nullable();
                $t->string('subject', 250)->nullable();
                $t->boolean('signed')->default(0)->index();
                $t->string('status', 20)->default('sent')->index();
                $t->string('error', 500)->nullable();
            });
        }
        return ['status' => 'success', 'description' => 'Đã tạo bảng mod_smime_log.'];
    } catch (\Exception $e) {
        return ['status' => 'error', 'description' => 'Lỗi tạo bảng: ' . $e->getMessage()];
    }
}

function smimemonitor_deactivate()
{
    // Giữ nguyên bảng log để không mất dữ liệu khi tạm tắt module.
    return [
        'status' => 'success',
        'description' => 'Đã tắt module. Bảng mod_smime_log vẫn được giữ lại.',
    ];
}

/**
 * Đọc thông tin chứng chỉ.
 *
 * @return array
 */
function smimemonitor_certInfo(array $vars)
{
    $result = [
        'ok' => false,
        'messages' => [],
        'subject' => '',
        'email' => '',
        'issuer' => '',
        'valid_from' => '',
        'valid_to' => '',
        'days_left' => null,
        'chain' => [],
    ];

    $certPath = trim((string) ($vars['cert_path'] ?? ''));
    if ($certPath === '') {
        $result['messages'][] = ['danger', 'Chưa khai đường dẫn certificate trong cấu hình addon.'];
        return $result;
    }
    if (!is_readable($certPath)) {
        $result['messages'][] = ['danger', 'Không đọc được certificate: ' . $certPath];
        return $result;
    }

    $cert = @openssl_x509_read(file_get_contents($certPath));
    if ($cert === false) {
        $result['messages'][] = ['danger', 'File certificate không hợp lệ.'];
        return $result;
    }

    $info = openssl_x509_parse($cert);
    $result['subject'] = $info['subject']['O'] ?? ($info['subject']['CN'] ?? '');
    $result['issuer'] = $info['issuer']['CN'] ?? '';
    $result['valid_from'] = date('d/m/Y', $info['validFrom_time_t']);
    $result['valid_to'] = date('d/m/Y', $info['validTo_time_t']);
    $result['days_left'] = (int) floor(($info['validTo_time_t'] - time()) / 86400);

    if (!empty($info['extensions']['subjectAltName'])) {
        if (preg_match('/email:([^,\s]+)/i', $info['extensions']['subjectAltName'], $m)) {
            $result['email'] = $m[1];
        }
    }

    // Trạng thái hạn
    if ($result['days_left'] < 0) {
        $result['messages'][] = ['danger', 'Certificate ĐÃ HẾT HẠN ngày ' . $result['valid_to']];
    } elseif ($result['days_left'] <= (int) ($vars['warn_days'] ?: 30)) {
        $result['messages'][] = ['warning', 'Certificate còn ' . $result['days_left'] . ' ngày. Cần gia hạn.'];
    } else {
        $result['messages'][] = ['success', 'Certificate còn hiệu lực ' . $result['days_left'] . ' ngày.'];
    }

    // Đối chiếu key
    $keyPath = trim((string) ($vars['key_path'] ?? ''));
    if ($keyPath !== '') {
        if (!is_readable($keyPath)) {
            $result['messages'][] = ['danger', 'Không đọc được private key: ' . $keyPath];
        } else {
            $result['messages'][] = ['info', 'Private key đọc được (không kiểm tra passphrase tại đây).'];
        }
    }

    // Chuỗi CA
    $caPath = trim((string) ($vars['ca_path'] ?? ''));
    if ($caPath !== '') {
        if (!is_readable($caPath)) {
            $result['messages'][] = ['danger', 'Không đọc được CA chain: ' . $caPath];
        } else {
            $raw = file_get_contents($caPath);
            preg_match_all('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $raw, $m);
            $hasLeaf = false;
            foreach ($m[0] as $pem) {
                $c = @openssl_x509_read($pem);
                if (!$c) {
                    continue;
                }
                $i = openssl_x509_parse($c);
                $cn = $i['subject']['CN'] ?? ($i['subject']['O'] ?? '?');
                $result['chain'][] = $cn;
                if (!empty($result['subject']) && $cn === $result['subject']) {
                    $hasLeaf = true;
                }
            }
            if (count($result['chain']) === 0) {
                $result['messages'][] = ['danger', 'CA chain không chứa certificate nào.'];
            } elseif ($hasLeaf) {
                $result['messages'][] = ['warning',
                    'CA chain đang chứa cả cert của bạn. Nên bỏ cert leaf, chỉ giữ cert CA.'];
            } else {
                $result['messages'][] = ['success',
                    'CA chain có ' . count($result['chain']) . ' certificate CA.'];
            }
        }
    }

    $result['ok'] = true;
    return $result;
}

function smimemonitor_output($vars)
{
    $modulelink = $vars['modulelink'];
    $action = $_GET['action'] ?? 'overview';

    echo '<ul class="nav nav-tabs admin-tabs">';
    foreach (['overview' => 'Tổng quan', 'log' => 'Nhật ký', 'tools' => 'Công cụ'] as $k => $label) {
        $active = $action === $k ? ' class="active"' : '';
        echo '<li' . $active . '><a href="' . $modulelink . '&action=' . $k . '">' . $label . '</a></li>';
    }
    echo '</ul><div style="padding:15px 0;">';

    if ($action === 'log') {
        smimemonitor_pageLog($vars);
    } elseif ($action === 'tools') {
        smimemonitor_pageTools($vars);
    } else {
        smimemonitor_pageOverview($vars);
    }

    echo '</div>';
}

function smimemonitor_pageOverview($vars)
{
    $info = smimemonitor_certInfo($vars);

    foreach ($info['messages'] as $m) {
        echo '<div class="alert alert-' . $m[0] . '">' . htmlspecialchars($m[1]) . '</div>';
    }

    if ($info['ok']) {
        echo '<table class="table table-bordered" style="max-width:800px;">';
        $rows = [
            'Tổ chức' => $info['subject'],
            'Email (SAN)' => $info['email'],
            'Cấp bởi' => $info['issuer'],
            'Hiệu lực từ' => $info['valid_from'],
            'Hết hạn' => $info['valid_to'],
            'Còn lại' => $info['days_left'] . ' ngày',
            'Chuỗi CA' => implode(' → ', $info['chain']),
        ];
        foreach ($rows as $k => $v) {
            echo '<tr><th style="width:200px;">' . $k . '</th><td>'
                . htmlspecialchars((string) $v) . '</td></tr>';
        }
        echo '</table>';
    }

    // Thống kê 7 ngày
    try {
        $since = date('Y-m-d H:i:s', strtotime('-7 days'));
        $total = Capsule::table('mod_smime_log')->where('sent_at', '>=', $since)->count();
        $signed = Capsule::table('mod_smime_log')->where('sent_at', '>=', $since)
            ->where('signed', 1)->count();
        $failed = Capsule::table('mod_smime_log')->where('sent_at', '>=', $since)
            ->where('status', 'failed')->count();

        echo '<h3>7 ngày gần nhất</h3>';
        echo '<div class="row">';
        $cards = [
            ['Tổng mail', $total, 'default'],
            ['Đã ký', $signed, 'success'],
            ['Không ký', $total - $signed, 'warning'],
            ['Thất bại', $failed, $failed > 0 ? 'danger' : 'default'],
        ];
        foreach ($cards as $c) {
            echo '<div class="col-sm-3"><div class="panel panel-' . $c[2] . '">'
                . '<div class="panel-heading">' . $c[0] . '</div>'
                . '<div class="panel-body" style="font-size:28px;text-align:center;">'
                . (int) $c[1] . '</div></div></div>';
        }
        echo '</div>';

        if ($total > 0 && $signed < $total) {
            echo '<div class="alert alert-info">Mail không ký thường do địa chỉ From không khớp '
                . 'ô "Chỉ ký khi From là" trong Mail Provider.</div>';
        }
    } catch (\Exception $e) {
        echo '<div class="alert alert-warning">Chưa có dữ liệu log.</div>';
    }
}

function smimemonitor_pageLog($vars)
{
    $modulelink = $vars['modulelink'];
    $filter = $_GET['filter'] ?? 'all';
    $page = max(1, (int) ($_GET['p'] ?? 1));
    $perPage = 50;

    echo '<div style="margin-bottom:10px;">Lọc: ';
    foreach (['all' => 'Tất cả', 'signed' => 'Đã ký', 'unsigned' => 'Không ký', 'failed' => 'Thất bại'] as $k => $l) {
        $style = $filter === $k ? 'btn-primary' : 'btn-default';
        echo '<a class="btn btn-xs ' . $style . '" href="' . $modulelink
            . '&action=log&filter=' . $k . '">' . $l . '</a> ';
    }
    echo '</div>';

    try {
        $q = Capsule::table('mod_smime_log');
        if ($filter === 'signed') {
            $q->where('signed', 1);
        } elseif ($filter === 'unsigned') {
            $q->where('signed', 0);
        } elseif ($filter === 'failed') {
            $q->where('status', 'failed');
        }

        $count = (clone $q)->count();
        $rows = $q->orderBy('id', 'desc')
            ->skip(($page - 1) * $perPage)->take($perPage)->get();

        echo '<table class="datatable table table-striped"><thead><tr>'
            . '<th>Thời gian</th><th>Từ</th><th>Đến</th><th>Tiêu đề</th>'
            . '<th>Ký</th><th>Trạng thái</th></tr></thead><tbody>';

        if (count($rows) === 0) {
            echo '<tr><td colspan="6">Chưa có bản ghi nào.</td></tr>';
        }

        foreach ($rows as $r) {
            $sign = $r->signed
                ? '<span class="label label-success">Có</span>'
                : '<span class="label label-default">Không</span>';
            $st = $r->status === 'failed'
                ? '<span class="label label-danger" title="' . htmlspecialchars((string) $r->error)
                    . '">Thất bại</span>'
                : '<span class="label label-success">Đã gửi</span>';
            echo '<tr>'
                . '<td>' . htmlspecialchars($r->sent_at) . '</td>'
                . '<td>' . htmlspecialchars((string) $r->from_email) . '</td>'
                . '<td>' . htmlspecialchars((string) $r->to_email) . '</td>'
                . '<td>' . htmlspecialchars((string) $r->subject) . '</td>'
                . '<td>' . $sign . '</td>'
                . '<td>' . $st . '</td>'
                . '</tr>';
            if ($r->status === 'failed' && $r->error) {
                echo '<tr><td colspan="6" style="color:#a94442;font-size:12px;padding-left:30px;">'
                    . htmlspecialchars((string) $r->error) . '</td></tr>';
            }
        }
        echo '</tbody></table>';

        $pages = (int) ceil($count / $perPage);
        if ($pages > 1) {
            echo '<ul class="pagination">';
            for ($i = 1; $i <= min($pages, 20); $i++) {
                $act = $i === $page ? ' class="active"' : '';
                echo '<li' . $act . '><a href="' . $modulelink . '&action=log&filter='
                    . $filter . '&p=' . $i . '">' . $i . '</a></li>';
            }
            echo '</ul>';
        }
    } catch (\Exception $e) {
        echo '<div class="alert alert-danger">Lỗi đọc log: '
            . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

function smimemonitor_pageTools($vars)
{
    $modulelink = $vars['modulelink'];

    // Xoá log cũ
    if (($_GET['do'] ?? '') === 'purge') {
        $days = (int) ($vars['log_days'] ?: 90);
        try {
            $n = Capsule::table('mod_smime_log')
                ->where('sent_at', '<', date('Y-m-d H:i:s', strtotime("-{$days} days")))
                ->delete();
            echo '<div class="alert alert-success">Đã xoá ' . $n . ' bản ghi cũ hơn '
                . $days . ' ngày.</div>';
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }

    // Ký thử
    if (($_GET['do'] ?? '') === 'testsign') {
        echo '<h4>Kết quả ký thử</h4>';
        $cert = $vars['cert_path'];
        $key = $vars['key_path'];
        $ca = $vars['ca_path'];
        $pass = $_POST['keypass'] ?? '';

        if (!is_readable($cert) || !is_readable($key)) {
            echo '<div class="alert alert-danger">Không đọc được cert hoặc key. '
                . 'Kiểm tra quyền file và quyền thư mục cha.</div>';
        } else {
            $pkey = @openssl_pkey_get_private('file://' . $key, $pass);
            if ($pkey === false) {
                $errs = [];
                while ($e = openssl_error_string()) {
                    $errs[] = $e;
                }
                echo '<div class="alert alert-danger">Không mở được private key — '
                    . 'thường do sai passphrase.<br><small>'
                    . htmlspecialchars(implode(' | ', $errs)) . '</small></div>';
            } else {
                $in = tempnam(sys_get_temp_dir(), 'smi');
                $out = tempnam(sys_get_temp_dir(), 'smo');
                file_put_contents($in, "Content-Type: text/plain\r\n\r\nsmime test\r\n");
                $ok = @openssl_pkcs7_sign(
                    $in, $out, 'file://' . $cert, ['file://' . $key, $pass],
                    [], PKCS7_DETACHED, $ca && is_readable($ca) ? $ca : null
                );
                if ($ok) {
                    echo '<div class="alert alert-success">Ký thành công. '
                        . 'Cấu hình chứng chỉ hoạt động tốt.</div>';
                } else {
                    $errs = [];
                    while ($e = openssl_error_string()) {
                        $errs[] = $e;
                    }
                    echo '<div class="alert alert-danger">Ký thất bại.<br><small>'
                        . htmlspecialchars(implode(' | ', $errs)) . '</small></div>';
                }
                @unlink($in);
                @unlink($out);
            }
        }
    }

    echo '<h4>Ký thử</h4>';
    echo '<form method="post" action="' . $modulelink . '&action=tools&do=testsign">'
        . '<div class="form-group"><label>Passphrase của private key</label>'
        . '<input type="password" name="keypass" class="form-control" style="max-width:300px;" '
        . 'placeholder="Để trống nếu key không mã hoá"></div>'
        . '<button type="submit" class="btn btn-primary">Ký thử</button></form>';

    echo '<hr><h4>Dọn nhật ký</h4>';
    echo '<p>Xoá các bản ghi cũ hơn ' . (int) ($vars['log_days'] ?: 90) . ' ngày.</p>';
    echo '<a class="btn btn-warning" href="' . $modulelink . '&action=tools&do=purge" '
        . 'onclick="return confirm(\'Xác nhận xoá log cũ?\')">Dọn ngay</a>';

    echo '<hr><h4>Thông tin môi trường</h4>';
    echo '<table class="table table-bordered" style="max-width:700px;">';
    echo '<tr><th style="width:250px;">OpenSSL</th><td>' . OPENSSL_VERSION_TEXT . '</td></tr>';
    echo '<tr><th>PHP</th><td>' . PHP_VERSION . '</td></tr>';
    echo '<tr><th>User chạy PHP</th><td>'
        . htmlspecialchars(function_exists('posix_getpwuid') && function_exists('posix_geteuid')
            ? (posix_getpwuid(posix_geteuid())['name'] ?? '?') : '?')
        . '</td></tr>';
    $ob = ini_get('open_basedir');
    echo '<tr><th>open_basedir</th><td>'
        . ($ob ? htmlspecialchars($ob) : '<em>không bật</em>') . '</td></tr>';
    echo '</table>';
}
