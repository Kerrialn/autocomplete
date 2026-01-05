<?php

namespace Kerrialnewham\Autocomplete\Provider\Contract;

interface ChipProviderInterface extends AutocompleteProviderInterface
{
    /**
     * Fetch a single item by id for chip rendering.
     *
     * @return array{id:string,label:string,meta:array|null}|null
     */
    public function get(string $id): ?array;
}