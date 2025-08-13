<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = [
        'minute_id',
        'assignee_name',
        'title',
        'due_at',
        'priority',
        'status'
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'priority' => 'integer'
    ];

    // リレーション
    public function minute()
    {
        return $this->belongsTo(MeetingMinute::class, 'minute_id');
    }
}
