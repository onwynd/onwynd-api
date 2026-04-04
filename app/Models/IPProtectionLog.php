<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IPProtectionLog extends Model
{
    use HasFactory;

    protected $table = 'ip_protection_logs';

    protected $fillable = [
        'user_id',
        'platform',
        'attempt_type',
        'page_path',
        'ip_address',
        'user_agent',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
