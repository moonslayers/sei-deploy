<x-filament-panels::page>
    <div id="terminal" style="height:600px;width:100%;" class="rounded-lg bg-black p-2"></div>

    @vite(['resources/js/echo.js', 'resources/js/terminal.js'])
    <script>
        window.__terminalConfig = {
            backendId: {{ $backend->id }},
            startUrl: "{{ route('terminal.start', $backend) }}",
            csrfToken: "{{ csrf_token() }}",
        };
    </script>
</x-filament-panels::page>
