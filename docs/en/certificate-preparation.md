# S/MIME Certificate Preparation

[Tiếng Việt](../vi/chuan-bi-chung-chi.md) | **English**

From creating the CSR to having the three ready-to-use files for WHMCS or HostBill.

**Read this document first**, then move on to the guide for the platform you use.

## A. Preparation

### A1. Decisions to make before you start

| Item | Recommendation |
|---|---|
| Cert type | S/MIME Organization Validation (OV) — shows the company name |
| Provider | Sectigo, DigiCert, GlobalSign |
| Signing email | One fixed address, e.g. `hotro@congty.vn` |
| Key length | RSA 4096 (or 2048 for older systems) |
| Where to create the CSR | **On a Linux server**, not Windows |

### A2. Why not to create the CSR on Windows

A `.pfx` file exported from Windows (via `certreq` or certmgr) is encrypted with **RC2-40-CBC** or **3DES + SHA1**. OpenSSL 3.x moves these algorithms to the legacy provider and does not enable it by default — and PHP has no `-legacy` flag, so `openssl_pkcs12_read()` will always fail with the message `bad decrypt` even when the password is completely correct.

If you must use a `.pfx` file from Windows:

```bash
openssl pkcs12 -in cu.pfx -nodes -legacy -out tam.pem
openssl pkcs12 -export -in tam.pem -out moi.pfx
shred -u tam.pem
```

### A3. Mandatory rules

1. **The private key cannot be re-downloaded from the CA.** The file the CA sends contains only the certificate. Losing the key = you must reissue.
2. **Pass the passphrase through an environment variable**, do not type it at the prompt. Typing it by hand is the most common cause of `bad decrypt` errors.
3. **Use only letters and digits in the passphrase.** Special characters may be escaped incorrectly when stored in a database.
4. **Paste one command block at a time**, wait for the result before moving to the next block.
5. **Back up instead of deleting.** Move files into a backup directory, do not `rm` them.

---

## B. Create the CSR and private key

### B1. Choose the directory

Preferably **outside the web root**:

```bash
mkdir -p /etc/smime && cd /etc/smime
```

If you must keep it inside the web root (because the panel's `open_basedir` limits you), you have to block HTTP access — see section E3.

### B2. Create the CSR

```bash
openssl req -new -newkey rsa:4096 -nodes \
  -keyout private.key \
  -out request.csr \
  -subj "/C=VN/ST=Ho Chi Minh/L=Ho Chi Minh City/O=TEN CONG TY VIET HOA/OU=Phong Ky Thuat/CN=Ten Hien Thi/emailAddress=hotro@congty.vn"

chmod 600 private.key
```

What the fields mean:

| Field | Meaning | Notes |
|---|---|---|
| `C` | Country code | `VN` |
| `ST` | Province/city | Do not abbreviate |
| `L` | City | |
| `O` | **Company name** | Must match the business license **exactly** |
| `OU` | Department | Optional |
| `CN` | Display name | e.g. `Cong Ty ABC Support` |
| `emailAddress` | **Signing address** | Becomes the SAN in the cert |

If the company name is off by a single character from the registered record, the CA will reject it and you have to start over.

### B3. Check the CSR before submitting

```bash
openssl req -in request.csr -noout -subject -verify
cat request.csr
```

Copy the entire content (including the `BEGIN`/`END` lines) and submit it on the CA portal.

### B4. Back up the private key NOW

```bash
cp private.key /root/backup-smime-$(date +%Y%m%d).key
chmod 600 /root/backup-smime-*.key
```

Keep another copy off the server (password vault, encrypted drive).

---

## C. Organization validation

For an OV cert, the CA verifies:

- The business license (a scan)
- Domain ownership (DNS TXT record or an email to `admin@tenmien`)
- A phone call to the company's publicly listed number

This usually takes 1–5 business days. You receive a `.p7b` or `.crt` file with the CA chain.

---

## D. Process the files sent by the CA

### D1. Automatic way (recommended)

```bash
chmod +x .../scripts/smime-setup.sh
./.../scripts/smime-setup.sh -p 'ten_file.p7b' -k private.key -w 'PassphraseMoi2026'
```

The script automatically: converts formats, classifies the certs by `basicConstraints`, matches the key against the cert, creates a passphrase-protected key, and performs a test signing.

The result is 3 files: `smime.crt`, `ca-chain.pem`, `smime-hb.key`.

### D2. Manual way

**Convert .p7b to PEM:**

```bash
openssl pkcs7 -print_certs -in 'ten_file.p7b' -out chain.pem
grep -c 'BEGIN CERTIFICATE' chain.pem
```

**Split and inspect each cert:**

```bash
mkdir -p sp && cd sp
csplit -z -f c- -b '%02d.pem' ../chain.pem '/BEGIN CERTIFICATE/' '{*}'
for f in c-*.pem; do echo "== $f"; openssl x509 -in "$f" -noout -subject 2>/dev/null; done
```

Typical output from Sectigo:

```
== c-00.pem                          <- empty, ignore
== c-01.pem  O = TEN CONG TY         <- YOUR CERT
== c-02.pem  CN = Sectigo ... CA R36 <- intermediate
== c-03.pem  CN = Sectigo ... Root R46 <- intermediate
== c-04.pem  CN = USERTrust RSA ...  <- root, not needed
```

**Assemble (adjust the numbers to your actual output):**

```bash
cp c-01.pem ../smime.crt
cat c-02.pem c-03.pem > ../ca-chain.pem
cd .. && rm -rf sp
```

**Rule:** `ca-chain.pem` contains the **intermediate certs** — no cert of your own, and no self-signed root.

**Verify:**

```bash
openssl x509 -in smime.crt -noout -subject -dates -ext subjectAltName
openssl crl2pkcs7 -nocrl -certfile ca-chain.pem | openssl pkcs7 -print_certs -noout
```

### D3. Match the key against the cert

**The most important step.** If the key does not match the cert, everything after this is meaningless.

```bash
openssl x509 -in smime.crt -noout -modulus | openssl md5
openssl rsa  -in private.key -noout -modulus | openssl md5
```

The two MD5 strings must **match exactly**.

Note: use `rsa`, not `pkey` — `pkey` has no `-modulus` option and will report `Unknown cipher: modulus`.

### D4. Create a passphrase-protected key

```bash
export NEWPW='PassphraseMoi2026'
openssl pkey -in private.key -aes-256-cbc -passout env:NEWPW -out smime-hb.key
openssl pkey -in smime-hb.key -passin env:NEWPW -noout && echo "PASS DUNG"
```

You must see `PASS DUNG`. The passphrase comes from the variable, so it cannot be mistyped.

### D5. Test signing

```bash
echo test > /tmp/t.txt
openssl smime -sign -in /tmp/t.txt -text -signer smime.crt \
  -inkey smime-hb.key -passin env:NEWPW \
  -certfile ca-chain.pem -out /tmp/t.p7m
openssl smime -verify -in /tmp/t.p7m -CAfile ca-chain.pem -out /dev/null
shred -u /tmp/t.txt /tmp/t.p7m
```

If it reports `unable to get issuer certificate`: this is **not a wrong-chain error**. When verifying with `-CAfile`, OpenSSL trusts only what is in that file and does not use the system trust store. The root CA is outside the file, so there is no trust anchor. Append the root to a temporary file to check:

```bash
cat ca-chain.pem /etc/ssl/certs/USERTrust_RSA_Certification_Authority.pem > /tmp/verify.pem
openssl smime -verify -in /tmp/t.p7m -CAfile /tmp/verify.pem -out /dev/null
```

The `ca-chain.pem` used for WHMCS/HostBill stays as-is — do not add the root to it.

---

## E. Permissions and security

### E1. Identify the user running PHP

```bash
ps -o user= -C php-fpm | sort -u | grep -v root
```

Common values: `www-data` (Debian/Ubuntu), `nginx`/`apache` (RHEL), `www` (aaPanel), `admin` (DirectAdmin).

### E2. Set permissions

```bash
PHPUSER=www-data   # change to match
chown root:"$PHPUSER" smime.crt ca-chain.pem smime-hb.key private.key .
chmod 640 smime.crt ca-chain.pem smime-hb.key private.key
chmod 750 .
```

**The most common mistake:** changing file permissions but forgetting the **directory**. Without the `x` bit on the directory, the PHP user cannot get in, even when the files inside are group-readable. Symptom: `Permission denied` together with `BIO routines::system lib`.

Check:

```bash
sudo -u "$PHPUSER" test -r "$(pwd)/smime-hb.key" && echo OK || echo CHAN
namei -l smime-hb.key     # see which directory level blocks access
```

### E3. Block HTTP access

**Mandatory if the directory is inside the web root.**

Apache / LiteSpeed:

```bash
printf 'Require all denied\n' > .htaccess
```

Nginx — add to the server block, then `nginx -t && systemctl reload nginx`:

```nginx
location ^~ /smime/ {
    deny all;
    return 404;
}
```

Test it for real:

```bash
curl -sI https://ten-mien/smime/smime-hb.key | head -1
```

You must get **403** or **404**. A **200** means the private key is downloadable from the internet — stop everything and fix it immediately.

### E4. open_basedir

Panels such as aaPanel/DirectAdmin often restrict each site to reading only inside its own directory:

```bash
grep -r open_basedir /etc/php*/
```

If a value is set, either add the cert path to it, or keep the cert inside the site directory and block HTTP access.

### E5. Clean up

```bash
unset NEWPW
cat /dev/null > ~/.bash_history && history -c
shred -u /tmp/*.php /tmp/t.* 2>/dev/null
```

---

## F. Diagnostics

```bash
chmod +x .../scripts/smime-check.sh
./.../scripts/smime-check.sh /path/to/smime-directory
```

The script checks: the OpenSSL/PHP environment, cert info and expiry, chain structure, file permissions, readability by the PHP user, and warns about HTTP leaks.

---

## G. Next steps

- **HostBill** → [HostBill guide](hostbill-guide.md)
- **WHMCS** → [WHMCS guide](whmcs-guide.md)
- **Something broke** → [Troubleshooting](troubleshooting.md)

---

## H. Final file list

| File | Role | Can it be deleted? |
|---|---|---|
| `private.key` | Original key | **NO** — losing it means reissue |
| `smime.crt` | Certificate | **NO** |
| `ca-chain.pem` | CA chain | **NO** |
| `smime-hb.key` | Passphrase-protected key used by the system | Regenerable from `private.key` |
| `request.csr` | Original CSR | Keep it for renewal |
| `*.p7b`, `chain.pem` | Intermediate files | Back up, then delete |
| `*.pfx` | Only needed to import into Outlook/Thunderbird | Regenerable |

---

## I. Renewal

S/MIME certs are usually valid for 1 year. Set a reminder **30 days before** expiry.

Check expiry:

```bash
openssl x509 -in smime.crt -noout -subject -issuer -dates
```

Reissue with the old CSR (fastest — the key stays the same):
- Resubmit `request.csr` in the Reissue section of the CA portal
- Receive the new `.p7b`, rerun section D2, replacing only `smime.crt` and `ca-chain.pem`
- The module configuration needs no changes

Create a new CSR (if the key is lost or you want to change details): start again from section B.

With WHMCS, the S/MIME Monitor addon sends alerts automatically at 30/14/7/3/1 days before expiry.
