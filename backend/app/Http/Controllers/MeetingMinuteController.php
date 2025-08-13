<?php

namespace App\Http\Controllers;

use App\Services\MinuteImportService;
use Illuminate\Http\Request;

class MeetingMinuteController extends Controller
{
    public function __construct(private MinuteImportService $service) {}

    public function store(Request $request)
    {
        $request->validate([
            'minutes_txt' => 'required|file|mimetypes:text/plain|max:2048',
            'title'       => 'required|string|max:255',
            'date'        => 'required|date',
        ]);

        $minute = $this->service->store($request->file('minutes_txt'), $request->only(['title', 'date']));
        
        // タスク情報を含めてレスポンスを返す
        return response()->json([
            'minute_id' => $minute->minute_id,
            'tasks' => $minute->tasks()->with('assignee:id,name')->get()->map(function($task) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'assignee' => $task->assignee ? $task->assignee->name : null,
                    'due_at' => $task->due_at,
                    'priority' => $task->priority,
                    'status' => $task->status,
                ];
            }),
        ], 201);
    }
}
