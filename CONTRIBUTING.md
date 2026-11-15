# Hướng dẫn đóng góp — Contributing Guide

**Tiếng Việt** | [English](#english)

---

## Tiếng Việt

Cảm ơn bạn quan tâm tới dự án.

### Trước khi bắt đầu

Dự án này phát hành theo [giấy phép nguồn-mở-có-điều-kiện](LICENSE), **không phải** giấy phép mã nguồn mở. Khi gửi đóng góp, bạn đồng ý rằng phần đóng góp đó được cấp cho Công ty Cổ phần Giải pháp Phần mềm và Công nghệ Phổ Tuệ theo cùng điều khoản giấy phép của dự án, và bạn có đủ quyền để làm việc đó.

### Báo lỗi

Mở [issue](https://github.com/hitechcloudvn/smime-mail-signing/issues) theo mẫu có sẵn.

Trước khi mở issue, hãy chạy công cụ chẩn đoán và đính kèm kết quả:

```bash
./scripts/smime-check.sh /duong/dan/thu-muc-smime
```

Đồng thời kiểm tra [Xử lý lỗi](docs/vi/xu-ly-loi.md) — phần lớn lỗi thường gặp đã có trong đó kèm cách xác minh nguyên nhân.

> [!CAUTION]
> **Không dán khoá riêng, passphrase, hay nội dung file `.key`/`.pfx` vào issue.** Che bớt tên miền và địa chỉ email nội bộ nếu cần. Lỗi bảo mật gửi riêng theo [SECURITY.md](SECURITY.md), đừng mở issue công khai.

### Gửi Pull Request

1. Fork repo và tạo nhánh từ `main`
2. Đặt tên nhánh có tiền tố: `fix/`, `feat/`, `docs/`, `refactor/`
3. Commit theo [Conventional Commits](https://www.conventionalcommits.org/)
4. Kiểm tra cú pháp trước khi gửi
5. Mở PR theo mẫu, mô tả rõ vấn đề đang giải quyết

#### Kiểm tra cú pháp

```bash
find src -name '*.php' -exec php -l {} \;
bash -n scripts/smime-setup.sh
bash -n scripts/smime-check.sh
shellcheck scripts/*.sh    # nếu có cài
```

CI sẽ chạy các bước này tự động, nhưng chạy trước ở máy sẽ nhanh hơn.

### Quy ước mã nguồn

**PHP** — theo PSR-12, thụt lề 4 khoảng trắng:

- Đặt tên biến và hàm bằng tiếng Anh
- Comment giải thích song ngữ Việt–Anh, viết dạng `Tiếng Việt / English`
- Chuỗi hiển thị cho người dùng song ngữ Việt–Anh, viết dạng `Tiếng Việt / English`
- Luôn kiểm tra giá trị trả về của hàm `openssl_*`, không giả định thành công
- Thông báo lỗi phải nói rõ nguyên nhân và hướng xử lý, không trả nguyên văn lỗi kỹ thuật

Ví dụ thông báo lỗi đạt yêu cầu:

```php
throw new SendFailure(
    "Private key không đọc được: {$path} — kiểm tra quyền file và quyền thư mục cha (user PHP cần bit x trên thư mục)"
    . ' / '
    . "Private key unreadable: {$path} — check file and parent directory permissions (the PHP user needs the x bit on the directory)"
);
```

**Shell** — `set -euo pipefail`, kiểm tra file tồn tại trước khi dùng, thông báo gồm tiếng Việt không dấu và tiếng Anh (viết dạng `Tieng Viet / English`), tránh lỗi hiển thị trên terminal cũ.

**Markdown** — tiêu đề dùng dấu `#`, bảng cho dữ liệu có cấu trúc, khối lệnh luôn ghi rõ ngôn ngữ.

### Nguyên tắc về bảo mật khi viết code

- Không log passphrase, khoá riêng, hoặc nội dung chữ ký
- Không ghi mật khẩu vào tham số dòng lệnh trong tài liệu hay script
- Mọi thao tác ghi log phải bọc `try/catch` — log hỏng không được phép chặn luồng gửi mail
- Khi thêm trường cấu hình chứa mật khẩu, dùng `'Type' => 'password'`

### Những gì không nhận

- Đổi giấy phép sang mã nguồn mở
- Gỡ bỏ nhận diện thương hiệu hoặc thông báo bản quyền
- Tính năng đưa khoá riêng ra khỏi máy chủ (upload, đồng bộ đám mây)
- Tích hợp dịch vụ bên thứ ba mà không có lý do vận hành rõ ràng

### Liên hệ

Thắc mắc về hướng phát triển hoặc muốn bàn về tính năng lớn trước khi code: [info@photuesoftware.com](mailto:info@photuesoftware.com)

---

## English

Thank you for your interest in the project.

### Before You Start

This project is released under a [conditional open-source license](LICENSE), **not** an open-source license. By submitting a contribution, you agree that the contribution is granted to Pho Tue Software Solutions JSC under the same license terms as the project, and that you have the rights to do so.

### Reporting Bugs

Open an [issue](https://github.com/hitechcloudvn/smime-mail-signing/issues) using the provided template.

Before opening an issue, run the diagnostic tool and attach its output:

```bash
./scripts/smime-check.sh /duong/dan/thu-muc-smime
```

Also check [Troubleshooting](docs/en/troubleshooting.md) — most common errors are already covered there, with steps to verify the root cause.

> [!CAUTION]
> **Never paste private keys, passphrases, or the contents of `.key`/`.pfx` files into an issue.** Redact domain names and internal email addresses if needed. Report security flaws privately per [SECURITY.md](SECURITY.md); do not open a public issue.

### Submitting a Pull Request

1. Fork the repo and create a branch from `main`
2. Name the branch with a prefix: `fix/`, `feat/`, `docs/`, `refactor/`
3. Commit following [Conventional Commits](https://www.conventionalcommits.org/)
4. Run the syntax checks before submitting
5. Open a PR using the template, describing clearly the problem being solved

#### Syntax Checks

```bash
find src -name '*.php' -exec php -l {} \;
bash -n scripts/smime-setup.sh
bash -n scripts/smime-check.sh
shellcheck scripts/*.sh    # nếu có cài
```

CI runs these steps automatically, but running them locally first is faster.

### Coding Conventions

**PHP** — PSR-12, 4-space indentation:

- Variable and function names in English
- Explanatory comments bilingual Vietnamese/English, written as `Tiếng Việt / English`
- User-facing strings bilingual Vietnamese/English, written as `Tiếng Việt / English`
- Always check the return value of `openssl_*` functions; never assume success
- Error messages must state the cause and the remediation, never echo raw technical errors

An example error message that meets the requirements:

```php
throw new SendFailure(
    "Private key không đọc được: {$path} — kiểm tra quyền file và quyền thư mục cha (user PHP cần bit x trên thư mục)"
    . ' / '
    . "Private key unreadable: {$path} — check file and parent directory permissions (the PHP user needs the x bit on the directory)"
);
```

**Shell** — `set -euo pipefail`, check that files exist before use; messages combine diacritics-free Vietnamese with English (written as `Tieng Viet / English`) to stay safe on legacy terminals.

**Markdown** — headings use `#`, tables for structured data, code blocks always declare the language.

### Security Principles When Writing Code

- Never log passphrases, private keys, or signature contents
- Never put passwords in command-line parameters in docs or scripts
- Wrap every logging operation in `try/catch` — a broken log must never block the mail-sending flow
- When adding a configuration field that holds a password, use `'Type' => 'password'`

### What We Do Not Accept

- Changing the license to open source
- Removing branding or copyright notices
- Features that move private keys off the server (upload, cloud sync)
- Third-party service integrations without a clear operational rationale

### Contact

Questions about the project direction, or want to discuss a large feature before coding: [info@photuesoftware.com](mailto:info@photuesoftware.com)
