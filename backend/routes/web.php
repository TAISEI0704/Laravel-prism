<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;
use App\Livewire\CreatePost;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/stream-response', [ChatController::class, 'streamResponse']);
Route::get('/chat', [ChatController::class, 'index']);
Route::get('/test-connection', [ChatController::class, 'testConnection']);

// Route::get('/chat', CreatePost::class)->name('chat');