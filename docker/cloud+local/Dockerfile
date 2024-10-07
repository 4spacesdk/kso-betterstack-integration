# PHP DeploymentSpecs can be found at https://hub.docker.com/_/php/
FROM php:8.3.12-alpine3.20

# Install OS dependencies
RUN apk add --update  \
    apache2 apache2-ssl \
    zlib-dev \
    icu-dev \
    nano \
    wget \
    curl-dev \
    linux-headers \
    php83-apache2 php83-mbstring php83-json php83-tokenizer php83-intl php83-curl php83-posix

RUN docker-php-ext-configure intl
RUN docker-php-ext-install intl pcntl posix
RUN docker-php-ext-enable intl posix

RUN rm -rf /tmp/* /var/cache/apk/*

# Configure apache
COPY "docker/cloud+local/apache/httpd.conf" /etc/apache2/httpd.conf

# Forward request and error logs to docker log collector
RUN ln -sf /dev/stdout /var/log/apache2/access.log \
    && ln -sf /dev/stderr /var/log/apache2/error.log

# Copy application files
ENV APP_HOME /var/www/html
COPY --chown=www-data app $APP_HOME

# Change ownership of our applications
RUN chown -R www-data:www-data /var/www

# Set TAG_NAME as Environment Variable
ARG BUILD_TAG_NAME=latest
ENV TAG_NAME ${BUILD_TAG_NAME}

## Git Commit
ARG BUILD_SHORT_SHA
ENV SHORT_SHA ${BUILD_SHORT_SHA}

# Launch the httpd in foreground
CMD rm -rf /run/apache2/*
CMD /usr/sbin/httpd -DFOREGROUND
