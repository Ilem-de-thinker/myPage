#!/usr/bin/env bash
set -u

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT" || exit 1

failures=0
warnings=0

pass() { printf '  [ok]   %s\n' "$1"; }
warn() { printf '  [warn] %s\n' "$1"; warnings=$((warnings + 1)); }
note() { printf '  [note] %s\n' "$1"; }
fail() { printf '  [FAIL] %s\n' "$1"; failures=$((failures + 1)); }

env_value() {
    local key="$1"
    [ -f .env ] || return 1
    grep -E "^${key}=" .env 2>/dev/null | tail -n 1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"
}

has_env() { [ -n "$(env_value "$1")" ]; }

echo "Mercha deploy preflight — $ROOT"
echo

echo "Runtime"
php -v >/dev/null 2>&1 && pass "php is installed" || fail "php is not on PATH"
php -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);' \
    && pass "PHP $(php -r 'echo PHP_VERSION;') is 8.3+" \
    || fail "PHP 8.3+ is required"
for ext in pdo mbstring openssl curl fileinfo gd; do
    php -m | grep -qi "^${ext}$" && pass "php extension: $ext" || warn "php extension missing: $ext"
done
case "$(env_value DB_CONNECTION)" in
    mysql|mariadb)
        php -m | grep -qi '^pdo_mysql$' && pass "php extension: pdo_mysql" || fail "pdo_mysql is required for the configured database"
        ;;
esac
[ -f vendor/autoload.php ] && pass "composer dependencies installed" || fail "vendor/ missing — run composer install"

echo
echo "Environment"
[ -f .env ] && pass ".env exists" || fail ".env is missing — copy .env.example and fill it"
if [ -n "${APP_KEY:-}" ] || has_env APP_KEY; then
    pass "APP_KEY is set"
else
    fail "APP_KEY is empty — run php artisan key:generate"
fi
[ "$(env_value APP_ENV)" = "production" ] && pass "APP_ENV=production" || warn "APP_ENV is not production ($(env_value APP_ENV))"
[ "$(env_value APP_DEBUG)" = "false" ] && pass "APP_DEBUG=false" || warn "APP_DEBUG must be false in production"
case "$(env_value APP_URL)" in
    *localhost*|*127.0.0.1*|"") warn "APP_URL is not a public URL ($(env_value APP_URL))" ;;
    *) pass "APP_URL is set" ;;
esac
[ -n "$(env_value APP_DOMAIN)" ] && pass "APP_DOMAIN is set" || warn "APP_DOMAIN is empty — tenant subdomains fall back to APP_URL host"

case "$(env_value STOREFRONT_MODE)" in
    path) pass "STOREFRONT_MODE=path — shops are served at /store/{slug}, no wildcard DNS needed" ;;
    subdomain|"") warn "STOREFRONT_MODE=subdomain — wildcard DNS and a wildcard TLS certificate are required" ;;
    *) fail "STOREFRONT_MODE must be 'subdomain' or 'path' (got '$(env_value STOREFRONT_MODE)')" ;;
esac

db="$(env_value DB_CONNECTION)"
case "$db" in
    sqlite|"") warn "DB_CONNECTION is '$db' — use the production database driver" ;;
    *) pass "database driver: $db" ;;
esac

echo
echo "Credentials"
[ -n "$(env_value PAYSTACK_SECRET)" ] && pass "PAYSTACK_SECRET is set" || warn "PAYSTACK_SECRET is empty — checkout will fail"
[ -n "$(env_value PAYSTACK_PUBLIC)" ] && pass "PAYSTACK_PUBLIC is set" || warn "PAYSTACK_PUBLIC is empty"
for plan in STARTER GROWTH PRO; do
    [ -n "$(env_value "PAYSTACK_PLAN_${plan}")" ] \
        && pass "PAYSTACK_PLAN_${plan} is set" \
        || warn "PAYSTACK_PLAN_${plan} is empty — that plan will not renew automatically"
done
if [ -n "$(env_value GOOGLE_CLIENT_ID)" ] && [ -n "$(env_value GOOGLE_CLIENT_SECRET)" ]; then
    pass "Google sign-in credentials are set"
else
    warn "Google sign-in credentials are empty — the Google button stays hidden"
fi
if [ "$(env_value AI_ENABLED)" != "false" ]; then
    if [ -n "$(env_value GROQ_API_KEY)" ] || [ -n "$(env_value OPENAI_API_KEY)" ] \
        || [ -n "$(env_value ANTHROPIC_API_KEY)" ] || [ -n "$(env_value GEMINI_API_KEY)" ]; then
        pass "an AI provider key is set"
    else
        warn "AI is enabled but no provider key is set (GROQ/OPENAI/ANTHROPIC/GEMINI)"
    fi
fi
[ -n "$(env_value MAIL_HOST)" ] && pass "MAIL_HOST is set" || warn "MAIL_HOST is empty — email delivery is unconfigured"

echo
echo "Database"
if php artisan migrate:status --no-interaction >/tmp/mercha-migrate-status 2>/tmp/mercha-migrate-error; then
    if grep -q "Pending" /tmp/mercha-migrate-status; then
        fail "pending migrations — run php artisan migrate --force"
    else
        pass "all migrations are applied"
    fi
else
    fail "cannot read migration status: $(head -n 1 /tmp/mercha-migrate-error)"
fi
rm -f /tmp/mercha-migrate-status /tmp/mercha-migrate-error

echo
echo "Storage and build"
for dir in storage storage/app storage/framework storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache; do
    [ -d "$dir" ] && [ -w "$dir" ] && pass "$dir is writable" || fail "$dir is missing or not writable"
done
[ -L public/storage ] && pass "public/storage symlink exists" || warn "public/storage missing — run php artisan storage:link"
[ -f public/build/manifest.json ] && pass "frontend assets are built" || fail "public/build/manifest.json missing — run npm ci && npm run build"

echo
echo "Workers and schedules"
queue="$(env_value QUEUE_CONNECTION)"
case "$queue" in
    sync|"") warn "QUEUE_CONNECTION is '$queue' — queued AI, email and settlement work will not run in the background" ;;
    *) pass "queue driver: $queue (confirm a worker is supervised)" ;;
esac
note "confirm deploy/supervisor/mypage-queue.conf is running"
note "confirm deploy/cron/mypage-scheduler is installed for schedule:run every minute"

echo
echo "Caches"
if [ "$(env_value APP_ENV)" = "production" ]; then
    note "run php artisan config:cache route:cache view:cache after deploy"
fi

echo
if [ "$failures" -gt 0 ]; then
    printf 'Preflight: %d failure(s), %d warning(s). Fix the failures before deploying.\n' "$failures" "$warnings"
    exit 1
fi

printf 'Preflight: no failures, %d warning(s). Review warnings, then deploy.\n' "$warnings"
