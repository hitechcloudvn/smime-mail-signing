# HostBill — S/MIME Signing Setup

[Tiếng Việt](../vi/huong-dan-hostbill.md) | **English**

Read [Certificate preparation](certificate-preparation.md) (sections A–E) first to get the 3 files: `smime.crt`, `ca-chain.pem`, `smime-hb.key`.

---

## 1. HostBill ships with a plugin

Unlike WHMCS, HostBill has a free **S/MIME Mail Signature** plugin for every customer. No code to write — just enable it and fill in the paths.

A note on the platform: HostBill is written in PHP, but most of its source is ionCube-encoded, so the core code cannot be modified. The plugin uses PHP's `openssl_pkcs7_sign()`, which means it depends on the system's OpenSSL — that is why legacy-algorithm issues and file permissions directly affect signing.

---

## 2. The plugin uses PEM files, NOT .pfx

The most common point of confusion. The plugin takes **3 separate files**:

- Certificate (`.crt`)
- Private key (`.key`)
- CA chain (`.pem`)

If you have a `.pfx` file, split it first:

```bash
openssl pkcs12 -in cu.pfx -clcerts -nokeys -out smime.crt
openssl pkcs12 -in cu.pfx -cacerts -nokeys -out ca-chain.pem
openssl pkcs12 -in cu.pfx -nocerts -out smime-hb.key
```

If these commands report `bad decrypt` while the password is correct, add the `-legacy` flag (file exported from an older Windows).

---

## 3. Enable the plugin

**Settings → Modules → Plugins** → tab **Inactive** → find `Smime_mail_sign` → **Activate**.

After enabling it, HostBill returns you to Settings → Modules → Plugins to configure:

- Module display name: `S/MIME Sign`
- Choose whether all staff use it, or only designated staff

Save.

---

## 4. Register the certificate

Open the module you just configured and add a new entry:

| Field | Sample value |
|---|---|
| Mail from | `hotro@congty.vn` |
| Certificate path | `/etc/smime/smime.crt` |
| Certificate key path | `/etc/smime/smime-hb.key` |
| Certificate CA path | `/etc/smime/ca-chain.pem` |
| Password | `PassphraseMoi2026` |

Paths must be **absolute**. Copy and paste, do not type them by hand.

The module has a **validate** button to check the certificate before saving — always press it first.

### About the Password field

This is the passphrase of the **private key**, not the password of a `.pfx` file. A wrong entry produces:

```
Signing Error: error:1C800064:Provider routines::bad decrypt
```

If the key is not encrypted, leave it empty. Conversely, if the path points at an unencrypted key and you still get `bad decrypt`, the plugin is definitely reading a different file — re-check the path.

### About the Mail from field

It must match the SAN in the certificate **character by character**:

```bash
openssl x509 -in smime.crt -noout -ext subjectAltName
```

You can register multiple entries for multiple addresses, but each address needs its own cert with a matching SAN.

---

## 5. Cross-check the sending address

**Settings → General Settings → Messages → Outgoing mail configuration**

The From address here must match the Mail from field in the plugin. If they differ, either the mail is not signed, or the client reports that the signature does not match the sender.

---

## 6. Check file permissions

This is the second most common cause after the passphrase.

```bash
# User running PHP
ps -o user= -C php-fpm | sort -u | grep -v root

# Real read test
sudo -u www-data test -r /etc/smime/smime-hb.key && echo OK || echo CHAN

# See which level blocks access
namei -l /etc/smime/smime-hb.key
```

If access is blocked, see section E2 in [Certificate preparation](certificate-preparation.md). Remember to fix permissions on **the directory too**, not just the files.

Check `open_basedir` — panels usually restrict each site to reading only inside its own directory:

```bash
grep -r open_basedir /etc/php*/
```

---

## 7. Verify the result

Press **validate** in the module, then send a test mail (create a draft invoice and send it, or send a manual mail to a test client).

Open the mail in Gmail → three dots → **Show original**. You should see:

```
Content-Type: multipart/signed; protocol="application/pkcs7-signature"
```

along with the `smime.p7s` attachment.

If it still fails, check the logs:

```bash
ls -lt /path/to/hostbill/includes/logs/ | head
tail -50 /var/log/php*fpm*.log
```

---

## 8. Quick diagnostics with PHP

Run it exactly the way the plugin calls OpenSSL, to separate certificate problems from HostBill problems:

```bash
sudo -u www-data php -r '
$d = "/etc/smime/";
var_dump(openssl_pkey_get_private("file://".$d."smime-hb.key", "PassphraseMoi2026") !== false);
var_dump((bool)openssl_x509_read(file_get_contents($d."smime.crt")));
while ($e = openssl_error_string()) echo $e, PHP_EOL;'
```

Both lines must print `bool(true)`. If it already fails here, the problem is outside HostBill — go back to section E of [Certificate preparation](certificate-preparation.md).

Or use the bundled script:

```bash
../../scripts/smime-check.sh /etc/smime www-data
```

---

## 9. Renewal

When reissuing with the old CSR, you only replace the two files `smime.crt` and `ca-chain.pem`, keeping the key and the plugin configuration. Nothing needs to be re-entered in HostBill.

Check expiry periodically:

```bash
openssl x509 -in /etc/smime/smime.crt -noout -dates
```

HostBill has no automatic expiry warnings. Set up a reminder cron:

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
