<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Payroll extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'amount',
        'pay_date',
        'period_start',
        'period_end',
        'status',
        'reference_number',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'pay_date' => 'date',
        'period_start' => 'date',
        'period_end' => 'date',
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
