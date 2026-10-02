<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Support\Facades\App;

trait HasLocalizedFields
{
    public function getLocalized(string $field): string|array|null
    {
        $translations = $this->getAttribute($field);

        if (!is_array($translations)) {
            return is_string($translations) ? $translations : null;
        }

        foreach (array_unique([App::currentLocale(), App::getFallbackLocale(), ...config('portfolio.locales')]) as $locale) {
            if (isset($translations[$locale])) {
                return $translations[$locale];
            }
        }

        return null;
    }
}
