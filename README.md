# laravel-dev: contenedor de desarrollo para descarga_sat

Imagen con PHP 8.5 (8.3+), Composer 2, el instalador de Laravel, Node.js 24, Git y GitHub CLI,
más las extensiones que necesita `phpcfdi/sat-ws-descarga-masiva` (openssl, dom, zip, soap).

## Archivos

| Archivo | Para qué |
| --- | --- |
| `build.ps1` | Construye la imagen, verifica versiones y arranca el contenedor |
| `Dockerfile` | Definición de la imagen |
| `compose.yaml` | Puertos 8000/5173, carpeta del proyecto en `/app`, home persistente |
| `verificar-entorno.sh` | Revisa herramientas y extensiones (se copia a la imagen) |

## Uso (Windows, PowerShell)

Requisito: Docker Desktop con el motor WSL 2 corriendo.

```powershell
# Pon esta carpeta FUERA del proyecto, por ejemplo D:\laravel-dev
cd D:\laravel-dev
powershell -ExecutionPolicy Bypass -File .\build.ps1                # PHP 8.5, proyecto en D:\descarga_sat
powershell -ExecutionPolicy Bypass -File .\build.ps1 -PhpVersion 8.4
powershell -ExecutionPolicy Bypass -File .\build.ps1 -WithClaudeCode
```

Dentro del contenedor:

```bash
docker compose exec dev bash
gh auth login
laravel new .          # la carpeta D:\descarga_sat debe estar vacía
composer run dev       # http://localhost:8000
```

## Notas

- **Vite desde el contenedor:** agrega en `vite.config.js`
  `server: { host: '0.0.0.0', port: 5173, hmr: { host: 'localhost' } }`.
- **`php artisan serve`** ya escucha en `0.0.0.0` gracias a `SERVER_HOST` en `compose.yaml`.
- **Rendimiento:** montar carpetas de `D:\` es más lento que el disco de WSL. Si `composer install` o
  las pruebas se sienten lentas, mueve el proyecto a `\\wsl$\Ubuntu\home\<usuario>\descarga_sat`
  y corre `build.ps1 -ProjectDir` con esa ruta.
- **Laravel Boost y Claude Code:** el servidor MCP de Boost ejecuta `php artisan`. Si Claude Code corre
  en Windows sin PHP, Boost no funciona; usa `-WithClaudeCode` y ejecuta `claude` dentro del contenedor.
- **Actualizar herramientas:** `build.ps1 -NoCache`.
- El volumen `home` guarda la sesión de `gh`, la configuración de git y las cachés. Para borrarlo:
  `docker compose down -v`.
