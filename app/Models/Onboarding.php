<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Onboarding extends Model
{
    /** Verfügbare Prozessarten / Available process types */
    public const PROCESSES = [
        Process::ONBOARDING         => 'Einstellung',
        Process::JOB_CHANGE         => 'Stellenwechsel',
        Process::EXIT               => 'Austritt',
        Process::MASTER_DATA_CHANGE => 'Stammdatenänderung',
    ];

    public function processLabel(): string
    {
        $p = Process::findByKey($this->process);

        return $p ? $p->displayName() : __(self::PROCESSES[$this->process ?? Process::ONBOARDING] ?? 'Einstellung');
    }

    /**
     * Am Vorgang beteiligte Workflow-Rollen (je nach Prozessart ggf. verkürzt)
     *
     * Workflow roles involved in the case (shortened depending on the process type)
     */
    public function workflowRoles()
    {
        return Process::findByKey($this->process)?->workflowRoles() ?? Role::workflow();
    }

    protected $fillable = [
        'first_name', 'last_name', 'start_date', 'department',
        'supervisor_username', 'supervisor_name', 'supervisor_email', 'created_by', 'process',
        'supervisor_due_date', 'roles_due_date', 'supervisor_reminder_sent_at', 'roles_reminder_sent_at',
        'current_role_id', 'status', 'completed_at', 'notes',
    ];

    protected $casts = [
        'supervisor_due_date' => 'date',
        'roles_due_date' => 'date',
        'supervisor_reminder_sent_at' => 'datetime',
        'roles_reminder_sent_at' => 'datetime',
        'start_date'   => 'date',
        'completed_at' => 'datetime',
    ];

    public function currentRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'current_role_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(OnboardingValue::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(OnboardingStep::class);
    }

    public function fullName(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Workflow-Rollen, die den Erhalt noch nicht quittiert haben
     *
     * Workflow roles that have not yet acknowledged receipt
     */
    public function pendingRoles()
    {
        $done = $this->steps->pluck('role_id')->all();

        return $this->workflowRoles()->reject(fn ($r) => in_array($r->id, $done, true))->values();
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'done'   => __('abgeschlossen'),
            'draft'  => __('in Vorbereitung'),
            'review' => __('wartet auf HR-Abschluss'),
            'cancelled' => __('abgebrochen'),
            default  => __('in Bearbeitung'),
        };
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            'done' => 'done',
            'cancelled' => 'cancelled',
            default => 'open',
        };
    }
}
