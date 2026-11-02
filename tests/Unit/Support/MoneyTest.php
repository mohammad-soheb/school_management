<?php

namespace Tests\Unit\Support;

use App\Support\Money\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    /** @return array<string, array{0: string|int|null, 1: ?int}> */
    public static function parseCases(): array
    {
        return [
            'whole rupees' => ['1250', 125000],
            'with paise' => ['1250.50', 125050],
            'single decimal digit' => ['99.5', 9950],
            'indian grouping' => ['1,23,456.75', 12345675],
            'rupee symbol' => ['₹ 10', 1000],
            'rs prefix' => ['Rs. 10.05', 1005],
            'zero' => ['0', 0],
            'integer input is rupees' => [15, 1500],
            'negative (a reversal)' => ['-20.25', -2025],
            'three decimals rejected' => ['10.999', null],
            'letters rejected' => ['ten', null],
            'empty rejected' => ['', null],
            'null rejected' => [null, null],
            'scientific notation rejected' => ['1e3', null],
        ];
    }

    #[DataProvider('parseCases')]
    public function test_parse(string|int|null $input, ?int $expected): void
    {
        $this->assertSame($expected, Money::parse($input));
    }

    /** @return array<string, array{0: int, 1: string}> */
    public static function formatCases(): array
    {
        return [
            'paise only' => [5, '₹0.05'],
            'under a thousand' => [99900, '₹999.00'],
            'thousands' => [125050, '₹1,250.50'],
            'lakhs use indian grouping' => [12345675, '₹1,23,456.75'],
            'crores' => [1234567800, '₹1,23,45,678.00'],
            'negative' => [-2025, '-₹20.25'],
            'zero' => [0, '₹0.00'],
        ];
    }

    #[DataProvider('formatCases')]
    public function test_format(int $paise, string $expected): void
    {
        $this->assertSame($expected, Money::format($paise));
    }

    public function test_format_options(): void
    {
        $this->assertSame('1,250.50', Money::format(125050, symbol: false));
        $this->assertSame('₹1,250', Money::format(125000, decimals: false));
        $this->assertSame('₹1,250.50', Money::format(125050, decimals: false));
    }

    public function test_plain_is_a_lossless_decimal_string(): void
    {
        $this->assertSame('1250.50', Money::plain(125050));
        $this->assertSame('0.05', Money::plain(5));
        $this->assertSame('-20.25', Money::plain(-2025));
    }

    public function test_parse_then_plain_round_trips_without_float_drift(): void
    {
        foreach (['0.10', '0.20', '0.30', '19.99', '1000000.01'] as $amount) {
            $this->assertSame($amount, Money::plain(Money::parse($amount)));
        }
    }
}
