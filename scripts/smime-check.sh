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
# smime-check.sh — Chẩn đoán cấu hình S/MIME / Diagnose the S/MIME configuration
#
# Cách dùng: / Usage:
#   ./smime-check.sh /duong/dan/thu-muc-smime [user-php]
#
# Kiểm tra: cert, key, chain, quyền file, khả năng đọc của user PHP, / Checks: cert, key, chain, file permissions, PHP-user readability,
#           môi trường OpenSSL, và rò rỉ qua HTTP. / OpenSSL environment, and HTTP leakage.

DIR="${1:-.}"
PHPUSER="${2:-}"

RED=$'\033[31m'; GRN=$'\033[32m'; YEL=$'\033[33m'; RST=$'\033[0m'
ok()   { echo "  ${GRN}[OK]${RST}   $1"; }
warn() { echo "  ${YEL}[CẢNH BÁO / WARNING]${RST} $1"; }
err()  { echo "  ${RED}[LỖI / ERROR]${RST} $1"; }

cd "$DIR" 2>/dev/null || { err "Không vào được thư mục $DIR / Cannot enter directory $DIR"; exit 1; }
echo "=== Kiểm tra: $(pwd) === / Check: $(pwd)"

echo
echo "--- 1. Môi trường --- / Environment"
echo "  OpenSSL : $(openssl version)"
if command -v php >/dev/null; then
    echo "  PHP     : $(php -r 'echo PHP_VERSION;')"
    echo "  PHP SSL : $(php -r 'echo OPENSSL_VERSION_TEXT;')"
    OB=$(php -r 'echo ini_get("open_basedir") ?: "khong bat / not enabled";')
    echo "  open_basedir: $OB"
fi
[ -z "$PHPUSER" ] && PHPUSER=$(ps -o user= -C php-fpm 2>/dev/null | sort -u | grep -v root | head -1)
echo "  User PHP: ${PHPUSER:-khong xac dinh / undetermined}"

echo
echo "--- 2. Certificate ---"
if [ -r smime.crt ]; then
    SUBJ=$(openssl x509 -in smime.crt -noout -subject 2>/dev/null)
    if [ -n "$SUBJ" ]; then
        ok "$(echo "$SUBJ" | sed 's/^subject=//' | cut -c1-70)"
        SAN=$(openssl x509 -in smime.crt -noout -ext subjectAltName 2>/dev/null | grep -o 'email:[^,]*')
        echo "         SAN: ${SAN:-khong co / none}"
        EXP=$(openssl x509 -in smime.crt -noout -enddate | cut -d= -f2)
        EXP_TS=$(date -d "$EXP" +%s 2>/dev/null || echo 0)
        NOW=$(date +%s)
        DAYS=$(( (EXP_TS - NOW) / 86400 ))
        if [ "$DAYS" -lt 0 ]; then
            err "ĐÃ HẾT HẠN $(( -DAYS )) ngày trước / EXPIRED $(( -DAYS )) days ago"
        elif [ "$DAYS" -lt 30 ]; then
            warn "Còn $DAYS ngày — cần gia hạn / $DAYS days left — renewal needed"
        else
            ok "Còn $DAYS ngày / $DAYS days left"
        fi
    else
        err "smime.crt không phải certificate hợp lệ / smime.crt is not a valid certificate"
    fi
else
    err "Không đọc được smime.crt / Cannot read smime.crt"
fi

echo
echo "--- 3. Chuỗi CA --- / CA chain"
if [ -r ca-chain.pem ]; then
    N=$(grep -c 'BEGIN CERTIFICATE' ca-chain.pem)
    LEAF_O=$(openssl x509 -in smime.crt -noout -subject 2>/dev/null | grep -o 'O = [^,]*' | head -1)
    if grep -q "CA:TRUE" <(openssl crl2pkcs7 -nocrl -certfile ca-chain.pem 2>/dev/null | openssl pkcs7 -print_certs -text -noout 2>/dev/null); then
        :
    fi
    HAS_LEAF=0
    openssl crl2pkcs7 -nocrl -certfile ca-chain.pem 2>/dev/null \
        | openssl pkcs7 -print_certs -noout 2>/dev/null \
        | grep '^subject' | while read -r line; do
            echo "         $(echo "$line" | sed 's/^subject=//' | cut -c1-65)"
        done
    if [ -n "$LEAF_O" ] && openssl crl2pkcs7 -nocrl -certfile ca-chain.pem 2>/dev/null \
        | openssl pkcs7 -print_certs -noout 2>/dev/null | grep -q "$LEAF_O"; then
        warn "Chain đang chứa cả cert của bạn — nên bỏ cert leaf ra / The chain also contains your own cert — remove the leaf cert"
    else
        ok "$N certificate CA, không lẫn cert leaf / $N CA certificates, no leaf cert mixed in"
    fi
else
    err "Không đọc được ca-chain.pem / Cannot read ca-chain.pem"
fi

echo
echo "--- 4. Private key ---"
for K in smime-hb.key smime.key private.key; do
    [ -r "$K" ] || continue
    if head -1 "$K" | grep -q ENCRYPTED; then
        echo "  $K: có passphrase / has a passphrase"
    else
        warn "$K: KHÔNG có passphrase / has NO passphrase"
    fi
    MD5C=$(openssl x509 -in smime.crt -noout -modulus 2>/dev/null | openssl md5 | awk '{print $NF}')
    echo "         (đối chiếu thủ công: openssl rsa -in $K -noout -modulus | openssl md5) / (manual check: openssl rsa -in $K -noout -modulus | openssl md5)"
    echo "         MD5 của cert: $MD5C / cert MD5: $MD5C"
    break
done

echo
echo "--- 5. Quyền truy cập --- / Access permissions"
ls -l smime.crt ca-chain.pem *.key 2>/dev/null | sed 's/^/  /'
echo "  Thư mục: $(ls -ld . | awk '{print $1, $3, $4}') / Directory: $(ls -ld . | awk '{print $1, $3, $4}')"

if [ -n "$PHPUSER" ] && id "$PHPUSER" >/dev/null 2>&1; then
    for F in smime.crt ca-chain.pem smime-hb.key; do
        [ -e "$F" ] || continue
        if sudo -u "$PHPUSER" test -r "$(pwd)/$F" 2>/dev/null; then
            ok "$PHPUSER đọc được $F / $PHPUSER can read $F"
        else
            err "$PHPUSER KHÔNG đọc được $F — kiểm tra quyền thư mục cha (cần bit x) / $PHPUSER CANNOT read $F — check parent-directory permissions (the x bit is needed)"
        fi
    done
fi

echo
echo "--- 6. Rò rỉ qua HTTP --- / HTTP leakage"
CUR=$(pwd)
case "$CUR" in
    */public_html/*|*/wwwroot/*|*/htdocs/*|*/www/*)
        warn "Thư mục nằm trong web root! / The directory is inside the web root!"
        if [ -f .htaccess ]; then
            ok "Có .htaccess / .htaccess present"
        else
            err "KHÔNG có .htaccess — private key có thể tải về từ internet / NO .htaccess — the private key may be downloadable from the internet"
            echo "         Sửa: printf 'Require all denied\\n' > .htaccess / Fix: printf 'Require all denied\\n' > .htaccess"
            echo "         Nginx: location ^~ /smime/ { deny all; return 404; }"
        fi
        echo "         Kiểm tra thật: curl -sI https://ten-mien/smime/smime-hb.key | head -1 / Real check: curl -sI https://ten-mien/smime/smime-hb.key | head -1"
        ;;
    *)
        ok "Ngoài web root / Outside the web root"
        ;;
esac

echo
echo "=== Kết thúc === / Done"
