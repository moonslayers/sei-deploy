<x-filament-panels::page>
    <div class="mb-4 rounded border border-warning-300 bg-warning-50 p-3 text-sm text-warning-800">
        Advertencia: el script se ejecuta en cada deploy de ese repo con acceso al .env del backend y al token de GitHub como argumento $3. Revisa antes de guardar o restaurar.
    </div>
    {{ $this->table }}
</x-filament-panels::page>
