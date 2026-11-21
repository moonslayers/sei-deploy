<x-filament-panels::page>
    <div id="terminal" style="height:600px;width:100%;" class="rounded-lg bg-black p-2"></div>

    <link rel="stylesheet" href="{{ asset('vendor/xterm-6.0.0.css') }}">
    <script src="{{ asset('vendor/pusher-8.6.0.min.js') }}"></script>
    <script>
        window.__terminalConfig = {
            backendId: {{ $backend->id }},
            startUrl: "{{ route('terminal.start', $backend) }}",
            csrfToken: "{{ csrf_token() }}",
            echo: @js([
                'key' => config('broadcasting.connections.reverb.key'),
                'wsHost' => config('broadcasting.connections.reverb.options.host'),
                'wsPort' => config('broadcasting.connections.reverb.options.port'),
                'wssPort' => config('broadcasting.connections.reverb.options.port'),
                'forceTLS' => (config('broadcasting.connections.reverb.options.scheme') === 'https'),
            ]),
        };
    </script>
    <script type="module" src="{{ asset('js/terminal.js') }}"></script>
</x-filament-panels::page>