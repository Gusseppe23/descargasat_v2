# syntax=docker/dockerfile:1.7
# Entorno de desarrollo para descarga_sat (Laravel 13)
# PHP 8.5 (o 8.3+) + Composer + instalador de Laravel + Node.js + Git + GitHub CLI

ARG PHP_VERSION=8.5
FROM php:${PHP_VERSION}-cli

ARG NODE_MAJOR=24
ARG USER_NAME=dev
ARG USER_UID=1000
ARG USER_GID=1000
ARG INSTALL_CLAUDE_CODE=false

ENV DEBIAN_FRONTEND=noninteractive \
    TZ=America/Mexico_City \
    PATH="/opt/composer/vendor/bin:${PATH}"

# 1. Paquetes base del sistema
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      ca-certificates curl gnupg git unzip zip sqlite3 openssh-client less nano procps tzdata \
 && rm -rf /var/lib/apt/lists/*

# 2. Extensiones de PHP
#    openssl, dom, xml, mbstring y pdo_sqlite ya vienen en la imagen oficial.
#    zip + soap: paquetes y servicio del SAT; intl/bcmath/gd: Laravel; pdo_*: MySQL/PostgreSQL.
COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions zip soap intl bcmath gd pcntl exif pdo_mysql pdo_pgsql

# 3. Configuración de PHP para desarrollo (zona horaria de México: el SAT usa hora local)
RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" \
 && printf '%s\n' \
      'date.timezone = America/Mexico_City' \
      'memory_limit = 512M' \
      'upload_max_filesize = 20M' \
      'post_max_size = 25M' \
    > "$PHP_INI_DIR/conf.d/zz-dev.ini"

# 4. Composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# 5. Instalador de Laravel (global, fuera del home para que el volumen no lo oculte)
RUN COMPOSER_HOME=/opt/composer composer global require laravel/installer --no-interaction --no-progress \
 && composer clear-cache \
 && chmod -R a+rX /opt/composer

# 6. Node.js (NodeSource)
RUN curl -fsSL "https://deb.nodesource.com/setup_${NODE_MAJOR}.x" | bash - \
 && apt-get install -y --no-install-recommends nodejs \
 && rm -rf /var/lib/apt/lists/*

# 7. GitHub CLI (repositorio oficial)
RUN mkdir -p -m 755 /etc/apt/keyrings \
 && curl -fsSL https://cli.github.com/packages/githubcli-archive-keyring.gpg \
      -o /etc/apt/keyrings/githubcli-archive-keyring.gpg \
 && chmod go+r /etc/apt/keyrings/githubcli-archive-keyring.gpg \
 && echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/githubcli-archive-keyring.gpg] https://cli.github.com/packages stable main" \
      > /etc/apt/sources.list.d/github-cli.list \
 && apt-get update \
 && apt-get install -y --no-install-recommends gh \
 && rm -rf /var/lib/apt/lists/*

# 8. (Opcional) Claude Code dentro del contenedor, para que Boost y artisan corran junto al agente
RUN if [ "$INSTALL_CLAUDE_CODE" = "true" ]; then npm install -g @anthropic-ai/claude-code && npm cache clean --force; fi

# 9. Git: carpetas montadas desde Windows aparecen con otro dueño; finales de línea LF
RUN git config --system --add safe.directory '*' \
 && git config --system core.autocrlf input \
 && git config --system init.defaultBranch main

# 10. Usuario sin privilegios
RUN groupadd --gid "${USER_GID}" "${USER_NAME}" \
 && useradd --uid "${USER_UID}" --gid "${USER_GID}" --create-home --shell /bin/bash "${USER_NAME}" \
 && mkdir -p /app && chown "${USER_UID}:${USER_GID}" /app

COPY --chmod=755 verificar-entorno.sh /usr/local/bin/verificar-entorno

USER ${USER_NAME}
WORKDIR /app

EXPOSE 8000 5173
CMD ["bash"]
