FROM php:8.1-apache

# Install PDO MySQL extension
RUN docker-php-ext-install pdo_mysql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Set permissions for the uploads directory
RUN chown -R www-data:www-data /var/www/html/uploads

# Expose port 80
EXPOSE 80
