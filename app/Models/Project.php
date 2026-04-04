<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Project extends Model
{
    protected $fillable = [
        'uuid',
        'name',
        'description',
        'manager_id',
        'start_date',
        'end_date',
        'status',
        'tags',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'tags' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function timeLogs()
    {
        return $this->hasMany(TimeLog::class);
    }
}
