# Manual de Uso - SEI Deploy

## Descripción

SEI Deploy es un sistema de despliegue automático basado en webhooks para Laravel. Permite actualizar automáticamente repositorios y submódulos mediante webhooks firmados con RSA.

## 💡 Nota sobre las Plantillas de GitHub Actions

Las plantillas de workflows vienen en diferentes variantes según el lenguaje de tu proyecto:

| Lenguaje | Plantilla Recomendada | Comentario |
|----------|---------------------|-----------|
| **Cualquiera** | `github-workflow-submodule-universal.yml` | Usa bash + openssl. Funciona para cualquier proyecto. **Más sencillo y sin dependencias extra** ⭐ |
| Node.js | `github-workflow-submodule-nodejs.yml` | Usa Node.js nativo para firmar. Ideal para proyectos JS/TS |
| Python | `github-workflow-submodule-python.yml` | Usa Python nativo para firmar. Ideal para proyectos Python |
| PHP | `github-workflow-submodule-template.yml` | Usa PHP para firmar. Solo si tu proyecto ya es PHP |

**¿Por qué hay diferentes versiones?** Porque cada proyecto usa el lenguaje que ya tiene instalado, evitando añadir dependencias innecesarias (como instalar PHP solo para firmar).

## APIs Disponibles

### 1. POST `/api/webhook/submodule`

Actualiza el submódulo `sei-compilados` del repositorio y opcionalmente despliega archivos de frontend a `/var/www/{project}`.

**Casos de uso:**
- Después de compilar cambios en el repositorio `sei-compilados`
- Cuando necesitas actualizar solo el submódulo sin afectar el repositorio principal
- En proyectos donde el submódulo contiene código compilado o artefactos
- **Despliegue automático de frontend**: Después del git pull, si se especifica el parámetro `project`, los archivos se copian automáticamente de `sei-compilados/{project}` a `/var/www/{project}`

**Nota importante sobre el despliegue:**
- El parámetro `project` es **opcional**
- Si se especifica: después de un git pull exitoso, el contenido de `/var/www/{project}/*` se borra y se reemplaza con `sei-compilados/{project}/*`
- El directorio `/var/www/{project}` **DEBE existir previamente** - el sistema NO lo creará
- Ejemplos de proyectos: `pmr-frontend`, `sagem-frontend`, `sidi-frontend`

**Request (sin despliegue):**
```bash
curl -X POST http://tu-servicio.com/api/webhook/submodule \
  -H "Content-Type: application/json" \
  -H "X-Webhook-Signature: <firma_base64>" \
  -d '{
    "repository": "sei-compilados",
    "branch": "main",
    "timestamp": 1737465600
  }'
```

**Request (con despliegue automático):**
```bash
curl -X POST http://tu-servicio.com/api/webhook/submodule \
  -H "Content-Type: application/json" \
  -H "X-Webhook-Signature: <firma_base64>" \
  -d '{
    "repository": "sei-compilados",
    "branch": "main",
    "project": "pmr-frontend",
    "timestamp": 1737465600
  }'
```

**Response exitoso (200) - Sin despliegue:**
```json
{
  "status": "success",
  "message": "Submodule updated successfully",
  "data": {
    "repository": "sei-compilados",
    "branch": "main",
    "output": ["Submodule updated successfully"]
  }
}
```

**Response exitoso (200) - Con despliegue:**
```json
{
  "status": "success",
  "message": "Submodule updated and deployed successfully",
  "data": {
    "repository": "sei-compilados",
    "branch": "main",
    "project": "pmr-frontend",
    "git_output": ["Submodule updated successfully"],
    "deploy_output": {
      "deployment": "success",
      "project": "pmr-frontend",
      "source": "/path/to/sei-compilados/pmr-frontend",
      "destination": "/var/www/pmr-frontend"
    }
  }
}
```

**Response error (400):**
```json
{
  "error": "Invalid repository",
  "message": "Only sei-compilados repository is supported"
}
```

**Response error (401):**
```json
{
  "error": "Invalid signature",
  "message": "Could not verify webhook signature"
}
```

**Response error (400) - Carpeta de proyecto no encontrada:**
```json
{
  "error": "Project folder not found",
  "message": "Folder 'pmr-frontend' does not exist in sei-compilados"
}
```

**Response error (400) - Carpeta de destino no encontrada:**
```json
{
  "error": "Destination folder not found",
  "message": "Folder 'pmr-frontend' does not exist in /var/www"
}
```

### 2. POST `/api/webhook/deploy`

Actualiza el repositorio principal y el submódulo.

**Nota importante:** Este endpoint NO incluye despliegue de frontend a `/var/www`. Para desplegar un proyecto específico después de actualizar el submódulo, usa el endpoint `/api/webhook/submodule` con el parámetro `project`.

**Casos de uso:**
- Después de hacer merge a la rama `main`
- Para desplegar cambios completos (principal + submódulo)
- En el flujo principal de CI/CD del proyecto

**Request:**
```bash
curl -X POST http://tu-servicio.com/api/webhook/deploy \
  -H "Content-Type: application/json" \
  -H "X-Webhook-Signature: <firma_base64>" \
  -d '{
    "branch": "main",
    "timestamp": 1737465600
  }'
```

**Response exitoso (200):**
```json
{
  "status": "success",
  "message": "Deploy updated successfully",
  "data": {
    "branch": "main",
    "main_pull": ["Pull successful"],
    "submodule_update": ["Submodule updated successfully"]
  }
}
```

**Response error (400):**
```json
{
  "error": "Invalid branch",
  "message": "Only main branch is supported"
}
```

### 3. GET `/api/status`

Obtiene el estado actual del repositorio y submódulo.

**Casos de uso:**
- Monitoreo del estado de despliegue
- Verificación manual del estado
- Integración con dashboards de monitoreo

**Request:**
```bash
curl http://tu-servicio.com/api/status
```

**Response exitoso (200):**
```json
{
  "status": "success",
  "data": {
    "current_branch": "main",
    "git_status": ["M app/Http/Controllers/WebhookController.php"],
    "submodule_status": ["+1234567 sei-compilados (main)"]
  }
}
```

## Seguridad y Firmas

### Requisitos de la Firma

Todos los webhooks deben estar firmados usando RSA + SHA256. El proceso es:

1. **Crear el payload JSON** con los datos requeridos
2. **Codificar en JSON** sin escapar slashes (`JSON_UNESCAPED_SLASHES`)
3. **Firmar con la llave privada RSA** usando `OPENSSL_ALGO_SHA256`
4. **Codificar la firma en base64**
5. **Enviar la firma** en el header `X-Webhook-Signature`

### Validación del Payload

El payload debe incluir:
- `timestamp`: Unix timestamp actual (validado dentro de ventana de 5 minutos)
- Datos específicos según la API (`repository`, `branch`, etc.)

### Códigos de Error de Seguridad

| Código | Error | Descripción |
|-------|-------|-------------|
| 401 | `Empty payload` | El cuerpo de la solicitud está vacío |
| 401 | `Missing signature` | No se envió la firma en header o body |
| 401 | `Invalid signature` | La firma no coincide con la llave pública |
| 401 | `Invalid timestamp` | El timestamp es muy antiguo o futuro |
| 400 | `Invalid JSON` | El payload no es JSON válido |
| 400 | `Project folder not found` | La carpeta de proyecto especificada no existe en `sei-compilados` |
| 400 | `Destination folder not found` | La carpeta `/var/www/{project}` no existe en el servidor |

## Configuración del Servidor

### Variables de Entorno

```bash
# .env
PUBLIC_KEY=LS0tLS1CRUdJTiBQVUJMSUMgS0VZLS0tLS0K... (llave pública en base64)
GITHUB_USER=tu-usuario-github
GITHUB_TOKEN=ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
GITHUB_REPO_MAIN=usuario/sei-deploy          # Formato: usuario/repositorio
GITHUB_REPO_SUBMODULE=usuario/sei-compilados # Formato: usuario/repositorio
GIT_USER_NAME=Deploy Bot
GIT_USER_EMAIL=deploy@server.com
```

### Notas importantes sobre las variables de entorno

- **GITHUB_REPO_MAIN**: Nombre completo del repositorio principal en formato `usuario/repositorio` (ej: `moonslayers/sei-deploy`)
- **GITHUB_REPO_SUBMODULE**: Nombre completo del repositorio del submódulo en formato `usuario/repositorio` (ej: `moonslayers/sei-compilados`)
- **GIT_USER_NAME** y **GIT_USER_EMAIL**: Identidad de Git que se configurará automáticamente para las operaciones de Git del sistema. Valores por defecto: "Deploy Bot" / "deploy@server.com"
- **GITHUB_TOKEN**: Debe ser un Personal Access Token (PAT) de GitHub con permisos de lectura para los repositorios especificados

### Configuración de Git

El servidor necesita tener Git configurado con credenciales para poder hacer pull de repositorios privados. El sistema SEI Deploy configura automáticamente la identidad de Git (`user.name` y `user.email`) usando las variables `GIT_USER_NAME` y `GIT_USER_EMAIL` del archivo `.env`.

## Integración con Proyectos Externos

Ver los archivos:
- **Plantillas de GitHub Actions:**
  - [github-workflow-template.yml](./github-workflow-template.yml) - Repositorio principal (PHP)
  - [github-workflow-submodule-universal.yml](./github-workflow-submodule-universal.yml) - Universal (bash + openssl) ⭐ **Recomendado**
  - [github-workflow-submodule-nodejs.yml](./github-workflow-submodule-nodejs.yml) - Proyectos Node.js
  - [github-workflow-submodule-python.yml](./github-workflow-submodule-python.yml) - Proyectos Python
  - [github-workflow-submodule-template.yml](./github-workflow-submodule-template.yml) - Submódulos (PHP)
- [generacion-de-llaves.md](./generacion-de-llaves.md) - Guía para generar llaves

## Casos de Uso Recomendados

### Flujo de Desarrollo Típico

1. **Desarrollador** hace push a rama `feature/xxx`
2. **GitHub Actions** ejecuta tests y compilaciones
3. **Si es rama `main` del repo principal**: Dispara `POST /api/webhook/deploy`
4. **Si solo actualiza submódulo**: Dispara `POST /api/webhook/submodule`
5. **Si actualiza submódulo con despliegue**: Dispara `POST /api/webhook/submodule` con parámetro `project`
   - Ejemplo: `{"repository": "sei-compilados", "branch": "main", "project": "pmr-frontend"}`
   - El servidor actualiza el submódulo y copia archivos a `/var/www/pmr-frontend/`
6. **Servidor** actualiza el repositorio automáticamente

### Cuándo usar cada API

| API | Cuándo usarla | Qué actualiza |
|-----|--------------|---------------|
| `/api/webhook/deploy` | Merge a `main` del repo principal | Repositorio principal + submódulo |
| `/api/webhook/submodule` (sin `project`) | Cambios solo en submódulo | Solo el submódulo `sei-compilados` |
| `/api/webhook/submodule` (con `project`) | Después de compilar frontend | Submódulo + despliegue automático a `/var/www/{project}` |
| `/api/status` | Cualquier momento (GET) | Solo lectura del estado |

### Proyectos que Deben Hacer POST

1. **Proyecto Principal** (contiene el submódulo)
   - Hacer POST a `/api/webhook/deploy` después de merge a `main`

2. **Proyecto Compilados** (submódulo)
   - Hacer POST a `/api/webhook/submodule` después de completar compilaciones
   - **Opción A (sin despliegue)**: Solo actualizar el submódulo
     ```json
     {"repository": "sei-compilados", "branch": "main"}
     ```
   - **Opción B (con despliegue automático)**: Actualizar submódulo y desplegar a `/var/www`
     ```json
     {"repository": "sei-compilados", "branch": "main", "project": "pmr-frontend"}
     ```
   - Ejemplos de proyectos: `pmr-frontend`, `sagem-frontend`, `sidi-frontend`

3. **Proyectos Relacionados**
   - Solo si necesitan actualizar el despliegue de forma coordinada

## Ejemplos de Implementación

### PHP (usando cURL)

```php
<?php
$data = [
    'repository' => 'sei-compilados',
    'branch' => 'main',
    'timestamp' => time(),
];

$payload = json_encode($data, JSON_UNESCAPED_SLASHES);
openssl_sign($payload, $signature, $privateKey, OPENSSL_ALGO_SHA256);
$encodedSignature = base64_encode($signature);

$ch = curl_init('http://tu-servicio.com/api/webhook/submodule');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-Webhook-Signature: ' . $encodedSignature,
]);
$response = curl_exec($ch);
curl_close($ch);
```

### Python (usando requests)

```python
import requests
import json
from cryptography.hazmat.primitives import hashes
from cryptography.hazmat.primitives.asymmetric import padding

data = {
    'repository': 'sei-compilados',
    'branch': 'main',
    'timestamp': int(time.time()),
}

payload = json.dumps(data, separators=(',', ':'))
signature = private_key.sign(
    payload.encode(),
    padding.PSS(
        mgf=padding.MGF1(hashes.SHA256()),
        salt_length=padding.PSS.MAX_LENGTH
    ),
    hashes.SHA256()
)
encoded_signature = base64.b64encode(signature).decode()

response = requests.post(
    'http://tu-servicio.com/api/webhook/submodule',
    data=payload,
    headers={
        'Content-Type': 'application/json',
        'X-Webhook-Signature': encoded_signature
    }
)
```

## Pruebas

Las pruebas del sistema están en el directorio `tests/Feature/`:

```bash
php artisan test
```

## Solución de Problemas

### Error: "Invalid signature"
- Verificar que la llave privada del cliente coincide con la llave pública del servidor
- Asegurarse de que el payload JSON se codifique sin escapes (`JSON_UNESCAPED_SLASHES`)
- Verificar que se usa el algoritmo correcto (RSA + SHA256)

### Error: "Invalid timestamp"
- El timestamp debe estar dentro de 5 minutos del tiempo actual
- Verificar que el servidor y el cliente tienen sincronización de hora

### Error: "Public key not configured"
- Configurar la variable `PUBLIC_KEY` en `.env`
- La llave debe estar codificada en base64

## Preguntas Frecuentes (FAQ) - Errores Comunes de Despliegue

### Error: "refusing to merge unrelated histories"

**Problema:** Git rechaza fusionar dos repositorios sin historia común.

**Causa:** El submódulo fue clonado incorrectamente o desde un repositorio diferente, o se intenta fusionar dos repositorios que no tienen commits en común.

**Solución 1 (desde el servidor):**
```bash
cd /ruta/a/sei-deploy/sei-compilados
git reset --hard HEAD
git pull --allow-unrelated-histories https://github.com/usuario/repositorio.git main
```

**Solución 2 (reiniciar submódulo desde cero - más limpia):**
```bash
cd /ruta/a/sei-deploy
git submodule deinit -f sei-compilados
rm -rf .git/modules/sei-compilados
git submodule update --init --remote sei-compilados
```

**Solución 3 (clonar manualmente el submódulo):**
```bash
cd /ruta/a/sei-deploy
rm -rf sei-compilados
git clone https://github.com/usuario/repositorio.git sei-compilados
cd sei-compilados
git checkout main
```

**Nota:** El sistema SEI Deploy ya usa automáticamente el flag `--allow-unrelated-histories` en el método `updateSubmodule()` (línea 76 de `app/Services/GitService.php`), por lo que este error debería ser raro en operaciones normales. Si ocurre, usa una de las soluciones manuales anteriores.

---

### Error: "Committer identity unknown" / "Please tell me who you are"

**Problema:** Git no tiene configurado `user.name` y `user.email` para el usuario que está ejecutando las operaciones.

**Causa:** El usuario del servidor (ej: `www-data`) no tiene configuración de Git, por lo que Git no puede realizar operaciones que requieren información del committer.

**Solución automática:** SEI Deploy configura automáticamente estos valores desde el archivo `.env`:
- `GIT_USER_NAME=Deploy Bot`
- `GIT_USER_EMAIL=deploy@server.com`

El método `ensureGitIdentity()` en `app/Services/GitService.php` verifica si Git ya tiene configuración y, si no, la configura automáticamente usando el flag `--local` para evitar problemas de permisos.

**Solución manual (si necesitas valores específicos):**
```bash
# Configurar globalmente (requiere permisos de root)
sudo git config --global user.email "tu-email@ejemplo.com"
sudo git config --global user.name "Tu Nombre"

# O configurar solo para el repositorio actual (recomendado)
cd /ruta/a/sei-deploy
git config --local user.email "tu-email@ejemplo.com"
git config --local user.name "Tu Nombre"
```

---

### Error: "could not lock config file /var/www/.gitconfig: Permission denied"

**Problema:** El usuario `www-data` (u otro usuario del servidor) no tiene permisos para escribir en el archivo de configuración global de Git en `/var/www/.gitconfig`.

**Causa:** Intento de configurar Git globalmente usando `git config --global`, lo que requiere permisos de escritura en `/var/www/.gitconfig`.

**Solución:** SEI Deploy usa `--local` en lugar de `--global` para escribir en `.git/config` del proyecto, donde el usuario `www-data` sí tiene permisos.

**Implementación en el código:**
El método `ensureGitIdentity()` en `app/Services/GitService.php` usa comandos como:
```bash
git config --local user.email deploy@server.com
git config --local user.name "Deploy Bot"
```

En lugar de:
```bash
git config --global user.email deploy@server.com  # ❌ Esto causa el error
```

**Nota:** Esto ya está implementado en el código actual, no requiere acción manual. Si ves este error, asegúrate de tener la versión más reciente de `GitService.php`.

---

### Error: Webhook timeout (curl: (28) Failed to connect after X ms)

**Problema:** El webhook tarda demasiado en responder (más de 134 segundos en el ejemplo).

**Causas posibles:**
- Operaciones Git lentas (pull muy grande o red lenta)
- Problemas de red entre GitHub Actions y el servidor
- Despliegue de archivos muy grandes
- El servidor está bloqueado en una operación Git pendiente

**Soluciones:**

1. **Aumentar el timeout en el GitHub Actions workflow:**
   ```yaml
   - name: Send Webhook to SEI Server
     run: |
       PAYLOAD=$(cat payload.json)
       SIGNATURE="${{ steps.sign_payload.outputs.signature }}"
       
       curl -X POST "${{ env.SEI_SERVER_URL }}" \
         -H "Content-Type: application/json" \
         -H "X-Webhook-Signature: ${SIGNATURE}" \
         -d "${PAYLOAD}" \
         --max-time 300 \
         --silent --show-error --fail-with-body
   ```

2. **Verificar que el servidor no esté bloqueado:**
   ```bash
   # Revisar logs
   tail -f storage/logs/laravel.log
   
   # Monitoreo en tiempo real
   php artisan pail
   
   # Ver procesos de Git
   ps aux | grep git
   ```

3. **Verificar estado del servidor:**
   ```bash
   # Si usas nginx
   systemctl status nginx
   
   # Si usas Apache
   systemctl status apache2
   
   # Si usas php artisan serve
   # Asegúrate de que esté corriendo en el puerto correcto
   ```

4. **Probar conexión local:**
   ```bash
   curl http://localhost/api/webhook/submodule
   curl http://localhost/api/webhook/deploy
   ```

---

### Error: "Failed to connect to server" / "Couldn't connect to server"

**Problema:** El servidor no responde en el puerto especificado (puerto 80 o 443).

**Causas posibles:**
- El servidor web no está corriendo
- Firewall bloqueando el puerto
- URL incorrecta en el webhook
- Problemas de red o DNS

**Soluciones:**

1. **Verificar que el servidor web está corriendo:**
   ```bash
   # Si usas nginx
   sudo systemctl status nginx
   sudo systemctl start nginx
   
   # Si usas Apache
   sudo systemctl status apache2
   sudo systemctl start apache2
   
   # Si usas php artisan serve (solo desarrollo)
   php artisan serve --host=0.0.0.0 --port=8000
   ```

2. **Verificar que el puerto está abierto:**
   ```bash
   # Ver puertos en uso
   netstat -tlnp | grep :80
   netstat -tlnp | grep :443
   
   # O con ss
   ss -tlnp | grep :80
   ```

3. **Verificar firewall:**
   ```bash
   # Si usas ufw (Ubuntu/Debian)
   sudo ufw status
   sudo ufw allow 80/tcp
   sudo ufw allow 443/tcp
   
   # Si usas firewalld (CentOS/RHEL)
   sudo firewall-cmd --list-ports
   sudo firewall-cmd --add-port=80/tcp --permanent
   sudo firewall-cmd --add-port=443/tcp --permanent
   sudo firewall-cmd --reload
   ```

4. **Probar conexión desde el servidor localmente:**
   ```bash
   # Probar en localhost
   curl http://localhost/api/webhook/submodule
   
   # Probar desde otra máquina en la misma red
   curl http://IP_DEL_SERVIDOR/api/webhook/submodule
   
   # Probar DNS
   ping sei-apps.bajacalifornia.gob.mx
   ```

5. **Revisar logs del servidor web:**
   ```bash
   # Nginx
   sudo tail -f /var/log/nginx/error.log
   sudo tail -f /var/log/nginx/access.log
   
   # Apache
   sudo tail -f /var/log/apache2/error.log
   sudo tail -f /var/log/apache2/access.log
   ```

---

### Error: "fatal: refusing to merge unrelated histories" en submódulo después de actualizar

**Problema:** Similar al primer error, pero ocurre específicamente en el submódulo `sei-compilados` después de un pull.

**Causa:** El submódulo apunta a un repositorio diferente o fue reemplazado manualmente.

**Solución más robusta (recomendada):**
```bash
cd /ruta/a/sei-deploy

# Eliminar completamente el submódulo y su historial
git submodule deinit -f sei-compilados
git rm -f sei-compilados
rm -rf .git/modules/sei-compilados

# Volver a agregar el submódulo
git submodule add https://github.com/usuario/sei-compilados.git sei-compilados

# Configurar el submódulo para que siga la rama main
cd sei-compilados
git checkout main
cd ..

# Commit del cambio
git add .gitmodules sei-compilados
git commit -m "Fix submodule: reinitialized sei-compilados"
```

---

### Error: "fatal: repository 'https://github.com/usuario/repositorio.git/' not found"

**Problema:** Git no puede encontrar el repositorio especificado.

**Causas posibles:**
- El nombre del repositorio en `.env` está incorrecto
- El token de GitHub (`GITHUB_TOKEN`) no tiene permisos para el repositorio
- El repositorio es privado y el token no tiene acceso
- El usuario de GitHub (`GITHUB_USER`) está incorrecto

**Soluciones:**

1. **Verificar el formato del repositorio en `.env`:**
   ```bash
   # Correcto: usuario/repositorio
   GITHUB_REPO_MAIN=moonslayers/sei-deploy
   GITHUB_REPO_SUBMODULE=moonslayers/sei-compilados
   
   # Incorrecto: sin usuario o mal formateado
   GITHUB_REPO_MAIN=sei-deploy  # ❌ Falta el usuario
   GITHUB_REPO_MAIN=https://github.com/moonslayers/sei-deploy  # ❌ URL completa
   ```

2. **Verificar que el token tiene permisos:**
   - Ve a GitHub → Settings → Developer settings → Personal access tokens
   - Asegúrate de que el token tenga permisos de `repo` (read and write)
   - Si es un repositorio de una organización, asegúrate de que el token tenga acceso a la organización

3. **Probar el token manualmente:**
   ```bash
   curl -H "Authorization: token GHP_XXXXXXXXXX" \
     https://api.github.com/repos/moonslayers/sei-deploy
   ```
   Si esto funciona, el token es correcto. Si falla, verifica el token y los permisos.

---

### Error: "fatal: unable to access 'https://github.com/...': Failed to connect to github.com port 443"

**Problema:** El servidor no puede conectarse a GitHub.

**Causas posibles:**
- Proxy corporativo bloqueando la conexión
- Firewall bloqueando la conexión a github.com
- Problemas de DNS

**Soluciones:**

1. **Probar conexión a GitHub:**
   ```bash
   ping github.com
   curl -I https://github.com
   ```

2. **Configurar proxy (si es necesario):**
   ```bash
   git config --global http.proxy http://proxy.example.com:8080
   git config --global https.proxy https://proxy.example.com:8080
   ```

3. **O configurar proxy solo para este repositorio:**
   ```bash
   cd /ruta/a/sei-deploy
   git config --local http.proxy http://proxy.example.com:8080
   git config --local https.proxy https://proxy.example.com:8080
   ```

## Soporte

Para más información o problemas:
- Revisar la carpeta `tests/` para ejemplos de uso
- Consultar el archivo `WEBHOOK_USAGE.md` en la raíz del proyecto
