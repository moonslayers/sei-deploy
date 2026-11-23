<x-filament-panels::page>
    <div id="terminal" style="height:600px;width:100%;" class="rounded-lg bg-black p-2"></div>

    <link rel="stylesheet" href="{{ asset('vendor/xterm-6.0.0.css') }}">
    <script>
        window.__terminalConfig = {
            backendId: {{ $backend->id }},
            startUrl: "{{ route('terminal.start', $backend) }}",
            outputUrl: "{{ route('terminal.output', ['session' => '__UUID__']) }}",
            inputUrl: "{{ route('terminal.write', ['session' => '__UUID__']) }}",
            resizeUrl: "{{ route('terminal.resize', ['session' => '__UUID__']) }}",
            endUrl: "{{ route('terminal.end', ['session' => '__UUID__']) }}",
            csrfToken: "{{ csrf_token() }}",
            sessionId: null,
        };
    </script>
    <script type="module" src="{{ asset('js/terminal.js') }}"></script>
</x-filament-panels::page>