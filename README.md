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

## Producción (Docker Compose en la red local)

La app corre en tres contenedores con la misma imagen (`Dockerfile.produccion`), definidos en
`compose.produccion.yaml`. Docker los vuelve a levantar si se caen o si se reinicia la máquina
(`restart: unless-stopped`).

| Servicio | Qué hace |
| --- | --- |
| `web` | Apache con PHP; la app queda en `http://<ip-de-la-máquina>:8080` (otro puerto: `PUERTO=9000 ./desplegar.sh`) |
| `scheduler` | `schedule:work`: corre la Verificación (`sat:verificar`) cada 5 minutos |
| `worker` | `queue:work database`: descarga y extrae los Paquetes. **Uno solo** (ver #21) |

Los tres comparten el volumen `datos`, montado en `storage/`: ahí viven la FIEL cifrada, los ZIP y
XML de los Paquetes, los logs y la base SQLite (`storage/app/database.sqlite`).

### Primera vez

1. Copia el proyecto a la máquina (por ejemplo con `git clone`) y entra a su carpeta.
2. Crea `.env.production` (no va al repo) con este contenido:

   ```dotenv
   APP_NAME="Descarga SAT"
   APP_ENV=production
   APP_KEY=
   APP_DEBUG=false
   APP_URL=http://<ip-de-la-máquina>:8080

   LOG_CHANNEL=stack
   # Al archivo de storage/logs y a la salida de Docker (docker compose logs)
   LOG_STACK=single,stderr
   LOG_LEVEL=warning

   DB_CONNECTION=sqlite
   DB_DATABASE=/var/www/html/storage/app/database.sqlite
   # Tres procesos escriben en la misma base: esperar hasta 5 s en vez de fallar, y leer mientras otro escribe
   DB_BUSY_TIMEOUT=5000
   DB_JOURNAL_MODE=wal

   SESSION_DRIVER=database
   CACHE_STORE=database
   QUEUE_CONNECTION=database
   # Mayor que el --timeout=300 del worker, para que la cola no repita una descarga larga
   DB_QUEUE_RETRY_AFTER=360
   ```

3. Genera la llave y pégala en `APP_KEY`:

   ```bash
   docker compose -f compose.produccion.yaml build
   docker compose -f compose.produccion.yaml run --rm --no-deps --user www-data web php artisan key:generate --show
   ```

4. Despliega: `./desplegar.sh`
5. Abre `http://<ip-de-la-máquina>:8080/register` y crea tu usuario. Es el único: en cuanto existe,
   el registro se cierra solo y nadie más en la red puede crear una cuenta.

> **Importante:** `APP_KEY` cifra la FIEL guardada. Si se pierde o se cambia, la FIEL ya no se puede
> abrir y hay que volver a subirla. Guarda una copia de `.env.production` en un lugar seguro.

### Actualizar

```bash
git pull
./desplegar.sh
```

`desplegar.sh` construye la imagen, aplica las migraciones una sola vez, levanta los tres servicios y
llama a `queue:restart` para que el worker tome el código nuevo.

### Si olvidas la contraseña

No hay correo configurado: el enlace de "¿Olvidaste tu contraseña?" no se envía, se escribe en el log.
Búscalo con `docker compose -f compose.produccion.yaml logs web`.

### Respaldo

Respalda `.env.production` y el volumen `datos`, por ejemplo:

```bash
docker run --rm -v descarga-sat_datos:/datos -v "$PWD":/respaldo alpine \
  tar czf /respaldo/datos-$(date +%F).tgz -C /datos .
```

### Revisar que todo corre

- `docker compose -f compose.produccion.yaml ps`: `web`, `scheduler` y `worker` en estado `running`.
- Abre `http://<ip-de-la-máquina>:8080`, inicia sesión y confirma que `/register` responde 404.
- `docker compose -f compose.produccion.yaml logs scheduler`: cada 5 minutos aparece `sat:verificar`.
- `docker compose -f compose.produccion.yaml logs worker`: al terminar una Solicitud aparecen los
  `DescargarPaquete` en `DONE`.
- Reinicia la máquina y confirma que los tres servicios vuelven solos.
