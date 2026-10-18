<?php

namespace App\Support;

use App\Services\LicenseService;

/**
 * Angaben zum Produkt für Footer, Hilfe-Seite und Fehlerseiten (Standard 03).
 *
 * Product details for footer, help page and error pages (Standard 03).
 */
class ProductInfo
{
    public static function name(): string
    {
        return (string) config('product.name');
    }

    /**
     * „Community Edition“ ohne gültige Lizenz, sonst „Supported Edition“
     *
     * “Community Edition” without a valid licence, otherwise “Supported Edition”
     */
    public static function edition(): string
    {
        try {
            return LicenseService::current() ? 'Supported Edition' : 'Community Edition';
        } catch (\Throwable) {
            return 'Community Edition';
        }
    }

    /**
     * Link für „freie Software (AGPL-3.0)“: eigener Quellcode, sonst GitHub
     *
     * Link for “free software (AGPL-3.0)”: own source code, otherwise GitHub
     */
    public static function sourceUrl(): ?string
    {
        return config('product.source_url') ?: (config('product.github') ?: null);
    }

    public static function projectUrl(): string
    {
        return config('product.project_url') ?: 'https://www.mai-digital.de';
    }
}
