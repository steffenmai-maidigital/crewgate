<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingStep extends Model
{
    protected $fillable = ['onboarding_id', 'role_id', 'completed_by', 'completed_at'];

    protected $casts = ['completed_at' => 'datetime'];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
