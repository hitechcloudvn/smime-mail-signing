# HostBill — Cấu hình ký S/MIME

Đọc [CHUAN-BI-CHUNG-CHI.md](CHUAN-BI-CHUNG-CHI.md) (phần A–E) trước để có 3 file: `smime.crt`, `ca-chain.pem`, `smime-hb.key`.

---

## 1. HostBill có sẵn plugin

Khác WHMCS, HostBill có plugin **S/MIME Mail Signature** miễn phí cho mọi khách hàng. Không cần viết code, chỉ cần bật và khai đường dẫn.

Lưu ý về nền tảng: HostBill viết bằng PHP, phần lớn source mã hoá bằng ionCube nên không sửa được code lõi. Plugin dùng hàm `openssl_pkcs7_sign()` của PHP, tức nó phụ thuộc vào OpenSSL của hệ thống — đây là lý do các vấn đề về thuật toán legacy và quyền file ảnh hưởng trực tiếp tới việc ký.

---

## 2. Plugin dùng file PEM, KHÔNG dùng .pfx

Điểm hay nhầm nhất. Plugin nhận **3 file riêng lẻ**:

- Certificate (`.crt`)
- Private key (`.key`)
- CA chain (`.pem`)

Nếu bạn có sẵn file `.pfx`, phải tách ra:

```bash
openssl pkcs12 -in cu.pfx -clcerts -nokeys -out smime.crt
openssl pkcs12 -in cu.pfx -cacerts -nokeys -out ca-chain.pem
openssl pkcs12 -in cu.pfx -nocerts -out smime-hb.key
```

Nếu các lệnh trên báo `bad decrypt` mà mật khẩu đúng, thêm cờ `-legacy` (file xuất từ Windows đời cũ).

---

## 3. Bật plugin

**Settings → Modules → Plugins** → tab **Inactive** → tìm `Smime_mail_sign` → **Activate**.

Sau khi bật, HostBill chuyển về Settings → Modules → Plugins để cấu hình:

- Module display name: `S/MIME Sign`
- Chọn cho toàn bộ staff dùng, hay chỉ vài staff được chỉ định

Lưu lại.

---

## 4. Khai chứng chỉ

Mở lại module vừa cấu hình, thêm một mục mới:

| Trường | Giá trị mẫu |
|---|---|
| Mail from | `hotro@congty.vn` |
| Certificate path | `/etc/smime/smime.crt` |
| Certificate key path | `/etc/smime/smime-hb.key` |
| Certificate CA path | `/etc/smime/ca-chain.pem` |
| Password | `PassphraseMoi2026` |

Đường dẫn phải **tuyệt đối**. Copy dán, đừng gõ tay.

Module có nút **validate** để kiểm tra chứng chỉ trước khi lưu — luôn bấm nút này trước.

### Về trường Password

Đây là passphrase của **private key**, không phải mật khẩu file `.pfx`. Nhập sai sẽ ra lỗi:

```
Signing Error: error:1C800064:Provider routines::bad decrypt
```

Nếu key không mã hoá thì để trống. Ngược lại, nếu để đường dẫn trỏ vào key không mã hoá mà vẫn báo `bad decrypt`, chắc chắn plugin đang đọc file khác — kiểm tra lại đường dẫn.

### Về trường Mail from

Phải khớp **từng ký tự** với SAN trong certificate:

```bash
openssl x509 -in smime.crt -noout -ext subjectAltName
```

Có thể khai nhiều mục cho nhiều địa chỉ, nhưng mỗi địa chỉ cần một cert riêng có SAN tương ứng.

---

## 5. Đối chiếu địa chỉ gửi

**Settings → General Settings → Messages → Outgoing mail configuration**

Địa chỉ From ở đây phải trùng với ô Mail from trong plugin. Lệch nhau thì hoặc mail không được ký, hoặc client báo chữ ký không khớp người gửi.

---

## 6. Kiểm tra quyền file

Đây là nguyên nhân phổ biến thứ hai sau passphrase.

```bash
# User chạy PHP
ps -o user= -C php-fpm | sort -u | grep -v root

# Test đọc thật
sudo -u www-data test -r /etc/smime/smime-hb.key && echo OK || echo CHAN

# Xem mắc ở tầng nào
namei -l /etc/smime/smime-hb.key
```

Nếu bị chặn, xem mục E2 trong [CHUAN-BI-CHUNG-CHI.md](CHUAN-BI-CHUNG-CHI.md). Nhớ đổi quyền **cả thư mục**, không chỉ file.

Kiểm tra `open_basedir` — panel thường giới hạn mỗi site chỉ đọc trong thư mục của nó:

```bash
grep -r open_basedir /etc/php*/
```

---

## 7. Kiểm tra kết quả

Bấm **validate** trong module, sau đó gửi một mail test (tạo hoá đơn nháp rồi gửi, hoặc gửi mail thủ công cho client test).

Mở mail ở Gmail → ba chấm → **Show original**. Cần thấy:

```
Content-Type: multipart/signed; protocol="application/pkcs7-signature"
```

kèm phần đính kèm `smime.p7s`.

Nếu vẫn lỗi, xem log:

```bash
ls -lt /duong/dan/hostbill/includes/logs/ | head
tail -50 /var/log/php*fpm*.log
```

---

## 8. Chẩn đoán nhanh bằng PHP

Chạy đúng cách plugin gọi, để tách lỗi chứng chỉ khỏi lỗi HostBill:

```bash
sudo -u www-data php -r '
$d = "/etc/smime/";
var_dump(openssl_pkey_get_private("file://".$d."smime-hb.key", "PassphraseMoi2026") !== false);
var_dump((bool)openssl_x509_read(file_get_contents($d."smime.crt")));
while ($e = openssl_error_string()) echo $e, PHP_EOL;'
```

Cần `bool(true)` cả hai dòng. Nếu ở đây đã fail thì vấn đề nằm ngoài HostBill — quay lại phần E của [CHUAN-BI-CHUNG-CHI.md](CHUAN-BI-CHUNG-CHI.md).

Hoặc dùng script có sẵn:

```bash
../scripts/smime-check.sh /etc/smime www-data
```

---

## 9. Gia hạn

Khi reissue bằng CSR cũ, chỉ cần thay hai file `smime.crt` và `ca-chain.pem`, giữ nguyên key và cấu hình plugin. Không cần khai lại gì trong HostBill.

Kiểm tra hạn định kỳ:

```bash
openssl x509 -in /etc/smime/smime.crt -noout -dates
```

HostBill không có cảnh báo hết hạn tự động. Đặt cron nhắc:

```bash
cat > /etc/cron.daily/smime-expiry <<'EOF'
#!/bin/bash
CERT=/etc/smime/smime.crt
MAIL=admin@congty.vn
DAYS=$(( ($(date -d "$(openssl x509 -in $CERT -noout -enddate | cut -d= -f2)" +%s) - $(date +%s)) / 86400 ))
if [ "$DAYS" -le 30 ]; then
    echo "Chung chi S/MIME con $DAYS ngay. File: $CERT" \
        | mail -s "[Canh bao] Chung chi S/MIME con $DAYS ngay" "$MAIL"
fi
EOF
chmod +x /etc/cron.daily/smime-expiry
```
