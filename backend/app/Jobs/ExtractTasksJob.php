<?php

namespace App\Jobs;

use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Ramsey\Uuid\UuidInterface;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Prism;
use Prism\Prism\Schema\StringSchema;

class ExtractTasksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string|UuidInterface $minuteId) {}

    public function handle(): void
    {
        // kNNによる関連チャンク取得
        // 最初にembeddingが存在するか確認
        $hasEmbeddings = DB::select(
            'SELECT EXISTS (
                SELECT 1 FROM minutes_chunks 
                WHERE minute_id = ? AND embedding IS NOT NULL
            ) as has_embeddings', 
            [$this->minuteId]
        )[0]->has_embeddings;

        if (!$hasEmbeddings) {
            throw new \Exception('Embeddings not found for this minute');
        }

        // すべてのチャンクを取得
        $chunks = DB::select(
            'SELECT chunk, idx
            FROM minutes_chunks
            WHERE minute_id = ? 
              AND embedding IS NOT NULL
            ORDER BY idx ASC
            LIMIT 10',
            [$this->minuteId]
        );

        if (empty($chunks)) {
            throw new \Exception('No valid chunks found for this minute');
        }

        $context = collect($chunks)->pluck('chunk')->implode("\n\n");

        $schema = new ObjectSchema('tasks', 'タスク', [
            new StringSchema('assignee', '担当者名を表す文字列', false),
            new StringSchema('description', 'タスクの説明', false),
            new StringSchema('due_at', 'タスクの期限', true),
            new StringSchema('priority', 'タスクの優先度', true),
        ], ['assignee', 'description']);
        try {
            // タスク抽出
            $response = Prism::structured()
                ->withSchema($schema)
                ->withPrompt("あなたは議事録からタスクを抽出する専門アシスタントです。
                    以下の議事録から、タスクを抽出してください：

                    {$context}

                    要件：
                    1. タスクの抽出基準
                    - 担当者が明確に指定されているもの
                    - 具体的なアクションが含まれているもの
                    - 完了期限が示唆されているもの

                    2. 各タスクの情報
                    - assignee: 担当者の名前（例: '山田太郎'）
                    - description: タスクの具体的な内容
                    - due_at: 期限をYYYY-MM-DD形式で（例: '2025-08-15'）
                    - priority: 優先度を1-5で評価（1:最重要, 5:低優先）

                    3. 出力形式
                    {
                        'tasks': [
                        {
                            'assignee': '担当者名',
                            'description': 'タスク内容',
                            'due_at': '2025-MM-DD',
                            'priority': 優先度
                        },
                        // 複数のタスクを配列として返す
                        ]
                    }

                    複数のタスクが見つかった場合は、全て抽出してください。")
                ->using(Provider::OpenAI, 'gpt-4')
                ->asStructured();

            // トランザクション開始
            DB::beginTransaction();
            
            try {
                // レスポンスからタスクを取得
                $tasks = $response->structured['tasks'];
                \Log::info($response->structured['tasks']);
                
                $createdTasks = [];
                // 各タスクを保存
                foreach ($tasks as $taskData) {
                    $task = Task::create([
                        'minute_id' => $this->minuteId,
                        'assignee_name' => $taskData['assignee'],  // 直接人物名を保存
                        'title' => $taskData['description'],
                        'due_at' => isset($taskData['due_at']) ? Carbon::parse($taskData['due_at']) : null,
                        'priority' => (int)($taskData['priority'] ?? 3),
                        'status' => 'pending'
                    ]);
                    $createdTasks[] = $task;
                }
                
                DB::commit();
                
                // トランザクション完了
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            // ジョブの再試行をトリガー
            $this->release(30); // 30秒後に再試行
            
            \Log::error('タスク抽出に失敗:', [
                'minute_id' => $this->minuteId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
