<#
.SYNOPSIS
  Construye y arranca el contenedor de desarrollo para descarga_sat (Laravel 13).

.DESCRIPTION
  Crea la imagen con PHP 8.5 (o 8.3+), Composer, el instalador de Laravel,
  Node.js, Git y GitHub CLI; verifica las versiones y deja el contenedor corriendo
  con la carpeta del proyecto montada en /app.

.EXAMPLE
  .\build.ps1
  .\build.ps1 -ProjectDir "D:\descarga_sat" -PhpVersion 8.4
  .\build.ps1 -WithClaudeCode -NoCache
#>
[CmdletBinding()]
param(
    # Carpeta del proyecto en Windows; se monta en /app dentro del contenedor
    [string]$ProjectDir = "D:\descarga_sat",

    [ValidateSet("8.5", "8.4", "8.3")]
    [string]$PhpVersion = "8.5",

    [ValidateSet(22, 24)]
    [int]$NodeMajor = 24,

    # Instala tambien Claude Code dentro del contenedor
    [switch]$WithClaudeCode,

    # Reconstruye sin usar capas en cache
    [switch]$NoCache
)

$ErrorActionPreference = "Stop"
Set-Location -Path $PSScriptRoot

function Paso($texto) { Write-Host "`n==> $texto" -ForegroundColor Cyan }

# 1. Docker disponible y corriendo
Paso "Verificando Docker"
if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw "No se encontro 'docker'. Instala Docker Desktop: https://www.docker.com/products/docker-desktop/"
}
docker info *> $null
if ($LASTEXITCODE -ne 0) {
    throw "Docker Desktop no esta corriendo. Abrelo, espera a que diga 'Engine running' y vuelve a ejecutar este script."
}
docker compose version *> $null
if ($LASTEXITCODE -ne 0) { throw "Falta 'docker compose' (v2). Actualiza Docker Desktop." }

# 2. Carpeta del proyecto
Paso "Preparando carpeta del proyecto: $ProjectDir"
if (-not (Test-Path -LiteralPath $ProjectDir)) {
    New-Item -ItemType Directory -Path $ProjectDir | Out-Null
    Write-Host "   Carpeta creada."
}
$ProjectDirResuelto = (Resolve-Path -LiteralPath $ProjectDir).Path
# Docker Compose prefiere diagonales normales en rutas de Windows (D:/descarga_sat)
$ProjectDirCompose = $ProjectDirResuelto -replace '\\', '/'

# 3. Variables para docker compose
$installClaude = if ($WithClaudeCode) { "true" } else { "false" }
@(
    "PROJECT_DIR=$ProjectDirCompose"
    "PHP_VERSION=$PhpVersion"
    "NODE_MAJOR=$NodeMajor"
    "INSTALL_CLAUDE_CODE=$installClaude"
) | Set-Content -Path (Join-Path $PSScriptRoot ".env") -Encoding ascii

# 4. Construir la imagen
Paso "Construyendo imagen laravel-dev:php$PhpVersion (la primera vez tarda varios minutos)"
$buildArgs = @("compose", "build")
if ($NoCache) { $buildArgs += "--no-cache" }
& docker @buildArgs
if ($LASTEXITCODE -ne 0) { throw "Fallo la construccion de la imagen." }

# 5. Verificar herramientas y extensiones
Paso "Verificando el entorno"
docker compose run --rm --no-deps dev verificar-entorno
if ($LASTEXITCODE -ne 0) { throw "La verificacion encontro componentes faltantes." }

# 6. Arrancar el contenedor en segundo plano
Paso "Arrancando el contenedor"
docker compose up -d
if ($LASTEXITCODE -ne 0) { throw "No se pudo arrancar el contenedor." }

Write-Host ""
Write-Host "Listo. Proyecto montado: $ProjectDirResuelto -> /app" -ForegroundColor Green
Write-Host ""
Write-Host "Siguientes pasos:"
Write-Host "  docker compose exec dev bash      # entrar al contenedor"
Write-Host "  gh auth login                     # una sola vez (queda guardado)"
Write-Host "  git config --global user.name  'Tu Nombre'"
Write-Host "  git config --global user.email 'tu@correo.com'"
Write-Host "  laravel new .                     # crear la app en /app (carpeta vacia)"
Write-Host ""
Write-Host "Detener: docker compose stop    |    Volver a entrar: docker compose up -d; docker compose exec dev bash"
