# Xử lý lỗi S/MIME

**Tiếng Việt** | [English](../en/troubleshooting.md)

Tra theo thông báo lỗi. Mỗi mục gồm: nguyên nhân thật, cách xác minh, cách sửa.

---

## `error:1C800064:Provider routines::bad decrypt`

Kèm `PKCS12_pbe_crypt_ex:pkcs12 cipherfinal error` hoặc `ossl_cipher_unpadblock`.

**Nghĩa:** OpenSSL giải mã được dữ liệu nhưng padding không hợp lệ. Trong bối cảnh ký mail, gần như luôn là **không mở được private key**.

**Nguyên nhân theo thứ tự khả năng:**

**1. Sai passphrase (~80%).** Xác minh:

```bash
openssl pkey -in smime-hb.key -noout && echo "PASS DUNG"
```

Nếu lệnh trên chạy được mà module vẫn lỗi, chuỗi trong module khác chuỗi bạn gõ.

**2. Passphrase bị "bẩn".** Khoảng trắng cuối, ký tự xuống dòng, hoặc ký tự đặc biệt bị escape khi lưu vào database. Cách chắc chắn: tạo lại key với passphrase **chỉ chữ và số**:

```bash
export NEWPW='ChiChuVaSo2026'
openssl pkey -in private.key -aes-256-cbc -passout env:NEWPW -out smime-hb.key
openssl pkey -in smime-hb.key -passin env:NEWPW -noout && echo "PASS DUNG"
```

Truyền qua `env:` thay vì gõ tay loại bỏ hoàn toàn khả năng gõ lệch — đây là lỗi thường gặp nhất khi nhập ở prompt không nhìn thấy ký tự.

**3. Module đang đọc file khác.** Mẹo phân biệt: nếu đường dẫn trỏ vào key **không** mã hoá mà vẫn báo `bad decrypt`, chắc chắn nó đang đọc file khác — OpenSSL bỏ qua password với key không mã hoá.

Kiểm tra bằng cách tạm đổi tên file cũ:

```bash
mv smime.key smime.key.hold
```

Bấm validate lại. Nếu lỗi đổi thành "không tìm thấy file" thì đúng là đường dẫn sai.

**4. File `.pfx` dùng thuật toán legacy.** Chỉ áp dụng khi module đọc `.pfx`. Xác minh:

```bash
openssl pkcs12 -in cu.pfx -info -nokeys            # fail
openssl pkcs12 -in cu.pfx -info -nokeys -legacy    # chạy được
```

PHP không có cờ `-legacy`, nên phải chuyển đổi:

```bash
openssl pkcs12 -in cu.pfx -nodes -legacy -out tam.pem
openssl pkcs12 -export -in tam.pem -out moi.pfx
shred -u tam.pem
```

---

## `error:FFFFFFFF8000000D:system library::Permission denied`

Kèm `BIO routines::system lib`.

**Nghĩa:** User chạy PHP không đọc được file.

**Nguyên nhân phổ biến nhất:** đã đổi quyền file nhưng **quên thư mục**. Không có bit `x` trên thư mục thì không vào được, dù file bên trong đã mở group.

```bash
PHPUSER=$(ps -o user= -C php-fpm | sort -u | grep -v root | head -1)
chown root:"$PHPUSER" /etc/smime /etc/smime/*
chmod 750 /etc/smime
chmod 640 /etc/smime/*
```

**Xác minh:**

```bash
sudo -u "$PHPUSER" test -r /etc/smime/smime-hb.key && echo OK || echo CHAN
namei -l /etc/smime/smime-hb.key   # xem mắc ở tầng nào
```

`namei -l` liệt kê quyền từng cấp thư mục trên đường dẫn — thấy ngay chỗ bị chặn.

**Nguyên nhân khác:**
- `open_basedir` chặn: `grep -r open_basedir /etc/php*/`
- SELinux: `getenforce`

---

## `Mac verify error: invalid password?`

Chỉ xuất hiện khi mở file `.pfx`.

**Nghĩa:** Sai mật khẩu file `.pfx` — đây **không phải** lỗi legacy provider. Lỗi xảy ra ở bước kiểm tra MAC, trước khi OpenSSL động tới thuật toán mã hoá nội dung. Nếu là legacy, MAC verify sẽ xong rồi mới fail ở bước sau.

**Xử lý:** Nếu còn `.key` và `.crt`, dựng lại `.pfx` thay vì đoán mật khẩu:

```bash
openssl pkcs12 -export -inkey smime.key -in smime.crt \
  -certfile ca-chain.pem \
  -keypbe aes-256-cbc -certpbe aes-256-cbc -macalg sha256 \
  -out moi.pfx
```

---

## `unable to get issuer certificate`

Xuất hiện khi verify chữ ký.

**Hai trường hợp khác nhau:**

**1. Chain thiếu cert intermediate.** Kiểm tra:

```bash
openssl crl2pkcs7 -nocrl -certfile ca-chain.pem | openssl pkcs7 -print_certs -noout
```

Với Sectigo cần đủ **CA R36 và Root R46**. Thiếu một cái là lỗi này.

**2. Verify thiếu root CA — không phải lỗi thật.** Khi dùng `-CAfile`, OpenSSL chỉ tin những gì trong file đó, không dùng trust store hệ thống. Root CA nằm ngoài file nên không có neo tin cậy.

Xác minh bằng cách ghép thêm root vào file tạm:

```bash
cat ca-chain.pem /etc/ssl/certs/USERTrust_RSA_Certification_Authority.pem > /tmp/v.pem
openssl smime -verify -in /tmp/t.p7m -CAfile /tmp/v.pem -out /dev/null
```

Nếu giờ thành công thì chain vẫn đúng, `ca-chain.pem` giữ nguyên cho module.

---

## `No certificate matches private key`

**Nghĩa:** Key không thuộc certificate này.

```bash
openssl x509 -in smime.crt -noout -modulus | openssl md5
openssl rsa  -in private.key -noout -modulus | openssl md5
```

Hai MD5 phải trùng. Lệch nhau thì tìm đúng key, hoặc reissue với CSR mới.

---

## `pkey: Unknown cipher: modulus`

**Nghĩa:** Dùng nhầm lệnh. `pkey` không có tuỳ chọn `-modulus`.

```bash
openssl rsa -in key.pem -noout -modulus | openssl md5   # đúng
```

---

## `SMTP Error: The following recipients failed: xxx: No such recipient here`

**Nghĩa:** Lỗi SMTP, không liên quan S/MIME. Hộp thư nhận không tồn tại.

Điểm tích cực: lỗi dừng ở bước `RCPT TO` nghĩa là kết nối, xác thực và **cả phần ký đều đã chạy trót lọt**.

**Xác minh hộp thư:**

```bash
swaks --to test@congty.vn --from hotro@congty.vn --server mail.congty.vn --quit-after RCPT
```

Không có `swaks` thì thủ công:

```bash
openssl s_client -connect mail.congty.vn:587 -starttls smtp -quiet
EHLO test
MAIL FROM:<hotro@congty.vn>
RCPT TO:<test@congty.vn>
QUIT
```

`250` là tồn tại, `550` là không có.

---

## Mail gửi được nhưng không có chữ ký

**Kiểm tra theo thứ tự:**

1. **From có khớp cấu hình không.** Nếu đã điền ô "Chỉ ký khi From là" / "Mail from", mail từ địa chỉ khác sẽ không được ký — đây là hành vi đúng, không phải lỗi.

2. **From có khớp SAN trong cert không:**

```bash
openssl x509 -in smime.crt -noout -ext subjectAltName
```

3. **Địa chỉ gửi của hệ thống:** WHMCS → System Settings → Mail; HostBill → General Settings → Messages → Outgoing mail configuration.

4. **Xem raw source** của mail nhận được. Nếu không thấy `multipart/signed` thì mail chưa qua module ký.

---

## Client báo "chữ ký không hợp lệ"

**Nguyên nhân thường gặp:**

| Triệu chứng | Nguyên nhân |
|---|---|
| Báo sai người gửi | From không khớp SAN trong cert |
| Báo không tin cậy | Chain thiếu intermediate |
| Báo cert hết hạn | Cần gia hạn |
| Chữ ký hỏng | Có thiết bị trung gian sửa nội dung mail (antivirus gateway, mailing list, footer injection) |

Trường hợp cuối đáng chú ý: nếu mail server hoặc gateway chèn thêm footer/disclaimer **sau khi** ký, chữ ký sẽ vỡ. Phải tắt tính năng đó, hoặc chèn footer trước khi ký.

---

## Chẩn đoán tổng thể

```bash
../../scripts/smime-check.sh /duong/dan/thu-muc-smime
```

Hoặc thủ công, chạy đúng cách module gọi:

```bash
sudo -u www-data php -r '
$d = "/etc/smime/";
var_dump(openssl_pkey_get_private("file://".$d."smime-hb.key", "PASSPHRASE") !== false);
var_dump((bool)openssl_x509_read(file_get_contents($d."smime.crt")));
while ($e = openssl_error_string()) echo $e, PHP_EOL;'
```

`bool(true)` cả hai dòng nghĩa là tầng file đã sạch — lỗi còn lại nằm ở cấu hình module hoặc SMTP.
