# Imagem de produção para o EasyPanel (servidor 207.180.205.211).
# Base Debian bookworm: NÃO serve para o host "docker" (Docker 19.03), que exige bullseye.

# Etapa 1: build do front (Vite + Tailwind + Vue)
FROM node:22-bookworm-slim AS front
WORKDIR /app
COPY package.json package-lock.json .npmrc ./
RUN npm ci
COPY . .
RUN npm run build

# Etapa 2: PHP 8.4 + Apache
# Versão fixa de propósito (2026-10-25): a 8.4.27 corrige o PHP2Shell (CVE-2026-109618 e CVE-2026-109619, execução
# remota de código sem login). Com a tag móvel `8.4-apache-bookworm` o builder pode reaproveitar a imagem antiga
# em cache. Ao sair um release de segurança, trocar o número aqui; não voltar para a tag móvel.
FROM php:8.4.27-apache-bookworm

# pdo_pgsql: banco; gd: QR Code do Pix (QRGdImagePNG) e imagens do dompdf; opcache: desempenho.
# zz-seguranca.ini: session.upload_progress desligado (recomendação do php.net; o Laravel não usa sessão nativa).
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_pgsql gd opcache \
    && rm -rf /var/lib/apt/lists/* \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && echo 'session.upload_progress.enabled = 0' > "$PHP_INI_DIR/conf.d/zz-seguranca.ini"

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && a2enmod rewrite

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
COPY --from=front /app/public/build ./public/build

# storage/ fica fora do contexto (.dockerignore): recria só o esqueleto que o Laravel precisa.
RUN mkdir -p storage/app/private storage/app/public storage/framework/cache/data \
        storage/framework/sessions storage/framework/views storage/logs \
    && composer dump-autoload --optimize --no-dev --no-interaction

EXPOSE 80

# O cache de config/rotas/views é feito na subida porque as variáveis de ambiente só existem em runtime.
# Migração roda sempre (migrate --force é idempotente: só aplica o que está pendente). Decisão do usuário,
# 2026-10-12 — antes exigia RODAR_MIGRACOES=1 manual no EasyPanel.
CMD ["sh", "-c", "php artisan optimize && php artisan migrate --force && chown -R www-data:www-data storage bootstrap/cache && exec apache2-foreground"]
