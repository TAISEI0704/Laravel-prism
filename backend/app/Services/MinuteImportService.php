<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\ChunkMinuteJob;
use App\Models\MeetingMinute;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class MinuteImportService
{
	/**
	 * Store the uploaded meeting minute file and create a MeetingMinute record.
	 *
	 * @param UploadedFile $file
	 * @param array $meta
	 * @return MeetingMinute
	 */
	public function store(UploadedFile $file, array $meta): MeetingMinute
	{
        // 1. 原文を Storage に保存
        $uuid  = Str::uuid();
        $path  = $file->storeAs('minutes', "$uuid.txt", ['disk'=>'local','visibility'=>'private']);
        $minute = MeetingMinute::create([
            'minute_id'   => $uuid,
            'title'       => $meta['title'],
            'meeting_date'=> $meta['date'],
            'file_path'   => $path,
        ]);

        // 2. ジョブを **サービス内** でキューに載せる
        try {            
            // 型を明示的に指定してジョブをディスパッチ
            ChunkMinuteJob::dispatch($minute);

        } catch (\Throwable $e) {
            \Log::error("Failed to dispatch ChunkMinuteJob for minute: {$uuid}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e; // エラーを上位に伝播
        }

        return $minute;
	}
}