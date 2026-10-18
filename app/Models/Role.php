<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = [
        'name', 'ad_group_dn', 'central_email', 'sort_order', 'in_workflow', 'is_intake', 'visible_role_ids',
    ];

    protected $casts = [
        'in_workflow' => 'boolean',
        'is_intake'   => 'boolean',
        'visible_role_ids' => 'array',
    ];

    public function fields(): HasMany
    {
        return $this->hasMany(CustomField::class)->orderBy('sort_order');
    }

    /**
     * Rollen in Workflow-Reihenfolge (ohne die HR-Anlage-Rolle)
     *
     * Roles in workflow order (without the HR intake role)
     */
    public static function workflow()
    {
        return static::where('in_workflow', true)
            ->where('is_intake', false)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Feste HR-Rolle, deren Felder beim Anlegen eines Vorgangs erscheinen
     *
     * Fixed HR role whose fields appear when a case is created
     */
    public static function intake(): ?Role
    {
        return static::where('is_intake', true)->first();
    }

    /** Nächste Rolle nach dieser (oder null am Ende) / Next role after this one (or null at the end) */
    public function next(): ?Role
    {
        return static::where('in_workflow', true)
            ->where('is_intake', false)
            ->where(function ($q) {
                $q->where('sort_order', '>', $this->sort_order)
                  ->orWhere(fn ($q2) => $q2->where('sort_order', $this->sort_order)->where('id', '>', $this->id));
            })
            ->orderBy('sort_order')->orderBy('id')
            ->first();
    }
}
