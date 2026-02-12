<?php

namespace Kerrialnewham\Autocomplete\Form\DataTransformer;

use Kerrialnewham\Autocomplete\Phone\DialCodeMap;
use Symfony\Component\Form\DataTransformerInterface;

class PhoneNumberTransformer implements DataTransformerInterface
{
    /**
     * Transform an E.164 string to the form array.
     *
     * @param string|null $value E.164 phone number (e.g. "+447911123456")
     * @return array{dialCode: string|null, number: string|null}
     */
    public function transform(mixed $value): array
    {
        if ($value === null || $value === '') {
            return ['dialCode' => null, 'number' => null];
        }

        $parsed = DialCodeMap::parseE164($value);

        if ($parsed === null) {
            return ['dialCode' => null, 'number' => null];
        }

        return [
            'dialCode' => $parsed['countryCode'],
            'number' => $parsed['number'],
        ];
    }

    /**
     * Reverse transform the form array to an E.164 string.
     *
     * @param mixed $data Array with 'dialCode' (country code) and 'number' keys
     * @return string|null E.164 phone number or null
     */
    public function reverseTransform(mixed $data): ?string
    {
        if (!is_array($data)) {
            return null;
        }

        $countryCode = $data['dialCode'] ?? null;
        $number = $data['number'] ?? null;

        if (($countryCode === null || $countryCode === '') && ($number === null || $number === '')) {
            return null;
        }

        if ($countryCode === null || $countryCode === '') {
            return null;
        }

        $dialCode = DialCodeMap::dialCode($countryCode);

        if ($dialCode === null) {
            return null;
        }

        // Strip hyphens from dial code and non-digit chars from the subscriber number
        $numericDialCode = str_replace('-', '', $dialCode);
        $cleanNumber = preg_replace('/\D/', '', $number ?? '');

        return $numericDialCode . $cleanNumber;
    }
}
