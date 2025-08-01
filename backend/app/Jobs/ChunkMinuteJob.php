<?php

namespace App\Jobs;

use App\Models\MeetingMinute;
use App\Jobs\EmbedMinuteJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ChunkMinuteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(public MeetingMinute $minute)
    {
        //
    }

    /**
     * Execute the job.
     */
    private const CHUNK_SIZE = 1000; // 1チャンクの文字数
    private const OVERLAP_SIZE = 100;  // オーバーラップの文字数

    public function handle(): void
    {
        // ファイル読み込み
        $text = Storage::get($this->minute->file_path);
        if (empty($text)) {
            throw new \Exception('議事録ファイルが空です');
        }

        // 文字数を保存
        $this->minute->update(['tokens' => mb_strlen($text)]);

        // テキストを文単位で分割
        $sentences = preg_split('/([。．！？])/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $currentChunk = '';
        $chunkIndex = 0;

        for ($i = 0; $i < count($sentences); $i++) {
            $sentence = $sentences[$i];
            
            // 句点などの区切り文字の場合、前の文につける
            if ($i % 2 === 1) {
                $currentChunk .= $sentence;
                continue;
            }

            if (empty(trim($sentence))) {
                continue;
            }

            // チャンクサイズを超える場合は新しいチャンクを開始
            if (mb_strlen($currentChunk) + mb_strlen($sentence) > self::CHUNK_SIZE) {
                if (!empty(trim($currentChunk))) {
                    // 前のチャンクの末尾を次のチャンクに重複させる
                    $overlapText = mb_substr($currentChunk, -self::OVERLAP_SIZE);
                    $this->saveChunk($currentChunk, $chunkIndex++);
                    $currentChunk = $overlapText . $sentence;
                } else {
                    $currentChunk = $sentence;
                }
            } else {
                $currentChunk .= $sentence;
            }
        }

        // 最後のチャンクを保存
        if (!empty(trim($currentChunk))) {
            $this->saveChunk($currentChunk, $chunkIndex);
        }

        // Dispatch embedding job
        EmbedMinuteJob::dispatch($this->minute);
    }

    private function saveChunk(string $text, int $index): void
    {
        $chunk = trim($text);
        if (empty($chunk)) {
            return;
        }

        \Log::info("Saving chunk:", [
            'index' => $index,
            'length' => mb_strlen($chunk),
            'preview' => mb_substr($chunk, 0, 50) . '...'
        ]);

        $this->minute->chunks()->create([
            'idx' => $index,
            'chunk' => $chunk
        ]);
    }
}
