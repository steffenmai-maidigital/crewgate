<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCategory extends Model
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

    protected $fillable = ['name', 'name_en', 'color', 'sort_order'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }
}
