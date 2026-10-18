<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    /**
     * Name in der Sprache der Oberfläche (EN nur, wenn gepflegt)
     *
     * Name in the interface language (EN only if maintained)
     */
    public function displayName(): string
    {
        return app()->getLocale() === 'en' && filled($this->name_en)
            ? $this->name_en
            : $this->name;
    }

    protected $fillable = [
        'name', 'name_en', 'article_no', 'manufacturer_no', 'price', 'image_path', 'active',
        'custom_field_id', 'category_id', 'deactivated_at',
    ];

    protected $casts = [
        'price'  => 'decimal:2',
        'active' => 'boolean',
        'deactivated_at' => 'datetime',
    ];

    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function field(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CustomField::class, 'custom_field_id');
    }

    public function priceFormatted(): ?string
    {
        return $this->price !== null
            ? number_format((float) $this->price, 2, ',', '.') . ' €'
            : null;
    }

    /** Kurzbeschreibung für Anzeige und Protokoll / Short description for display and log */
    public function label(): string
    {
        $parts = [$this->displayName()];
        if ($this->article_no) {
            $parts[] = 'Art. ' . $this->article_no;
        }
        if ($this->manufacturer_no) {
            $parts[] = 'Herst. ' . $this->manufacturer_no;
        }
        if ($this->price !== null) {
            $parts[] = $this->priceFormatted();
        }

        return implode(', ', $parts);
    }
}
