<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class ActivityLog extends Model
{
    /**
     * Typen der Einträge. Die Werte stehen so in der Hash-Kette und werden
     * nie umgeschrieben (Standard 10) — darum bleiben sie deutsch; die
     * Anzeige übersetzt sie über labels().
     *
     * Entry types. The values are stored like this in the hash chain and are
     * never rewritten (standard 10) — so they stay German; the display
     * translates them via labels().
     */
    public const TYPE_CASE = 'vorgang';
    public const TYPE_FIELD = 'feld';
    public const TYPE_STEP = 'schritt';
    public const TYPE_MAIL = 'mail';
    public const TYPE_DELETION = 'löschung';
    public const TYPE_SETTINGS = 'einstellungen';
    public const TYPE_USER = 'benutzer';
    public const TYPE_SIGN_IN = 'anmeldung';
    public const TYPE_EXPORT = 'export';

    /**
     * Anzeigenamen der Typen (deutsche Übersetzungsschlüssel)
     *
     * Display names of the types (German translation keys)
     */
    public const LABELS = [
        self::TYPE_CASE     => 'Vorgänge',
        self::TYPE_FIELD    => 'Ausgefüllte Daten',
        self::TYPE_STEP     => 'Workflow-Schritte',
        self::TYPE_MAIL     => 'E-Mails',
        self::TYPE_DELETION => 'Löschungen',
        self::TYPE_SETTINGS => 'Einstellungen',
        self::TYPE_USER     => 'Benutzer',
        self::TYPE_SIGN_IN  => 'Anmeldungen',
        self::TYPE_EXPORT   => 'Exporte',
    ];

    protected $fillable = [
        'type', 'user', 'onboarding_id', 'onboarding_name', 'message', 'details', 'hash',
    ];

    public function onboarding()
    {
        return $this->belongsTo(\App\Models\Onboarding::class);
    }

    protected $casts = ['details' => 'array'];

    /**
     * Protokolleintrag schreiben (append-only, mit Hash-Kette für
     * Revisionssicherheit). Fehler beim Loggen dürfen den eigentlichen
     * Vorgang nie abbrechen.
     *
     * Write a log entry (append-only, with hash chain for
     * audit-proofing). Errors while logging must never abort the
     * actual operation.
     */
    public static function write(
        string $type,
        string $message,
        ?string $user = null,
        ?Onboarding $onboarding = null,
        ?array $details = null,
    ): void {
        try {
            $timestamp = now();
            $previous = static::orderByDesc('id')->value('hash') ?? 'GENESIS';

            $payload = implode('|', [
                $previous,
                $timestamp->toIso8601String(),
                $type,
                $user ?? '',
                $onboarding?->id ?? '',
                $onboarding?->fullName() ?? '',
                $message,
                json_encode($details, JSON_UNESCAPED_UNICODE) ?: '',
            ]);

            static::create([
                'type' => $type,
                'message' => $message,
                'user' => $user,
                'onboarding_id' => $onboarding?->id,
                'onboarding_name' => $onboarding?->fullName(),
                'details' => $details,
                'hash' => hash('sha256', $payload),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Protokoll konnte nicht geschrieben werden: ' . $e->getMessage());
        }
    }
}
