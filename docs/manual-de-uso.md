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

Actualiza el submódulo `sei-compilados` del repositorio.

**Casos de uso:**
- Después de compilar cambios en el repositorio `sei-compilados`
- Cuando necesitas actualizar solo el submódulo sin afectar el repositorio principal
- En proyectos donde el submódulo contiene código compilado o artefactos

**Request:**
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

**Response exitoso (200):**
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

### 2. POST `/api/webhook/deploy`

Actualiza el repositorio principal y el submódulo.

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

## Configuración del Servidor

### Variables de Entorno

```bash
# .env
PUBLIC_KEY=LS0tLS1CRUdJTiBQVUJMSUMgS0VZLS0tLS0K... (llave pública en base64)
GITHUB_USER=tu-usuario
GITHUB_TOKEN=ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

### Configuración de Git

El servidor necesita tener Git configurado con credenciales para poder hacer pull de repositorios privados.

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
3. **Si es rama `main`**: Dispara `POST /api/webhook/deploy`
4. **Si solo actualiza submódulo**: Dispara `POST /api/webhook/submodule`
5. **Servidor** actualiza el repositorio automáticamente

### Cuándo usar cada API

| API | Cuándo usarla | Qué actualiza |
|-----|--------------|---------------|
| `/api/webhook/deploy` | Merge a `main` del repo principal | Repositorio principal + submódulo |
| `/api/webhook/submodule` | Cambios solo en submódulo | Solo el submódulo `sei-compilados` |
| `/api/status` | Cualquier momento (GET) | Solo lectura del estado |

### Proyectos que Deben Hacer POST

1. **Proyecto Principal** (contiene el submódulo)
   - Hacer POST a `/api/webhook/deploy` después de merge a `main`

2. **Proyecto Compilados** (submódulo)
   - Hacer POST a `/api/webhook/submodule` después de completar compilaciones
   - Usar `/api/webhook/submodule` si hay cambios en artefactos compilados

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

## Soporte

Para más información o problemas:
- Revisar la carpeta `tests/` para ejemplos de uso
- Consultar el archivo `WEBHOOK_USAGE.md` en la raíz del proyecto
