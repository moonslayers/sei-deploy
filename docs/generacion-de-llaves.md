# Generación de Llaves RSA para Webhooks

Este documento explica cómo generar y configurar las llaves RSA para usar con las APIs de SEI Deploy.

## Conceptos Básicos

El sistema usa criptografía asimétrica (RSA):
- **Llave Privada**: Usada por el cliente para firmar webhooks (MANTENER SECRETA)
- **Llave Pública**: Usada por el servidor para verificar firmas (PUEDE SER PÚBLICA)

## Generación de Llaves

### Opción 1: Usar OpenSSL (Recomendado)

```bash
# Generar llave privada RSA de 2048 bits
openssl genrsa -out webhook_private.pem 2048

# Extraer llave pública de la llave privada
openssl rsa -in webhook_private.pem -pubout -out webhook_public.pem
```

### Opción 2: Generar con PHP

```php
<?php
$config = [
    "digest_alg" => "sha256",
    "private_key_bits" => 2048,
    "private_key_type" => OPENSSL_KEYTYPE_RSA,
];

$privateKey = openssl_pkey_new($config);
openssl_pkey_export_to_file($privateKey, 'webhook_private.pem');

$publicKey = openssl_pkey_get_details($privateKey)['key'];
file_put_contents('webhook_public.pem', $publicKey);
```

### Opción 3: Generar con Python

```python
from cryptography.hazmat.primitives.asymmetric import rsa
from cryptography.hazmat.primitives import serialization

# Generar llave privada
private_key = rsa.generate_private_key(
    public_exponent=65537,
    key_size=2048,
)

# Guardar llave privada
with open("webhook_private.pem", "wb") as f:
    f.write(private_key.private_bytes(
        encoding=serialization.Encoding.PEM,
        format=serialization.PrivateFormat.PKCS8,
        encryption_algorithm=serialization.NoEncryption()
    ))

# Extraer y guardar llave pública
public_key = private_key.public_key()
with open("webhook_public.pem", "wb") as f:
    f.write(public_key.public_bytes(
        encoding=serialization.Encoding.PEM,
        format=serialization.PublicFormat.SubjectPublicKeyInfo
    ))
```

## Configuración en el Servidor

### 1. Codificar la llave pública en Base64

```bash
# Linux/Mac
cat webhook_public.pem | base64 -w 0

# Windows (PowerShell)
[Convert]::ToBase64String([IO.File]::ReadAllBytes("webhook_public.pem"))
```

Este será el valor de la variable `PUBLIC_KEY`.

### 2. Configurar variable de entorno

Edita el archivo `.env` en el servidor:

```bash
PUBLIC_KEY=LS0tLS1CRUdJTiBQVUJMSUMgS0VZLS0tLS0K... (tu llave pública en base64)
```

### 3. Recargar configuración

```bash
# En desarrollo
php artisan config:clear

# En producción
# Reiniciar el servicio (depende de tu configuración)
# Ejemplo: sudo systemctl restart php-fpm
```

## Configuración en el Cliente

### 1. Para GitHub Actions

#### Paso 1: Codificar la llave privada en Base64

```bash
# Linux/Mac
cat webhook_private.pem | base64 -w 0

# Windows (PowerShell)
[Convert]::ToBase64String([IO.File]::ReadAllBytes("webhook_private.pem"))
```

#### Paso 2: Agregar Secret en GitHub

Ve a tu repositorio en GitHub:
1. Settings → Secrets and variables → Actions
2. New repository secret
3. Nombre: `WEBHOOK_PRIVATE_KEY`
4. Valor: (la llave privada codificada en base64)

#### Paso 3: Usar en el Workflow

En tu archivo `.github/workflows/deploy.yml`:

```yaml
- name: Sign Payload with RSA
  run: |
    # Decodificar llave privada del secret
    echo "${{ secrets.WEBHOOK_PRIVATE_KEY }}" | base64 -d > private_key.pem

    # Firmar payload
    PAYLOAD=$(cat payload.json)
    SIGNATURE=$(php -r "
      \$privateKey = file_get_contents('private_key.pem');
      openssl_sign('${PAYLOAD}', \$signature, \$privateKey, OPENSSL_ALGO_SHA256);
      echo base64_encode(\$signature);
    ")

    # Enviar webhook
    curl -X POST "${DEPLOY_SERVER_URL}/api/webhook/deploy" \
      -H "X-Webhook-Signature: ${SIGNATURE}" \
      -d "${PAYLOAD}"
```

### 2. Para otros clientes

La llave privada se puede usar directamente en tu código:

#### PHP
```php
$privateKey = file_get_contents('webhook_private.pem');
openssl_sign($payload, $signature, $privateKey, OPENSSL_ALGO_SHA256);
```

#### Python
```python
from cryptography.hazmat.primitives import serialization
with open("webhook_private.pem", "rb") as f:
    private_key = serialization.load_pem_private_key(f.read(), password=None)
```

#### Node.js
```javascript
const crypto = require('crypto');
const privateKey = fs.readFileSync('webhook_private.pem');
const signature = crypto.sign('sha256', payload, { key: privateKey });
```

## Secrets Recomendados por Proyecto

### Para el Servidor (SEI Deploy)

| Secret/Variable | Descripción | ¿Dónde configurarlo? |
|-----------------|-------------|----------------------|
| `PUBLIC_KEY` | Llave pública RSA en base64 | `.env` |
| `GITHUB_USER` | Usuario de GitHub | `.env` |
| `GITHUB_TOKEN` | Personal Access Token de GitHub | `.env` |

### Para Proyectos Cliente (usando GitHub Actions)

| Secret | Descripción | ¿Dónde configurarlo? |
|--------|-------------|----------------------|
| `WEBHOOK_PRIVATE_KEY` | Llave privada RSA en base64 | GitHub → Settings → Secrets |
| `DEPLOY_SERVER_URL` | URL del servidor de despliegue | GitHub → Settings → Secrets (o en el workflow) |

### Opcional: Para múltiples entornos

Si tienes múltiples entornos (dev, staging, production):

| Secret | Descripción | Ejemplo de valor |
|--------|-------------|------------------|
| `DEV_WEBHOOK_PRIVATE_KEY` | Llave para entorno dev | `LS0tLS1...` |
| `STAGING_WEBHOOK_PRIVATE_KEY` | Llave para staging | `LS0tLS1...` |
| `PROD_WEBHOOK_PRIVATE_KEY` | Llave para producción | `LS0tLS1...` |
| `DEV_DEPLOY_SERVER_URL` | URL del servidor dev | `http://dev.example.com` |
| `STAGING_DEPLOY_SERVER_URL` | URL del servidor staging | `http://staging.example.com` |
| `PROD_DEPLOY_SERVER_URL` | URL del servidor prod | `http://prod.example.com` |

## Verificación de Llaves

### Verificar que las llaves coinciden

```bash
# Crear un mensaje de prueba
MESSAGE="test message"

# Firmar con la llave privada
SIGNATURE=$(echo -n "$MESSAGE" | openssl dgst -sha256 -sign webhook_private.pem | base64 -w 0)

# Verificar con la llave pública
echo -n "$MESSAGE" | openssl dgst -sha256 -verify webhook_public.pem -signature <(echo "$SIGNATURE" | base64 -d)
```

Si las llaves coinciden, verás: `Verified OK`

## Rotación de Llaves

### Proceso para cambiar las llaves

1. **Generar nuevas llaves** (sigue los pasos anteriores)
2. **Actualizar el servidor**:
   - Codificar la nueva llave pública en base64
   - Actualizar `PUBLIC_KEY` en `.env` del servidor
   - Ejecutar `php artisan config:clear`
3. **Actualizar los clientes**:
   - Agregar la nueva llave privada como secret en GitHub
   - Actualizar los workflows para usar la nueva llave
4. **Verificar** haciendo un deploy de prueba

### Transición sin interrupciones (opcional)

Si necesitas cambiar llaves sin interrupciones:

```php
// En el servidor temporalmente soportar múltiples llaves
public function verifySignature($payload, $signature) {
    // Probar llave principal
    $result = $this->verifyWithKey($payload, $signature, env('PUBLIC_KEY'));

    // Si falla, probar llave antigua (opcional, por tiempo limitado)
    if (!$result && env('OLD_PUBLIC_KEY')) {
        $result = $this->verifyWithKey($payload, $signature, env('OLD_PUBLIC_KEY'));
    }

    return $result;
}
```

## Seguridad

### ❌ NO HACER

- ❌ Nunca commits la llave privada a Git
- ❌ Nunca compartas la llave privada por email/chat
- ❌ Nunca uses llaves RSA de menos de 2048 bits
- ❌ Nunca uses llaves en texto plano en el código

### ✅ HACER

- ✅ Agregar la llave privada a `.gitignore`
- ✅ Usar secrets de GitHub o variables de entorno
- ✅ Rotar las llaves periódicamente
- ✅ Usar al menos RSA-2048 (RSA-4096 es mejor)
- ✅ Mantener backups seguros de las llaves privadas
- ✅ Usar timestamps para prevenir ataques replay

### `.gitignore` recomendado

```
*.pem
*.key
webhook_private_key.pem
webhook_public_key.pem
```

## Troubleshooting

### Error: "Public key not configured"

- Verificar que `PUBLIC_KEY` está configurada en `.env`
- Verificar que la llave está codificada correctamente en base64
- Ejecutar `php artisan config:clear`

### Error: "Invalid signature"

- Verificar que la llave privada del cliente coincide con la llave pública del servidor
- Asegurarse de usar `OPENSSL_ALGO_SHA256`
- Verificar que el payload JSON se codifique sin escapes (`JSON_UNESCAPED_SLASHES`)

### Error: "Failed to load public key"

- Verificar que la llave pública está en formato PEM
- Asegurarse de no incluir saltos de línea extra al codificar en base64

## Recursos Adicionales

- [Documentación de OpenSSL](https://www.openssl.org/docs/)
- [Documentación de GitHub Secrets](https://docs.github.com/en/actions/security-guides/encrypted-secrets)
- [Documentación de PHP: openssl_sign](https://www.php.net/manual/en/function.openssl-sign.php)
