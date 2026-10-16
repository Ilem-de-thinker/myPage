<?php

namespace App\Shared\Support;

/**
 * Nigerian banks, by the code Paystack expects.
 *
 * Kept as a static list rather than fetched live: the list is stable, and a
 * dropdown that empties itself when an API call fails would quietly stop letting
 * sellers withdraw. The codes come from Paystack's bank list endpoint.
 */
final class NigerianBanks
{
    /**
     * @var array<string, string>
     */
    private const BANKS = [
        '001' => 'Access Bank',
        '002' => 'Ecobank',
        '003' => 'First Bank of Nigeria',
        '004' => 'First Bank of Nigeria (USSD)',
        '005' => 'Guaranty Trust Bank',
        '006' => 'Diamond Trust Bank',
        '007' => 'Providus Bank',
        '008' => 'Stanbic IBTC Bank',
        '009' => 'Sterling Bank',
        '010' => ' Union Bank',
        '011' => 'Unity Bank',
        '012' => 'Zenith Bank',
        '013' => 'Polaris Bank',
        '014' => 'Fidelity Bank',
        '015' => 'Jaiz Bank',
        '016' => 'Wema Bank',
        '017' => 'Moniepoint MFB',
        '018' => 'Globacom Bank',
        '019' => 'Kuda Bank',
        '020' => 'Airtel Bank',
        '021' => 'Coronation Merchant Bank',
        '022' => 'Opay',
        '023' => 'PalmPay',
        '024' => 'Providus Bank',
        '025' => 'Fidelity Bank',
    ];

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return self::BANKS;
    }

    public static function exists(?string $code): bool
    {
        return $code !== null && array_key_exists($code, self::BANKS);
    }

    public static function name(?string $code): ?string
    {
        return $code === null ? null : (self::BANKS[$code] ?? null);
    }
}
