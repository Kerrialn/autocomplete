<?php

namespace Kerrialnewham\Autocomplete\Phone;

final class DialCodeMap
{
    private const DIAL_CODES = [
        'AF' => '+93', 'AL' => '+355', 'DZ' => '+213', 'AS' => '+1-684', 'AD' => '+376',
        'AO' => '+244', 'AI' => '+1-264', 'AG' => '+1-268', 'AR' => '+54', 'AM' => '+374',
        'AW' => '+297', 'AU' => '+61', 'AT' => '+43', 'AZ' => '+994', 'BS' => '+1-242',
        'BH' => '+973', 'BD' => '+880', 'BB' => '+1-246', 'BY' => '+375', 'BE' => '+32',
        'BZ' => '+501', 'BJ' => '+229', 'BM' => '+1-441', 'BT' => '+975', 'BO' => '+591',
        'BA' => '+387', 'BW' => '+267', 'BR' => '+55', 'BN' => '+673', 'BG' => '+359',
        'BF' => '+226', 'BI' => '+257', 'KH' => '+855', 'CM' => '+237', 'CA' => '+1',
        'CV' => '+238', 'KY' => '+1-345', 'CF' => '+236', 'TD' => '+235', 'CL' => '+56',
        'CN' => '+86', 'CO' => '+57', 'KM' => '+269', 'CG' => '+242', 'CD' => '+243',
        'CK' => '+682', 'CR' => '+506', 'CI' => '+225', 'HR' => '+385', 'CU' => '+53',
        'CW' => '+599', 'CY' => '+357', 'CZ' => '+420', 'DK' => '+45', 'DJ' => '+253',
        'DM' => '+1-767', 'DO' => '+1-809', 'EC' => '+593', 'EG' => '+20', 'SV' => '+503',
        'GQ' => '+240', 'ER' => '+291', 'EE' => '+372', 'SZ' => '+268', 'ET' => '+251',
        'FK' => '+500', 'FO' => '+298', 'FJ' => '+679', 'FI' => '+358', 'FR' => '+33',
        'GF' => '+594', 'PF' => '+689', 'GA' => '+241', 'GM' => '+220', 'GE' => '+995',
        'DE' => '+49', 'GH' => '+233', 'GI' => '+350', 'GR' => '+30', 'GL' => '+299',
        'GD' => '+1-473', 'GP' => '+590', 'GU' => '+1-671', 'GT' => '+502', 'GN' => '+224',
        'GW' => '+245', 'GY' => '+592', 'HT' => '+509', 'HN' => '+504', 'HK' => '+852',
        'HU' => '+36', 'IS' => '+354', 'IN' => '+91', 'ID' => '+62', 'IR' => '+98',
        'IQ' => '+964', 'IE' => '+353', 'IL' => '+972', 'IT' => '+39', 'JM' => '+1-876',
        'JP' => '+81', 'JO' => '+962', 'KZ' => '+7', 'KE' => '+254', 'KI' => '+686',
        'KP' => '+850', 'KR' => '+82', 'KW' => '+965', 'KG' => '+996', 'LA' => '+856',
        'LV' => '+371', 'LB' => '+961', 'LS' => '+266', 'LR' => '+231', 'LY' => '+218',
        'LI' => '+423', 'LT' => '+370', 'LU' => '+352', 'MO' => '+853', 'MG' => '+261',
        'MW' => '+265', 'MY' => '+60', 'MV' => '+960', 'ML' => '+223', 'MT' => '+356',
        'MH' => '+692', 'MQ' => '+596', 'MR' => '+222', 'MU' => '+230', 'YT' => '+262',
        'MX' => '+52', 'FM' => '+691', 'MD' => '+373', 'MC' => '+377', 'MN' => '+976',
        'ME' => '+382', 'MS' => '+1-664', 'MA' => '+212', 'MZ' => '+258', 'MM' => '+95',
        'NA' => '+264', 'NR' => '+674', 'NP' => '+977', 'NL' => '+31', 'NC' => '+687',
        'NZ' => '+64', 'NI' => '+505', 'NE' => '+227', 'NG' => '+234', 'NU' => '+683',
        'NF' => '+672', 'MK' => '+389', 'MP' => '+1-670', 'NO' => '+47', 'OM' => '+968',
        'PK' => '+92', 'PW' => '+680', 'PS' => '+970', 'PA' => '+507', 'PG' => '+675',
        'PY' => '+595', 'PE' => '+51', 'PH' => '+63', 'PL' => '+48', 'PT' => '+351',
        'PR' => '+1-787', 'QA' => '+974', 'RE' => '+262', 'RO' => '+40', 'RU' => '+7',
        'RW' => '+250', 'BL' => '+590', 'SH' => '+290', 'KN' => '+1-869', 'LC' => '+1-758',
        'MF' => '+590', 'PM' => '+508', 'VC' => '+1-784', 'WS' => '+685', 'SM' => '+378',
        'ST' => '+239', 'SA' => '+966', 'SN' => '+221', 'RS' => '+381', 'SC' => '+248',
        'SL' => '+232', 'SG' => '+65', 'SX' => '+1-721', 'SK' => '+421', 'SI' => '+386',
        'SB' => '+677', 'SO' => '+252', 'ZA' => '+27', 'SS' => '+211', 'ES' => '+34',
        'LK' => '+94', 'SD' => '+249', 'SR' => '+597', 'SE' => '+46', 'CH' => '+41',
        'SY' => '+963', 'TW' => '+886', 'TJ' => '+992', 'TZ' => '+255', 'TH' => '+66',
        'TL' => '+670', 'TG' => '+228', 'TK' => '+690', 'TO' => '+676', 'TT' => '+1-868',
        'TN' => '+216', 'TR' => '+90', 'TM' => '+993', 'TC' => '+1-649', 'TV' => '+688',
        'UG' => '+256', 'UA' => '+380', 'AE' => '+971', 'GB' => '+44', 'US' => '+1',
        'UY' => '+598', 'UZ' => '+998', 'VU' => '+678', 'VA' => '+379', 'VE' => '+58',
        'VN' => '+84', 'VG' => '+1-284', 'VI' => '+1-340', 'WF' => '+681', 'YE' => '+967',
        'ZM' => '+260', 'ZW' => '+263',
    ];

    private static ?array $reverseMap = null;

    public static function all(): array
    {
        return self::DIAL_CODES;
    }

    public static function dialCode(string $countryCode): ?string
    {
        return self::DIAL_CODES[strtoupper($countryCode)] ?? null;
    }

    /**
     * Parse an E.164 phone number into its components.
     *
     * @return array{countryCode: string, dialCode: string, number: string}|null
     */
    public static function parseE164(string $e164): ?array
    {
        $e164 = trim($e164);

        if ($e164 === '' || $e164[0] !== '+') {
            return null;
        }

        $reverseMap = self::buildReverseMap();

        // Try longest prefix first to resolve ambiguous codes (e.g. +1242 → BS before +1 → US)
        foreach ($reverseMap as $numericCode => $countryCode) {
            if (str_starts_with($e164, $numericCode)) {
                return [
                    'countryCode' => $countryCode,
                    'dialCode' => self::DIAL_CODES[$countryCode],
                    'number' => substr($e164, strlen($numericCode)),
                ];
            }
        }

        return null;
    }

    /**
     * Build a reverse map from numeric dial code (hyphens stripped) to country code,
     * sorted longest-first so specific codes match before generic ones.
     *
     * @return array<string, string>
     */
    private static function buildReverseMap(): array
    {
        if (self::$reverseMap !== null) {
            return self::$reverseMap;
        }

        $map = [];
        foreach (self::DIAL_CODES as $countryCode => $dialCode) {
            $numeric = str_replace('-', '', $dialCode);
            // If multiple countries share the same numeric code, the first one wins.
            // NANP entries with suffixes (e.g. +1-242) will have longer keys and match first.
            if (!isset($map[$numeric])) {
                $map[$numeric] = $countryCode;
            }
        }

        // Sort by key length descending so longest codes match first
        uksort($map, fn (string $a, string $b) => strlen($b) <=> strlen($a));

        self::$reverseMap = $map;

        return self::$reverseMap;
    }
}
