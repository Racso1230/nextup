#!/usr/bin/env bash
# Creates nextup.env and nextup-db.env for the production stack.
#
# Run it ON THE SERVER, once:   bash setup-env.sh
# Secrets are typed with hidden input (nothing is echoed or stored in your
# shell history). The files are written with mode 600 (only you can read them).
# APP_KEY and the database password are generated here, never typed.
#
# Usage: bash setup-env.sh [target-dir] [--force]
#   target-dir  where the files go (default: ~/stack)
#   --force     overwrite existing files (normally the script refuses)
set -euo pipefail

target="$HOME/stack"
force=false
for arg in "$@"; do
    case "$arg" in
        --force) force=true ;;
        *) target="$arg" ;;
    esac
done

app_env="$target/nextup.env"
db_env="$target/nextup-db.env"

command -v openssl >/dev/null || { echo "openssl is required." >&2; exit 1; }
mkdir -p "$target"

if [ "$force" = false ] && { [ -e "$app_env" ] || [ -e "$db_env" ]; }; then
    echo "Refusing to overwrite existing files in $target. Re-run with --force to replace them." >&2
    echo "(Replacing the database password only works on a fresh database volume.)" >&2
    exit 1
fi

# Values go inside double quotes in the env files, so reject characters that
# would break the quoting or be expanded by Docker Compose.
check() {
    case "$1" in
        *'"'* | *'\'* | *'$'* | *'`'* | *$'\n'*)
            echo "That value contains a character that is not allowed (\" \ \$ \` or a newline)." >&2
            exit 1
            ;;
    esac
}

# ask VAR "prompt" [secret]  -> sets VAR; empty input is allowed.
ask() {
    local __var="$1" __prompt="$2" __secret="${3:-}" __value
    if [ "$__secret" = secret ]; then
        read -r -s -p "$__prompt (hidden): " __value
        echo
    else
        read -r -p "$__prompt: " __value
    fi
    check "$__value"
    printf -v "$__var" '%s' "$__value"
}

echo "nextup production settings. Press Enter to leave an optional value empty."
echo
ask steam_key "Steam Web API key" secret
ask igdb_id "IGDB (Twitch) client ID" secret
ask igdb_secret "IGDB (Twitch) client secret" secret
ask alert_to "Email address for failed-job alerts (optional)"
ask mail_host "SMTP host (optional, e.g. smtp.example.com)"
mail_port=587
mail_user=""
mail_pass=""
mail_from=""
if [ -n "$mail_host" ]; then
    ask mail_port "SMTP port (587 for STARTTLS)"
    mail_port="${mail_port:-587}"
    ask mail_user "SMTP username"
    ask mail_pass "SMTP password" secret
    ask mail_from "From address (e.g. nextup@ojfdigital.co.uk)"
fi

app_key="base64:$(openssl rand -base64 32)"
db_pass="$(openssl rand -hex 24)"

umask 077

cat >"$app_env" <<ENV
APP_NAME=nextup
APP_ENV=production
APP_DEBUG=false
APP_URL=https://nextup.ojfdigital.co.uk
APP_KEY="$app_key"

LOG_CHANNEL=stack
LOG_STACK=json
LOG_JSON_TARGET=stderr
LOG_LEVEL=info

DB_CONNECTION=pgsql
DB_HOST=nextup-db
DB_PORT=5432
DB_DATABASE=nextup
DB_USERNAME=nextup
DB_PASSWORD="$db_pass"

REDIS_CLIENT=phpredis
REDIS_HOST=nextup-redis
REDIS_PORT=6379
REDIS_PASSWORD=null

SESSION_DRIVER=redis
SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=null
CACHE_STORE=redis
QUEUE_CONNECTION=redis

INERTIA_SSR_URL=http://nextup-ssr:13714

STEAM_API_KEY="$steam_key"
IGDB_CLIENT_ID="$igdb_id"
IGDB_CLIENT_SECRET="$igdb_secret"

ALERT_MAIL_TO="$alert_to"
ENV

if [ -n "$mail_host" ]; then
    cat >>"$app_env" <<ENV
MAIL_MAILER=smtp
MAIL_HOST="$mail_host"
MAIL_PORT=$mail_port
MAIL_USERNAME="$mail_user"
MAIL_PASSWORD="$mail_pass"
MAIL_FROM_ADDRESS="$mail_from"
MAIL_FROM_NAME=nextup
ENV
else
    cat >>"$app_env" <<ENV
MAIL_MAILER=log
ENV
fi

cat >"$db_env" <<ENV
POSTGRES_DB=nextup
POSTGRES_USER=nextup
POSTGRES_PASSWORD="$db_pass"
ENV

chmod 600 "$app_env" "$db_env"
echo
echo "Wrote $app_env and $db_env (mode 600)."
echo "Back both files up somewhere safe (a password manager): APP_KEY cannot be recovered."
