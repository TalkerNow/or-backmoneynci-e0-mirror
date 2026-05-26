<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Files extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'filename', 'url', 'dossier',
        'file_content', 'mime_type', 'file_size',
    ];

    /**
     * Masquer le contenu binaire dans les réponses JSON (listings, etc.)
     * pour ne pas surcharger les réponses API.
     */
    protected $hidden = ['file_content'];

    public function fileCreator()
    {
        return $this->belongsTo('App\Models\User', 'creator_id');
    }
}

