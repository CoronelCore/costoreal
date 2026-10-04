<?php

namespace Tests\Unit;

use App\Modules\Importations\Importing\DelimitedReader;
use DomainException;
use PHPUnit\Framework\TestCase;

class DelimitedReaderTest extends TestCase
{
    public function test_utf8_bom_quoted_comma_multiline_and_tab_are_preserved(): void
    {
        $rows = (new DelimitedReader)->read("\xEF\xBB\xBFnombre,cantidad\r\n\"Equipo, versión \"\"pro\"\"\",3\r\n\"Sensor\nde precisión\",2\n\n", ',');
        $this->assertSame(['nombre', 'cantidad'], $rows[0]);
        $this->assertSame(['Equipo, versión "pro"', '3'], $rows[1]);
        $this->assertSame(["Sensor\nde precisión", '2'], $rows[2]);
        $this->assertSame([['Sensor', '2', '100.10']], (new DelimitedReader)->read("Sensor\t2\t100.10", 'tab'));
    }

    public function test_binary_unclosed_quote_and_limits_fail(): void
    {
        foreach (["bad\0data", '"sin cierre', str_repeat("x\n", 1002), str_repeat('x,', 33), str_repeat('x', 2097153)] as $source) {
            try {
                (new DelimitedReader)->read($source, ',');
                $this->fail('Invalid source accepted');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
