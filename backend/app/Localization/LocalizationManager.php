<?php

declare(strict_types=1);

namespace App\Localization;

class LocalizationManager
{
    public function __construct(
        private readonly array $config,
    ) {}

    public function isSupportedLanguage(string $language): bool
    {
        $language = strtolower($language);

        return in_array($language, $this->getLanguages(), true);
    }

    public function getDefaultLanguage(): string
    {
        return $this->config['default_language'];
    }

    public function getLanguages(): array
    {
        return $this->config['supported_languages'];
    }

    public function matchLanguage(string $language): string
    {
        $language = strtolower($language);

        if ($this->isSupportedLanguage($language)) {
            return $language;
        }

        return $this->getDefaultLanguage();
    }
}
