# Changelog

Định dạng theo [Keep a Changelog](https://keepachangelog.com/vi/1.1.0/), đánh số theo [Semantic Versioning](https://semver.org/lang/vi/).

## [1.0.0] — 2026-07-31

### Thêm mới

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

[1.0.0]: https://github.com/hitechcloud-vietnam/smime-mail-signing/releases/tag/v1.0.0
