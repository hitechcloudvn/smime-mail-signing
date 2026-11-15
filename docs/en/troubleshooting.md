# S/MIME Troubleshooting

[Tiếng Việt](../vi/xu-ly-loi.md) | **English**

Look up the error message. Each entry gives: the real cause, how to verify it, how to fix it.

---

## `error:1C800064:Provider routines::bad decrypt`

Accompanied by `PKCS12_pbe_crypt_ex:pkcs12 cipherfinal error` or `ossl_cipher_unpadblock`.

**Meaning:** OpenSSL decrypted the data but the padding is invalid. In the mail-signing context, this almost always means **the private key cannot be opened**.

**Causes in order of likelihood:**

**1. Wrong passphrase (~80%).** Verify:

```bash
openssl pkey -in smime-hb.key -noout && echo "PASS DUNG"
```

If the command above works but the module still fails, the string stored in the module differs from what you typed.

**2. A "dirty" passphrase.** Trailing whitespace, a newline character, or special characters escaped when stored in the database. The sure fix: recreate the key with a **letters-and-digits-only** passphrase:

```bash
export NEWPW='ChiChuVaSo2026'
openssl pkey -in private.key -aes-256-cbc -passout env:NEWPW -out smime-hb.key
openssl pkey -in smime-hb.key -passin env:NEWPW -noout && echo "PASS DUNG"
```

Passing it via `env:` instead of typing it eliminates the possibility of typos entirely — that is the most common mistake when entering a passphrase at a prompt that shows no characters.

**3. The module is reading a different file.** How to tell: if the path points at an **unencrypted** key and you still get `bad decrypt`, it is definitely reading a different file — OpenSSL ignores the password for unencrypted keys.

Check by temporarily renaming the old file:

```bash
mv smime.key smime.key.hold
```

Press validate again. If the error changes to "file not found", the path was wrong.

**4. The `.pfx` file uses a legacy algorithm.** Only applies when the module reads a `.pfx`. Verify:

```bash
openssl pkcs12 -in cu.pfx -info -nokeys            # fail
openssl pkcs12 -in cu.pfx -info -nokeys -legacy    # works
```

PHP has no `-legacy` flag, so you must convert:

```bash
openssl pkcs12 -in cu.pfx -nodes -legacy -out tam.pem
openssl pkcs12 -export -in tam.pem -out moi.pfx
shred -u tam.pem
```

---

## `error:FFFFFFFF8000000D:system library::Permission denied`

Accompanied by `BIO routines::system lib`.

**Meaning:** The user running PHP cannot read the file.

**Most common cause:** file permissions were changed but the **directory** was forgotten. Without the `x` bit on the directory, access is impossible even when the file inside is group-readable.

```bash
PHPUSER=$(ps -o user= -C php-fpm | sort -u | grep -v root | head -1)
chown root:"$PHPUSER" /etc/smime /etc/smime/*
chmod 750 /etc/smime
chmod 640 /etc/smime/*
```

**Verify:**

```bash
sudo -u "$PHPUSER" test -r /etc/smime/smime-hb.key && echo OK || echo CHAN
namei -l /etc/smime/smime-hb.key   # see which level blocks access
```

`namei -l` lists the permissions of every directory level on the path — you see exactly where access is blocked.

**Other causes:**
- `open_basedir` blocking: `grep -r open_basedir /etc/php*/`
- SELinux: `getenforce`

---

## `Mac verify error: invalid password?`

Only appears when opening a `.pfx` file.

**Meaning:** The `.pfx` file password is wrong — this is **not** a legacy-provider error. The failure happens at the MAC check step, before OpenSSL ever touches the content encryption algorithm. If it were a legacy issue, MAC verification would succeed and the failure would come later.

**Fix:** If you still have the `.key` and `.crt`, rebuild the `.pfx` instead of guessing the password:

```bash
openssl pkcs12 -export -inkey smime.key -in smime.crt \
  -certfile ca-chain.pem \
  -keypbe aes-256-cbc -certpbe aes-256-cbc -macalg sha256 \
  -out moi.pfx
```

---

## `unable to get issuer certificate`

Appears when verifying a signature.

**Two different cases:**

**1. The chain is missing an intermediate cert.** Check:

```bash
openssl crl2pkcs7 -nocrl -certfile ca-chain.pem | openssl pkcs7 -print_certs -noout
```

With Sectigo you need both **CA R36 and Root R46**. Missing one of them causes this error.

**2. Verification without the root CA — not a real error.** When using `-CAfile`, OpenSSL trusts only what is in that file and does not use the system trust store. The root CA is outside the file, so there is no trust anchor.

Verify by appending the root to a temporary file:

```bash
cat ca-chain.pem /etc/ssl/certs/USERTrust_RSA_Certification_Authority.pem > /tmp/v.pem
openssl smime -verify -in /tmp/t.p7m -CAfile /tmp/v.pem -out /dev/null
```

If this succeeds, the chain is still correct — keep `ca-chain.pem` unchanged for the module.

---

## `No certificate matches private key`

**Meaning:** The key does not belong to this certificate.

```bash
openssl x509 -in smime.crt -noout -modulus | openssl md5
openssl rsa  -in private.key -noout -modulus | openssl md5
```

The two MD5s must match. If they differ, find the right key, or reissue with a new CSR.

---

## `pkey: Unknown cipher: modulus`

**Meaning:** Wrong command. `pkey` has no `-modulus` option.

```bash
openssl rsa -in key.pem -noout -modulus | openssl md5   # correct
```

---

## `SMTP Error: The following recipients failed: xxx: No such recipient here`

**Meaning:** An SMTP error, unrelated to S/MIME. The recipient mailbox does not exist.

The silver lining: failing at the `RCPT TO` step means the connection, authentication and **the signing itself all ran successfully**.

**Verify the mailbox:**

```bash
swaks --to test@congty.vn --from hotro@congty.vn --server mail.congty.vn --quit-after RCPT
```

Without `swaks`, do it manually:

```bash
openssl s_client -connect mail.congty.vn:587 -starttls smtp -quiet
EHLO test
MAIL FROM:<hotro@congty.vn>
RCPT TO:<test@congty.vn>
QUIT
```

`250` means it exists, `550` means it does not.

---

## Mail is sent but has no signature

**Check in this order:**

1. **Does From match the configuration?** If the "Only sign when From is" / "Mail from" field is filled in, mail from other addresses will not be signed — this is correct behavior, not a bug.

2. **Does From match the SAN in the cert:**

```bash
openssl x509 -in smime.crt -noout -ext subjectAltName
```

3. **The system's sending address:** WHMCS → System Settings → Mail; HostBill → General Settings → Messages → Outgoing mail configuration.

4. **View the raw source** of the received mail. If you do not see `multipart/signed`, the mail never went through the signing module.

---

## Client reports "invalid signature"

**Common causes:**

| Symptom | Cause |
|---|---|
| Reports the wrong sender | From does not match the SAN in the cert |
| Reports untrusted | Chain missing an intermediate |
| Reports cert expired | Needs renewal |
| Signature broken | A device in the middle modified the mail content (antivirus gateway, mailing list, footer injection) |

The last case deserves attention: if the mail server or a gateway appends a footer/disclaimer **after** signing, the signature breaks. You must disable that feature, or insert the footer before signing.

---

## Full diagnostics

```bash
../../scripts/smime-check.sh /path/to/smime-directory
```

Or manually, running it exactly the way the module does:

```bash
sudo -u www-data php -r '
$d = "/etc/smime/";
var_dump(openssl_pkey_get_private("file://".$d."smime-hb.key", "PASSPHRASE") !== false);
var_dump((bool)openssl_x509_read(file_get_contents($d."smime.crt")));
while ($e = openssl_error_string()) echo $e, PHP_EOL;'
```

`bool(true)` on both lines means the file layer is clean — any remaining error is in the module configuration or SMTP.
