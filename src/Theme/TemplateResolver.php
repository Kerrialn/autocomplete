<?php

namespace Kerrialnewham\Autocomplete\Theme;

final class TemplateResolver
{
    public function __construct(
        private readonly string $defaultTheme,
        /** @var string[] */
        private readonly array $allowedThemes,
    ) {}

    public function theme(?string $requested): string
    {
        $t = $requested ?: $this->defaultTheme;
        return in_array($t, $this->allowedThemes, true) ? $t : $this->defaultTheme;
    }

    public function options(string $theme): string
    {
        return sprintf('@Autocomplete/theme/%s/_options.html.twig', $theme);
    }

    public function chip(string $theme): string
    {
        return sprintf('@Autocomplete/theme/%s/_chip.html.twig', $theme);
    }
}
