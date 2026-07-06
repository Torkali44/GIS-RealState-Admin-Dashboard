<?php

namespace Tests\Unit;

use App\Support\AreaNameSanitizer;
use PHPUnit\Framework\TestCase;

class AreaNameSanitizerTest extends TestCase
{
    public function test_strips_unsafe_characters(): void
    {
        $this->assertSame('الصالة', AreaNameSanitizer::sanitize('  الصالة  '));
        $this->assertSame('مطبخ1', AreaNameSanitizer::sanitize('مطبخ<>1'));
    }

    public function test_rejects_hidden_names(): void
    {
        $this->assertSame('', AreaNameSanitizer::sanitize('.hidden'));
        $this->assertSame('', AreaNameSanitizer::sanitize('$RECYCLE.BIN'));
    }

    public function test_truncates_long_names(): void
    {
        $long = str_repeat('أ', 300);
        $this->assertSame(255, mb_strlen(AreaNameSanitizer::sanitize($long)));
    }
}
