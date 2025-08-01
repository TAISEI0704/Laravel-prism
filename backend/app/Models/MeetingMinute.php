<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MeetingMinute extends Model
{
    use HasFactory;
    public $incrementing = false;   // UUID
    protected $keyType = 'string';
    protected $fillable = ['minute_id','file_path','title','meeting_date','tokens'];
    public function chunks() { return $this->hasMany(MinuteChunk::class); }
    public function tasks()  { return $this->hasMany(Task::class); }
}
