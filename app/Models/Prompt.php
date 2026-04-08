<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prompt extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'description',
        'type',
        'prompt_text',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who created this prompt
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all history entries for this prompt
     */
    public function history()
    {
        return $this->hasMany(PromptHistory::class, 'prompt_id')->orderBy('version', 'desc');
    }

    /**
     * Override the boot method to create history automatically
     */
    protected static function boot()
    {
        parent::boot();

        // Créer automatiquement une entrée dans l'historique lors de la mise à jour
        static::updated(function ($prompt) {
            if ($prompt->isDirty('prompt_text')) {
                $latestVersion = $prompt->history()->max('version') ?? 0;
                
                PromptHistory::create([
                    'prompt_id' => $prompt->id,
                    'version' => $latestVersion + 1,
                    'prompt_text' => $prompt->getOriginal('prompt_text'),
                    'created_by' => auth()->id() ?? $prompt->created_by,
                ]);
            }
        });
    }
}
