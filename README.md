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
cd /tmp && laravel new descarga_sat --no-node   # 'laravel new .' no funciona
cp -r /tmp/descarga_sat/. /app/ && rm -rf /tmp/descarga_sat
cd /app && npm install && npm run build
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

## Producción en una máquina virtual con Rocky Linux 10

Pasos para levantar la sección anterior ("Producción") en una VM con Rocky Linux 10. Todos los
comandos se ejecutan con un usuario administrador del grupo `wheel`, no como root.

### Requisitos de la VM

| Recurso | Mínimo | Nota |
| --- | --- | --- |
| CPU | 2 vCPU con soporte x86-64-v3 | Rocky 10 no arranca en CPUs más viejas; en VirtualBox o Hyper-V usa el tipo de CPU del host |
| RAM | 4 GB | La primera construcción compila Composer y Node |
| Disco | 30 GB | Imágenes de Docker más los ZIP y XML que se acumulan |
| Red | IP fija en la red local | En VirtualBox, adaptador puente (bridged), no NAT |
| Internet | Docker Hub, GitHub, Packagist, npm y el SAT | Para construir la imagen y descargar del SAT |

### 1. Preparar el sistema

```bash
sudo dnf -y update
sudo timedatectl set-timezone America/Mexico_City
sudo hostnamectl set-hostname descarga-sat
sudo dnf -y install git tar cronie
sudo systemctl enable --now crond
ip -4 addr show | grep inet     # anota la IP de la VM
sudo reboot                     # si el update instaló un kernel nuevo
```

Si la red usa DHCP, reserva la IP en el router o fíjala con `nmtui`: `APP_URL` depende de ella.

### 2. Instalar Docker Engine

Procedimiento oficial de Docker para RHEL (soporta la versión 10). Rocky trae Podman; se quita para evitar conflictos.

```bash
sudo dnf -y remove docker docker-client docker-client-latest docker-common docker-latest \
  docker-latest-logrotate docker-logrotate docker-engine podman runc
sudo dnf -y install dnf-plugins-core
sudo dnf config-manager --add-repo https://download.docker.com/linux/rhel/docker-ce.repo
sudo dnf -y install docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
sudo systemctl enable --now docker
sudo usermod -aG docker $USER
```

Si el `install` responde 404 al leer el repositorio de Docker:
`sudo sed -i 's/\$releasever/10/g' /etc/yum.repos.d/docker-ce.repo` y repítelo.

Cierra la sesión y vuelve a entrar (para el grupo `docker`), y verifica:

```bash
docker run --rm hello-world
docker compose version
```

### 3. Abrir el puerto

```bash
sudo firewall-cmd --permanent --add-port=8080/tcp
sudo firewall-cmd --reload
```

Con otro puerto (`PUERTO=9000 ./desplegar.sh`) abre ese. SELinux no requiere cambios: el compose usa
el volumen `datos`, no carpetas del sistema.

### 4. Descargar el proyecto

```bash
sudo mkdir -p /opt/descarga-sat
sudo chown $USER: /opt/descarga-sat
git clone https://github.com/Gusseppe23/descargasat_v2.git /opt/descarga-sat
cd /opt/descarga-sat
```

### 5. Crear `.env.production` y la llave

Crea `.env.production` con el contenido de "Primera vez", paso 2, poniendo la IP de la VM en
`APP_URL` (por ejemplo `APP_URL=http://192.168.1.50:8080`). Luego:

```bash
chmod 600 .env.production
docker compose -f compose.produccion.yaml build
KEY=$(docker compose -f compose.produccion.yaml run --rm -T --no-deps --user www-data web \
  php artisan key:generate --show | tr -d '\r')
sed -i "s|^APP_KEY=.*|APP_KEY=${KEY}|" .env.production
grep ^APP_KEY .env.production   # debe mostrar APP_KEY=base64:...
```

Guarda una copia de `.env.production` fuera de la VM (sin `APP_KEY` la FIEL no se puede abrir).

### 6. Desplegar y crear el usuario

```bash
./desplegar.sh
```

Abre `http://<ip-de-la-vm>:8080/register`, crea tu usuario y confirma que `/register` ya responde
404. Revisa que todo corre con "Revisar que todo corre" (arriba), incluido el reinicio de la VM.

### 7. Respaldo diario automático

Detiene la app unos segundos para copiar la base SQLite en estado consistente y conserva 14 copias.

```bash
sudo mkdir -p /var/backups/descarga-sat
sudo tee /usr/local/bin/respaldo-descarga-sat.sh > /dev/null <<'SCRIPT'
#!/usr/bin/env bash
set -euo pipefail
cd /opt/descarga-sat
destino=/var/backups/descarga-sat
compose=(docker compose -f compose.produccion.yaml)

"${compose[@]}" stop
docker run --rm -v descarga-sat_datos:/datos:ro -v "$destino":/respaldo alpine \
  tar czf "/respaldo/datos-$(date +%F).tgz" -C /datos .
cp .env.production "$destino/env.production-$(date +%F)"
"${compose[@]}" start

ls -1t "$destino"/datos-*.tgz | tail -n +15 | xargs -r rm --
ls -1t "$destino"/env.production-* | tail -n +15 | xargs -r rm --
SCRIPT
sudo chmod 700 /usr/local/bin/respaldo-descarga-sat.sh
echo "30 3 * * * root /usr/local/bin/respaldo-descarga-sat.sh" | sudo tee /etc/cron.d/descarga-sat
sudo /usr/local/bin/respaldo-descarga-sat.sh    # prueba manual
sudo ls -lh /var/backups/descarga-sat
```

Copia esos respaldos de vez en cuando a otro equipo (por ejemplo con `scp`).

### 8. Actualizar

```bash
cd /opt/descarga-sat
git pull
./desplegar.sh
```

### Problemas comunes

| Síntoma | Solución |
| --- | --- |
| `permission denied ... docker.sock` | Cierra sesión y vuelve a entrar después de `usermod` |
| No abre desde otra PC | Revisa el puerto (paso 3) y que la VM use adaptador puente |
| `Falta .env.production` | Ejecuta `./desplegar.sh` desde `/opt/descarga-sat` |
| La FIEL no abre tras reinstalar | Restaura la copia de `.env.production` con el `APP_KEY` original |

> La app viaja por HTTP sin cifrar en la red local: la contraseña de la FIEL pasa en texto claro.
> Aceptable en una red de confianza; si la comparten otras personas, pon HTTPS con un proxy
> (Caddy o Nginx) delante del puerto 8080.
