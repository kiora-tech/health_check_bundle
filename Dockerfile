FROM php:8.3-cli-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    unzip \
    libzip-dev \
    postgresql-dev \
    curl \
    bash \
    linux-headers \
    $PHPIZE_DEPS

# Install PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    pdo_pgsql \
    zip

# Install Redis extension
RUN pecl install redis && \
    docker-php-ext-enable redis

# Install MongoDB extension (optional, for future MongoDB support)
RUN pecl install mongodb && \
    docker-php-ext-enable mongodb

# Install Xdebug for code coverage
RUN pecl install xdebug && \
    docker-php-ext-enable xdebug

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Note: Dependencies will be installed after container starts
# because the volume mount will override /app
# Use: docker-compose exec php composer install

# Default command - keep container running
CMD ["tail", "-f", "/dev/null"]
