<?php

namespace Kerrialnewham\Autocomplete\Theme;

final class TemplateResolver
{
    private const DEFAULT_THEME = 'default';

    /**
     * Validates and returns a safe theme name.
     *
     * @param string|null $requested The requested theme name
     * @return string The validated theme name or default
     */
    public function theme(?string $requested): string
    {
        $theme = $requested ?: self::DEFAULT_THEME;

        // Security: validate theme name to prevent path traversal attacks
        // Only allow alphanumeric characters, hyphens, and underscores
        if (!$this->isValidThemeName($theme)) {
            return self::DEFAULT_THEME;
        }

        return $theme;
    }

    /**
     * Returns the template path for options rendering.
     */
    public function options(string $theme): string
    {
        return sprintf('@Autocomplete/theme/%s/_options.html.twig', $theme);
    }

    /**
     * Returns the template path for chip rendering.
     */
    public function chip(string $theme): string
    {
        return sprintf('@Autocomplete/theme/%s/_chip.html.twig', $theme);
    }

    /**
     * Validates that a theme name contains only safe characters.
     *
     * @param string $theme The theme name to validate
     * @return bool True if the theme name is valid
     */
    private function isValidThemeName(string $theme): bool
    {
        // Only allow alphanumeric characters, hyphens, and underscores
        // This prevents path traversal attacks like "../../../etc/passwd"
        return preg_match('/^[a-zA-Z0-9_-]+$/', $theme) === 1;
    }
}
