#!/bin/sh
set -eu

: "${DB_HOST:?DB_HOST obrigatorio}"
: "${DB_DATABASE:?DB_DATABASE obrigatorio}"
: "${DB_USERNAME:?DB_USERNAME obrigatorio}"
: "${DB_PASSWORD:?DB_PASSWORD obrigatoria}"
: "${BACKUP_ENCRYPTION_KEY:?BACKUP_ENCRYPTION_KEY obrigatoria}"

if [ "${#BACKUP_ENCRYPTION_KEY}" -lt 32 ]; then
    echo "BACKUP_ENCRYPTION_KEY deve possuir ao menos 32 caracteres." >&2
    exit 1
fi

DB_PORT="${DB_PORT:-3306}"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-30}"
INTERVAL_SECONDS="${BACKUP_INTERVAL_SECONDS:-86400}"

case "$RETENTION_DAYS:$INTERVAL_SECONDS" in
    *[!0-9:]*|:*|*:)
        echo "Retencao e intervalo devem ser numeros inteiros positivos." >&2
        exit 1
        ;;
esac

if [ "$RETENTION_DAYS" -le 0 ] || [ "$INTERVAL_SECONDS" -le 0 ]; then
    echo "Retencao e intervalo devem ser maiores que zero." >&2
    exit 1
fi

mkdir -p /backups

create_backup() {
    timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
    temporary_dir="$(mktemp -d)"
    archive="/backups/sistema-frequencia-${timestamp}.tar.gz.enc"

    cleanup() {
        rm -rf -- "$temporary_dir"
    }
    trap cleanup EXIT INT TERM

    export MYSQL_PWD="$DB_PASSWORD"
    mysqldump \
        --host="$DB_HOST" \
        --port="$DB_PORT" \
        --user="$DB_USERNAME" \
        --single-transaction \
        --quick \
        --routines \
        --triggers \
        "$DB_DATABASE" > "$temporary_dir/database.sql"
    unset MYSQL_PWD

    tar -C /source -czf "$temporary_dir/payload.tar.gz" storage/app/private
    tar -C "$temporary_dir" -czf - database.sql payload.tar.gz \
        | openssl enc -aes-256-cbc -pbkdf2 -salt -pass env:BACKUP_ENCRYPTION_KEY -out "$archive"
    sha256sum "$archive" > "${archive}.sha256"

    find /backups -type f -name 'sistema-frequencia-*.tar.gz.enc' -mtime "+$RETENTION_DAYS" -delete
    find /backups -type f -name 'sistema-frequencia-*.tar.gz.enc.sha256' -mtime "+$RETENTION_DAYS" -delete

    cleanup
    trap - EXIT INT TERM
    echo "Backup criptografado criado: $(basename "$archive")"
}

while true; do
    create_backup
    sleep "$INTERVAL_SECONDS"
done
