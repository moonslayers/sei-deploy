# Documentación - SEI Deploy

Esta carpeta contiene toda la documentación necesaria para integrar y usar el sistema SEI Deploy.

## 📚 Archivos de Documentación

### [manual-de-uso.md](./manual-de-uso.md)
Manual completo del sistema. Contiene:
- Descripción del sistema
- APIs disponibles y endpoints
- Casos de uso de cada API
- Ejemplos de requests y responses
- Códigos de error
- Solución de problemas

### [generacion-de-llaves.md](./generacion-de-llaves.md)
Guía detallada para generar y configurar llaves RSA:
- Cómo generar llaves RSA-2048
- Configuración en el servidor
- Configuración en GitHub Actions
- Secrets que debes configurar
- Rotación de llaves
- Mejores prácticas de seguridad

### [github-workflow-template.yml](./github-workflow-template.yml)
Plantilla de GitHub Actions para desplegar cambios:
- Ejemplo de workflow para el repositorio principal
- Usa `/api/webhook/deploy`
- Incluye firma RSA del payload
- Verificación del estado de despliegue

### [github-workflow-submodule-template.yml](./github-workflow-submodule-template.yml)
Plantilla de GitHub Actions para actualizar submódulos (PHP):
- Ejemplo de workflow para proyectos que son submódulos
- Usa `/api/webhook/submodule`
- Incluye pasos opcionales de tests y compilación
- Verificación del estado del submódulo

### [github-workflow-submodule-nodejs.yml](./github-workflow-submodule-nodejs.yml)
Plantilla para proyectos Node.js que son submódulos:
- Usa Node.js nativo para firmar (sin PHP extra)
- Ideal para proyectos JavaScript/TypeScript
- Más rápido y sin dependencias extra

### [github-workflow-submodule-python.yml](./github-workflow-submodule-python.yml)
Plantilla para proyectos Python que son submódulos:
- Usa Python nativo para firmar (sin PHP extra)
- Ideal para proyectos Python/Django/FastAPI
- Usa la librería `cryptography` (incluida en muchas distros)

### [github-workflow-submodule-universal.yml](./github-workflow-submodule-universal.yml)
Plantilla universal para cualquier proyecto:
- Usa bash + openssl (funciona con cualquier lenguaje)
- No requiere PHP, Node.js ni Python
- Ideal para Go, Rust, Ruby, o cualquier otro lenguaje

## 🚀 Guía Rápida de Inicio

### 1. Si eres el administrador del servidor:

1. Lee **[manual-de-uso.md](./manual-de-uso.md)** para entender el sistema
2. Sigue **[generacion-de-llaves.md](./generacion-de-llaves.md)** para crear las llaves RSA
3. Configura la llave pública en `.env`
4. Comparte la llave privada (codificada en base64) con los equipos de proyectos

### 2. Si tienes un proyecto que necesita desplegarse:

1. Lee **[manual-de-uso.md](./manual-de-uso.md)** para entender las APIs
2. Solicita la llave privada al administrador del servidor
3. Revisa las plantillas de workflow:
   - **[github-workflow-template.yml](./github-workflow-template.yml)** si tu proyecto es el principal
   - **[github-workflow-submodule-template.yml](./github-workflow-submodule-template.yml)** si tu proyecto es un submódulo
4. Configura los secrets en tu repositorio de GitHub

## 📋 Diagrama de Flujo

```
┌─────────────────┐
│  Desarrollador  │
│   hace push     │
└────────┬────────┘
         │
         ▼
┌─────────────────────────┐
│  GitHub Actions CI/CD  │
│  (usando workflow)     │
└────────┬────────────────┘
         │
         ▼
┌─────────────────────────┐
│  Firmar Payload con    │
│  Llave Privada RSA     │
└────────┬────────────────┘
         │
         ▼
┌─────────────────────────┐
│  POST Webhook a        │
│  SEI Deploy Server     │
└────────┬────────────────┘
         │
         ▼
┌─────────────────────────┐
│  Verificar Firma con   │
│  Llave Pública RSA     │
└────────┬────────────────┘
         │
         ▼
┌─────────────────────────┐
│  Ejecutar Git Pull     │
│  y Update Submodule    │
└─────────────────────────┘
```

## 🔐 Seguridad

- **Llave Pública**: Configurada en el servidor (`PUBLIC_KEY` en `.env`)
- **Llave Privada**: Configurada en cada proyecto como secret de GitHub
- **Firma**: RSA-2048 + SHA256
- **Validación**: Timestamp con ventana de 5 minutos

## 📞 Soporte

Para preguntas o problemas:
1. Revisa el **[manual-de-uso.md](./manual-de-uso.md)** - sección de Solución de Problemas
2. Revisa el **[generacion-de-llaves.md](./generacion-de-llaves.md)** - sección de Troubleshooting
3. Consulta los tests en `tests/Feature/` para ejemplos de uso

## 📖 Adicional

- Para ejemplos de implementación en diferentes lenguajes, ver el **[manual-de-uso.md](./manual-de-uso.md)** - sección de Ejemplos de Implementación
- Para entender mejor el flujo de webhooks, revisa el archivo `WEBHOOK_USAGE.md` en la raíz del proyecto
