# WHMCS — Cài đặt module ký S/MIME

**Tiếng Việt** | [English](../en/whmcs-guide.md)

Đọc [Chuẩn bị chứng chỉ](chuan-bi-chung-chi.md) (phần A–E) trước để có 3 file: `smime.crt`, `ca-chain.pem`, `smime-hb.key`.

---

## 1. Vì sao phải viết module

WHMCS không có sẵn chức năng ký S/MIME. Nhiều người thử dùng hook `EmailPreSend` nhưng **không được** — hook đó chạy trước khi message được dựng thành MIME, chỉ cho sửa merge fields hoặc huỷ gửi, trong khi ký S/MIME phải thao tác trên body MIME hoàn chỉnh.

WHMCS 8.0 giới thiệu hệ thống **Mail Provider** (`SenderModuleInterface`), cho phép module tự nắm khâu gửi. Đó là chỗ đúng để chèn chữ ký.

Gói này gồm hai module:

| Module | Vai trò | Bắt buộc? |
|---|---|---|
| `smimesmtp` (Mail Provider) | Gửi mail qua SMTP + ký S/MIME | Có |
| `smimemonitor` (Addon) | Giám sát hạn cert, nhật ký ký, công cụ chẩn đoán | Không, nhưng nên có |

---

## 2. Yêu cầu

- WHMCS 8.0 trở lên
- PHP 7.4+ với extension `openssl`
- Mail server SMTP (module không hỗ trợ PHP `mail()`)

Kiểm tra:

```bash
php -r 'echo extension_loaded("openssl") ? "openssl OK" : "THIEU openssl", PHP_EOL;'
```

---

## 3. Cài Mail Provider

### 3.1. Upload file

```bash
cd /duong/dan/whmcs
mkdir -p modules/mail/smimesmtp
# upload smimesmtp.php vào đây
chown -R www-data:www-data modules/mail/smimesmtp
chmod 644 modules/mail/smimesmtp/smimesmtp.php
```

Cấu trúc đúng:

```
whmcs/modules/mail/smimesmtp/smimesmtp.php
```

Tên thư mục, tên file và tên class phải khớp nhau (`smimesmtp` / `Smimesmtp`).

### 3.2. Kích hoạt

WHMCS Admin → **Configuration → System Settings → Mail** → mục **Mail Provider** → chọn **SMTP + S/MIME Sign**.

### 3.3. Điền cấu hình

| Trường | Giá trị mẫu | Ghi chú |
|---|---|---|
| SMTP Host | `mail.congty.vn` | |
| SMTP Port | `587` | 587 cho STARTTLS, 465 cho SMTPS |
| Mã hoá | `tls` | |
| SMTP Username | `hotro@congty.vn` | |
| SMTP Password | | |
| Đường dẫn Certificate | `/etc/smime/smime.crt` | tuyệt đối |
| Đường dẫn Private Key | `/etc/smime/smime-hb.key` | tuyệt đối |
| Passphrase của Key | `PassphraseMoi2026` | để trống nếu key không mã hoá |
| Đường dẫn CA Chain | `/etc/smime/ca-chain.pem` | |
| **Chỉ ký khi From là** | `hotro@congty.vn` | quan trọng, xem 3.4 |
| **Tên người gửi** | `Cong Ty ABC` | ghi đè tên WHMCS gửi sang |
| Ghi đè địa chỉ gửi | *(để trống)* | xem 3.5 |
| Địa chỉ nhận mail test | `admin@congty.vn` | hộp thư có thật |

Bấm **Save Changes**, rồi bấm nút test.

### 3.4. Về ô "Chỉ ký khi From là"

Certificate chỉ chứa **một** địa chỉ trong SAN. Nhưng WHMCS gửi mail từ nhiều địa chỉ khác nhau tuỳ department hỗ trợ (`sales@`, `billing@`, `support@`).

Ký bằng cert không khớp From thì client hiển thị cảnh báo chữ ký không hợp lệ — tệ hơn là không ký gì cả.

Điền địa chỉ khớp SAN vào ô này. Mail từ địa chỉ khác vẫn gửi bình thường, chỉ không ký. Kiểm tra SAN:

```bash
openssl x509 -in smime.crt -noout -ext subjectAltName
```

Để trống ô này = ký mọi mail, chỉ nên dùng khi toàn hệ thống gửi từ một địa chỉ duy nhất.

### 3.5. Về ô "Ghi đè địa chỉ gửi"

Chỉ điền khi mail server bắt buộc gửi từ một địa chỉ cố định (nhiều nhà cung cấp SMTP từ chối `MAIL FROM` khác tài khoản đăng nhập, báo lỗi 553).

Khi điền, module tự đưa địa chỉ gốc của WHMCS vào **Reply-To**, nên khách bấm Trả lời vẫn đến đúng phòng ban.

---

## 4. Cài Addon giám sát

### 4.1. Upload

```bash
cd /duong/dan/whmcs
mkdir -p modules/addons/smimemonitor
# upload smimemonitor.php và hooks.php vào đây
chown -R www-data:www-data modules/addons/smimemonitor
chmod 644 modules/addons/smimemonitor/*.php
```

### 4.2. Kích hoạt

Admin → **System Settings → Addon Modules** → tìm **S/MIME Monitor** → **Activate**.

Bấm **Configure**, gán quyền truy cập cho nhóm admin, rồi điền:

| Trường | Giá trị |
|---|---|
| Đường dẫn Certificate | `/etc/smime/smime.crt` |
| Đường dẫn Private Key | `/etc/smime/smime-hb.key` |
| Đường dẫn CA Chain | `/etc/smime/ca-chain.pem` |
| Cảnh báo trước (ngày) | `30` |
| Email nhận cảnh báo | `admin@congty.vn` |
| Giữ log (ngày) | `90` |

Đường dẫn phải **giống hệt** bên Mail Provider.

Khi activate, addon tạo bảng `mod_smime_log`. Mail Provider tự phát hiện bảng này và bắt đầu ghi log — không cần cấu hình thêm.

### 4.3. Ba tab của addon

**Tổng quan** — thông tin cert (tổ chức, SAN, ngày hết hạn, số ngày còn lại, chuỗi CA), cảnh báo nếu CA chain lẫn cert leaf, thống kê 7 ngày.

**Nhật ký** — bảng log lọc theo đã ký / không ký / thất bại. Lỗi hiển thị ngay dưới dòng tương ứng.

**Công cụ** — nút ký thử (trả về lỗi OpenSSL cụ thể nếu fail), dọn log cũ, và bảng thông tin môi trường: phiên bản OpenSSL, user chạy PHP, `open_basedir`.

### 4.4. Cảnh báo tự động

`hooks.php` gắn vào `DailyCronJob`, gửi email cảnh báo ở các mốc **30, 14, 7, 3, 1 ngày** trước hạn và khi đã hết hạn. Chỉ gửi ở đúng các mốc này để tránh spam.

Cũng thêm một widget trên trang chủ admin hiển thị trạng thái cert và số mail đã ký trong 7 ngày.

Yêu cầu WHMCS cron đang chạy bình thường.

---

## 5. Kiểm tra

### 5.1. Mail test

Bấm nút test trong cấu hình Mail Provider. Nếu lỗi, module trả về thông báo có hướng xử lý cụ thể thay vì lỗi thô của SMTP.

### 5.2. Xem mail nhận được

Gmail → mở mail → ba chấm → **Show original**. Cần thấy:

```
Content-Type: multipart/signed; protocol="application/pkcs7-signature"; micalg=sha-256
```

kèm phần đính kèm `smime.p7s`. Gmail hiển thị biểu tượng xác thực nếu chuỗi CA hợp lệ.

Outlook hiển thị biểu tượng ruy băng đỏ ở góc phải.

### 5.3. Ký thử trong addon

Tab **Công cụ** → nhập passphrase → **Ký thử**. Đây là cách nhanh nhất phân biệt lỗi chứng chỉ với lỗi SMTP.

---

## 6. Nâng cấp WHMCS

Module nằm trong `modules/`, WHMCS không ghi đè thư mục này khi cập nhật. Nhưng nên backup trước mỗi lần nâng cấp lớn:

```bash
tar czf /root/smime-modules-$(date +%Y%m%d).tar.gz \
  modules/mail/smimesmtp modules/addons/smimemonitor
```

Sau khi nâng cấp, kiểm tra Mail Provider vẫn được chọn đúng và bấm test lại.

---

## 7. Hướng thay thế: ký ở tầng MTA

Nếu bạn tự quản mail server, cân nhắc ký bằng milter (`smime-milter`, hoặc script gắn vào Postfix). 

**Ưu điểm:** ký được mọi mail rời server bất kể ứng dụng nào gửi, không phụ thuộc WHMCS, không cần bảo trì code qua các bản cập nhật.

**Nhược điểm:** cấu hình phức tạp hơn, cần quyền quản trị mail server, và không có giao diện giám sát như addon này.

Với hệ thống chỉ cần ký mail từ WHMCS, module trong gói này đơn giản hơn nhiều.
