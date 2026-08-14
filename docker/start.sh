#!/bin/bash
set -e

# Railway injects PORT. Default for local runs; 8080 is reserved for Reverb.
export PORT="${PORT:-8000}"

echo "🏀 HoopSense+ — Starting deployment..."

# Composer dependencies and frontend assets are built into the image at
# Docker build time, so runtime startup should not depend on npm/node.
echo "📦 Using bundled production dependencies and built assets..."

# Ensure storage structure exists — Persistent Volume mount replaces /app/storage,
# so required subdirectories must be recreated at runtime on every start.
echo "📁 Ensuring storage directories..."
mkdir -p storage/framework/{cache,sessions,views,testing} \
    storage/logs \
    bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Run migrations
echo "📦 Running migrations..."
php artisan migrate --force

# Seed demo data — only on first deploy (skip if teams already exist)
TEAM_COUNT=$(php artisan tinker --execute="echo App\Models\Team::count();" 2>/dev/null | tail -1)
if [ "$TEAM_COUNT" = "0" ] || [ -z "$TEAM_COUNT" ]; then
    echo "🌱 Empty database detected — seeding demo data..."
    php artisan db:seed --force
else
    echo "✅ Database already has data — skipping seed to preserve prod data."
fi

# Cache configuration for production
echo "⚡ Caching config..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Link storage
echo "🔗 Linking storage..."
php artisan storage:link || true

echo "✅ Deployment ready! Starting services..."

# Start supervisor (web + queue + reverb)
exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
