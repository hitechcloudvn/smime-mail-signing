# WHMCS — Install the S/MIME Signing Module

[Tiếng Việt](../vi/huong-dan-whmcs.md) | **English**

Read [Certificate preparation](certificate-preparation.md) (sections A–E) first to get the 3 files: `smime.crt`, `ca-chain.pem`, `smime-hb.key`.

---

## 1. Why a module is needed

WHMCS has no built-in S/MIME signing. Many people try the `EmailPreSend` hook, but it **does not work** — that hook runs before the message is assembled into MIME and only lets you edit merge fields or cancel the send, while S/MIME signing must operate on the complete MIME body.

WHMCS 8.0 introduced the **Mail Provider** system (`SenderModuleInterface`), which lets a module take over the sending step. That is the right place to insert the signature.

This package contains two modules:

| Module | Role | Required? |
|---|---|---|
| `smimesmtp` (Mail Provider) | Send mail via SMTP + S/MIME signing | Yes |
| `smimemonitor` (Addon) | Cert expiry monitoring, signing log, diagnostic tools | No, but recommended |

---

## 2. Requirements

- WHMCS 8.0 or later
- PHP 7.4+ with the `openssl` extension
- An SMTP mail server (the module does not support PHP `mail()`)

Check:

```bash
php -r 'echo extension_loaded("openssl") ? "openssl OK" : "THIEU openssl", PHP_EOL;'
```

---

## 3. Install the Mail Provider

### 3.1. Upload the files

```bash
cd /path/to/whmcs
mkdir -p modules/mail/smimesmtp
# upload smimesmtp.php here
chown -R www-data:www-data modules/mail/smimesmtp
chmod 644 modules/mail/smimesmtp/smimesmtp.php
```

Correct structure:

```
whmcs/modules/mail/smimesmtp/smimesmtp.php
```

The directory name, file name and class name must match (`smimesmtp` / `Smimesmtp`).

### 3.2. Activate

WHMCS Admin → **Configuration → System Settings → Mail** → the **Mail Provider** section → select **SMTP + S/MIME Sign**.

### 3.3. Fill in the configuration

| Field | Sample value | Notes |
|---|---|---|
| SMTP Host | `mail.congty.vn` | |
| SMTP Port | `587` | 587 for STARTTLS, 465 for SMTPS |
| Encryption | `tls` | |
| SMTP Username | `hotro@congty.vn` | |
| SMTP Password | | |
| Certificate path | `/etc/smime/smime.crt` | absolute |
| Private Key path | `/etc/smime/smime-hb.key` | absolute |
| Key Passphrase | `PassphraseMoi2026` | leave empty if the key is not encrypted |
| CA Chain path | `/etc/smime/ca-chain.pem` | |
| **Only sign when From is** | `hotro@congty.vn` | important, see 3.4 |
| **Sender name** | `Cong Ty ABC` | overrides the name WHMCS sends with |
| Override sending address | *(leave empty)* | see 3.5 |
| Test mail recipient | `admin@congty.vn` | a real mailbox |

Click **Save Changes**, then click the test button.

### 3.4. About the "Only sign when From is" field

The certificate contains only **one** address in its SAN. But WHMCS sends mail from several different addresses depending on the support department (`sales@`, `billing@`, `support@`).

Signing with a cert that does not match the From address makes the client show an invalid-signature warning — worse than not signing at all.

Put the address that matches the SAN in this field. Mail from other addresses still sends normally, just unsigned. Check the SAN:

```bash
openssl x509 -in smime.crt -noout -ext subjectAltName
```

Leaving this field empty = sign every mail; only use that when the whole system sends from a single address.

### 3.5. About the "Override sending address" field

Only fill this in when the mail server requires sending from one fixed address (many SMTP providers reject a `MAIL FROM` different from the login account and return error 553).

When you fill it in, the module automatically puts WHMCS's original address into **Reply-To**, so when customers hit Reply it still reaches the right department.

---

## 4. Install the monitoring Addon

### 4.1. Upload

```bash
cd /path/to/whmcs
mkdir -p modules/addons/smimemonitor
# upload smimemonitor.php and hooks.php here
chown -R www-data:www-data modules/addons/smimemonitor
chmod 644 modules/addons/smimemonitor/*.php
```

### 4.2. Activate

Admin → **System Settings → Addon Modules** → find **S/MIME Monitor** → **Activate**.

Click **Configure**, grant access to the admin groups, then fill in:

| Field | Value |
|---|---|
| Certificate path | `/etc/smime/smime.crt` |
| Private Key path | `/etc/smime/smime-hb.key` |
| CA Chain path | `/etc/smime/ca-chain.pem` |
| Warn in advance (days) | `30` |
| Alert recipient email | `admin@congty.vn` |
| Keep logs (days) | `90` |

The paths must be **identical** to those in the Mail Provider.

On activation, the addon creates the `mod_smime_log` table. The Mail Provider detects this table automatically and starts logging — no extra configuration needed.

### 4.3. The addon's three tabs

**Overview** — cert info (organization, SAN, expiry date, days left, CA chain), a warning if the CA chain contains the leaf cert, and 7-day statistics.

**Log** — a log table filterable by signed / unsigned / failed. Errors are shown right under the corresponding row.

**Tools** — a test-sign button (returns the specific OpenSSL error on failure), old-log cleanup, and an environment info table: OpenSSL version, PHP user, `open_basedir`.

### 4.4. Automatic alerts

`hooks.php` plugs into `DailyCronJob` and sends alert emails at **30, 14, 7, 3, 1 days** before expiry and once expired. It sends only at these exact thresholds to avoid spam.

It also adds a widget to the admin homepage showing cert status and the number of mails signed in the last 7 days.

Requires the WHMCS cron to be running normally.

---

## 5. Verify

### 5.1. Test mail

Click the test button in the Mail Provider configuration. On failure, the module returns a message with concrete guidance instead of the raw SMTP error.

### 5.2. Inspect the received mail

Gmail → open the mail → three dots → **Show original**. You should see:

```
Content-Type: multipart/signed; protocol="application/pkcs7-signature"; micalg=sha-256
```

along with the `smime.p7s` attachment. Gmail shows an authentication icon when the CA chain is valid.

Outlook shows a red ribbon icon in the top-right corner.

### 5.3. Test-sign in the addon

Tab **Tools** → enter the passphrase → **Test sign**. This is the fastest way to tell a certificate problem from an SMTP problem.

---

## 6. Upgrading WHMCS

The modules live under `modules/`, which WHMCS does not overwrite during updates. Still, back up before every major upgrade:

```bash
tar czf /root/smime-modules-$(date +%Y%m%d).tar.gz \
  modules/mail/smimesmtp modules/addons/smimemonitor
```

After the upgrade, confirm the Mail Provider is still selected correctly and run the test again.

---

## 7. Alternative: sign at the MTA layer

If you run your own mail server, consider signing with a milter (`smime-milter`, or a script hooked into Postfix).

**Pros:** signs every mail leaving the server regardless of which application sent it, independent of WHMCS, no code maintenance across updates.

**Cons:** more complex setup, requires mail server administration rights, and no monitoring UI like this addon.

For a system that only needs to sign mail from WHMCS, the modules in this package are far simpler.
