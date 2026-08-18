<?php

namespace App\Localization;

/**
 * Centralise les langues réellement prises en charge par l’interface SafeCity.
 */
final class SupportedLocale
{
    public const DEFAULT = 'fr';

    /**
     * @var list<string>
     */
    public const ALL = ['fr', 'en', 'es', 'pt', 'it', 'de', 'ja', 'pl', 'ru'];

    /**
     * Les noms sont volontairement écrits dans leur propre langue afin que
     * l’utilisateur puisse toujours reconnaître son choix.
     *
     * @return array<string, string>
     */
    public static function choices(): array
    {
        return [
            'Français' => 'fr',
            'English' => 'en',
            'Español' => 'es',
            'Português' => 'pt',
            'Italiano' => 'it',
            'Deutsch' => 'de',
            '日本語' => 'ja',
            'Polski' => 'pl',
            'Русский' => 'ru',
        ];
    }

    /**
     * Garantit qu’une ancienne valeur ou une valeur inattendue retombe en
     * français au lieu d’activer une langue partiellement configurée.
     */
    public static function normalize(?string $locale): string
    {
        $normalized = strtolower(substr((string) $locale, 0, 2));

        return in_array($normalized, self::ALL, true) ? $normalized : self::DEFAULT;
    }
}