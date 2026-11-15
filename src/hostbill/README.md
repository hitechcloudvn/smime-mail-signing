# HostBill — không cần module trong repo này / no module needed from this repo

**Tiếng Việt** | [English](#english)

## Tiếng Việt

HostBill đã có sẵn plugin **`Smime_mail_sign`**, miễn phí cho mọi khách hàng HostBill. Không cần cài thêm code từ repo này.

Thư mục này chỉ tồn tại để làm rõ điều đó, tránh nhầm lẫn khi so sánh với nhánh `whmcs/`.

### Bạn cần gì

1. Ba file chứng chỉ — tạo theo [Chuẩn bị chứng chỉ](../../docs/vi/chuan-bi-chung-chi.md)
2. Bật và cấu hình plugin — theo [Hướng dẫn HostBill](../../docs/vi/huong-dan-hostbill.md)
3. Script chẩn đoán trong `scripts/` vẫn dùng được bình thường

### Khác biệt so với WHMCS

| | HostBill | WHMCS |
|---|---|---|
| Chức năng ký S/MIME | Có sẵn (`Smime_mail_sign`) | Không có, cần module trong repo |
| Định dạng file nhận | PEM riêng lẻ (`.crt`, `.key`, `.pem`) | PEM riêng lẻ |
| Giám sát hạn chứng chỉ | Không có, dùng cron thủ công | Addon `smimemonitor` |
| Nhật ký ký số | Không có | Addon `smimemonitor` |

Điểm hay nhầm nhất với HostBill: plugin nhận **file PEM riêng lẻ**, không phải file `.pfx`. Xem mục 2 trong hướng dẫn.

---

## English

HostBill already ships the **`Smime_mail_sign`** plugin, free for every HostBill customer. There is no code to install from this repo.

This directory exists only to make that explicit and to avoid confusion when comparing against the `whmcs/` tree.

### What you need

1. The three certificate files — create them per [Certificate preparation](../../docs/en/certificate-preparation.md)
2. Enable and configure the plugin — per the [HostBill guide](../../docs/en/hostbill-guide.md)
3. The diagnostic scripts in `scripts/` work as usual

### Differences from WHMCS

| | HostBill | WHMCS |
|---|---|---|
| S/MIME signing | Built in (`Smime_mail_sign`) | Not available; needs the module in this repo |
| Accepted file format | Separate PEM files (`.crt`, `.key`, `.pem`) | Separate PEM files |
| Certificate expiry monitoring | None; use a manual cron | `smimemonitor` add-on |
| Signing log | None | `smimemonitor` add-on |

The most common HostBill mistake: the plugin takes **separate PEM files**, not a `.pfx` file. See section 2 of the guide.
