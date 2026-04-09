<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'audit_log';

    protected $fillable = [
        'user_id',
        'client_id',
        'frozen_data_id',
        'skill_name',
        'skill_version',
        'system_prompt_id',
        'model_used',
        'llm_request',
        'llm_response_raw',
        'tokens_input',
        'tokens_output',
        'python_request',
        'python_response_raw',
        'user_context',
        'status',
        'error_message',
        'latency_ms',
    ];

    protected $casts = [
        'llm_request'        => 'array',
        'llm_response_raw'   => 'array',
        'python_request'     => 'array',
        'python_response_raw' => 'array',
    ];

    // Audit logs are immutable — no updates allowed after creation.
    // Enforce this by disabling mass-update methods at the model level.
    public function update(array $attributes = [], array $options = [])
    {
        throw new \LogicException('AuditLog entries are immutable and cannot be updated.');
    }
}
