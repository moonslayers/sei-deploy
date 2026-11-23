<x-filament-panels::page>
    <div id="terminal" style="height:600px;width:100%;" class="rounded-lg bg-black p-2"></div>

    <link rel="stylesheet" href="{{ asset('vendor/xterm-6.0.0.css') }}">
    <script>
        window.__terminalConfig = {
            backendId: {{ $backend->id }},
            startUrl: "{{ route('terminal.start', $backend) }}",
            csrfToken: "{{ csrf_token() }}",
            sessionId: null,
        };
    </script>
    <script type="module" src="{{ asset('js/terminal.js') }}"></script>
</x-filament-panels::page>