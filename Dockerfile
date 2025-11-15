FROM php:8.4-cli-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    unzip \
    libzip-dev \
    oniguruma-dev \
    postgresql-dev \
    redis \
    $PHPIZE_DEPS

# Install PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    pdo_pgsql \
    zip \
    pcntl

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install PCOV for coverage
RUN pecl install pcov && docker-php-ext-enable pcov

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# For development, we mount the code as a volume
# Dependencies will be installed at runtime via `make install`
CMD ["tail", "-f", "/dev/null"]
