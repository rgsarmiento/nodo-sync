<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SyncVentasController;

Route::get('/', function () {
    return view('landing');
});

// Route::prefix('api')->group(function () {
//     Route::get('/ventas', [SyncVentasController::class, 'index']);
//     Route::get('/ventas/{id}', [SyncVentasController::class, 'show']);
//     Route::post('/ventas', [SyncVentasController::class, 'store']);
// });