<?php

namespace App\Jobs;

use App\Models\MeetingMinute;
use App\Jobs\ExtractTasksJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Prism\Prism\Prism;
use Exception;
use Illuminate\Support\Facades\Log;

class EmbedMinuteJob implements ShouldQueue
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
    public $backoff = [3, 5, 10];

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(public MeetingMinute $minute)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $chunks = $this->minute->chunks()->whereNull('embedding')->get();
            
            if ($chunks->isEmpty()) {
                Log::info("No chunks to embed for minute: " . $this->minute->minute_id);
                return;
            }

            // チャンクをバッチ処理（5件ずつ）
            foreach ($chunks->chunk(5) as $chunkBatch) {
                $texts = $chunkBatch->pluck('chunk')->toArray();
                
                // OpenAIでEmbedding生成
                foreach ($chunkBatch as $chunk) {
                    // エンベディングを生成して配列として取得
                    $response = Prism::embeddings()
                        ->using('openai', 'text-embedding-3-small') // ここでモデルを指定
                        ->fromInput($chunk->chunk)
                        ->asEmbeddings();  // 直接embeddingsプロパティにアクセス
                    
                    $embedding = $response->embeddings[0]->embedding;
                    
                    $chunk->update(['embedding' => $embedding]);
                }
            }

            // すべてのチャンクにembeddingが設定されているか確認
            if (!$this->minute->chunks()->whereNull('embedding')->exists()) {
                // 次のジョブ（タスク抽出）を起動
                ExtractTasksJob::dispatch($this->minute->minute_id);
            }

        } catch (Exception $e) {
            Log::error("Error embedding chunks for minute {$this->minute->minute_id}: " . $e->getMessage());
            throw $e;
        }
    }
}
