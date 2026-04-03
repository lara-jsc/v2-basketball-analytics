#!/bin/bash
set -e

echo "🏀 HoopSense+ — Starting deployment..."

# Run migrations
echo "📦 Running migrations..."
php artisan migrate --force

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
