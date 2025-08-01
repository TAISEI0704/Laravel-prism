<?php

declare(strict_types=1);

use App\Http\Controllers\MeetingMinuteController;
use App\Http\Controllers\TaskController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('minutes', [MeetingMinuteController::class, 'store']);

// タスク関連のエンドポイント
Route::prefix('tasks')->group(function () {
    Route::get('/', [TaskController::class, 'index']); // 全タスク取得
    Route::get('/latest', [TaskController::class, 'latest']); // 最新タスク取得
    Route::get('/stream', [TaskController::class, 'stream']); // SSEによるリアルタイム更新
});
