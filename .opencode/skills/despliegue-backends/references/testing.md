# Testing

Los tests de `BackendService` usan **reflection** para probar métodos protegidos:

```php
$service = new BackendService();
$reflection = new \ReflectionClass($service);
$method = $reflection->getMethod('getMigrateCommand');
$result = $method->invoke($service, $repoFullName);
```

Para mockear la configuración:

```php
Config::set('sei.backend_commands', [
    'moonslayers/vdin' => 'migrate:fresh --seed',
]);
```

Los tests están en `tests/Unit/BackendServiceTest.php`.
