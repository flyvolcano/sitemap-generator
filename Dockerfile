FROM php:8.2-cli-alpine

RUN docker-php-ext-install exif

WORKDIR /app

COPY sitemap /sitemap

ENTRYPOINT ["/sitemap"]
