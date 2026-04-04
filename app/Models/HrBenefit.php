<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrBenefit extends Model
{
    protected $table = 'hr_benefits';

    protected $fillable = [
        'title',
        'description',
        'icon',
        'status',
        'enrolled_count',
    ];

    protected $casts = [
        'enrolled_count' => 'integer',
    ];
}
