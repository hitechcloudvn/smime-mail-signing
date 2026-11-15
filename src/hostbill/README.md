# HostBill — không cần module trong repo này

HostBill đã có sẵn plugin **`Smime_mail_sign`**, miễn phí cho mọi khách hàng HostBill. Không cần cài thêm code từ repo này.

Thư mục này chỉ tồn tại để làm rõ điều đó, tránh nhầm lẫn khi so sánh với nhánh `whmcs/`.

## Bạn cần gì

1. Ba file chứng chỉ — tạo theo [docs/CHUAN-BI-CHUNG-CHI.md](../../docs/CHUAN-BI-CHUNG-CHI.md)
2. Bật và cấu hình plugin — theo [docs/HUONG-DAN-HOSTBILL.md](../../docs/HUONG-DAN-HOSTBILL.md)
3. Script chẩn đoán trong `scripts/` vẫn dùng được bình thường

## Khác biệt so với WHMCS

| | HostBill | WHMCS |
|---|---|---|
| Chức năng ký S/MIME | Có sẵn (`Smime_mail_sign`) | Không có, cần module trong repo |
| Định dạng file nhận | PEM riêng lẻ (`.crt`, `.key`, `.pem`) | PEM riêng lẻ |
| Giám sát hạn chứng chỉ | Không có, dùng cron thủ công | Addon `smimemonitor` |
| Nhật ký ký số | Không có | Addon `smimemonitor` |

Điểm hay nhầm nhất với HostBill: plugin nhận **file PEM riêng lẻ**, không phải file `.pfx`. Xem mục 2 trong hướng dẫn.
