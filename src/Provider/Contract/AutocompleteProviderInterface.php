<?php

namespace Kerrialnewham\Autocomplete\Provider\Contract;

interface AutocompleteProviderInterface
{
    /** @return array<int, array{id:string,label:string,meta?:array}> */
    public function search(string $query, int $limit, array $selected): array;
}
