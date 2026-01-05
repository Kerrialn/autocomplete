<?php

namespace Kerrialnewham\Autocomplete\Provider;

interface ChipProviderInterface
{
    /**
     * Get a single item by ID for rendering a chip.
     *
     * @param string $id
     * @return array|null Array with 'id', 'label', and optional 'meta', or null if not found
     */
    public function get(string $id): ?array;
}
