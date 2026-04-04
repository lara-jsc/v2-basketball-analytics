#!/bin/bash
set -e

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

# Seed demo data
echo "🌱 Seeding database..."
php artisan db:seed --force

# Cache configuration for production
echo "⚡ Caching config..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Link storage
echo "🔗 Linking storage..."
php artisan storage:link || true

echo "✅ Deployment ready! Starting services..."

# Start supervisor (web + queue)
exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
