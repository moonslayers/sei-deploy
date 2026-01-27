# Configuración de Backends para Actualización Automática

Esta guía explica cómo configurar los backends Laravel para usar el sistema de actualización automática de SEI Deploy.

## Descripción General

El sistema permite actualizar automáticamente backends Laravel mediante webhooks cuando se hace push a la rama `main` del repositorio. El proceso ejecuta:

1. **Git pull** del branch `main` con credenciales inline
2. **Limpieza de cache**:
   - `php artisan cache:clear`
   - `php artisan config:clear`
   - `php artisan route:clear`
3. **Regeneración de cache**:
   - `php artisan config:cache`
   - `php artisan route:cache`
4. **Migraciones**:
   - `php artisan migrate --force`

## Requisitos Previos

### En el Servidor (sei-deploy)

1. El servidor debe tener el repositorio del backend clonado
2. El backend debe ser un proyecto Laravel funcional
3. Debe tener las credenciales de GitHub configuradas (`GITHUB_USER` y `GITHUB_TOKEN`)
4. El backend debe estar listado en la variable `BACKEND_PROJECTS`

### En el Repositorio del Backend

1. Debe ser un proyecto Laravel
2. Debe tener migraciones funcionales (si usa el comando migrate)
3. Debe tener permisos de escritura en los directorios de cache y logs

## Configuración Paso a Paso

### Paso 1: Configurar el Servidor (sei-deploy)

#### 1.1 Editar el archivo `.env` de sei-deploy

```env
# Git and GitHub Configuration
GITHUB_USER=moonslayers
GITHUB_TOKEN=ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx

# Backends to update automatically via webhook
# Format: organization/backend-name (e.g., moonslayers/sagem-backend)
# Located at ../backend-directory-name (same level as sei-deploy)
BACKEND_PROJECTS=moonslayers/cef-backend,moonslayers/pmr-backend,moonslayers/sagem-backend,moonslayers/sidi-backend
```

**Notas importantes:**
- `BACKEND_PROJECTS` usa el formato completo: `organizacion/nombre-repo`
- El directorio físico del backend es solo el nombre del repo (sin organización)
- Ejemplo: `moonslayers/sagem-backend` → directorio `../sagem-backend`

#### 1.2 Verificar la estructura de directorios

```
/projects/
├── sei-deploy/           # Sistema de despliegue
├── cef-backend/          # Backend 1
│   ├── .env
│   ├── app/
│   ├── database/
│   ├── public/
│   └── ...
├── pmr-backend/         # Backend 2
├── sagem-backend/        # Backend 3
└── sidi-backend/        # Backend 4
```

Asegúrate de que cada directorio de backend:
- Es un proyecto Laravel válido
- Tiene su archivo `.env` configurado
- Tiene permisos de escritura en `storage/` y `bootstrap/cache/`

### Paso 2: Configurar el Repositorio del Backend

#### 2.1 Generar o obtener llaves RSA

Si no tienes llaves RSA generadas para SEI Deploy, ve a [Generación de Llaves RSA](./generacion-de-llaves.md).

Si ya tienes las llaves configuradas en sei-deploy, puedes usar las mismas llaves.

#### 2.2 Codificar la llave privada en Base64

```bash
# Linux/Mac
cat webhook_private.pem | base64 -w 0

# Windows (PowerShell)
[Convert]::ToBase64String([IO.File]::ReadAllBytes("webhook_private.pem"))
```

Copia el resultado (una larga cadena de caracteres).

#### 2.3 Agregar Secrets en GitHub

1. Ve al repositorio del backend en GitHub
2. Settings → Secrets and variables → Actions
3. Agrega un nuevo secret:
   - **Name**: `WEBHOOK_PRIVATE_KEY`
   - **Value**: (pega la llave privada codificada en base64)

Opcionalmente, también puedes agregar:
- **Name**: `DEPLOY_SERVER_URL`
- **Value**: `http://tu-servidor.com`
- Nota: Si no agregas este secret, puedes definir la URL directamente en el workflow.

### Paso 3: Crear el Workflow de GitHub Actions

#### 3.1 Copiar el template

Copia el archivo `docs/github-workflow-backend-universal.yml` de sei-deploy al directorio `.github/workflows/` de tu backend:

```bash
# En tu repositorio del backend
mkdir -p .github/workflows
cp ../sei-deploy/docs/github-workflow-backend-universal.yml .github/workflows/deploy.yml
```

#### 3.2 Configurar el workflow

Edita `.github/workflows/deploy.yml` y ajusta las variables:

```yaml
env:
  DEPLOY_SERVER_URL: http://tu-servidor.com  # URL de sei-deploy
  BACKEND_NAME: moonslayers/sagem-backend    # Formato: organizacion/repo-name
```

**Importante:**
- `BACKEND_NAME` debe coincidir exactamente con el valor en `BACKEND_PROJECTS` del .env de sei-deploy
- Si el backend es `moonslayers/sagem-backend`, el workflow debe usar `moonslayers/sagem-backend`
- El directorio físico debe ser `../sagem-backend`

#### 3.3 Verificar el workflow

El workflow debe verse así:

```yaml
name: Update Backend (Universal)

on:
  push:
    branches:
      - main
  workflow_dispatch:

env:
  DEPLOY_SERVER_URL: http://deploy-server.com
  BACKEND_NAME: moonslayers/sagem-backend

jobs:
  update_backend:
    runs-on: ubuntu-latest

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Prepare Webhook Payload
        id: prepare
        run: |
          TIMESTAMP=$(date +%s)
          cat > payload.json << EOF
          {
            "timestamp": ${TIMESTAMP}
          }
          EOF

      - name: Sign Payload (usando OpenSSL)
        id: sign
        env:
          PRIVATE_KEY: ${{ secrets.WEBHOOK_PRIVATE_KEY }}
        run: |
          echo "${PRIVATE_KEY}" | base64 -d > private_key.pem
          PAYLOAD=$(cat payload.json)
          SIGNATURE=$(echo -n "${PAYLOAD}" | \
            openssl dgst -sha256 -sign private_key.pem | \
            base64 -w 0)
          echo "signature=${SIGNATURE}" >> $GITHUB_OUTPUT
          rm private_key.pem

      - name: Send Webhook to Deploy Server
        run: |
          PAYLOAD=$(cat payload.json)
          SIGNATURE="${{ steps.sign.outputs.signature }}"
          
          RESPONSE=$(curl -X POST "${{ env.DEPLOY_SERVER_URL }}/api/webhook/${{ env.BACKEND_NAME }}" \
            -H "Content-Type: application/json" \
            -H "X-Webhook-Signature: ${SIGNATURE}" \
            -d "${PAYLOAD}" \
            -w "\n%{http_code}" \
            --silent --show-error)

          HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
          BODY=$(echo "$RESPONSE" | sed '$d')

          echo "Response: ${BODY}"
          echo "HTTP Status: ${HTTP_CODE}"

          if [ "$HTTP_CODE" != "200" ]; then
            echo "❌ Webhook failed with status ${HTTP_CODE}"
            exit 1
          fi

          echo "✅ Backend updated successfully"
```

### Paso 4: Hacer Push y Probar

#### 4.1 Commit y push del workflow

```bash
git add .github/workflows/deploy.yml
git commit -m "Add automatic deployment workflow"
git push origin main
```

#### 4.2 Verificar la ejecución

Ve al repositorio en GitHub:
1. Tab "Actions"
2. Deberías ver el workflow "Update Backend (Universal)" ejecutándose
3. Haz clic para ver el progreso
4. Verifica que todos los pasos se completen exitosamente

#### 3.3 Probar manualmente (opcional)

Puedes ejecutar el workflow manualmente:

1. Ve a Actions → Update Backend (Universal)
2. Haz clic en "Run workflow"
3. Selecciona la rama `main`
4. Haz clic en "Run workflow"

## Ejemplo Completo: Configuración de sagem-backend

Supongamos que queremos configurar el backend `sagem-backend`.

### En el servidor (sei-deploy)

1. Editar `.env`:
```env
BACKEND_PROJECTS=moonslayers/cef-backend,moonslayers/pmr-backend,moonslayers/sagem-backend,moonslayers/sidi-backend
```

2. Verificar directorio:
```bash
ls -la ../sagem-backend
# Debe ver: app/, bootstrap/, config/, database/, public/, etc.
```

### En el repositorio sagem-backend

1. Generar/agregar secret `WEBHOOK_PRIVATE_KEY` en GitHub

2. Crear `.github/workflows/deploy.yml`:
```yaml
env:
  DEPLOY_SERVER_URL: http://deploy.example.com
  BACKEND_NAME: moonslayers/sagem-backend
```

3. Commit y push:
```bash
git add .github/workflows/deploy.yml
git commit -m "Add automatic deployment workflow"
git push origin main
```

4. Verificar en Actions que se ejecutó correctamente

## Verificación del Despliegue

### Verificar Logs en el Servidor

Para ver los logs de Laravel en el servidor:

```bash
# En el servidor
tail -f storage/logs/laravel.log

# Buscar logs específicos del backend
grep "sagem-backend" storage/logs/laravel.log
```

Deberías ver logs como:
```
[2025-01-27 10:30:00] production.INFO: Webhook received: update backend {"repository":"moonslayers/sagem-backend","timestamp":"2025-01-27T10:30:00+00:00"}
[2025-01-27 10:30:01] production.INFO: Starting git pull for backend moonslayers/sagem-backend
[2025-01-27 10:30:05] production.INFO: Git pull completed for backend moonslayers/sagem-backend
[2025-01-27 10:30:05] production.INFO: Executing artisan commands for backend {"path":"/path/to/sagem-backend"}
[2025-01-27 10:30:06] production.INFO: Command executed successfully {"command_key":"cache_clear","command":"php artisan cache:clear"}
[2025-01-27 10:30:06] production.INFO: Command executed successfully {"command_key":"config_clear","command":"php artisan config:clear"}
[2025-01-27 10:30:06] production.INFO: Command executed successfully {"command_key":"route_clear","command":"php artisan route:clear"}
[2025-01-27 10:30:07] production.INFO: Command executed successfully {"command_key":"config_cache","command":"php artisan config:cache"}
[2025-01-27 10:30:07] production.INFO: Command executed successfully {"command_key":"route_cache","command":"php artisan route:cache"}
[2025-01-27 10:30:08] production.INFO: Command executed successfully {"command_key":"migrate","command":"php artisan migrate --force"}
[2025-01-27 10:30:08] production.INFO: All artisan commands completed successfully
[2025-01-27 10:30:08] production.INFO: Backend update completed successfully for moonslayers/sagem-backend
```

### Verificar el Estado de Git

```bash
cd ../sagem-backend
git status
git log -1
```

### Verificar Migraciones

```bash
cd ../sagem-backend
php artisan migrate:status
```

## Troubleshooting Común

### Error 400: "Backend no configurado"

**Error:**
```json
{
  "status": "error",
  "message": "Invalid backend repository",
  "error": "Backend 'sagem-backend' is not in the configured list",
  "configured_backends": ["moonslayers/cef-backend", "moonslayers/pmr-backend", "moonslayers/sagem-backend", "moonslayers/sidi-backend"]
}
```

**Causa:** El backend no está en la lista `BACKEND_PROJECTS` del .env.

**Solución:**
1. Verifica que el backend esté en `BACKEND_PROJECTS` en el formato correcto: `moonslayers/sagem-backend`
2. Recarga la configuración: `php artisan config:clear`
3. Reinicia el servidor si es necesario

### Error 400: "Backend directory not found"

**Error:**
```json
{
  "status": "error",
  "message": "Backend directory not found",
  "error": "Directory '/path/to/sagem-backend' does not exist"
}
```

**Causa:** El directorio del backend no existe en la ubicación esperada.

**Solución:**
1. Verifica que el directorio exista: `ls -la ../sagem-backend`
2. El nombre del directorio debe ser solo el nombre del repo (sin organización)
3. El directorio debe estar al mismo nivel que `sei-deploy`

### Error 500: "Git pull failed"

**Error:**
```json
{
  "status": "error",
  "message": "Failed to update backend",
  "error": "Git pull failed for moonslayers/sagem-backend: remote: Not Found\nfatal: repository 'https://github.com/moonslayers/sagem-backend.git' not found",
  "results": []
}
```

**Causa:** El formato del repositorio es incorrecto o el repo no existe.

**Solución:**
1. Verifica el formato en `BACKEND_PROJECTS`: debe ser `moonslayers/sagem-backend`
2. Verifica que el repo exista en GitHub
3. Verifica que `GITHUB_USER` y `GITHUB_TOKEN` estén configurados correctamente
4. Asegúrate de que el token tenga permisos de lectura del repo

### Error 500: "Command failed: php artisan migrate --force"

**Error:**
```json
{
  "status": "error",
  "message": "Failed to update backend",
  "error": "Command failed: php artisan migrate --force. Error: SQLSTATE[HY000] [2002] Connection refused",
  "results": {
    "git_pull": {"status": "success", "output": [...]},
    "artisan_commands": {
      "cache_clear": {"status": "success", "output": [...]},
      "config_clear": {"status": "success", "output": [...]},
      "route_clear": {"status": "success", "output": [...]},
      "config_cache": {"status": "success", "output": [...]},
      "route_cache": {"status": "success", "output": [...]}
    }
  }
}
```

**Causa:** Un comando artisan falló.

**Solución:**
1. Revisa los logs del servidor: `tail -f storage/logs/laravel.log`
2. Verifica que el .env del backend esté configurado correctamente
3. Asegúrate de que la base de datos esté accesible
4. Verifica permisos en `storage/` y `bootstrap/cache/`
5. Prueba ejecutar el comando manualmente: `cd ../sagem-backend && php artisan migrate --force`

### Error 401: "Invalid signature"

**Error:**
```json
{
  "status": "error",
  "message": "Invalid signature",
  "error": "Could not verify webhook signature"
}
```

**Causa:** La firma del webhook no coincide con la llave pública configurada.

**Solución:**
1. Verifica que la llave privada en GitHub coincida con la llave pública en el .env de sei-deploy
2. Asegúrate de que la llave privada esté codificada en base64 en el secret de GitHub
3. Verifica que la llave pública esté codificada en base64 en el .env de sei-deploy
4. Prueba con el script de verificación de llaves

## Security Considerations

### Llaves Privadas

- ❌ **Nunca** commits la llave privada a Git
- ❌ **Nunca** compartas la llave privada por email o chat
- ❌ **Nunca** uses llaves privadas de otros proyectos

- ✅ Agrega `*.pem` y `*.key` a `.gitignore`
- ✅ Usa secrets de GitHub para la llave privada
- ✅ Rotación de llaves periódicamente
- ✅ Mantén backups seguros de las llaves privadas

### Permisos en el Servidor

Asegúrate de que el usuario del servidor tenga permisos adecuados:

```bash
# Permisos en storage/
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/

# Propietario correcto (ejemplo: www-data)
chown -R www-data:www-data storage/
chown -R www-data:www-data bootstrap/cache/
```

### Variables de Entorno

- ❌ **Nunca** commits el archivo `.env` de producción
- ✅ Usa `.env.example` como plantilla
- ✅ Asegúrate de que `GITHUB_TOKEN` no tenga permisos excesivos
- ✅ Usa tokens de GitHub con permisos mínimos necesarios

## Referencias

- [Manual de Uso de SEI Deploy](./manual-de-uso.md)
- [Generación de Llaves RSA](./generacion-de-llaves.md)
- [Ejemplos de Clientes](./ejemplos-clientes.md)
- [WEBHOOK_USAGE.md](../WEBHOOK_USAGE.md) - Documentación técnica en inglés
