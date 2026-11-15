# Chính sách bảo mật

## Báo cáo lỗ hổng

**Đừng mở issue công khai cho lỗ hổng bảo mật.**

Gửi báo cáo riêng tư tới **info@photuesoftware.com** với tiêu đề bắt đầu bằng `[SECURITY]`.

Nội dung nên có:

- Mô tả lỗ hổng và mức độ ảnh hưởng
- Các bước tái hiện
- Phiên bản phần mềm, PHP, OpenSSL, hệ điều hành
- Đề xuất khắc phục nếu có

Chúng tôi phản hồi trong vòng **5 ngày làm việc** và thông báo tiến độ xử lý. Người báo cáo có trách nhiệm sẽ được ghi nhận trong changelog nếu đồng ý.

## Phạm vi

**Trong phạm vi:**
- Rò rỉ khoá riêng hoặc passphrase qua log, thông báo lỗi, giao diện quản trị
- Lỗi khiến mail không được ký nhưng hệ thống báo đã ký
- SQL injection, XSS trong giao diện addon
- Lỗi phân quyền cho phép người dùng không đủ quyền xem nhật ký hoặc cấu hình

**Ngoài phạm vi:**
- Lỗ hổng của bản thân WHMCS, HostBill, PHP, OpenSSL — báo cho nhà cung cấp tương ứng
- Cấu hình sai do người triển khai (quyền file, khoá đặt trong web root)
- Tấn công cần quyền root sẵn trên máy chủ

---

## Khuyến nghị khi triển khai

Phần mềm này xử lý khoá riêng và chứng chỉ số. Những điểm dưới đây là bắt buộc, không phải tuỳ chọn.

### Đặt khoá ngoài web root

Ưu tiên `/etc/smime` hoặc thư mục tương đương nằm ngoài phạm vi web server phục vụ.

Nếu buộc phải đặt trong web root do giới hạn `open_basedir` của panel, phải chặn HTTP và **kiểm chứng thật**:

```bash
curl -sI https://ten-mien/smime/smime-hb.key | head -1
```

Kết quả phải là 403 hoặc 404. Ra 200 nghĩa là khoá riêng đang tải về được từ internet.

### Phân quyền tối thiểu

```bash
chown root:php-user /etc/smime /etc/smime/*
chmod 750 /etc/smime
chmod 640 /etc/smime/*
```

Khoá riêng không bao giờ để `644` hoặc thuộc sở hữu của user web.

### Passphrase

- Chỉ dùng chữ và số. Ký tự đặc biệt có thể bị escape sai khi lưu vào database.
- Truyền qua biến môi trường (`-passin env:VAR`), không gõ ở prompt và không ghi trên dòng lệnh.
- Không dùng lại passphrase đã dùng cho mục đích khác.

Lưu ý: passphrase được lưu dạng plaintext trong database của WHMCS/HostBill để hệ thống tự ký. Bảo mật thực chất nằm ở quyền file, không nằm ở passphrase.

### Dọn dấu vết

```bash
unset NEWPW
cat /dev/null > ~/.bash_history && history -c
```

Nếu đã lỡ gõ passphrase trên dòng lệnh, coi như đã lộ — tạo lại khoá với passphrase mới.

### Sao lưu khoá riêng

Khoá riêng **không tải lại được** từ tổ chức phát hành chứng chỉ. Mất khoá là phải xin cấp lại chứng chỉ.

Sao lưu ra ngoài máy chủ, ở nơi mã hoá (két mật khẩu doanh nghiệp, ổ đĩa mã hoá). Không commit vào Git — repo đã có `.gitignore` chặn các đuôi file liên quan, nhưng hãy kiểm tra lại trước mỗi lần commit.

### Theo dõi hạn chứng chỉ

Chứng chỉ hết hạn khiến mail gửi đi mang chữ ký không hợp lệ, gây tác dụng ngược so với không ký. Addon giám sát trong repo tự cảnh báo trước 30 ngày. Với HostBill, đặt cron theo hướng dẫn trong tài liệu.

---

## Phiên bản được hỗ trợ

| Phiên bản | Trạng thái |
|---|---|
| 1.0.x | Đang hỗ trợ |

---

Copyright © 2026 Pho Tue Software Solutions JSC
