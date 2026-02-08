<?php

namespace Kerrialnewham\Autocomplete\Security;

final class AutocompleteSigner
{
    public function __construct(
        private readonly string $secret,
    ) {}

    /** @return array{ts:int,sig:string} */
    public function sign(
        string $routeName,
        string $provider,
        string $theme,
        string $translationDomain,
        string $choiceLabel,
        string $choiceValue,
        string $userId = '',
    ): array {
        $ts = time();

        $payload = implode('|', [
            $routeName,
            $provider,
            $theme,
            $translationDomain,
            $choiceLabel,
            $choiceValue,
            $userId,
            (string) $ts,
        ]);

        return [
            'ts' => $ts,
            'sig' => hash_hmac('sha256', $payload, $this->secret),
        ];
    }
}
