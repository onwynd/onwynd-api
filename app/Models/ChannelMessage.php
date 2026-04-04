<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ChannelMessage extends Model
{
    protected $fillable = [
        'uuid',
        'channel_id',
        'user_id',
        'content',
        'attachments',
        'parent_id',
    ];

    protected $casts = [
        'attachments' => 'array',
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

    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reactions()
    {
        return $this->hasMany(MessageReaction::class, 'message_id');
    }

    public function parent()
    {
        return $this->belongsTo(ChannelMessage::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(ChannelMessage::class, 'parent_id');
    }
}
