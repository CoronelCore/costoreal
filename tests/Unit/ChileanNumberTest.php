<?php

namespace Tests\Unit;

use App\Support\ChileanNumber;
use PHPUnit\Framework\TestCase;

class ChileanNumberTest extends TestCase
{
    public function test_comma_decimals_and_dot_thousands_are_read_as_written_in_chile(): void
    {
        foreach (['450.000' => '450000', '1.050' => '1050', '1.050,5' => '1050.5', '12.345.678,90' => '12345678.90',
            '175,5' => '175.5', '$ 12.990' => '12990', ' 900 ' => '900'] as $typed => $read) {
            $this->assertSame($read, ChileanNumber::normalize($typed), $typed);
        }
    }

    public function test_dot_decimals_and_invalid_text_stay_for_the_validator(): void
    {
        foreach (['175.5', '0.025', '0.030', '950.12345678', '1000', '1.2.3', 'abc'] as $typed) {
            $this->assertSame($typed, ChileanNumber::normalize($typed), $typed);
        }
        $this->assertNull(ChileanNumber::normalize(null));
        $this->assertSame(3, ChileanNumber::normalize(3));
        $this->assertSame('', ChileanNumber::normalize(''));
    }

    public function test_only_the_requested_fields_present_in_the_input_are_returned(): void
    {
        $this->assertSame(['monto' => '450000'], ChileanNumber::fields(['monto' => '450.000', 'concepto' => '1.000 cajas'], ['monto', 'weight']));
    }
}
