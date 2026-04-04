<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AmbassadorPayout extends Model
{
    protected $fillable = [
        'uuid',
        'ambassador_id',
        'amount',
        'status',
        'transaction_id',
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

    public function ambassador()
    {
        return $this->belongsTo(Ambassador::class);
    }
}
