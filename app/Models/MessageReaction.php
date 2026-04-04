<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageReaction extends Model
{
    protected $fillable = [
        'message_id',
        'user_id',
        'reaction',
    ];

    public function message()
    {
        return $this->belongsTo(ChannelMessage::class, 'message_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
