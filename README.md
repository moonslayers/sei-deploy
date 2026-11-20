# SEI Deploy

Sistema de despliegue automático basado en webhooks para Laravel, diseñado para gestionar actualizaciones de repositorios y submódulos mediante webhooks firmados con RSA.

## Documentación

La documentación completa se encuentra en el directorio `docs/`:

- 📖 [Manual de Uso](./docs/manual-de-uso.md) - Guía completa del sistema, APIs y casos de uso
- 🔧 [Configuración de Backends](./docs/configuracion-backends.md) - Guía para configurar actualización automática de backends Laravel
- 🔑 [Generación de Llaves RSA](./docs/generacion-de-llaves.md) - Cómo generar y configurar llaves RSA
- 📋 [README de Documentación](./docs/README.md) - Índice de toda la documentación
- 🔌 [Ejemplos de Clientes](./docs/ejemplos-clientes.md) - Implementaciones en múltiples lenguajes
- 🚀 [WEBHOOK_USAGE.md](./WEBHOOK_USAGE.md) - Documentación técnica de webhooks en inglés

## Preguntas Frecuentes y Troubleshooting

Para errores comunes de despliegue, consulta la sección de **[Preguntas Frecuentes (FAQ)](./docs/manual-de-uso.md#preguntas-frecuentes-faq-errores-comunes-de-despliegue)** en el manual de uso, donde encontrarás soluciones para:

- ✅ Error: "refusing to merge unrelated histories"
- ✅ Error: "Committer identity unknown"
- ✅ Error: "could not lock config file /var/www/.gitconfig: Permission denied"
- ✅ Error: Webhook timeout
- ✅ Error: "Failed to connect to server"
- ✅ Y más errores comunes...

## Características Principales

- 🔐 **Seguridad RSA**: Webhooks firmados con criptografía asimétrica (RSA-2048 + SHA256)
- 🔄 **Actualización Automática**: Pull de repositorios principales y submódulos
- 🚀 **Despliegue de Frontend**: Copia automática de archivos compilados a `/var/www/`
- 📊 **Monitoreo**: API de estado para verificar el estado del repositorio
- 🌐 **Multi-lenguaje**: Plantillas para GitHub Actions en PHP, Node.js, Python y Bash

## Inicio Rápido

1. **Configurar el servidor:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   # Configurar las variables de entorno (ver abajo)
   php artisan migrate
   php artisan serve
   ```

2. **Variables de entorno requeridas:**
    ```bash
    # Configuración de GitHub
    GITHUB_USER=your-github-username
    GITHUB_TOKEN=ghp_your-personal-access-token
    GITHUB_REPO_MAIN=username/sei-deploy          # Formato: usuario/repositorio
    GITHUB_REPO_SUBMODULE=username/sei-compilados # Formato: usuario/repositorio

    # Configuración de Git
    GIT_USER_NAME=Deploy Bot
    GIT_USER_EMAIL=deploy@server.com

    # Llave pública RSA (codificada en base64)
    PUBLIC_KEY=LS0tLS1CRUdJTiBQVUJMSUMgS0VZLS0tLS0K...
    ```

3. **Generar llaves RSA** (ver [generacion-de-llaves.md](./docs/generacion-de-llaves.md))

## APIs Disponibles

### POST `/api/webhook/submodule`
Actualiza el submódulo `sei-compilados` y opcionalmente despliega archivos de frontend.

### POST `/api/webhook/deploy`
Actualiza el repositorio principal y el submódulo.

### POST `/api/webhook/{repo}`
Actualiza automáticamente un backend Laravel específico. Ejecuta git pull, limpia y regenera cache, y ejecuta migraciones. Soporta: `cef-backend`, `pmr-backend`, `sagem-backend`, `sidi-backend`.

### GET `/api/status`
Obtiene el estado actual del repositorio y submódulo.

Para más detalles, consulta el [Manual de Uso](./docs/manual-de-uso.md) o la [Guía de Configuración de Backends](./docs/configuracion-backends.md).

## Desarrollo

```bash
# Instalar dependencias
composer install
npm install

# Ejecutar tests
php artisan test

# Verificar estilo de código
./vendor/bin/pint --test

# Correr servidor de desarrollo
composer dev
```

## Soporte

Para preguntas o problemas:
1. Revisa la sección de [Preguntas Frecuentes](./docs/manual-de-uso.md#preguntas-frecuentes-faq-errores-comunes-de-despliegue)
2. Consulta el [WEBHOOK_USAGE.md](./WEBHOOK_USAGE.md) para troubleshooting técnico
3. Revisa los tests en `tests/Feature/` para ejemplos de uso

---

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
