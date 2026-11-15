# Chuẩn bị chứng chỉ S/MIME

**Tiếng Việt** | [English](../en/certificate-preparation.md)

Từ tạo CSR đến khi có bộ ba file dùng được cho WHMCS hoặc HostBill.

**Đọc tài liệu này trước**, rồi mới sang hướng dẫn của nền tảng bạn dùng.




## A. Chuẩn bị

### A1. Quyết định trước khi bắt đầu

| Hạng mục | Khuyến nghị |
|---|---|
| Loại cert | S/MIME Organization Validation (OV) — hiển thị tên công ty |
| Nhà cung cấp | Sectigo, DigiCert, GlobalSign |
| Email ký | Một địa chỉ cố định, ví dụ `hotro@congty.vn` |
| Độ dài key | RSA 4096 (hoặc 2048 nếu hệ thống cũ) |
| Nơi tạo CSR | **Trên server Linux**, không phải Windows |

### A2. Vì sao không tạo CSR trên Windows

File `.pfx` xuất từ Windows (qua `certreq` hoặc certmgr) mã hoá bằng **RC2-40-CBC** hoặc **3DES + SHA1**. OpenSSL 3.x đẩy các thuật toán này sang legacy provider và không bật mặc định — PHP lại không có cờ `-legacy`, nên `openssl_pkcs12_read()` sẽ luôn thất bại với thông báo `bad decrypt` dù mật khẩu hoàn toàn đúng.

Nếu buộc phải dùng file `.pfx` từ Windows:

```bash
openssl pkcs12 -in cu.pfx -nodes -legacy -out tam.pem
openssl pkcs12 -export -in tam.pem -out moi.pfx
shred -u tam.pem
```

### A3. Nguyên tắc bắt buộc

1. **Private key không tải lại được từ CA.** File CA gửi chỉ chứa chứng chỉ. Mất key = phải reissue.
2. **Passphrase truyền qua biến môi trường**, đừng gõ tay ở prompt. Gõ tay là nguồn gốc phổ biến nhất của lỗi `bad decrypt`.
3. **Passphrase chỉ dùng chữ và số.** Ký tự đặc biệt có thể bị escape sai khi lưu vào database.
4. **Dán từng khối lệnh**, chờ kết quả rồi mới sang khối tiếp.
5. **Backup thay vì xoá.** Chuyển vào thư mục backup, đừng `rm`.

---

## B. Tạo CSR và private key

### B1. Chọn thư mục

Ưu tiên **ngoài web root**:

```bash
mkdir -p /etc/smime && cd /etc/smime
```

Nếu buộc phải để trong web root (do `open_basedir` của panel giới hạn), phải chặn HTTP — xem mục E3.

### B2. Tạo CSR

```bash
openssl req -new -newkey rsa:4096 -nodes \
  -keyout private.key \
  -out request.csr \
  -subj "/C=VN/ST=Ho Chi Minh/L=Ho Chi Minh City/O=TEN CONG TY VIET HOA/OU=Phong Ky Thuat/CN=Ten Hien Thi/emailAddress=hotro@congty.vn"

chmod 600 private.key
```

Giải thích các trường:

| Trường | Ý nghĩa | Lưu ý |
|---|---|---|
| `C` | Mã quốc gia | `VN` |
| `ST` | Tỉnh/thành | Không viết tắt |
| `L` | Thành phố | |
| `O` | **Tên công ty** | Phải trùng **chính xác** giấy phép kinh doanh |
| `OU` | Phòng ban | Tuỳ chọn |
| `CN` | Tên hiển thị | Ví dụ `Cong Ty ABC Support` |
| `emailAddress` | **Địa chỉ ký** | Sẽ thành SAN trong cert |

Tên công ty sai một ký tự so với hồ sơ đăng ký là CA sẽ từ chối, phải làm lại từ đầu.

### B3. Kiểm tra CSR trước khi nộp

```bash
openssl req -in request.csr -noout -subject -verify
cat request.csr
```

Copy toàn bộ nội dung (kể cả dòng `BEGIN`/`END`) nộp lên portal CA.

### B4. Backup private key NGAY

```bash
cp private.key /root/backup-smime-$(date +%Y%m%d).key
chmod 600 /root/backup-smime-*.key
```

Copy thêm một bản ra ngoài server (két mật khẩu, ổ mã hoá).

---

## C. Xác thực tổ chức

Với cert OV, CA sẽ xác minh:

- Giấy phép kinh doanh (bản scan)
- Quyền sở hữu tên miền (DNS TXT record hoặc email tới `admin@tenmien`)
- Gọi điện xác nhận tới số điện thoại công khai của công ty

Thời gian thường 1–5 ngày làm việc. Nhận về file `.p7b` hoặc `.crt` kèm chuỗi CA.

---

## D. Xử lý file CA gửi về

### D1. Cách tự động (khuyến nghị)

```bash
chmod +x .../scripts/smime-setup.sh
./.../scripts/smime-setup.sh -p 'ten_file.p7b' -k private.key -w 'PassphraseMoi2026'
```

Script tự động: chuyển định dạng, phân loại cert bằng `basicConstraints`, đối chiếu key với cert, tạo key có passphrase, và ký thử.

Kết quả là 3 file: `smime.crt`, `ca-chain.pem`, `smime-hb.key`.

### D2. Cách thủ công

**Chuyển .p7b sang PEM:**

```bash
openssl pkcs7 -print_certs -in 'ten_file.p7b' -out chain.pem
grep -c 'BEGIN CERTIFICATE' chain.pem
```

**Tách và xem từng cert:**

```bash
mkdir -p sp && cd sp
csplit -z -f c- -b '%02d.pem' ../chain.pem '/BEGIN CERTIFICATE/' '{*}'
for f in c-*.pem; do echo "== $f"; openssl x509 -in "$f" -noout -subject 2>/dev/null; done
```

Kết quả điển hình từ Sectigo:

```
== c-00.pem                          <- rỗng, bỏ qua
== c-01.pem  O = TEN CONG TY         <- CERT CỦA BẠN
== c-02.pem  CN = Sectigo ... CA R36 <- intermediate
== c-03.pem  CN = Sectigo ... Root R46 <- intermediate
== c-04.pem  CN = USERTrust RSA ...  <- root, không cần
```

**Ghép (sửa số theo kết quả thực tế):**

```bash
cp c-01.pem ../smime.crt
cat c-02.pem c-03.pem > ../ca-chain.pem
cd .. && rm -rf sp
```

**Quy tắc:** `ca-chain.pem` chứa **các cert intermediate**, không có cert của bạn, không cần root tự ký.

**Kiểm tra:**

```bash
openssl x509 -in smime.crt -noout -subject -dates -ext subjectAltName
openssl crl2pkcs7 -nocrl -certfile ca-chain.pem | openssl pkcs7 -print_certs -noout
```

### D3. Đối chiếu key với cert

**Bước quan trọng nhất.** Key không khớp cert thì mọi thứ phía sau vô nghĩa.

```bash
openssl x509 -in smime.crt -noout -modulus | openssl md5
openssl rsa  -in private.key -noout -modulus | openssl md5
```

Hai chuỗi MD5 phải **trùng khớp**.

Lưu ý: dùng `rsa`, không dùng `pkey` — `pkey` không có tuỳ chọn `-modulus` và sẽ báo `Unknown cipher: modulus`.

### D4. Tạo key có passphrase

```bash
export NEWPW='PassphraseMoi2026'
openssl pkey -in private.key -aes-256-cbc -passout env:NEWPW -out smime-hb.key
openssl pkey -in smime-hb.key -passin env:NEWPW -noout && echo "PASS DUNG"
```

Phải thấy `PASS DUNG`. Passphrase lấy từ biến nên không thể gõ sai.

### D5. Test ký

```bash
echo test > /tmp/t.txt
openssl smime -sign -in /tmp/t.txt -text -signer smime.crt \
  -inkey smime-hb.key -passin env:NEWPW \
  -certfile ca-chain.pem -out /tmp/t.p7m
openssl smime -verify -in /tmp/t.p7m -CAfile ca-chain.pem -out /dev/null
shred -u /tmp/t.txt /tmp/t.p7m
```

Nếu báo `unable to get issuer certificate`: đây **không phải lỗi chain sai**. Khi verify bằng `-CAfile`, OpenSSL chỉ tin những gì trong file đó và không dùng trust store hệ thống. Root CA nằm ngoài file nên không tìm được neo tin cậy. Ghép thêm root vào file tạm để kiểm tra:

```bash
cat ca-chain.pem /etc/ssl/certs/USERTrust_RSA_Certification_Authority.pem > /tmp/verify.pem
openssl smime -verify -in /tmp/t.p7m -CAfile /tmp/verify.pem -out /dev/null
```

`ca-chain.pem` dùng cho WHMCS/HostBill vẫn giữ nguyên, không thêm root.

---

## E. Phân quyền và bảo mật

### E1. Xác định user chạy PHP

```bash
ps -o user= -C php-fpm | sort -u | grep -v root
```

Thường gặp: `www-data` (Debian/Ubuntu), `nginx`/`apache` (RHEL), `www` (aaPanel), `admin` (DirectAdmin).

### E2. Phân quyền

```bash
PHPUSER=www-data   # đổi cho khớp
chown root:"$PHPUSER" smime.crt ca-chain.pem smime-hb.key private.key .
chmod 640 smime.crt ca-chain.pem smime-hb.key private.key
chmod 750 .
```

**Lỗi phổ biến nhất:** chỉ đổi quyền file mà quên **thư mục**. User PHP không có bit `x` trên thư mục thì không vào được, dù file bên trong đã mở group. Triệu chứng: `Permission denied` kèm `BIO routines::system lib`.

Kiểm tra:

```bash
sudo -u "$PHPUSER" test -r "$(pwd)/smime-hb.key" && echo OK || echo CHAN
namei -l smime-hb.key     # xem mắc ở tầng thư mục nào
```

### E3. Chặn truy cập HTTP

**Bắt buộc nếu thư mục nằm trong web root.**

Apache / LiteSpeed:

```bash
printf 'Require all denied\n' > .htaccess
```

Nginx — thêm vào server block rồi `nginx -t && systemctl reload nginx`:

```nginx
location ^~ /smime/ {
    deny all;
    return 404;
}
```

Kiểm tra thật:

```bash
curl -sI https://ten-mien/smime/smime-hb.key | head -1
```

Phải ra **403** hoặc **404**. Ra **200** nghĩa là private key đang tải về được từ internet — dừng mọi việc, xử lý ngay.

### E4. open_basedir

Panel như aaPanel/DirectAdmin thường giới hạn mỗi site chỉ đọc được trong thư mục của nó:

```bash
grep -r open_basedir /etc/php*/
```

Nếu có giá trị, hoặc thêm đường dẫn cert vào, hoặc để cert trong thư mục site rồi chặn HTTP.

### E5. Dọn dẹp

```bash
unset NEWPW
cat /dev/null > ~/.bash_history && history -c
shred -u /tmp/*.php /tmp/t.* 2>/dev/null
```

---

## F. Chẩn đoán

```bash
chmod +x .../scripts/smime-check.sh
./.../scripts/smime-check.sh /duong/dan/thu-muc-smime
```

Script kiểm tra: môi trường OpenSSL/PHP, thông tin và hạn cert, cấu trúc chain, quyền file, khả năng đọc của user PHP, và cảnh báo rò rỉ HTTP.

---

## G. Bước tiếp theo

- **HostBill** → [Hướng dẫn HostBill](huong-dan-hostbill.md)
- **WHMCS** → [Hướng dẫn WHMCS](huong-dan-whmcs.md)
- **Gặp lỗi** → [Xử lý lỗi](xu-ly-loi.md)

---

## H. Danh sách file cuối cùng

| File | Vai trò | Xoá được? |
|---|---|---|
| `private.key` | Key gốc | **KHÔNG** — mất là phải reissue |
| `smime.crt` | Certificate | **KHÔNG** |
| `ca-chain.pem` | Chuỗi CA | **KHÔNG** |
| `smime-hb.key` | Key có passphrase, hệ thống dùng | Tái tạo được từ `private.key` |
| `request.csr` | CSR gốc | Nên giữ để gia hạn |
| `*.p7b`, `chain.pem` | File trung gian | Backup rồi xoá được |
| `*.pfx` | Chỉ cần khi import Outlook/Thunderbird | Tái tạo được |

---

## I. Gia hạn

Cert S/MIME thường có hạn 1 năm. Đặt nhắc lịch **trước 30 ngày**.

Kiểm tra hạn:

```bash
openssl x509 -in smime.crt -noout -subject -issuer -dates
```

Reissue bằng CSR cũ (nhanh nhất, key giữ nguyên):
- Nộp lại `request.csr` vào mục Reissue trên portal CA
- Nhận `.p7b` mới, chạy lại mục D2, chỉ thay `smime.crt` và `ca-chain.pem`
- Cấu hình module không cần đổi gì

Tạo CSR mới (khi mất key hoặc muốn đổi thông tin): làm lại từ mục B.

Với WHMCS, addon S/MIME Monitor tự gửi cảnh báo ở các mốc 30/14/7/3/1 ngày.
