<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MinuteChunk extends Model
{
    protected $table = 'minutes_chunks';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = [
        'minute_id',
        'idx',
        'chunk',
        'embedding'
    ];

    public function meetingMinute()
    {
        return $this->belongsTo(MeetingMinute::class, 'minute_id');
    }
}
