# Chính sách bảo mật — Security Policy

**Tiếng Việt** | [English](#english)

---

## Tiếng Việt

### Báo cáo lỗ hổng

**Đừng mở issue công khai cho lỗ hổng bảo mật.**

Gửi báo cáo riêng tư tới **info@photuesoftware.com** với tiêu đề bắt đầu bằng `[SECURITY]`.

Nội dung nên có:

- Mô tả lỗ hổng và mức độ ảnh hưởng
- Các bước tái hiện
- Phiên bản phần mềm, PHP, OpenSSL, hệ điều hành
- Đề xuất khắc phục nếu có

Chúng tôi phản hồi trong vòng **5 ngày làm việc** và thông báo tiến độ xử lý. Người báo cáo có trách nhiệm sẽ được ghi nhận trong changelog nếu đồng ý.

### Phạm vi

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

### Khuyến nghị khi triển khai

Phần mềm này xử lý khoá riêng và chứng chỉ số. Những điểm dưới đây là bắt buộc, không phải tuỳ chọn.

#### Đặt khoá ngoài web root

Ưu tiên `/etc/smime` hoặc thư mục tương đương nằm ngoài phạm vi web server phục vụ.

Nếu buộc phải đặt trong web root do giới hạn `open_basedir` của panel, phải chặn HTTP và **kiểm chứng thật**:

```bash
curl -sI https://ten-mien/smime/smime-hb.key | head -1
```

Kết quả phải là 403 hoặc 404. Ra 200 nghĩa là khoá riêng đang tải về được từ internet.

#### Phân quyền tối thiểu

```bash
chown root:php-user /etc/smime /etc/smime/*
chmod 750 /etc/smime
chmod 640 /etc/smime/*
```

Khoá riêng không bao giờ để `644` hoặc thuộc sở hữu của user web.

#### Passphrase

- Chỉ dùng chữ và số. Ký tự đặc biệt có thể bị escape sai khi lưu vào database.
- Truyền qua biến môi trường (`-passin env:VAR`), không gõ ở prompt và không ghi trên dòng lệnh.
- Không dùng lại passphrase đã dùng cho mục đích khác.

Lưu ý: passphrase được lưu dạng plaintext trong database của WHMCS/HostBill để hệ thống tự ký. Bảo mật thực chất nằm ở quyền file, không nằm ở passphrase.

#### Dọn dấu vết

```bash
unset NEWPW
cat /dev/null > ~/.bash_history && history -c
```

Nếu đã lỡ gõ passphrase trên dòng lệnh, coi như đã lộ — tạo lại khoá với passphrase mới.

#### Sao lưu khoá riêng

Khoá riêng **không tải lại được** từ tổ chức phát hành chứng chỉ. Mất khoá là phải xin cấp lại chứng chỉ.

Sao lưu ra ngoài máy chủ, ở nơi mã hoá (két mật khẩu doanh nghiệp, ổ đĩa mã hoá). Không commit vào Git — repo đã có `.gitignore` chặn các đuôi file liên quan, nhưng hãy kiểm tra lại trước mỗi lần commit.

#### Theo dõi hạn chứng chỉ

Chứng chỉ hết hạn khiến mail gửi đi mang chữ ký không hợp lệ, gây tác dụng ngược so với không ký. Addon giám sát trong repo tự cảnh báo trước 30 ngày. Với HostBill, đặt cron theo hướng dẫn trong tài liệu.

---

### Phiên bản được hỗ trợ

| Phiên bản | Trạng thái |
|---|---|
| 1.0.x | Đang hỗ trợ |

---

Copyright © 2026 Pho Tue Software Solutions JSC

---

## English

### Reporting Vulnerabilities

**Do not open a public issue for a security vulnerability.**

Send a private report to **info@photuesoftware.com** with a subject line starting with `[SECURITY]`.

The report should include:

- A description of the vulnerability and its impact
- Steps to reproduce
- Software, PHP, OpenSSL, and operating system versions
- A suggested fix, if any

We respond within **5 business days** and keep you posted on remediation progress. Responsible reporters will be credited in the changelog if they agree.

### Scope

**In scope:**
- Private key or passphrase leakage via logs, error messages, or the admin interface
- Bugs that leave mail unsigned while the system reports it as signed
- SQL injection, XSS in the addon interface
- Authorization flaws allowing users without sufficient rights to view logs or configuration

**Out of scope:**
- Vulnerabilities in WHMCS, HostBill, PHP, or OpenSSL themselves — report those to the respective vendor
- Deployment misconfiguration (file permissions, keys placed in the web root)
- Attacks that already require root access on the server

---

### Deployment Recommendations

This software handles private keys and digital certificates. The points below are mandatory, not optional.

#### Keep Keys Outside the Web Root

Prefer `/etc/smime` or an equivalent directory outside the reach of the web server.

If you must place keys in the web root due to the panel's `open_basedir` limits, block HTTP access and **verify for real**:

```bash
curl -sI https://ten-mien/smime/smime-hb.key | head -1
```

The result must be 403 or 404. A 200 means the private key is downloadable from the internet.

#### Least Privilege

```bash
chown root:php-user /etc/smime /etc/smime/*
chmod 750 /etc/smime
chmod 640 /etc/smime/*
```

A private key must never be `644` or owned by the web user.

#### Passphrase

- Use letters and digits only. Special characters may be escaped incorrectly when stored in the database.
- Pass it via an environment variable (`-passin env:VAR`); never type it at a prompt or write it on the command line.
- Never reuse a passphrase used for another purpose.

Note: the passphrase is stored in plaintext in the WHMCS/HostBill database so the system can sign automatically. Real security lies in file permissions, not in the passphrase.

#### Clearing Traces

```bash
unset NEWPW
cat /dev/null > ~/.bash_history && history -c
```

If you accidentally typed the passphrase on the command line, treat it as compromised — regenerate the key with a new passphrase.

#### Backing Up the Private Key

A private key **cannot be re-downloaded** from the certificate authority. Lose the key and you must request a new certificate.

Back it up off the server, in an encrypted location (enterprise password vault, encrypted drive). Never commit it to Git — the repo already has a `.gitignore` blocking the relevant file extensions, but double-check before every commit.

#### Monitoring Certificate Expiry

An expired certificate makes outgoing mail carry an invalid signature — worse than not signing at all. The monitoring addon in this repo alerts you 30 days in advance. For HostBill, set up the cron as described in the docs.

---

### Supported Versions

| Version | Status |
|---|---|
| 1.0.x | Supported |

---

Copyright © 2026 Pho Tue Software Solutions JSC
