<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomField extends Model
{
    public const TYPE_SELECT      = 'select';
    public const TYPE_TEXT        = 'text';
    public const TYPE_MULTISELECT = 'multiselect';
    public const TYPE_CHECKBOX    = 'checkbox';
    public const TYPE_DATE        = 'date';
    public const TYPE_PRODUCT     = 'product';

    /** Wer das Feld der HR-Anlage-Rolle ausfüllt / Who fills in the field of the HR intake role */
    public const STAGE_INTAKE = 'intake';
    public const STAGE_PREPARATION = 'preparation';

    /** Gespeicherte Werte einer Checkbox / Stored values of a checkbox */
    public const CHECKBOX_YES = 'yes';
    public const CHECKBOX_NO = 'no';

    /** Höchstlänge eines Freitexts (Schutz der Prüfung per regulärem Ausdruck) / Maximum length of a free text */
    public const TEXT_MAX = 2000;

    /** Grenze für Rückschritte der Format-Prüfung (PHP-Standard: 1 000 000) / Backtrack limit of the format check */
    public const REGEX_BACKTRACK_LIMIT = 100000;

    public const TYPES = [
        self::TYPE_SELECT      => 'Auswahlfeld',
        self::TYPE_MULTISELECT => 'Mehrfachauswahl',
        self::TYPE_CHECKBOX    => 'Checkbox',
        self::TYPE_DATE        => 'Datumsauswahl',
        self::TYPE_PRODUCT     => 'Produktauswahl',
        self::TYPE_TEXT        => 'Freitext (mit Regex)',
    ];

    protected $fillable = [
        'role_id', 'label', 'label_en', 'fill_stage', 'visible_to_all', 'processes', 'type', 'options', 'regex', 'regex_hint',
        'required', 'sort_order', 'active',
    ];

    protected $casts = [
        'options'  => 'array',
        'required' => 'boolean',
        'visible_to_all' => 'boolean',
        'processes' => 'array',
        'active'   => 'boolean',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Validierungsregeln für dieses Feld, geschachtelt nach Eingabe-Schlüssel
     * (z. B. ['fields.5' => [...], 'fields.5.*' => [...]]).
     *
     * Validation rules for this field, nested by input key
     * (e.g. ['fields.5' => [...], 'fields.5.*' => [...]]).
     */
    /** Gilt das Feld für die angegebene Prozessart? / Does the field apply to the given process type? */
    public function appliesTo(string $process): bool
    {
        $list = $this->processes ?? [Process::ONBOARDING, Process::JOB_CHANGE, Process::EXIT, Process::MASTER_DATA_CHANGE];

        return in_array($process, $list, true);
    }

    /** Query-Scope: nur Felder der angegebenen Prozessart / Query scope: only fields of the given process type */
    public function scopeForProcess($query, string $process)
    {
        return $query->whereJsonContains('processes', $process);
    }

    /**
     * Bezeichnung in der Sprache der Oberfläche (EN nur, wenn gepflegt)
     *
     * Label in the interface language (EN only if maintained)
     */
    public function displayLabel(): string
    {
        return app()->getLocale() === 'en' && filled($this->label_en)
            ? $this->label_en
            : $this->label;
    }

    /**
     * Bezeichnung zweisprachig (für Mails/Exporte): "Deutsch / English"
     *
     * Label in both languages (for mails/exports): "Deutsch / English"
     */
    public function bilingualLabel(): string
    {
        return filled($this->label_en) && $this->label_en !== $this->label
            ? $this->label . ' / ' . $this->label_en
            : $this->label;
    }

    public function rulesFor(string $key, bool $forceOptional = false): array
    {
        $required = $this->required && ! $forceOptional;
        $base = $required ? 'required' : 'nullable';

        return match ($this->type) {
            self::TYPE_SELECT => [
                $key => [$base, \Illuminate\Validation\Rule::in($this->options ?? [])],
            ],
            self::TYPE_MULTISELECT => [
                $key => array_merge([$base, 'array'], $required ? ['min:1'] : []),
                $key . '.*' => [\Illuminate\Validation\Rule::in($this->options ?? [])],
            ],
            self::TYPE_CHECKBOX => [
                $key => [$required ? 'accepted' : 'nullable'],
            ],
            self::TYPE_DATE => [
                $key => [$base, 'date'],
            ],
            self::TYPE_PRODUCT => [
                $key => array_merge([$base, 'array'], $required ? ['min:1'] : []),
                $key . '.*' => ['integer', 'exists:products,id'],
            ],
            default => [ // Freitext / free text
                $key => array_merge(
                    ['bail', $base, 'max:' . self::TEXT_MAX],
                    $this->regex ? [$this->regexGuard(), 'regex:' . $this->pattern()] : [],
                ),
            ],
        };
    }

    /** Regulärer Ausdruck des Feldes mit Begrenzern / Regular expression of the field with delimiters */
    public function pattern(): string
    {
        return '~' . str_replace('~', '\~', (string) $this->regex) . '~u';
    }

    /**
     * Schutz gegen ReDoS: Der reguläre Ausdruck läuft zuerst mit kleiner
     * Rückschritt-Grenze. Bricht PCRE ab (zu aufwendig, z. B. „(a+)+$“ bei
     * langer Eingabe), gilt der Wert als ungültig, statt den Server lange zu
     * beschäftigen. Zusammen mit TEXT_MAX bleibt jede Prüfung kurz.
     *
     * Protection against ReDoS: the regular expression runs first with a small
     * backtrack limit. If PCRE gives up (too costly, e.g. "(a+)+$" on long
     * input), the value counts as invalid instead of keeping the server busy.
     * Together with TEXT_MAX every check stays short.
     */
    private function regexGuard(): \Closure
    {
        $pattern = $this->pattern();

        return function (string $attribute, mixed $value, \Closure $fail) use ($pattern) {
            if (! is_string($value) && ! is_numeric($value)) {
                return;   // übernimmt die regex-Regel / the regex rule takes over
            }
            $limit = ini_get('pcre.backtrack_limit');
            ini_set('pcre.backtrack_limit', (string) self::REGEX_BACKTRACK_LIMIT);
            $result = @preg_match($pattern, (string) $value);
            ini_set('pcre.backtrack_limit', (string) $limit);
            if ($result === false) {
                $fail(__(':attribute lässt sich mit dem hinterlegten Format nicht prüfen. Bitte den Wert kürzen oder das Format des Feldes vereinfachen lassen.'));
            }
        };
    }

    /** Eingabewert in den Speicherwert (String) umwandeln / Convert the input value into the stored value (string) */
    public function prepareValue(mixed $input): ?string
    {
        return match ($this->type) {
            self::TYPE_MULTISELECT => $input ? json_encode(array_values((array) $input), JSON_UNESCAPED_UNICODE) : null,
            self::TYPE_PRODUCT => $input ? json_encode(array_map('intval', array_values((array) $input))) : null,
            self::TYPE_CHECKBOX => $input ? self::CHECKBOX_YES : self::CHECKBOX_NO,
            default => $input !== null && $input !== '' ? (string) $input : null,
        };
    }

    /**
     * Speicherwert lesbar anzeigen (auch für Protokoll und Bericht)
     *
     * Display the stored value readably (also for log and report)
     */
    public function displayValue(?string $stored): ?string
    {
        if ($stored === null || $stored === '') {
            return null;
        }

        if ($this->type === self::TYPE_CHECKBOX) {
            return match ($stored) {
                self::CHECKBOX_YES => __('ja'),
                self::CHECKBOX_NO  => __('nein'),
                default            => $stored,
            };
        }

        if ($this->type === self::TYPE_MULTISELECT) {
            $items = json_decode($stored, true);
            return is_array($items) ? implode(', ', $items) : $stored;
        }

        if ($this->type === self::TYPE_DATE) {
            try {
                return \Illuminate\Support\Carbon::parse($stored)->format('d.m.Y');
            } catch (\Throwable) {
                return $stored;
            }
        }

        if ($this->type === self::TYPE_PRODUCT) {
            $ids = json_decode($stored, true);
            if (! is_array($ids) || $ids === []) {
                return $stored;
            }

            $products = Product::whereIn('id', $ids)->get();
            $sum = $products->whereNotNull('price')->sum('price');

            $text = $products->map(fn ($p) => $p->label())->implode('; ');
            if ($sum > 0 && $products->count() > 1) {
                $text .= ' – Summe: ' . number_format((float) $sum, 2, ',', '.') . ' €';
            }

            return $text ?: $stored;
        }

        return $stored;
    }
}
