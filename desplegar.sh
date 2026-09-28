#!/usr/bin/env bash
# Despliega descarga_sat en producción con Docker Compose (ver la sección "Producción" del README).
# Uso: ./desplegar.sh   (desde la carpeta del proyecto, con .env.production ya preparado)

set -euo pipefail

compose=(docker compose -f compose.produccion.yaml)

if [[ ! -f .env.production ]]; then
    echo "Falta .env.production; ver la sección \"Producción\" del README." >&2
    exit 1
fi

echo "1/4 Construyendo la imagen..."
"${compose[@]}" build

echo "2/4 Aplicando migraciones..."
"${compose[@]}" run --rm --no-deps --user www-data web php artisan migrate --force

echo "3/4 Levantando web, scheduler y worker..."
"${compose[@]}" up -d --remove-orphans

echo "4/4 Pidiendo al worker que se reinicie con el código nuevo..."
"${compose[@]}" exec --user www-data worker php artisan queue:restart \
    || echo "El worker no estaba corriendo; revisa: docker compose -f compose.produccion.yaml logs worker" >&2

"${compose[@]}" ps
