<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\P2PController;

Route::get('/p2p', [P2PController::class, 'index']);

Route::get('/', function () {
    return view('welcome');
});
