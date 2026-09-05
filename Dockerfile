FROM php:8.4-apache
RUN apt-get update && apt-get install -y --no-install-recommends libxml2-dev ca-certificates \
    && docker-php-ext-install pdo_mysql soap bcmath \
    && apt-get clean && rm -rf /var/lib/apt/lists/*
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini
COPY public/ /var/www/html/public/
COPY src/ /var/www/html/src/
COPY sql/ /var/www/html/sql/
COPY bin/ /var/www/html/bin/
COPY tests/ /var/www/html/tests/
WORKDIR /var/www/html
EXPOSE 80

