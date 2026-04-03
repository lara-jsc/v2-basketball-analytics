# ============================================================
# HoopSense+ — Multi-runtime Dockerfile (PHP + Node + Python)
# ============================================================

# ------- Stage 1: Build frontend assets -------
FROM node:20-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN npm run build

# ------- Stage 2: Production image -------
FROM php:8.3-cli

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    zip \
    unzip \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libsqlite3-dev \
    python3 \
    python3-pip \
    default-mysql-client \
    supervisor \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql pdo_sqlite mbstring exif pcntl bcmath gd zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy composer files first for layer caching
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# Copy the rest of the app
COPY . .

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

# Create supervisord config
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy and set start script
COPY docker/start.sh /app/docker/start.sh
RUN chmod +x /app/docker/start.sh

EXPOSE ${PORT:-8080}

CMD ["/app/docker/start.sh"]
