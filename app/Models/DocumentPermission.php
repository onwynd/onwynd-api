<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentPermission extends Model
{
    protected $fillable = [
        'document_id',
        'folder_id',
        'user_id',
        'permission',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function folder()
    {
        return $this->belongsTo(DocumentFolder::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
