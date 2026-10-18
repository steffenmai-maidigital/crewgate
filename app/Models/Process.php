<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Process extends Model
{
    /** Eingebaute Prozessarten / Built-in process types */
    public const ONBOARDING = 'onboarding';
    public const JOB_CHANGE = 'job_change';
    public const EXIT = 'exit';
    public const MASTER_DATA_CHANGE = 'master_data_change';

    /**
     * Alte (deutsche) Schlüssel → neue; für CSV-Import und API, die alte
     * Werte weiter annehmen (Standard 10)
     *
     * Old (German) keys → new ones; for CSV import and API, which keep
     * accepting old values (standard 10)
     */
    public const LEGACY_KEYS = [
        'wechsel'    => self::JOB_CHANGE,
        'austritt'   => self::EXIT,
        'stammdaten' => self::MASTER_DATA_CHANGE,
    ];

    /** Neuer Schlüssel zu einem evtl. alten / New key for a possibly old one */
    public static function currentKey(string $key): string
    {
        return self::LEGACY_KEYS[$key] ?? $key;
    }

    /** Alter Schlüssel zu einem neuen (sonst unverändert) / Old key for a new one (otherwise unchanged) */
    public static function legacyKey(?string $key): ?string
    {
        $old = array_search($key, self::LEGACY_KEYS, true);

        return $old === false ? $key : $old;
    }

    protected $fillable = ['key', 'name', 'name_en', 'role_ids', 'sort_order', 'active', 'is_system'];

    protected $casts = [
        'role_ids' => 'array',
        'active' => 'boolean',
        'is_system' => 'boolean',
    ];

    /** Name in der Sprache der Oberfläche / Name in the interface language */
    public function displayName(): string
    {
        return app()->getLocale() === 'en' && filled($this->name_en)
            ? $this->name_en
            : $this->name;
    }

    /** Zweisprachig für Exporte: "Deutsch / English" / Bilingual for exports: "Deutsch / English" */
    public function bilingualName(): string
    {
        return filled($this->name_en) && $this->name_en !== $this->name
            ? $this->name . ' / ' . $this->name_en
            : $this->name;
    }

    /** Beteiligte Workflow-Rollen (leer = alle) / Workflow roles involved (empty = all) */
    public function workflowRoles()
    {
        $roles = Role::workflow();
        $ids = array_map('intval', $this->role_ids ?? []);

        return $ids === [] ? $roles : $roles->filter(fn ($r) => in_array($r->id, $ids, true))->values();
    }

    public static function activeOrdered()
    {
        return static::where('active', true)->orderBy('sort_order')->orderBy('name')->get();
    }

    public static function findByKey(?string $key): ?self
    {
        static $cache = [];
        $key = $key ?: self::ONBOARDING;

        return $cache[$key] ??= static::where('key', $key)->first();
    }
}
