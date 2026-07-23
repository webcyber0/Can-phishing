# Dockerfile - Render par PHP-Apache server chalane ke liye
FROM php:8.1-apache

# libcurl install karo
RUN apt-get update && \
    apt-get install -y libcurl4-openssl-dev && \
    rm -rf /var/lib/apt/lists/*

# Apache rewrite module enable karo
RUN a2enmod rewrite

# PHP curl extension install karo
RUN docker-php-ext-install curl

# ⭐ ALLOW OVERRIDE ENABLE KARO — Yehi main fix hai!
RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Poori repo copy karo web server directory mein
COPY . /var/www/html/

# Permissions set karo
RUN chmod -R 755 /var/www/html/ && \
    chmod -R 777 /var/www/html/anmol/

# Apache server name set karo
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

EXPOSE 80

CMD ["apache2-foreground"]