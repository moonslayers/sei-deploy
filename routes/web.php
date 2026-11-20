<?php

use App\Http\Controllers\TerminalController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::middleware('auth')->group(function () {
    Route::post('/terminal/{backend}/start', [TerminalController::class, 'start'])->name('terminal.start');
    Route::post('/terminal/{session:uuid}/input', [TerminalController::class, 'write'])->name('terminal.write');
    Route::post('/terminal/{session:uuid}/resize', [TerminalController::class, 'resize'])->name('terminal.resize');
    Route::delete('/terminal/{session:uuid}', [TerminalController::class, 'end'])->name('terminal.end');
    Route::get('/terminal/{session:uuid}/output', [TerminalController::class, 'output'])->name('terminal.output');
});
