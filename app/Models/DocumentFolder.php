<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DocumentFolder extends Model
{
    protected $fillable = [
        'uuid',
        'name',
        'parent_id',
        'creator_id',
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

    public function parent()
    {
        return $this->belongsTo(DocumentFolder::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(DocumentFolder::class, 'parent_id');
    }

    public function documents()
    {
        return $this->hasMany(Document::class, 'folder_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }
}
