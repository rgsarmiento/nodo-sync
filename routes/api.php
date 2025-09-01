<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SyncVentasController;

Route::get('/ventas', [SyncVentasController::class, 'index']);       // Lista todas las ventas
Route::get('/ventas/{id}', [SyncVentasController::class, 'show']);   // Venta por id
Route::post('/ventas', [SyncVentasController::class, 'store']);      // Crear venta

