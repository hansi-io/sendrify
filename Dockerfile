FROM php:8.3-apache

# Install PHP extensions required by Sendrify
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache mod_rewrite for pretty URLs
RUN a2enmod rewrite

# Set Apache document root to /var/www/html/public
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Allow .htaccess overrides
RUN sed -ri -e 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# PHP configuration for file uploads
RUN echo "upload_max_filesize = 50M" > /usr/local/etc/php/conf.d/sendrify.ini \
    && echo "post_max_size = 55M" >> /usr/local/etc/php/conf.d/sendrify.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/sendrify.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/sendrify.ini \
    && echo "max_input_time = 300" >> /usr/local/etc/php/conf.d/sendrify.ini

# Copy application files
COPY . /var/www/html/

# Ensure storage directory is writable
RUN mkdir -p /var/www/html/storage \
    && chown -R www-data:www-data /var/www/html/storage

# Copy entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
