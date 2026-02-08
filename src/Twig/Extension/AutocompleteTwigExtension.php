<?php

namespace Kerrialnewham\Autocomplete\Twig\Extension;

use Kerrialnewham\Autocomplete\Security\AutocompleteSigner;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AutocompleteTwigExtension extends AbstractExtension
{
    public function __construct(
        private readonly AutocompleteSigner $signer,
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
        $userId = $this->security->getUser()?->getUserIdentifier() ?? '';

        return $this->signer->sign(
            routeName: $routeName,
            provider: $provider,
            theme: $theme,
            translationDomain: (string) ($translationDomain ?? ''),
            choiceLabel: (string) ($choiceLabel ?? ''),
            choiceValue: (string) ($choiceValue ?? ''),
            userId: $userId,
        );
    }
}
