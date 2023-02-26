FROM serversideup/php:8.2-cli as builder

WORKDIR /var/www/html

COPY . .

RUN composer install \
    --ignore-platform-reqs \
    --no-interaction \
    --no-plugins \
    --no-scripts \
    --optimize-autoloader \
    --prefer-dist

RUN php sitemap app:build --build-version=0.0.1

FROM php:8.2-cli-alpine
WORKDIR /app

# Copy Composer dependencies
COPY --from=builder /var/www/html/builds/sitemap /app/sitemap

ENTRYPOINT ["/app/sitemap"]
