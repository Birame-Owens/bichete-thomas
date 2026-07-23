#!/bin/sh
set -e

cd /app

echo "==> Ensuring storage directories exist..."
mkdir -p storage/app/public storage/framework/views storage/framework/cache storage/framework/sessions storage/logs bootstrap/cache
chown -R www-data:www-data storage/ bootstrap/cache

echo "==> Running database migrations..."
php artisan migrate --force

echo "==> Creating storage symlink..."
php artisan storage:link 2>/dev/null || true

echo "==> Caching configuration for production..."
php artisan config:cache
php artisan route:cache
php artisan optimize 2>/dev/null || true

# Génère la map nginx du bypass rate-limit pour les tests de charge.
# Le token vient de l'env (jamais en dur dans le repo) : clé vide => limite
# ignorée quand X-Performance-Test-Token correspond, sinon limite par vraie IP.
echo "==> Generating nginx rate-limit bypass map..."
if [ -n "$PERFORMANCE_TEST_BYPASS_TOKEN" ]; then
    cat > /etc/nginx/perf-token.conf <<EOF
map \$http_x_performance_test_token \$rl_key {
    "$PERFORMANCE_TEST_BYPASS_TOKEN"  "";
    default                            \$binary_remote_addr;
}
EOF
else
    cat > /etc/nginx/perf-token.conf <<EOF
map \$http_x_performance_test_token \$rl_key {
    default \$binary_remote_addr;
}
EOF
fi

echo "==> Starting supervisord..."
exec /usr/bin/supervisord -c /etc/supervisord.conf
