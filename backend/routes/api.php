<?php

declare(strict_types=1);

use App\Http\Controllers\MeetingMinuteController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('minutes', [MeetingMinuteController::class, 'store']);
