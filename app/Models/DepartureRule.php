<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepartureRule extends Model
{
    protected $table = 'departure_rules';

    protected $fillable = [
        'territoire',
        'key_max',
        'age_months',
        'trim',
        'is_default',
        'sort_order',
        'updated_by',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function toApiArray(): array
    {
        $years  = intdiv($this->age_months, 12);
        $months = $this->age_months % 12;
        $label  = $years . ' ans' . ($months > 0 ? ' et ' . $months . ' mois' : '');

        return [
            'id'         => $this->id,
            'key_max'    => $this->key_max,
            'age_months' => $this->age_months,
            'age_label'  => $label,
            'trim'       => $this->trim,
            'is_default' => $this->is_default,
            'sort_order' => $this->sort_order,
        ];
    }
}
