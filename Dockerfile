# ============================================================
# HoopSense+ — Multi-runtime Dockerfile (PHP + Node + Python)
# ============================================================

# ------- Stage 1: Build frontend assets -------
FROM node:20-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

# Vite inlines import.meta.env.VITE_* at build time.
ARG VITE_APP_NAME="HoopSense+"
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT=443
ARG VITE_REVERB_SCHEME=https
ENV VITE_APP_NAME=$VITE_APP_NAME \
    VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY \
    VITE_REVERB_HOST=$VITE_REVERB_HOST \
    VITE_REVERB_PORT=$VITE_REVERB_PORT \
    VITE_REVERB_SCHEME=$VITE_REVERB_SCHEME

COPY . .
RUN npm run build

# ------- Stage 2: Production image -------
FROM dunglas/frankenphp:php8.4

RUN install-php-extensions \
    pdo_mysql pdo_sqlite mbstring exif pcntl bcmath gd zip opcache

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    zip \
    unzip \
    python3 \
    python3-pip \
    default-mysql-client \
    supervisor \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy composer files first for layer caching
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# Copy the rest of the app
COPY . .
RUN rm -f public/hot

# Run post-install scripts
RUN composer dump-autoload --optimize

# Copy built frontend assets from Stage 1
COPY --from=frontend /app/public/build public/build

# Install Python dependencies
COPY requirements.txt ./
RUN pip3 install --break-system-packages -r requirements.txt || true

# Create storage structure & set permissions
RUN mkdir -p storage/framework/{cache,sessions,views,testing} \
    storage/logs \
    bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Web server config (proxies Reverb on the same domain)
COPY docker/Caddyfile /etc/frankenphp/Caddyfile

# Create supervisord config
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy and set start script
COPY docker/start.sh /app/docker/start.sh
RUN chmod +x /app/docker/start.sh

EXPOSE 8000 8080

CMD ["/app/docker/start.sh"]
