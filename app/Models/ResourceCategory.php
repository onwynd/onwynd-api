<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResourceCategory extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon_url',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function resources()
    {
        return $this->hasMany(MindfulResource::class);
    }
}
