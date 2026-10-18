<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingValue extends Model
{
    protected $fillable = ['onboarding_id', 'custom_field_id', 'value', 'filled_by'];

    public function field(): BelongsTo
    {
        return $this->belongsTo(CustomField::class, 'custom_field_id');
    }
}
