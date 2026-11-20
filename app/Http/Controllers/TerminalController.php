<?php

namespace App\Http\Controllers;

use App\Models\Backend;
use App\Models\TerminalSession;
use App\Services\TerminalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TerminalController extends Controller
{
    public function __construct(protected TerminalService $service) {}

    public function start(Request $request, Backend $backend)
    {
        abort_unless(Gate::allows('admin'), 403);

        try {
            $session = $this->service->start($backend, $request->user());
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['session' => $session->uuid]);
    }

    public function write(Request $request, TerminalSession $session)
    {
        abort_unless($session->user_id === $request->user()->id, 403);
        $this->service->write($session, (string) $request->input('input'), $request->user());

        return response()->json(['ok' => true]);
    }

    public function resize(Request $request, TerminalSession $session)
    {
        abort_unless($session->user_id === $request->user()->id, 403);
        $this->service->resize($session, (int) $request->input('cols', 80), (int) $request->input('rows', 24));

        return response()->json(['ok' => true]);
    }

    public function end(Request $request, TerminalSession $session)
    {
        abort_unless($session->user_id === $request->user()->id, 403);
        $this->service->end($session);

        return response()->json(['ok' => true]);
    }

    public function output(Request $request, TerminalSession $session)
    {
        abort_unless($session->user_id === $request->user()->id, 403);

        $offset = max(0, (int) $request->query('offset', 0));
        $logFile = storage_path('app/terminal'.DIRECTORY_SEPARATOR.$session->uuid.'.log');

        $output = '';
        $size = 0;
        if (is_file($logFile)) {
            $size = (int) filesize($logFile);
            if ($size > $offset) {
                $fh = fopen($logFile, 'r');
                if ($fh) {
                    fseek($fh, $offset);
                    $chunk = stream_get_contents($fh);
                    fclose($fh);
                    if (is_string($chunk)) {
                        $output = $chunk;
                    }
                }
            }
        }

        return response()->json([
            'output' => $output,
            'offset' => $offset + strlen($output),
            'size' => $size,
        ]);
    }
}
