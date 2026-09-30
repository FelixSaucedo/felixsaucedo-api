<?php

declare(strict_types=1);

namespace App\Models\Concerns;

trait HasLocalizedFields
{
    public function getLocalized(string $field, ?string $lang = null): string|array|null
    {
        $requested = $lang ?? request()->input('lang', 'es');
        $locale = in_array($requested, ['es', 'en'], true) ? $requested : 'es';
        $translations = $this->getAttribute($field);

        if (!is_array($translations)) {
            return is_string($translations) ? $translations : null;
        }

        return $translations[$locale] ?? $translations['es'] ?? $translations['en'] ?? null;
    }
}
