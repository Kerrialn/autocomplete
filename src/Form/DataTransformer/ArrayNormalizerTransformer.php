<?php

namespace Kerrialnewham\Autocomplete\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;

/**
 * Normalizes submitted array data for autocomplete fields.
 * 
 * Handles:
 * - Nested arrays like [["en"], ["ru"]] -> ["en", "ru"]
 * - {id, label} objects like [{id: "en", label: "English"}] -> ["en"]
 * - Empty value filtering
 */
class ArrayNormalizerTransformer implements DataTransformerInterface
{
    public function transform(mixed $value): mixed
    {
        // Pass through - no transformation needed for display
        return $value;
    }

    public function reverseTransform(mixed $value): mixed
    {
        if (!\is_array($value)) {
            return $value;
        }

        $normalized = [];
        foreach ($value as $item) {
            // Handle nested arrays like [["en"]] -> ["en"]
            if (\is_array($item) && count($item) === 1 && isset($item[0]) && !is_array($item[0])) {
                $item = $item[0];
            }
            
            // Handle {id, label} objects
            if (\is_array($item)) {
                if (isset($item['id'])) {
                    $id = $item['id'];
                    
                    // Handle nested structures
                    while (\is_array($id) && isset($id['id'])) {
                        $id = $id['id'];
                    }
                    
                    $item = $id;
                } else {
                    // No 'id' key, skip this entry
                    continue;
                }
            }
            
            // Filter out empty values
            if ($item !== '' && $item !== null && $item !== []) {
                $normalized[] = $item;
            }
        }

        return array_values($normalized);
    }
}
