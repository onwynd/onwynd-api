<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OperationalLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'type',
        'title',
        'body',
        'log_date',
        'visibility',
        'created_by',
    ];

    protected $casts = [
        'log_date' => 'date',
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

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
