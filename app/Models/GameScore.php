<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameScore extends Model
{
    protected $fillable = ['user_id', 'game', 'score', 'bugs_eaten', 'max_combo'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
