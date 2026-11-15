# Changelog — Nhật ký thay đổi

**Tiếng Việt** | [English](#english)

---

## Tiếng Việt

Định dạng theo [Keep a Changelog](https://keepachangelog.com/vi/1.1.0/), đánh số theo [Semantic Versioning](https://semver.org/lang/vi/).

### [1.0.0] — 2026-11-15

#### Thêm mới

**WHMCS Mail Provider (`smimesmtp`)**
- Gửi mail qua SMTP kèm ký S/MIME theo `SenderModuleInterface` của WHMCS 8
- Lọc ký theo địa chỉ gửi, tránh ký bằng chứng chỉ không khớp SAN
- Ghi đè tên và địa chỉ người gửi, tự động chuyển địa chỉ gốc sang Reply-To
- Kiểm tra chứng chỉ trước mỗi lần ký: quyền đọc, passphrase, khớp khoá, hạn sử dụng
- Diễn giải lỗi SMTP thành thông báo có hướng xử lý cụ thể
- Ghi nhật ký từng mail nếu addon giám sát đã cài

**WHMCS Addon (`smimemonitor`)**
- Trang tổng quan: thông tin chứng chỉ, hạn còn lại, cấu trúc chuỗi CA, thống kê 7 ngày
- Trang nhật ký: lọc theo đã ký / không ký / thất bại, phân trang
- Trang công cụ: ký thử, dọn nhật ký cũ, thông tin môi trường OpenSSL và `open_basedir`
- Cron cảnh báo email ở mốc 30/14/7/3/1 ngày trước hạn và khi đã hết hạn
- Widget trạng thái trên trang chủ admin

**Script**
- `smime-setup.sh` — xử lý file CA gửi về thành bộ ba file dùng được, phân loại chứng chỉ theo `basicConstraints` thay vì đoán theo thứ tự
- `smime-check.sh` — chẩn đoán 6 nhóm: môi trường, chứng chỉ, chuỗi CA, khoá, quyền truy cập, rò rỉ HTTP

**Tài liệu**
- Chuẩn bị chứng chỉ từ CSR đến file dùng được
- Hướng dẫn triển khai cho WHMCS và HostBill
- Bảng tra lỗi kèm cách xác minh nguyên nhân
- Song ngữ Việt–Anh cho toàn bộ tài liệu, mã nguồn và thông báo
- CI/CD tự động đóng gói và phát hành bản release

[1.0.0]: https://github.com/hitechcloudvn/smime-mail-signing/releases/tag/v1.0.0

---

## English

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), versioning follows [Semantic Versioning](https://semver.org/).

### [1.0.0] — 2026-11-15

#### Added

**WHMCS Mail Provider (`smimesmtp`)**
- Sends mail over SMTP with S/MIME signing via the `SenderModuleInterface` of WHMCS 8
- Filters signing by sender address, avoiding signing with a certificate whose SAN does not match
- Overrides sender name and address, automatically moving the original address to Reply-To
- Validates the certificate before each signing: read permission, passphrase, key match, expiration
- Turns SMTP errors into messages with concrete remediation steps
- Logs each message if the monitoring addon is installed

**WHMCS Addon (`smimemonitor`)**
- Dashboard page: certificate info, remaining validity, CA chain structure, 7-day statistics
- Log page: filter by signed / unsigned / failed, pagination
- Tools page: test signing, purge old logs, OpenSSL environment and `open_basedir` info
- Email alert cron at 30/14/7/3/1 days before expiry and once expired
- Status widget on the admin homepage

**Scripts**
- `smime-setup.sh` — turns the files returned by the CA into a usable three-file set, classifying certificates by `basicConstraints` instead of guessing by order
- `smime-check.sh` — diagnoses 6 groups: environment, certificates, CA chain, keys, access permissions, HTTP leaks

**Documentation**
- Certificate preparation, from CSR to usable files
- Deployment guides for WHMCS and HostBill
- Troubleshooting table with root-cause verification steps
- Full Vietnamese–English bilingual docs, source code, and messages
- CI/CD packaging and release automation

[1.0.0]: https://github.com/hitechcloudvn/smime-mail-signing/releases/tag/v1.0.0
