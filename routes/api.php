<?php

use App\Http\Controllers\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/webhook/submodule', [WebhookController::class, 'updateSubmodule'])
    ->middleware('verify.webhook.signature');

Route::post('/webhook/deploy', [WebhookController::class, 'updateDeploy'])
    ->middleware('verify.webhook.signature');

Route::get('/status', [WebhookController::class, 'status']);
