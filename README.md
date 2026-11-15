<div align="center">

# S/MIME Mail Signing

**Ký số S/MIME cho mail gửi đi từ WHMCS và HostBill**

**Tiếng Việt** | [English](README.en.md)

[![License](https://img.shields.io/badge/License-Source--Available%20Non--Commercial-red?style=flat-square)](LICENSE)
[![WHMCS](https://img.shields.io/badge/WHMCS-8.0%2B-0073AA?style=flat-square)](docs/vi/huong-dan-whmcs.md)
[![HostBill](https://img.shields.io/badge/HostBill-Supported-00A4E4?style=flat-square)](docs/vi/huong-dan-hostbill.md)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?style=flat-square&logo=php&logoColor=white)](https://www.php.net)
[![Made in Vietnam](https://img.shields.io/badge/Made%20in-Vietnam-DA251D?style=flat-square)](https://hitechcloud.vn)

[Tài liệu](#tài-liệu) · [Cài đặt nhanh](#cài-đặt-nhanh) · [Giấy phép](#giấy-phép) · [Hỗ trợ](#hỗ-trợ)

</div>

---

## Giới thiệu

Bộ công cụ và module giúp ký số S/MIME cho toàn bộ mail gửi đi từ hệ thống quản lý khách hàng, để người nhận xác minh được mail thực sự đến từ tổ chức của bạn và nội dung không bị sửa đổi trên đường truyền.

Gói bao gồm tài liệu triển khai đầy đủ từ khâu tạo CSR, script tự động xử lý chứng chỉ, module cho WHMCS, và hướng dẫn cấu hình plugin sẵn có của HostBill.

## Tính năng

| | |
|---|---|
| **Ký S/MIME tự động** | Mọi mail gửi đi được ký, người nhận thấy biểu tượng xác thực |
| **Lọc theo địa chỉ gửi** | Chỉ ký mail từ địa chỉ khớp SAN trong chứng chỉ, tránh cảnh báo sai |
| **Tuỳ chỉnh người gửi** | Ghi đè tên và địa chỉ hiển thị, tự động giữ Reply-To đúng |
| **Giám sát chứng chỉ** | Cảnh báo email trước hạn 30/14/7/3/1 ngày, widget trên trang chủ admin |
| **Nhật ký ký số** | Ghi lại từng mail: đã ký hay chưa, gửi thành công hay lỗi |
| **Chẩn đoán tích hợp** | Công cụ ký thử, kiểm tra quyền file, thông tin môi trường OpenSSL |
| **Script tự động hoá** | Xử lý file CA gửi về thành bộ file dùng được chỉ bằng một lệnh |

## Hỗ trợ nền tảng

| Nền tảng | Cách triển khai | Tài liệu |
|---|---|---|
| **WHMCS 8.0+** | Mail Provider module + Addon giám sát (có trong repo) | [Hướng dẫn WHMCS](docs/vi/huong-dan-whmcs.md) |
| **HostBill** | Plugin `Smime_mail_sign` có sẵn, chỉ cần cấu hình | [Hướng dẫn HostBill](docs/vi/huong-dan-hostbill.md) |

WHMCS không có sẵn chức năng ký S/MIME và hook `EmailPreSend` không làm được việc này — hook chạy trước khi message được dựng thành MIME. Vì vậy repo cung cấp một Mail Provider module theo `SenderModuleInterface` của WHMCS 8.

## Cấu trúc repo

```
.
├── docs/
│   ├── vi/                          Tài liệu tiếng Việt
│   │   ├── chuan-bi-chung-chi.md    Từ CSR đến file dùng được (đọc trước)
│   │   ├── huong-dan-whmcs.md       Cài module cho WHMCS
│   │   ├── huong-dan-hostbill.md    Cấu hình plugin HostBill
│   │   └── xu-ly-loi.md             Bảng tra lỗi
│   └── en/                          English documentation
│       ├── certificate-preparation.md
│       ├── whmcs-guide.md
│       ├── hostbill-guide.md
│       └── troubleshooting.md
├── scripts/
│   ├── smime-setup.sh               Xử lý file CA gửi về thành 3 file dùng được
│   └── smime-check.sh               Chẩn đoán cấu hình
└── src/
    ├── whmcs/modules/
    │   ├── mail/smimesmtp/          Mail Provider: SMTP + ký S/MIME
    │   └── addons/smimemonitor/     Addon giám sát chứng chỉ và nhật ký
    └── hostbill/                    Chỉ tài liệu — plugin có sẵn của HostBill
```

## Yêu cầu

- PHP 7.4 trở lên, có extension `openssl`
- OpenSSL 1.1.1 hoặc 3.x trên hệ thống
- Chứng chỉ S/MIME còn hiệu lực (khuyến nghị loại Organization Validation)
- Mail server SMTP
- WHMCS 8.0+ (nếu dùng module WHMCS)

## Cài đặt nhanh

### 1. Chuẩn bị chứng chỉ

```bash
git clone https://github.com/hitechcloudvn/smime-mail-signing.git
cd smime-mail-signing
chmod +x scripts/*.sh

./scripts/smime-setup.sh -p 'cert.p7b' -k private.key -w 'PassphraseMoi2026'
```

Script tự động phân loại chứng chỉ, đối chiếu khoá với chứng chỉ, tạo khoá có passphrase và ký thử. Kết quả là ba file: `smime.crt`, `ca-chain.pem`, `smime-hb.key`.

Chi tiết từng bước, kể cả khi làm thủ công: [Chuẩn bị chứng chỉ](docs/vi/chuan-bi-chung-chi.md)

### 2. Phân quyền

```bash
PHPUSER=$(ps -o user= -C php-fpm | sort -u | grep -v root | head -1)
chown root:"$PHPUSER" /etc/smime /etc/smime/*
chmod 750 /etc/smime && chmod 640 /etc/smime/*
```

> [!WARNING]
> Nếu thư mục nằm trong web root, phải chặn truy cập HTTP ngay. Private key tải về được từ internet là sự cố bảo mật nghiêm trọng. Xem mục E3 trong tài liệu chuẩn bị chứng chỉ.

### 3. Cài module

**WHMCS:**

```bash
cp -r src/whmcs/modules/mail/smimesmtp      /duong/dan/whmcs/modules/mail/
cp -r src/whmcs/modules/addons/smimemonitor /duong/dan/whmcs/modules/addons/
```

Rồi vào Admin → System Settings → Mail → chọn **SMTP + S/MIME Sign**.

**HostBill:** Settings → Modules → Plugins → bật `Smime_mail_sign`.

### 4. Kiểm tra

```bash
./scripts/smime-check.sh /etc/smime
```

Gửi mail test, mở ở Gmail → Show original, cần thấy `Content-Type: multipart/signed`.

## Tài liệu

| Tài liệu | Nội dung |
|---|---|
| [Chuẩn bị chứng chỉ](docs/vi/chuan-bi-chung-chi.md) | Tạo CSR, xác thực tổ chức, xử lý file CA gửi về, phân quyền, bảo mật |
| [Hướng dẫn WHMCS](docs/vi/huong-dan-whmcs.md) | Cài Mail Provider và Addon, giải thích từng ô cấu hình |
| [Hướng dẫn HostBill](docs/vi/huong-dan-hostbill.md) | Bật và cấu hình plugin sẵn có, cron cảnh báo hết hạn |
| [Xử lý lỗi](docs/vi/xu-ly-loi.md) | Tra lỗi theo thông báo, kèm cách xác minh nguyên nhân |

Bản tiếng Anh của tài liệu nằm trong [docs/en/](README.en.md).

## Phát hành

Mọi bản release và tag được **GitHub Actions** đóng gói và phát hành tự động — repo không yêu cầu build ở máy cục bộ. Xem [CHANGELOG.md](CHANGELOG.md) để biết nội dung từng phiên bản.

## Giấy phép

Phần mềm này được phát hành theo **HiTechCloud Source-Available License (Non-Commercial)**. Đây **không phải** giấy phép mã nguồn mở.

**Được phép:**
- Sử dụng, cài đặt, vận hành cho mục đích nội bộ của tổ chức bạn
- Sửa đổi và tạo bản tuỳ chỉnh cho nội bộ
- Nghiên cứu, học tập

**Bị cấm:**
- Bán hoặc bán lại phần mềm, dù ở dạng gốc hay đã sửa đổi
- Sử dụng cho mục đích thương mại: đưa vào sản phẩm/dịch vụ có thu phí, cung cấp như dịch vụ cho bên thứ ba
- Phân phối lại ra công chúng, đăng lên chợ ứng dụng hay thư mục module
- Gỡ bỏ thông báo bản quyền hoặc nhận diện thương hiệu

Doanh nghiệp vận hành phần mềm này trên hạ tầng của chính mình để ký mail của chính mình **không** bị coi là sử dụng thương mại, kể cả khi là công ty hoạt động vì lợi nhuận.

Cần giấy phép thương mại? Liên hệ [info@photuesoftware.com](mailto:info@photuesoftware.com).

Toàn văn: [LICENSE](LICENSE)

## Bảo mật

Phần mềm xử lý khoá riêng và chứng chỉ số. Đọc [SECURITY.md](SECURITY.md) trước khi triển khai lên môi trường thật.

Phát hiện lỗ hổng? Báo riêng tư tới [info@photuesoftware.com](mailto:info@photuesoftware.com), đừng mở issue công khai.

## Hỗ trợ

| Kênh | Địa chỉ |
|---|---|
| Cổng khách hàng | [my.hitechcloud.vn](https://my.hitechcloud.vn) |
| Website | [hitechcloud.vn](https://hitechcloud.vn) · [photuesoftware.com](https://photuesoftware.com) |
| Email | [info@photuesoftware.com](mailto:info@photuesoftware.com) |
| Zalo OA | [oa.zalo.me/2386540850578657637](https://oa.zalo.me/2386540850578657637) |
| Báo lỗi | [GitHub Issues](https://github.com/hitechcloudvn/smime-mail-signing/issues) |

---

<div align="center">

**HiTechCloud** — thương hiệu hạ tầng đám mây của **Công ty Cổ phần Giải pháp Phần mềm và Công nghệ Phổ Tuệ**

Mã số doanh nghiệp 0318222903 · Thành phố Hồ Chí Minh, Việt Nam

Copyright © 2026 Pho Tue Software Solutions JSC. All rights reserved.

</div>
