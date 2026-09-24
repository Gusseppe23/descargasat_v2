#!/usr/bin/env bash
# Verifica que el contenedor tenga todo lo necesario para descarga_sat.
set -u
fallas=0

check() {
  local nombre="$1"; shift
  local salida
  if salida=$("$@" 2>&1); then
    printf '  %-20s %s\n' "$nombre" "${salida%%$'\n'*}"
  else
    printf '  %-20s FALTA\n' "$nombre"
    fallas=1
  fi
}

echo "Herramientas:"
check "PHP"               php -r 'echo PHP_VERSION;'
check "Composer"          composer --version --no-ansi
check "Laravel installer" laravel --version --no-ansi
check "Node.js"           node -v
check "npm"               npm -v
check "Git"               git --version
check "GitHub CLI"        gh --version
if command -v claude >/dev/null 2>&1; then
  check "Claude Code"     claude --version
fi

if ! php -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);'; then
  echo "  PHP debe ser 8.3 o superior"
  fallas=1
fi

echo "Extensiones de PHP:"
modulos=$(php -m)
for ext in openssl dom xml mbstring zip soap intl bcmath gd pcntl pdo_sqlite pdo_mysql pdo_pgsql; do
  if grep -qix "$ext" <<<"$modulos"; then
    printf '  %-20s ok\n' "$ext"
  else
    printf '  %-20s FALTA\n' "$ext"
    fallas=1
  fi
done

echo "Zona horaria de PHP: $(php -r 'echo date_default_timezone_get();')"

if [ "$fallas" -eq 0 ]; then
  echo "Entorno listo."
else
  echo "Hay componentes faltantes (ver arriba)."
fi
exit "$fallas"
