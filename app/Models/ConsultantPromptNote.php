<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsultantPromptNote extends Model
{
    protected $table = 'consultant_prompt_notes';

    protected $fillable = [
        'client_id',
        'consultant_id',
        'content',
    ];

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function consultant()
    {
        return $this->belongsTo(User::class, 'consultant_id');
    }
}
