# Dockerfile - Render par PHP-Apache server chalane ke liye
FROM php:8.1-apache

# Apache rewrite module enable karo (URL short links ke liye)
RUN a2enmod rewrite

# PHP curl extension install karo (Telegram API calls ke liye)
RUN docker-php-ext-install curl

# Poori repo copy karo web server directory mein
COPY . /var/www/html/

# Permissions set karo
RUN chmod -R 755 /var/www/html/ && \
    chmod -R 777 /var/www/html/anmol/

# Apache server name set karo (warning avoid karne ke liye)
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Port 80 expose karo (Render ko batane ke liye)
EXPOSE 80

# Apache start karo
CMD ["apache2-foreground"]
