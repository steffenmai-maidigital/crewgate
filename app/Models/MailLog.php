<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailLog extends Model
{
    /** Versandstatus (gespeicherte Werte) / Delivery status (stored values) */
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    /** Anzeigename des Status / Display name of the status */
    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_SENT   => __('gesendet'),
            self::STATUS_FAILED => __('fehlgeschlagen'),
            default             => (string) $this->status,
        };
    }

    protected $fillable = [
        'recipient', 'subject', 'body', 'status', 'error',
        'onboarding_id', 'onboarding_name',
    ];
}
