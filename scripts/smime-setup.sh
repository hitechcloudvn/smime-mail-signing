#!/bin/bash
#
# HiTechCloud S/MIME Mail Signing
#
# Copyright (c) 2026 Pho Tue Software Solutions Joint Stock Company
# License: HiTechCloud Source-Available License (Non-Commercial) — xem LICENSE / see LICENSE
# https://github.com/hitechcloudvn/smime-mail-signing
#
# CAM su dung thuong mai, ban, ban lai hoac phan phoi lai phan mem nay.
# Commercial use, resale, and redistribution are PROHIBITED.
#
#
# smime-setup.sh — Xử lý bộ file Sectigo gửi thành 3 file dùng được / Process the Sectigo bundle into 3 usable files
#
# Đầu vào : file .p7b (hoặc chain.pem) + private key / Input: the .p7b file (or chain.pem) + private key
# Đầu ra  : smime.crt, ca-chain.pem, smime-hb.key / Output: smime.crt, ca-chain.pem, smime-hb.key
#
# Cách dùng: / Usage:
#   ./smime-setup.sh -p cert.p7b -k private.key -w 'PassphraseMoi'
#

set -euo pipefail

P7B=""
KEY=""
NEWPW=""
OUTDIR="$(pwd)"

usage() {
    cat <<'EOF'
Cách dùng: smime-setup.sh -p <file.p7b> -k <private.key> -w <passphrase-moi> [-o <thu-muc-ra>] / Usage: smime-setup.sh -p <file.p7b> -k <private.key> -w <new-passphrase> [-o <output-dir>]

  -p   File .p7b hoặc chain.pem do CA gửi / .p7b or chain.pem file sent by the CA
  -k   Private key (file bạn tạo lúc làm CSR) / Private key (the file you created when making the CSR)
  -w   Passphrase mới cho key dùng với WHMCS/HostBill (chỉ chữ và số) / New passphrase for the key used with WHMCS/HostBill (letters and digits only)
  -o   Thư mục xuất kết quả (mặc định: thư mục hiện tại) / Output directory (default: the current directory)

Ví dụ: / Example:
  ./smime-setup.sh -p '3226567586_SMIME_OVM.p7b' -k private.key -w 'BNIX2026'
EOF
    exit 1
}

while getopts "p:k:w:o:h" opt; do
    case $opt in
        p) P7B="$OPTARG" ;;
        k) KEY="$OPTARG" ;;
        w) NEWPW="$OPTARG" ;;
        o) OUTDIR="$OPTARG" ;;
        *) usage ;;
    esac
done

[ -z "$P7B" ] || [ -z "$KEY" ] || [ -z "$NEWPW" ] && usage
[ -r "$P7B" ] || { echo "LỖI: không đọc được $P7B / ERROR: cannot read $P7B"; exit 1; }
[ -r "$KEY" ] || { echo "LỖI: không đọc được $KEY / ERROR: cannot read $KEY"; exit 1; }

mkdir -p "$OUTDIR"
WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT

echo "==> [1/6] Chuyển sang định dạng PEM / Convert to PEM format"
if head -1 "$P7B" | grep -q 'BEGIN CERTIFICATE'; then
    cp "$P7B" "$WORK/chain.pem"
else
    openssl pkcs7 -print_certs -in "$P7B" -out "$WORK/chain.pem"
fi
echo "    Tìm thấy $(grep -c 'BEGIN CERTIFICATE' "$WORK/chain.pem") certificate / Found $(grep -c 'BEGIN CERTIFICATE' "$WORK/chain.pem") certificate(s)"

echo "==> [2/6] Tách và phân loại certificate / Split and classify certificates"
cd "$WORK"
csplit -sz -f c- -b '%02d.pem' chain.pem '/BEGIN CERTIFICATE/' '{*}'

LEAF=""
declare -a CAS=()

for f in c-*.pem; do
    SUBJ=$(openssl x509 -in "$f" -noout -subject 2>/dev/null || true)
    [ -z "$SUBJ" ] && continue
    ISSUER=$(openssl x509 -in "$f" -noout -issuer 2>/dev/null)
    S=$(echo "$SUBJ" | sed 's/^subject=//')
    I=$(echo "$ISSUER" | sed 's/^issuer=//')

    # Cert leaf: không phải CA (không có CA:TRUE trong basicConstraints) / Leaf cert: not a CA (no CA:TRUE in basicConstraints)
    if openssl x509 -in "$f" -noout -text 2>/dev/null | grep -q 'CA:TRUE'; then
        # Root tự ký thì bỏ, chỉ giữ intermediate / Skip self-signed roots, keep intermediates only
        if [ "$S" = "$I" ]; then
            echo "    [bỏ ] Root tự ký: $(echo "$S" | grep -o 'CN = [^,]*' || echo "$S") / [skip ] Self-signed root: $(echo "$S" | grep -o 'CN = [^,]*' || echo "$S")"
        else
            CAS+=("$f")
            echo "    [CA ] $(echo "$S" | grep -o 'CN = [^,]*' || echo "$S")"
        fi
    else
        LEAF="$f"
        echo "    [LEAF] $S"
    fi
done

[ -z "$LEAF" ] && { echo "LỖI: không tìm thấy certificate của bạn trong file / ERROR: your certificate was not found in the file"; exit 1; }
[ ${#CAS[@]} -eq 0 ] && { echo "LỖI: không tìm thấy certificate CA / ERROR: no CA certificate found"; exit 1; }

cp "$LEAF" "$OUTDIR/smime.crt"
cat "${CAS[@]}" > "$OUTDIR/ca-chain.pem"

echo "==> [3/6] Đối chiếu key với certificate / Match the key against the certificate"
MD5_CERT=$(openssl x509 -in "$OUTDIR/smime.crt" -noout -modulus | openssl md5 | awk '{print $NF}')
MD5_KEY=$(openssl rsa -in "$OLDPWD/$KEY" -noout -modulus 2>/dev/null | openssl md5 | awk '{print $NF}' || true)

if [ -z "$MD5_KEY" ] || [ "$MD5_KEY" = "d41d8cd98f00b204e9800998ecf8427e" ]; then
    echo "    Key có passphrase — nhập để đối chiếu: / Key has a passphrase — enter it to compare:"
    MD5_KEY=$(openssl rsa -in "$OLDPWD/$KEY" -noout -modulus | openssl md5 | awk '{print $NF}')
fi

if [ "$MD5_CERT" != "$MD5_KEY" ]; then
    echo "LỖI: KEY KHÔNG KHỚP CERTIFICATE / ERROR: KEY DOES NOT MATCH CERTIFICATE"
    echo "     cert: $MD5_CERT"
    echo "     key : $MD5_KEY"
    exit 1
fi
echo "    Khớp: $MD5_CERT / Match: $MD5_CERT"

echo "==> [4/6] Tạo key có passphrase mới / Create key with new passphrase"
export SMIME_NEWPW="$NEWPW"
openssl pkey -in "$OLDPWD/$KEY" -aes-256-cbc -passout env:SMIME_NEWPW -out "$OUTDIR/smime-hb.key"
openssl pkey -in "$OUTDIR/smime-hb.key" -passin env:SMIME_NEWPW -noout
echo "    OK"

echo "==> [5/6] Test ký thử / Test signing"
echo "test" > "$WORK/t.txt"
openssl smime -sign -in "$WORK/t.txt" -text \
    -signer "$OUTDIR/smime.crt" \
    -inkey "$OUTDIR/smime-hb.key" -passin env:SMIME_NEWPW \
    -certfile "$OUTDIR/ca-chain.pem" -out "$WORK/t.p7m"

# Verify cần cả root, ghép tạm để kiểm tra / Verify needs the root too; concat temporarily to check
cat "$OUTDIR/ca-chain.pem" c-*.pem > "$WORK/verify.pem" 2>/dev/null || true
if openssl smime -verify -in "$WORK/t.p7m" -CAfile "$WORK/verify.pem" -out /dev/null 2>/dev/null; then
    echo "    Verification successful"
else
    echo "    Ký OK (verify cần root CA của hệ thống, không phải lỗi) / Signing OK (verify needs the system root CA — not an error)"
fi
unset SMIME_NEWPW

echo "==> [6/6] Thông tin certificate / Certificate info"
openssl x509 -in "$OUTDIR/smime.crt" -noout -subject -dates -ext subjectAltName | sed 's/^/    /'

cat <<EOF

========================================
HOÀN TẤT. File đã tạo tại: $OUTDIR / DONE. Files created in: $OUTDIR

  smime.crt      Certificate
  ca-chain.pem   Chuỗi CA / CA chain
  smime-hb.key   Private key (passphrase vừa đặt) / Private key (the passphrase just set)

BƯỚC TIẾP THEO — phân quyền: / NEXT STEP — set permissions:

  PHPUSER=\$(ps -o user= -C php-fpm | sort -u | grep -v root | head -1)
  chown root:"\$PHPUSER" $OUTDIR/{smime.crt,ca-chain.pem,smime-hb.key} $OUTDIR
  chmod 640 $OUTDIR/{smime.crt,ca-chain.pem,smime-hb.key}
  chmod 750 $OUTDIR

Nếu thư mục nằm trong web root, chặn HTTP ngay: / If the directory is inside the web root, block HTTP now:
  printf 'Require all denied\\n' > $OUTDIR/.htaccess
  curl -sI https://ten-mien/duong-dan/smime-hb.key | head -1

Xoá passphrase khỏi lịch sử shell: / Remove the passphrase from shell history:
  history -d \$((HISTCMD-1))
========================================
EOF
