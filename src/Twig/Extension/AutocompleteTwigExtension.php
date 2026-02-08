<?php

namespace Kerrialnewham\Autocomplete\Twig\Extension;

use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AutocompleteTwigExtension extends AbstractExtension
{
    public function __construct(
        private readonly string $signingSecret,
        private readonly Security $security,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('autocomplete_sig', [$this, 'sig']),
        ];
    }

    /** @return array{ts:int,sig:string} */
    public function sig(
        string $routeName,
        string $provider,
        string $theme,
        ?string $translationDomain,
        ?string $choiceLabel,
        ?string $choiceValue,
    ): array {
        $ts = time();

        $td = (string) ($translationDomain ?? '');
        $cl = (string) ($choiceLabel ?? '');
        $cv = (string) ($choiceValue ?? '');

        $userId = $this->security->getUser()?->getUserIdentifier() ?? '';

        $payload = implode('|', [
            $routeName,
            $provider,
            $theme,
            $td,
            $cl,
            $cv,
            $userId,
            (string) $ts,
        ]);

        return [
            'ts' => $ts,
            'sig' => hash_hmac('sha256', $payload, $this->signingSecret),
        ];
    }
}
