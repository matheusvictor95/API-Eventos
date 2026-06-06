FROM php:8.4-fpm-alpine

# Set working directory
WORKDIR /var/www/html

# Install system dependencies & PHP extensions helper
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

# Install PHP extensions
RUN install-php-extensions pdo_pgsql redis gd zip bcmath intl opcache pcntl

# Install composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Create a system user to run commands
RUN addgroup -g 1000 laravel && adduser -u 1000 -G laravel -s /bin/sh -D laravel

# Copy existing application directory permissions
COPY --chown=laravel:laravel . /var/www/html

# Change current user to laravel
USER laravel

EXPOSE 9000

CMD ["php-fpm"]
