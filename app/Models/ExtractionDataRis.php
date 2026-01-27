<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class ExtractionDataRis extends Model
{
    protected $table = 'extraction_data_ris';

    protected $fillable = [
        'user_id',
        'text',
        'nir',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Optionnel: normalise le NIR (enlève espaces)
    public function setNirAttribute($value): void
    {
        $this->attributes['nir'] = is_null($value)
            ? null
            : preg_replace('/\s+/', '', (string) $value);
    }
}
