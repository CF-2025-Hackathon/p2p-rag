<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\SpiderController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/test', [ChatController::class, 'test']);
Route::post('/chat', [ChatController::class, 'chat']);
Route::post('/train', [ChatController::class, 'train']);
Route::post('/expertise', [ChatController::class, 'handleExpertise']);
Route::post('/query', [ChatController::class, 'query']);

Route::post('/crawl', [SpiderController::class, 'crawl']);
Route::post('/scrape', [SpiderController::class, 'scrape']);
Route::post('/clear', [SpiderController::class, 'clear']);
