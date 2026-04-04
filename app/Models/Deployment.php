<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Deployment extends Model
{
    use HasFactory;

    protected $fillable = [
        'version',
        'environment',
        'status',
        'deployed_by',
        'duration',
        'details',
    ];

    protected $casts = [
        'details' => 'array',
        'deployed_at' => 'datetime', // Maps to created_at usually, but let's keep it simple
    ];

    public function deployer()
    {
        return $this->belongsTo(User::class, 'deployed_by');
    }
}
