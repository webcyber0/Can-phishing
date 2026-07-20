# Dockerfile - Render par PHP-Apache server chalane ke liye
FROM php:8.1-apache

# libcurl install karo (pehle yeh missing tha - isliye error aa raha tha)
RUN apt-get update && \
    apt-get install -y libcurl4-openssl-dev && \
    rm -rf /var/lib/apt/lists/*

# Apache rewrite module enable karo
RUN a2enmod rewrite

# PHP curl extension install karo (ab kaam karega)
RUN docker-php-ext-install curl

# Poori repo copy karo web server directory mein
COPY . /var/www/html/

# Permissions set karo
RUN chmod -R 755 /var/www/html/ && \
    chmod -R 777 /var/www/html/anmol/

# Apache server name set karo
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

EXPOSE 80

CMD ["apache2-foreground"]
