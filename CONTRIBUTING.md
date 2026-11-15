# Hướng dẫn đóng góp

Cảm ơn bạn quan tâm tới dự án.

## Trước khi bắt đầu

Dự án này phát hành theo [giấy phép nguồn-mở-có-điều-kiện](LICENSE), **không phải** giấy phép mã nguồn mở. Khi gửi đóng góp, bạn đồng ý rằng phần đóng góp đó được cấp cho Công ty Cổ phần Giải pháp Phần mềm và Công nghệ Phổ Tuệ theo cùng điều khoản giấy phép của dự án, và bạn có đủ quyền để làm việc đó.

## Báo lỗi

Mở [issue](https://github.com/hitechcloud-vietnam/smime-mail-signing/issues) theo mẫu có sẵn.

Trước khi mở issue, hãy chạy công cụ chẩn đoán và đính kèm kết quả:

```bash
./scripts/smime-check.sh /duong/dan/thu-muc-smime
```

Đồng thời kiểm tra [XU-LY-LOI.md](docs/XU-LY-LOI.md) — phần lớn lỗi thường gặp đã có trong đó kèm cách xác minh nguyên nhân.

> [!CAUTION]
> **Không dán khoá riêng, passphrase, hay nội dung file `.key`/`.pfx` vào issue.** Che bớt tên miền và địa chỉ email nội bộ nếu cần. Lỗi bảo mật gửi riêng theo [SECURITY.md](SECURITY.md), đừng mở issue công khai.

## Gửi Pull Request

1. Fork repo và tạo nhánh từ `main`
2. Đặt tên nhánh có tiền tố: `fix/`, `feat/`, `docs/`, `refactor/`
3. Commit theo [Conventional Commits](https://www.conventionalcommits.org/)
4. Kiểm tra cú pháp trước khi gửi
5. Mở PR theo mẫu, mô tả rõ vấn đề đang giải quyết

### Kiểm tra cú pháp

```bash
find src -name '*.php' -exec php -l {} \;
bash -n scripts/smime-setup.sh
bash -n scripts/smime-check.sh
shellcheck scripts/*.sh    # nếu có cài
```

CI sẽ chạy các bước này tự động, nhưng chạy trước ở máy sẽ nhanh hơn.

## Quy ước mã nguồn

**PHP** — theo PSR-12, thụt lề 4 khoảng trắng:

- Đặt tên biến và hàm bằng tiếng Anh
- Comment giải thích bằng tiếng Việt
- Chuỗi hiển thị cho người dùng bằng tiếng Việt
- Luôn kiểm tra giá trị trả về của hàm `openssl_*`, không giả định thành công
- Thông báo lỗi phải nói rõ nguyên nhân và hướng xử lý, không trả nguyên văn lỗi kỹ thuật

Ví dụ thông báo lỗi đạt yêu cầu:

```php
throw new SendFailure(
    "Private key không đọc được: {$path} — "
    . 'kiểm tra quyền file và quyền thư mục cha (user PHP cần bit x trên thư mục).'
);
```

**Shell** — `set -euo pipefail`, kiểm tra file tồn tại trước khi dùng, thông báo bằng tiếng Việt không dấu để tránh lỗi hiển thị trên terminal cũ.

**Markdown** — tiêu đề dùng dấu `#`, bảng cho dữ liệu có cấu trúc, khối lệnh luôn ghi rõ ngôn ngữ.

## Nguyên tắc về bảo mật khi viết code

- Không log passphrase, khoá riêng, hoặc nội dung chữ ký
- Không ghi mật khẩu vào tham số dòng lệnh trong tài liệu hay script
- Mọi thao tác ghi log phải bọc `try/catch` — log hỏng không được phép chặn luồng gửi mail
- Khi thêm trường cấu hình chứa mật khẩu, dùng `'Type' => 'password'`

## Những gì không nhận

- Đổi giấy phép sang mã nguồn mở
- Gỡ bỏ nhận diện thương hiệu hoặc thông báo bản quyền
- Tính năng đưa khoá riêng ra khỏi máy chủ (upload, đồng bộ đám mây)
- Tích hợp dịch vụ bên thứ ba mà không có lý do vận hành rõ ràng

## Liên hệ

Thắc mắc về hướng phát triển hoặc muốn bàn về tính năng lớn trước khi code: [info@photuesoftware.com](mailto:info@photuesoftware.com)
