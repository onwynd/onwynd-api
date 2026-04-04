<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'user_id',
        'amount',
        'currency',
        'status',
        'due_date',
        'paid_at',
        'items',
        'notes',
    ];

    protected $casts = [
        'due_date' => 'date',
        'paid_at' => 'date',
        'items' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
