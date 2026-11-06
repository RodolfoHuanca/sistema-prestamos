<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

use App\Http\Controllers\RecursoController;

Route::get('/recursos', [RecursoController::class, 'index'])->name('recursos.index');
Route::post('/recursos', [RecursoController::class, 'store'])->name('recursos.store');
Route::put('/recursos/{recurso}', [RecursoController::class, 'update'])->name('recursos.update');
Route::patch('/recursos/{recurso}/baja', [RecursoController::class, 'baja'])->name('recursos.baja');