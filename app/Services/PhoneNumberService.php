<?php

namespace App\Services;

class PhoneNumberService
{
    /**
     * International dialling codes the recipient filter can name, keyed by
     * code. +1 and +7 are shared by several countries, so they are labelled
     * by their main members rather than guessed apart.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const DIAL_CODES = [
        '1' => ['US', 'USA / Canada'], '7' => ['RU', 'Russia'], '20' => ['EG', 'Egypt'], '27' => ['ZA', 'South Africa'],
        '30' => ['GR', 'Greece'], '31' => ['NL', 'Netherlands'], '32' => ['BE', 'Belgium'], '33' => ['FR', 'France'],
        '34' => ['ES', 'Spain'], '36' => ['HU', 'Hungary'], '39' => ['IT', 'Italy'], '40' => ['RO', 'Romania'],
        '41' => ['CH', 'Switzerland'], '43' => ['AT', 'Austria'], '44' => ['GB', 'United Kingdom'], '45' => ['DK', 'Denmark'],
        '46' => ['SE', 'Sweden'], '47' => ['NO', 'Norway'], '48' => ['PL', 'Poland'], '49' => ['DE', 'Germany'],
        '52' => ['MX', 'Mexico'], '54' => ['AR', 'Argentina'], '55' => ['BR', 'Brazil'], '57' => ['CO', 'Colombia'],
        '60' => ['MY', 'Malaysia'], '61' => ['AU', 'Australia'], '62' => ['ID', 'Indonesia'], '63' => ['PH', 'Philippines'],
        '64' => ['NZ', 'New Zealand'], '65' => ['SG', 'Singapore'], '66' => ['TH', 'Thailand'], '81' => ['JP', 'Japan'],
        '82' => ['KR', 'South Korea'], '84' => ['VN', 'Vietnam'], '86' => ['CN', 'China'], '90' => ['TR', 'Turkey'],
        '91' => ['IN', 'India'], '92' => ['PK', 'Pakistan'], '94' => ['LK', 'Sri Lanka'], '98' => ['IR', 'Iran'],
        '211' => ['SS', 'South Sudan'], '212' => ['MA', 'Morocco'], '213' => ['DZ', 'Algeria'], '216' => ['TN', 'Tunisia'],
        '218' => ['LY', 'Libya'], '220' => ['GM', 'Gambia'], '221' => ['SN', 'Senegal'], '222' => ['MR', 'Mauritania'],
        '223' => ['ML', 'Mali'], '224' => ['GN', 'Guinea'], '225' => ['CI', "Côte d'Ivoire"], '226' => ['BF', 'Burkina Faso'],
        '227' => ['NE', 'Niger'], '228' => ['TG', 'Togo'], '229' => ['BJ', 'Benin'], '230' => ['MU', 'Mauritius'],
        '231' => ['LR', 'Liberia'], '232' => ['SL', 'Sierra Leone'], '233' => ['GH', 'Ghana'], '234' => ['NG', 'Nigeria'],
        '235' => ['TD', 'Chad'], '236' => ['CF', 'Central African Republic'], '237' => ['CM', 'Cameroon'], '238' => ['CV', 'Cape Verde'],
        '239' => ['ST', 'São Tomé and Príncipe'], '240' => ['GQ', 'Equatorial Guinea'], '241' => ['GA', 'Gabon'], '242' => ['CG', 'Congo'],
        '243' => ['CD', 'DR Congo'], '244' => ['AO', 'Angola'], '245' => ['GW', 'Guinea-Bissau'], '248' => ['SC', 'Seychelles'],
        '249' => ['SD', 'Sudan'], '250' => ['RW', 'Rwanda'], '251' => ['ET', 'Ethiopia'], '252' => ['SO', 'Somalia'],
        '253' => ['DJ', 'Djibouti'], '254' => ['KE', 'Kenya'], '255' => ['TZ', 'Tanzania'], '256' => ['UG', 'Uganda'],
        '257' => ['BI', 'Burundi'], '258' => ['MZ', 'Mozambique'], '260' => ['ZM', 'Zambia'], '261' => ['MG', 'Madagascar'],
        '263' => ['ZW', 'Zimbabwe'], '264' => ['NA', 'Namibia'], '265' => ['MW', 'Malawi'], '266' => ['LS', 'Lesotho'],
        '267' => ['BW', 'Botswana'], '268' => ['SZ', 'Eswatini'], '269' => ['KM', 'Comoros'], '291' => ['ER', 'Eritrea'],
        '351' => ['PT', 'Portugal'], '353' => ['IE', 'Ireland'], '358' => ['FI', 'Finland'], '380' => ['UA', 'Ukraine'],
        '420' => ['CZ', 'Czechia'], '852' => ['HK', 'Hong Kong'], '880' => ['BD', 'Bangladesh'], '961' => ['LB', 'Lebanon'],
        '962' => ['JO', 'Jordan'], '964' => ['IQ', 'Iraq'], '965' => ['KW', 'Kuwait'], '966' => ['SA', 'Saudi Arabia'],
        '968' => ['OM', 'Oman'], '971' => ['AE', 'United Arab Emirates'], '972' => ['IL', 'Israel'], '973' => ['BH', 'Bahrain'],
        '974' => ['QA', 'Qatar'],
    ];

    /**
     * Ghana numbers only. Accepted shapes: local 0XXXXXXXXX, international
     * 233XXXXXXXXX (optionally 00233...), and the bare 9-digit subscriber
     * number XXXXXXXXX that spreadsheets produce when they drop the leading
     * zero (Ghana subscriber numbers start with 2, 3 or 5). Anything else —
     * an explicit non-233 "+" country code, a different digit count, or no
     * digits — is foreign, never guessed into a Ghanaian number.
     */
    public static function isGhanaNumber(?string $phone): bool
    {
        return self::toArkeselFormat($phone) !== null;
    }

    /** The Arkesel-ready 233XXXXXXXXX format, or null if this isn't a Ghana number. */
    public static function toArkeselFormat(?string $phone): ?string
    {
        $raw = trim($phone ?? '');
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits === '') {
            return null;
        }

        if (preg_match('/^(?:00)?(233\d{9})$/', $digits, $match)) {
            return $match[1];
        }

        if (str_starts_with($raw, '+')) {
            return null;
        }

        if (preg_match('/^0(\d{9})$/', $digits, $match) || preg_match('/^([235]\d{8})$/', $digits, $match)) {
            return '233'.$match[1];
        }

        return null;
    }

    /**
     * Country names a manager can choose for someone: the names countryOf() gives, so a chosen
     * country and one read from a phone group together in reports. "+1" numbers stay
     * "USA / Canada" there, but here the two are separate.
     *
     * @return array<int, string>
     */
    public static function countryNames(): array
    {
        return collect(self::DIAL_CODES)->pluck(1)
            ->flatMap(fn (string $name) => $name === 'USA / Canada' ? ['Canada', 'United States'] : [$name])
            ->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
    }

    /**
     * The country a phone number belongs to, as [ISO code, name], or null when
     * there is no number or its country can't be told. Ghana follows
     * isGhanaNumber(). Other numbers need their country code: written with
     * "+" or "00", or as a bare 11-15 digit international number (234803...).
     * The one local shape recognised abroad is Nigeria's 11-digit
     * 0[7-9][01]XXXXXXXX, which can't be mistaken for a 10-digit Ghana number.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function countryOf(?string $phone): ?array
    {
        if (self::isGhanaNumber($phone)) {
            return self::DIAL_CODES['233'];
        }

        $raw = trim($phone ?? '');
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if (preg_match('/^0[7-9][01]\d{8}$/', $digits)) {
            return self::DIAL_CODES['234'];
        }

        if (str_starts_with($raw, '+')) {
            $international = $digits;
        } elseif (str_starts_with($digits, '00')) {
            $international = substr($digits, 2);
        } elseif (! str_starts_with($digits, '0') && strlen($digits) >= 11 && strlen($digits) <= 15) {
            $international = $digits;
        } else {
            return null;
        }

        foreach ([3, 2, 1] as $length) {
            $code = substr($international, 0, $length);
            if (isset(self::DIAL_CODES[$code])) {
                return self::DIAL_CODES[$code];
            }
        }

        return null;
    }
}
