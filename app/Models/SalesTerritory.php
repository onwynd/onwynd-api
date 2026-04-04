<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SalesTerritory extends Model
{
    protected $fillable = [
        'uuid', 'name', 'code', 'type', 'parent_id', 'country', 'description', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($m) => $m->uuid = $m->uuid ?? (string) Str::uuid());
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function agents()
    {
        return $this->belongsToMany(User::class, 'sales_agent_territories', 'territory_id', 'user_id')
            ->withPivot(['role', 'is_primary'])
            ->withTimestamps();
    }
}
