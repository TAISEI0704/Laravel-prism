# Copilot Instructions – Laravel × Prism タスク自動割当システム

> **目的**: 会議議事録（.txt など）をアップロードすると、LLM (Prism + OpenAI など) が担当者付きアクションアイテムを抽出し、優先度計算・子タスク分解まで行ってデータベースへ登録。Slack DM と SSE ダッシュボードで即時通知する。
>
> このファイルは GitHub Copilot／ChatGPT Code Interpreter などの AI コーディング支援ツールがコード提案しやすいよう、**ディレクトリ構造・主要クラス・メソッド・マイグレーション**を網羅的に記載する。

---

## 0. 技術スタック

| レイヤ        | 採用技術                                      | 補足                                        |
| ------------- | --------------------------------------------- | ------------------------------------------- |
| Framework     | **Laravel 12**                                | Job / Queue / Policy / SSE 対応             |
| LLM ラッパー  | **prism-php/prism**                           | Structured Output, Tool Calling, Embeddings |
| データベース  | **PostgreSQL 17 + pgvector**                  | `vector` 型で 1536 次元 Embedding 保存      |
| 検索 & 類似度 | pgvector `ivfflat` (小規模) / `hnsw` (高精度) | `vector_cosine_ops`                         |
| フロント      | Vite + React 18                               | `/resources/js` に配置                      |
| 通知          | `laravel/slack-notification-channel`          | Slack Bot DM                                |
| ストレージ    | `storage/app/minutes/*.txt`                   | 原文を暗号化保存                            |

---

## 1. ディレクトリ構成

```
app/
 ├─Console/
 │   └─ArchiveMinutes.php
 ├─Http/Controllers/MeetingMinuteController.php
 ├─Jobs/
 │   ├─ChunkMinuteJob.php
 │   ├─EmbedMinuteJob.php
 │   └─ExtractTasksJob.php
 ├─Models/
 │   ├─MeetingMinute.php
 │   ├─MinuteChunk.php
 │   └─Task.php
 ├─Notifications/TaskSlackNotification.php
 ├─Policies/MeetingMinutePolicy.php
 └─Services/
     ├─MinuteImportService.php
     └─PrismTaskService.php
database/
 └─migrations/XXXX_create_*.php
resources/js/
 └─sse.js
routes/
 └─api.php
storage/app/minutes/
```

---

## 2. .env 例

```dotenv
APP_ENV=local
APP_KEY=base64:...
QUEUE_CONNECTION=database

# LLM
OPENAI_API_KEY=sk-...
PRISM_OPENAI_KEY="${OPENAI_API_KEY}"

# Slack
SLACK_WEBHOOK_URL=https://hooks.slack.com/services/...

# DB
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=minutes
DB_USERNAME=postgres
DB_PASSWORD=password
```

---

## 3. マイグレーション

### 3.1 meeting_minutes

```php
Schema::create('meeting_minutes', function (Blueprint $t) {
    $t->uuid('minute_id')->primary();
    $t->string('title');
    $t->date('meeting_date');
    $t->string('file_path');           // storage path
    $t->integer('tokens')->nullable(); // total tokens of raw text
    $t->timestamps();
});
```

### 3.2 minute_chunks (pgvector)

```php
Schema::create('minute_chunks', function (Blueprint $t) {
    $t->uuid('minute_id');
    $t->unsignedInteger('idx');
    $t->text('chunk');
    $t->vector('embedding', 1536)->nullable();
    $t->primary(['minute_id', 'idx']);
});
DB::statement('CREATE INDEX minute_chunks_embedding_idx ON minute_chunks USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100);');
```

### 3.3 tasks

```php
Schema::create('tasks', function (Blueprint $t) {
    $t->bigIncrements('id');
    $t->uuid('minute_id');
    $t->unsignedBigInteger('parent_id')->nullable();
    $t->unsignedBigInteger('assignee_id')->nullable();
    $t->string('title');
    $t->date('due_at')->nullable();
    $t->unsignedTinyInteger('priority')->default(3); // 1–5
    $t->string('status')->default('pending');
    $t->jsonb('meta')->nullable();
    $t->timestamps();
});
```

---

## 4. モデル

### 4.1 MeetingMinute

```php
class MeetingMinute extends Model
{
    use HasFactory;
    public $incrementing = false;
    protected $primaryKey = 'minute_id';
    protected $keyType = 'string';
    protected $fillable = ['minute_id','title','meeting_date','file_path','tokens'];

    public function chunks() { return $this->hasMany(MinuteChunk::class, 'minute_id'); }
    public function tasks()  { return $this->hasMany(Task::class,          'minute_id'); }
}
```

### 4.2 MinuteChunk

```php
use MemocHou\PgVector\Casts\VectorCast;
class MinuteChunk extends Model
{
    public $timestamps = false;
    protected $primaryKey = false;
    protected $fillable = ['minute_id','idx','chunk','embedding'];
    protected $casts = ['embedding' => VectorCast::class];

    // k-NN 類似検索
    public static function similar(UUID $minuteId, int $k = 10)
    {
        return self::where('minute_id', $minuteId)
            ->orderByRaw('embedding <#> (SELECT embedding FROM minute_chunks WHERE minute_id = ? LIMIT 1) ASC', [$minuteId])
            ->limit($k)
            ->get();
    }
}
```

### 4.3 Task

```php
class Task extends Model
{
    protected $fillable = ['minute_id','parent_id','assignee_id','title','due_at','priority','status','meta'];

    public function assignee() { return $this->belongsTo(User::class, 'assignee_id'); }
    public function children() { return $this->hasMany(Task::class, 'parent_id'); }
}
```

---

## 5. サービス & ジョブ概要

| ファイル                     | 役割                                  | 主要メソッド                             |
| ---------------------------- | ------------------------------------- | ---------------------------------------- |
| **MinuteImportService**      | .txt 保存 & チャンク Job 起動         | `store(UploadedFile $file, array $meta)` |
| **ChunkMinuteJob**           | 512 token + 10% overlap で分割        | `handle(MeetingMinute $minute)`          |
| **EmbedMinuteJob**           | OpenAI Embedding → pgvector 更新      | `handle(MeetingMinute $minute)`          |
| **PrismTaskService**         | Context 組み立て & Prism 呼出         | `extract()`, `saveTasks()`               |
| **ExtractTasksJob**          | 上記サービスでタスク抽出 & Slack 通知 | `handle(MeetingMinute $minute)`          |
| **ArchiveMinutes (Console)** | 365 日超えファイルをアーカイブ        | `handle()`                               |

---

## 6. PrismTaskService サンプル

```php
class PrismTaskService
{
    private ObjectSchema $schema;
    public function __construct()
    {
        $this->schema = new ObjectSchema('task','Action Item',[
            new StringSchema('assignee'),
            new StringSchema('title'),
            (new StringSchema('due'))->nullable(),
            new StringSchema('priority'),
        ]);
    }

    /** Build context text by KNN search */
    public function buildContext(MeetingMinute $minute, int $k): string
    {
        return MinuteChunk::similar($minute->minute_id, $k)->pluck('chunk')->implode("\n\n");
    }

    /** LLM extraction */
    public function extract(string $prompt): array
    {
        return Prism::structured()
            ->using('openai','gpt-4o')
            ->withSchema($this->schema)
            ->withPrompt($prompt)
            ->asStructured()
            ->toArray();
    }

    /** Save & notify */
    public function saveTasks(MeetingMinute $minute, array $rows): void
    {
        foreach ($rows as $row) {
            $task = $minute->tasks()->create([
                'title'       => $row['title'],
                'assignee_id' => User::nameToId($row['assignee']),
                'due_at'      => $row['due'] ?? null,
                'priority'    => $row['priority'] ?? 3,
            ]);
            $task->assignee?->notify(new TaskSlackNotification($task));
        }
    }
}
```

---

## 7. ルーティング & Controller

### routes/api.php

```php
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/minutes', [MeetingMinuteController::class, 'store']);
    Route::get('/tasks/stream', [TaskStreamController::class, 'index']);
});
```

### MeetingMinuteController

```php
class MeetingMinuteController extends Controller
{
    public function store(Request $r, MinuteImportService $svc)
    {
        $r->validate([
            'minutes_txt' => 'required|file|mimetypes:text/plain',
            'title'       => 'required|string',
            'date'        => 'required|date',
        ]);
        $minute = $svc->store($r->file('minutes_txt'), $r->only(['title','date']));
        return response()->json(['minute_id' => $minute->minute_id]);
    }
}
```

---

## 8. SSE Endpoint

```php
class TaskStreamController extends Controller
{
    public function index(Request $r)
    {
        $user = $r->user();
        $stream = response()->stream(function () use ($user) {
            while (true) {
                $tasks = $user->tasks()->where('status','pending')->get();
                echo 'data: '. $tasks->toJson() . "\n\n";
                ob_flush(); flush();
                sleep(5);
            }
        }, 200, ['Content-Type'=>'text/event-stream']);
        return $stream;
    }
}
```

---

## 9. Slack Notification

```php
class TaskSlackNotification extends Notification
{
    public function via($notifiable)
    {
        return ['slack'];
    }

    public function toSlack($notifiable)
    {
        return (new SlackMessage)
            ->content('*新しいタスク* : '.$this->task->title)
            ->attachment(function ($a) {
                $a->fields([
                    '期限'   => $this->task->due_at ?? '未設定',
                    '優先度' => $this->task->priority,
                ]);
            });
    }
}
```

---

## 10. Policy

```php
class MeetingMinutePolicy
{
    public function viewRaw(User $user, MeetingMinute $minute)
    {
        return $user->hasRole('auditor');
    }
}
```

---

## 11. スケジューラ

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->command('minutes:archive')->daily();
}
```

---

## 12. 開発 & CI Tips

1. **ローカル DB**: `make postgres` で `postgres:17-alpine` + `pgvector` を Docker 起動。
2. **SQLite fallback**: `.env.testing` で `DB_CONNECTION=sqlite` にし、CI 速度アップ。
3. **Prism Fake**: ユニットテストは `Prism::fake()` を使用し外部 API 呼び出しゼロ化。
4. **Seed**: `database/seeders/DemoMinuteSeeder.php` でサンプル議事録 & embeddings を投入。
5. **CI**: GitHub Actions で `postgres` service と `php artisan test` を実行。

---
