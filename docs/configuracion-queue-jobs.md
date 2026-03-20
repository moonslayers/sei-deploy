# 📄 Documentación: Configuración de Jobs en Laravel con Driver `database`

**Ubicación sugerida:** `docs/laravel-queues-database.md`  
**Última actualización:** Marzo 2026  
**Aplica para:** Todos los repositorios Laravel del proyecto que no utilicen Redis

---

## 🎯 Propósito

Esta guía documenta cómo configurar **Laravel Queues** utilizando el driver `database` para procesar tareas en segundo plano (ej: envío de emails, generación de reportes, sincronización de datos) sin depender de servicios externos como Redis.

### ✅ Beneficios

- No requiere instalar/configurar Redis o servicios adicionales
- Usa la misma conexión de base de datos existente del proyecto
- Reintentos automáticos con backoff exponencial
- Registro de jobs fallidos para diagnóstico
- Escalable mediante workers con Supervisor

### ⚠️ Consideraciones

- El driver `database` realiza polling a MySQL (mayor carga en BD vs Redis)
- Recomendado para cargas bajas/medias (< 100 jobs/minuto)
- Para alta concurrencia, evaluar migración a Redis en el futuro

---

## 📋 Requisitos Previos

| Requisito | Verificación |
|-----------|------------ -|
| Laravel 9.x o superior | `php artisan --version` |
| Conexión a BD funcional | `.env` con `DB_CONNECTION=mysql` |
| Permisos de escritura en `storage/` | `chmod -R 775 storage bootstrap/cache` |
| Acceso SSH al servidor de producción | Clave SSH o credenciales |
| Supervisor instalado (producción) | `supervisorctl --version` |

---

## 🚀 Guía de Implementación

### Paso 1: Configurar el driver de cola

Editar el archivo `.env` del proyecto:

```env
# ==========================================
# QUEUE CONFIGURATION
# ==========================================
QUEUE_CONNECTION=database

# Opcional: prefijo para colas por entorno
# QUEUE_PREFIX=prod_
```

> 📝 **Nota**: No modificar la configuración de correo (`MAIL_*`) ni otras variables existentes.

---

### Paso 2: Crear las tablas de cola

Ejecutar los siguientes comandos en la raíz del proyecto:

```bash
# Generar migraciones para las tablas de cola
php artisan queue:table
php artisan queue:failed-table

# Ejecutar migraciones
php artisan migrate
```

#### 🗄️ Estructura de tablas creadas

**Tabla `jobs`** (jobs pendientes/en proceso):

| Columna | Tipo | Descripción |
|---------|------|------------ -|
| `id` | BIGINT UNSIGNED | Primary Key |
| `queue` | VARCHAR | Nombre de la cola (ej: `emails`, `reports`) |
| `payload` | LONGTEXT | Datos serializados del job |
| `attempts` | TINYINT UNSIGNED | Intentos de ejecución |
| `reserved_at` | INT UNSIGNED NULL | Timestamp cuando se reservó |
| `available_at` | INT UNSIGNED | Timestamp cuando está disponible |
| `created_at` | INT UNSIGNED | Timestamp de creación |

**Tabla `failed_jobs`** (jobs que agotaron reintentos):

| Columna | Tipo | Descripción |
|---------|------|----------- --|
| `id` | BIGINT UNSIGNED | Primary Key |
| `uuid` | VARCHAR UNIQUE | Identificador único |
| `connection` | TEXT | Conexión de cola usada |
| `queue` | TEXT | Nombre de la cola |
| `payload` | LONGTEXT | Datos del job fallido |
| `exception` | LONGTEXT | Stack trace del error |
| `failed_at` | TIMESTAMP | Cuando falló |

---

### Paso 3: Crear un Job

#### 3.1 Generar la clase del Job

```bash
php artisan make:job NombreDelJob
```

#### 3.2 Estructura recomendada del Job

```php
<?php
// app/Jobs/NombreDelJob.php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class NombreDelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número máximo de intentos antes de marcar como fallido
     */
    public int $tries = 3;

    /**
     * Timeout en segundos para la ejecución del job
     */
    public int $timeout = 60;

    /**
     * Tiempo de espera entre reintentos (en segundos)
     * Backoff exponencial: 1min → 5min → 10min
     */
    public function backoff(): array
    {
        return [60, 300, 600];
    }

    /**
     * Datos que necesita el job (solo valores escalares o IDs)
     * ⚠️ NO serializar modelos completos con relaciones
     */
    public function __construct(
        public int $resourceId,
        public string $action,
        public array $metadata = []
    ) {}

    /**
     * Lógica principal del job
     */
    public function handle(): void
    {
        // Tu lógica aquí
        // Ej: enviar email, procesar archivo, llamar API externa
        
        // Ejemplo genérico:
        // $service = app(SomeService::class);
        // $service->process($this->resourceId, $this->action);
    }

    /**
     * Se ejecuta cuando el job falla después de todos los intentos
     */
    public function failed(?Throwable $exception): void
    {
        \Log::error("Job fallido: " . static::class, [
            'resource_id' => $this->resourceId ?? null,
            'action' => $this->action ?? null,
            'error' => $exception?->getMessage(),
            'trace' => $exception?->getTraceAsString(),
        ]);
        
        // Opcional: notificar al equipo de soporte
        // Notification::send(...);
    }
}
```

#### 📌 Buenas prácticas para Jobs

```php
// ✅ HACER:
// - Pasar solo IDs y datos escalares al constructor
public function __construct(public int $userId, public string $type) {}

// - Usar servicios inyectados desde el contenedor
public function handle(): void {
    $service = app(MyService::class);
    $service->execute($this->userId);
}

// - Manejar excepciones específicas cuando sea posible
public function handle(): void {
    try {
        // lógica
    } catch (ConnectionException $e) {
        $this->release(300); // Reintentar en 5 min
    }
}

// ❌ NO HACER:
// - Serializar modelos completos con relaciones cargadas
public function __construct(public User $user) {} // ❌ Puede crecer mucho el payload

// - Realizar lógica crítica sin manejo de errores
public function handle(): void {
    file_put_contents(...); // ❌ Sin try-catch
}
```

---

### Paso 4: Despachar el Job desde un Controller/Service

```php
<?php
// En tu Controller o Service

use App\Jobs\NombreDelJob;

public function store(Request $request)
{
    // 1. Ejecutar lógica síncrona crítica (ej: guardar en BD)
    $resource = Resource::create($request->validated());
    
    // 2. Despachar job para procesamiento asíncrono
    NombreDelJob::dispatch(
        resourceId: $resource->id,
        action: 'created',
        metadata: ['source' => 'api']
    )->onQueue('default'); // 👈 Especificar cola
    
    // 3. Retornar respuesta inmediata al cliente
    return response()->json([
        'status' => true,
        'message' => 'Recurso creado. Procesamiento en background.',
        'data' => $resource
    ]);
}
```

#### 🎯 Colas recomendadas por tipo de tarea

| Cola | Uso recomendado | Prioridad |
|------|----------------|---------- -|
| `emails` | Envío de notificaciones, correos | Alta |
| `reports` | Generación de PDFs, exportaciones | Media |
| `sync` | Sincronización con APIs externas | Baja |
| `default` | Tareas genéricas sin clasificación | Media |

```php
// Ejemplo: despachar a cola específica
NombreDelJob::dispatch(...)->onQueue('emails');

// Ejemplo: retrasar ejecución (ej: enviar email en 10 min)
NombreDelJob::dispatch(...)->delay(now()->addMinutes(10));

// Ejemplo: ejecutar solo en producción
if (app()->environment('production')) {
    NombreDelJob::dispatch(...);
}
```

---

### Paso 5: Configurar Supervisor (Producción)

Los workers de cola **no se ejecutan automáticamente**. Supervisor mantiene los procesos activos y los reinicia si fallan.

#### 5.1 Instalar Supervisor (Ubuntu/Debian)

```bash
sudo apt update
sudo apt install supervisor -y
```

#### 5.2 Crear configuración del worker

Archivo: `/etc/supervisor/conf.d/laravel-worker.conf`

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/nombre-proyecto/artisan queue:work database --sleep=3 --tries=3 --max-jobs=500 --max-time=3600 --memory=256 --queue=default,emails
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/nombre-proyecto/storage/logs/worker.log
stopwaitsecs=3600
```

#### 🔑 Explicación de parámetros críticos

| Parámetro | Valor recomendado | Propósito |
|-----------|------------------|---------- -|
| `--sleep=3` | `3` | Segundos de espera entre polls a la BD (reduce carga) |
| `--tries=3` | `3` | Reintentos por defecto si el job no define `$tries` |
| `--max-jobs=500` | `500` | Reinicia worker cada N jobs (previene memory leaks) |
| `--max-time=3600` | `3600` | Reinicia worker cada hora (seguridad adicional) |
| `--memory=256` | `256` | Mata el worker si supera MB de RAM |
| `--queue=default,emails` | `default,emails` | Colas que este worker procesará (orden = prioridad) |
| `numprocs=2` | `2-4` | Número de procesos worker en paralelo |

#### 5.3 Activar y gestionar Supervisor

```bash
# Recargar configuración después de crear/editar archivo .conf
sudo supervisorctl reread
sudo supervisorctl update

# Iniciar todos los workers
sudo supervisorctl start laravel-worker:*

# Ver estado de los procesos
sudo supervisorctl status

# Ver logs en tiempo real
tail -f /var/www/nombre-proyecto/storage/logs/worker.log

# Comandos útiles de gestión
sudo supervisorctl stop laravel-worker:*      # Detener todos
sudo supervisorctl restart laravel-worker:*   # Reiniciar todos
sudo supervisorctl tail -f laravel-worker:0   # Ver log de proceso específico
```

#### 5.4 Reiniciar workers después de cada deploy

Agregar al script de despliegue (después de `php artisan migrate`):

```bash
# Señalar a los workers que se reinicien limpiamente después del job actual
php artisan queue:restart

# Esperar a que los workers antiguos terminen (opcional pero recomendado)
sleep 10
```

> ⚠️ `queue:restart` **no mata procesos inmediatamente**. Envía una señal para que terminen el job actual y se reinicien con el nuevo código.

---

### Paso 6: Optimizaciones para driver `database`

#### 🔹 6.1 Indexar la tabla `jobs` para rendimiento

Crear migración opcional:

```bash
php artisan make:migration add_indexes_to_jobs_table --table=jobs
```

```php
// database/migrations/xxxx_xx_xx_add_indexes_to_jobs_table.php
public function up(): void
{
    Schema::table('jobs', function (Blueprint $table) {
        // Índice compuesto para consultas frecuentes de polling
        $table->index(['queue', 'reserved_at', 'available_at']);
        // Índice para filtrar por disponibilidad
        $table->index('available_at');
    });
}

public function down(): void
{
    Schema::table('jobs', function (Blueprint $table) {
        $table->dropIndex(['queue', 'reserved_at', 'available_at']);
        $table->dropIndex('available_at');
    });
}
```

#### 🔹 6.2 Limpieza automática de jobs procesados

Configurar tarea cron para evitar crecimiento infinito de la tabla `jobs`:

```bash
# Editar crontab del usuario www-data o del proyecto
crontab -e -u www-data
```

Agregar línea (ejecutar diariamente a las 2 AM):

```cron
0 2 * * * cd /var/www/nombre-proyecto && php artisan queue:prune --hours=24 >> /dev/null 2>&1
```

#### 🔹 6.3 Monitoreo básico de colas

```bash
# Contar jobs pendientes por cola
php artisan tinker
>>> DB::table('jobs')->where('queue', 'emails')->count();

# Ver jobs fallidos
php artisan queue:failed

# Ver detalles de un job fallido específico
php artisan queue:failed <ID>

# Reintentar un job fallido
php artisan queue:retry <ID>

# Reintentar todos los fallidos
php artisan queue:retry all

# Eliminar jobs fallidos antiguos (>48 horas)
php artisan queue:prune-failed --hours=48
```

---

### Paso 7: Testing Local

```bash
# 1. Configurar entorno local
# En .env.local:
QUEUE_CONNECTION=database

# 2. Ejecutar migraciones locales
php artisan migrate

# 3. Despachar job de prueba vía Tinker
php artisan tinker
>>> App\Jobs\NombreDelJob::dispatch(123, 'test', ['source' => 'tinker'])->onQueue('default');
>>> exit

# 4. Verificar que el job se guardó en la tabla
php artisan tinker
>>> DB::table('jobs')->count();  // Debería ser >= 1
>>> DB::table('jobs')->first();  // Inspeccionar payload
>>> exit

# 5. Ejecutar worker en modo "una sola vez" para procesar
php artisan queue:work --queue=default --stop-when-empty

# 6. Verificar resultados
# - Revisar logs: storage/logs/laravel.log
# - Confirmar que la tabla 'jobs' quedó vacía
# - Validar efecto secundario esperado (ej: email enviado)
```

---

## 🚨 Checklist de Producción

Antes de desplegar, verificar:

### ✅ Configuración del Proyecto

- [ ] `QUEUE_CONNECTION=database` en `.env` de producción
- [ ] Tablas `jobs` y `failed_jobs` migradas en BD de producción
- [ ] Índices agregados a `jobs` para rendimiento
- [ ] Permisos correctos en `storage/logs/` para escritura de logs del worker

### ✅ Configuración del Servidor

- [ ] Supervisor instalado y configurado con `laravel-worker.conf`
- [ ] Workers activos: `sudo supervisorctl status` muestra `RUNNING`
- [ ] Logs de worker accesibles y rotando (logrotate opcional)
- [ ] Usuario del worker (`www-data`) tiene permisos para ejecutar artisan

### ✅ Procesos de Despliegue

- [ ] `php artisan queue:restart` incluido en script de deploy
- [ ] Cron job configurado para `queue:prune --hours=24`
- [ ] Monitoreo básico implementado (ej: alerta si `jobs` > 100)

### ✅ Pruebas de Validación

- [ ] Job de prueba despachado y procesado exitosamente en staging
- [ ] Job fallido registrado en `failed_jobs` con stack trace legible
- [ ] Reintentos funcionando (verificar `attempts` incrementa)
- [ ] Backoff funcionando (tiempos entre reintentos crecientes)

---

## 🔧 Comandos de Referencia Rápida

```bash
# ===== GESTIÓN DE JOBS =====
php artisan queue:work --queue=emails --stop-when-empty    # Ejecutar worker manualmente
php artisan queue:restart                                    # Señalar reinicio a workers
php artisan queue:failed                                     # Listar jobs fallidos
php artisan queue:retry <ID>                                 # Reintentar job específico
php artisan queue:prune --hours=24                           # Limpiar jobs exitosos antiguos
php artisan queue:prune-failed --hours=48                    # Limpiar jobs fallidos antiguos

# ===== MONITOREO =====
sudo supervisorctl status                                    # Estado de workers
tail -f storage/logs/worker.log                              # Logs en tiempo real
mysql -u user -p -e "SELECT queue, COUNT(*) FROM jobs GROUP BY queue" db_name  # Jobs por cola

# ===== DEBUGGING =====
php artisan tinker                                           # Consola interactiva
>>> DB::table('jobs')->where('queue', 'emails')->get()       # Inspeccionar jobs en cola
>>> DB::table('failed_jobs')->latest()->first()              # Último job fallido
```

---

## 🔄 Flujo de Ejecución Típico

```mermaid
graph TD
    A[Usuario realiza acción] --> B[Controller/Service]
    B --> C{¿Requiere procesamiento asíncrono?}
    C -->|Sí| D[Dispatch Job → tabla 'jobs']
    C -->|No| E[Respuesta inmediata]
    D --> F[Respuesta JSON al usuario ✅]
    F --> G[Supervisor worker hace polling cada 3s]
    G --> H{¿Job disponible?}
    H -->|Sí| I[Reservar job + ejecutar handle()]
    H -->|No| G
    I --> J{¿Éxito?}
    J -->|Sí| K[Eliminar job de tabla 'jobs' ✅]
    J -->|No| L[attempts++ ]
    L --> M{attempts < $tries?}
    M -->|Sí| N[Esperar backoff + reintentar]
    N --> I
    M -->|No| O[Mover a 'failed_jobs' + log error]
```

---

## 💡 Tips Adicionales

### 1. Manejo de transacciones de base de datos

```php
// ✅ RECOMENDADO: Despachar DESPUÉS del commit de la transacción
DB::transaction(function () use ($data) {
    $resource = Resource::create($data);
    // ... más operaciones atómicas
});
// El job se despacha fuera de la transacción
NombreDelJob::dispatch($resource->id)->onQueue('default');

// ⚠️ ALTERNATIVA: Usar after_commit() si el job depende de datos recién guardados
NombreDelJob::dispatch($resource->id)
    ->onQueue('default')
    ->afterCommit();  // Solo se encola si la transacción actual hace commit
```

### 2. Configuración recomendada en `config/queue.php`

```php
'database' => [
    'driver' => 'database',
    'table' => 'jobs',
    'queue' => 'default',
    'retry_after' => 90,        // Segundos antes de liberar job 'reservado' si worker muere
    'after_commit' => false,    // Cambiar a true si usas DB transactions y quieres afterCommit()
],
```

### 3. Monitoreo simple sin herramientas externas

Script Bash para alerta por email si hay acumulación de jobs:

```bash
#!/bin/bash
# /usr/local/bin/check-laravel-queues.sh

DB_USER="tu_usuario"
DB_PASS="tu_contraseña"
DB_NAME="tu_base"
QUEUE="emails"
THRESHOLD=100
ALERT_EMAIL="soporte@tuempresa.com"

COUNT=$(mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -N -e \
    "SELECT COUNT(*) FROM jobs WHERE queue='$QUEUE' AND reserved_at IS NULL")

if [ "$COUNT" -gt "$THRESHOLD" ]; then
    echo "⚠️ Alerta: $COUNT jobs pendientes en cola '$QUEUE'" | \
        mail -s "[ALERTA] Colas Laravel - $QUEUE" "$ALERT_EMAIL"
fi
```

Agregar al crontab para ejecutar cada 15 minutos:

```cron
*/15 * * * * /usr/local/bin/check-laravel-queues.sh >> /dev/null 2>&1
```

### 4. Depuración de jobs que no se ejecutan

```bash
# 1. Verificar que Supervisor está corriendo workers
sudo supervisorctl status

# 2. Verificar que hay jobs en la cola
php artisan tinker
>>> DB::table('jobs')->where('queue', 'emails')->count();

# 3. Verificar logs del worker
tail -n 50 /var/www/proyecto/storage/logs/worker.log

# 4. Probar ejecución manual de un job
php artisan queue:work --queue=emails --stop-when-empty -v

# 5. Verificar configuración de retry_after en config/queue.php
#    (si es muy bajo, jobs pueden liberarse antes de terminar)
```

---

## 📚 Recursos Adicionales

- [Documentación oficial: Laravel Queues](https://laravel.com/docs/queues)
- [Documentación oficial: Task Scheduling](https://laravel.com/docs/scheduling)
- [Supervisor Documentation](http://supervisord.org/)
- [MySQL Performance: Indexing Best Practices](https://dev.mysql.com/doc/refman/8.0/en/optimization-indexes.html)

---

## 🆘 Solución de Problemas Comunes

| Síntoma | Causa probable | Solución |
|---------|---------------|--------- -|
| Jobs no se procesan | Supervisor no está corriendo | `sudo supervisorctl start laravel-worker:*` |
| `scp: command not found` en worker | OpenSSH client no instalado en servidor | `sudo apt install openssh-client` |
| Jobs se acumulan en `jobs` | Worker no hace polling o está caído | Verificar `supervisorctl status` y logs |
| `Memory exhausted` en worker | Job muy pesado o memory leak | Ajustar `--memory=512` o optimizar lógica del job |
| Job falla y no se reintenta | `$tries` no definido o excepción no capturada | Definir `public $tries = 3;` y manejar excepciones |
| `PDOException` en worker | Conexión BD se cierra por inactividad | Aumentar `wait_timeout` en MySQL o usar `--max-time` más bajo |

---

> 📝 **Nota para equipos**: Esta documentación es genérica y aplicable a cualquier repositorio Laravel del proyecto. Para casos específicos (ej: jobs que procesan archivos grandes, integración con APIs externas con rate limiting), agregar una sección específica en la documentación del repositorio correspondiente.
