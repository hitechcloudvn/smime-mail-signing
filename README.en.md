<div align="center">

# S/MIME Mail Signing

**S/MIME digital signing for outgoing mail from WHMCS and HostBill**

[Tiếng Việt](README.md) | **English**

[![License](https://img.shields.io/badge/License-Source--Available%20Non--Commercial-red?style=flat-square)](LICENSE)
[![WHMCS](https://img.shields.io/badge/WHMCS-8.0%2B-0073AA?style=flat-square)](docs/en/whmcs-guide.md)
[![HostBill](https://img.shields.io/badge/HostBill-Supported-00A4E4?style=flat-square)](docs/en/hostbill-guide.md)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?style=flat-square&logo=php&logoColor=white)](https://www.php.net)
[![Made in Vietnam](https://img.shields.io/badge/Made%20in-Vietnam-DA251D?style=flat-square)](https://hitechcloud.vn)

[Documentation](#documentation) · [Quick install](#quick-install) · [License](#license) · [Support](#support)

</div>

---

## Introduction

A set of tools and modules that S/MIME-signs every outgoing mail from your customer management system, so recipients can verify that the mail really comes from your organization and that its content was not altered in transit.

The package ships complete deployment documentation (from CSR creation onward), scripts that automate certificate handling, a module for WHMCS, and a setup guide for HostBill's built-in plugin.

## Features

| | |
|---|---|
| **Automatic S/MIME signing** | Every outgoing mail is signed; recipients see the authentication icon |
| **Filter by sender address** | Only mail from the address matching the certificate SAN is signed, avoiding false warnings |
| **Sender customization** | Override the display name and address, while keeping Reply-To correct |
| **Certificate monitoring** | Email alerts 30/14/7/3/1 days before expiry, plus an admin dashboard widget |
| **Signing log** | Records every mail: signed or not, sent successfully or failed |
| **Built-in diagnostics** | Test-signing tool, file-permission check, OpenSSL environment info |
| **Automation scripts** | Turn the CA-returned files into a usable set with a single command |

## Platform support

| Platform | How to deploy | Documentation |
|---|---|---|
| **WHMCS 8.0+** | Mail Provider module + monitoring add-on (included in this repo) | [WHMCS guide](docs/en/whmcs-guide.md) |
| **HostBill** | Built-in `Smime_mail_sign` plugin — configuration only | [HostBill guide](docs/en/hostbill-guide.md) |

WHMCS has no built-in S/MIME signing and the `EmailPreSend` hook cannot do it — the hook runs before the message is assembled into MIME. That is why this repo provides a Mail Provider module implementing WHMCS 8's `SenderModuleInterface`.

## Repository layout

```
.
├── docs/
│   ├── vi/                          Vietnamese documentation
│   │   ├── chuan-bi-chung-chi.md    From CSR to usable files (read first)
│   │   ├── huong-dan-whmcs.md       Install the WHMCS module
│   │   ├── huong-dan-hostbill.md    Configure the HostBill plugin
│   │   └── xu-ly-loi.md             Troubleshooting table
│   └── en/                          English documentation
│       ├── certificate-preparation.md
│       ├── whmcs-guide.md
│       ├── hostbill-guide.md
│       └── troubleshooting.md
├── scripts/
│   ├── smime-setup.sh               Turn CA-returned files into 3 usable files
│   └── smime-check.sh               Diagnose the configuration
└── src/
    ├── whmcs/modules/
    │   ├── mail/smimesmtp/          Mail Provider: SMTP + S/MIME signing
    │   └── addons/smimemonitor/     Certificate and signing-log monitoring add-on
    └── hostbill/                    Docs only — HostBill ships its own plugin
```

## Requirements

- PHP 7.4 or later, with the `openssl` extension
- OpenSSL 1.1.1 or 3.x on the system
- A valid S/MIME certificate (Organization Validation recommended)
- An SMTP mail server
- WHMCS 8.0+ (if you use the WHMCS module)

## Quick install

### 1. Prepare the certificate

```bash
git clone https://github.com/hitechcloudvn/smime-mail-signing.git
cd smime-mail-signing
chmod +x scripts/*.sh

./scripts/smime-setup.sh -p 'cert.p7b' -k private.key -w 'PassphraseMoi2026'
```

The script automatically classifies the certificates, matches the key with the certificate, creates a passphrase-protected key, and performs a test signature. The result is three files: `smime.crt`, `ca-chain.pem`, `smime-hb.key`.

Step-by-step details, including the manual procedure: [Certificate preparation](docs/en/certificate-preparation.md)

### 2. Set permissions

```bash
PHPUSER=$(ps -o user= -C php-fpm | sort -u | grep -v root | head -1)
chown root:"$PHPUSER" /etc/smime /etc/smime/*
chmod 750 /etc/smime && chmod 640 /etc/smime/*
```

> [!WARNING]
> If the directory is inside the web root, block HTTP access immediately. A private key downloadable from the internet is a serious security incident. See section E3 of the certificate preparation guide.

### 3. Install the modules

**WHMCS:**

```bash
cp -r src/whmcs/modules/mail/smimesmtp      /path/to/whmcs/modules/mail/
cp -r src/whmcs/modules/addons/smimemonitor /path/to/whmcs/modules/addons/
```

Then in Admin → System Settings → Mail, choose **SMTP + S/MIME Sign**.

**HostBill:** Settings → Modules → Plugins → enable `Smime_mail_sign`.

### 4. Verify

```bash
./scripts/smime-check.sh /etc/smime
```

Send a test mail, open it in Gmail → Show original; you should see `Content-Type: multipart/signed`.

## Documentation

| Document | Contents |
|---|---|
| [Certificate preparation](docs/en/certificate-preparation.md) | CSR creation, organization validation, processing CA-returned files, permissions, hardening |
| [WHMCS guide](docs/en/whmcs-guide.md) | Installing the Mail Provider and add-on, every configuration field explained |
| [HostBill guide](docs/en/hostbill-guide.md) | Enabling and configuring the built-in plugin, expiry-alert cron |
| [Troubleshooting](docs/en/troubleshooting.md) | Errors by message, with how to verify the root cause |

The Vietnamese edition lives in [docs/vi/](README.md).

## Releases

Every release and tag is packaged and published by **GitHub Actions** — the repo requires no local build. See [CHANGELOG.md](CHANGELOG.md) for what each version contains.

## License

This software is released under the **HiTechCloud Source-Available License (Non-Commercial)**. It is **not** an open-source license.

**Permitted:**
- Use, install, and run for your organization's internal purposes
- Modify and create internal customizations
- Study, learn, and research

**Prohibited:**
- Selling or reselling the software, in original or modified form
- Commercial use: embedding in a paid product/service, or offering it as a service to third parties
- Redistributing to the public, listing on marketplaces or module directories
- Removing copyright notices or brand identity

A business running this software on its own infrastructure to sign its own mail is **not** considered commercial use, even if the business is for-profit.

Need a commercial license? Contact [info@photuesoftware.com](mailto:info@photuesoftware.com).

Full text: [LICENSE](LICENSE)

## Security

This software handles private keys and certificates. Read [SECURITY.md](SECURITY.md) before deploying to production.

Found a vulnerability? Report it privately to [info@photuesoftware.com](mailto:info@photuesoftware.com) — do not open a public issue.

## Support

| Channel | Address |
|---|---|
| Customer portal | [my.hitechcloud.vn](https://my.hitechcloud.vn) |
| Websites | [hitechcloud.vn](https://hitechcloud.vn) · [photuesoftware.com](https://photuesoftware.com) |
| Email | [info@photuesoftware.com](mailto:info@photuesoftware.com) |
| Zalo OA | [oa.zalo.me/2386540850578657637](https://oa.zalo.me/2386540850578657637) |
| Bug reports | [GitHub Issues](https://github.com/hitechcloudvn/smime-mail-signing/issues) |

---

<div align="center">

**HiTechCloud** — the cloud infrastructure brand of **Pho Tue Software Solutions Joint Stock Company**
(Công ty Cổ phần Giải pháp Phần mềm và Công nghệ Phổ Tuệ)

Business Registration No. 0318222903 · Ho Chi Minh City, Vietnam

Copyright © 2026 Pho Tue Software Solutions JSC. All rights reserved.

</div>
