<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PenilaianController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Route untuk penilaian kelayakan laptop
Route::post('/penilaian', [PenilaianController::class, 'penilaian']);
