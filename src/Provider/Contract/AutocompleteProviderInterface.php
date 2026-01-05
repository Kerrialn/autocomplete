<?php

namespace Kerrialnewham\Autocomplete\Provider\Contract;

interface AutocompleteProviderInterface
{
    public function getName(): string;

    /** @return array<int, array{id:string,label:string,meta?:array}> */
    public function search(string $query, int $limit, array $selected): array;

}
