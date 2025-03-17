<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\P2PController;

Route::get('/query', [P2PController::class, 'query']);
Route::get('/topics', [P2PController::class, 'topics']);

//Route::get('/user', function (Request $request) {
//    return $request->user();
//})->middleware('auth:sanctum');
