<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExtractionDataRis extends Model
{
    protected $table = 'extraction_data_ris';

    protected $fillable = [
        'text',
        'nir',
    ];

    // Optionnel: normalise le NIR (supprime espaces)
    public function setNirAttribute($value): void
    {
        $this->attributes['nir'] = is_null($value)
            ? null
            : preg_replace('/\s+/', '', (string) $value);
    }
}
