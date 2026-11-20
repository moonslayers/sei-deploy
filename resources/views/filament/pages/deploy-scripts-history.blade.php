<div class="space-y-4">
    @forelse ($versions as $version)
        <div class="border rounded p-3">
            <div class="flex justify-between items-center mb-2">
                <span class="text-sm text-gray-500">{{ $version->created_at }} — {{ $version->user?->name ?? 'sistema' }}</span>
                <button wire:click="restoreVersion({{ $version->id }})" class="text-primary-600 text-sm">Restaurar</button>
            </div>
            <pre class="text-xs overflow-auto max-h-40">{{ $version->content }}</pre>
        </div>
    @empty
        <p class="text-gray-500">Sin versiones anteriores.</p>
    @endforelse
</div>
