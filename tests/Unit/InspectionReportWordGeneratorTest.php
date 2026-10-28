<?php

namespace Tests\Unit;

use App\Services\InspectionReportWordGenerator;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class InspectionReportWordGeneratorTest extends TestCase
{
    public function test_resolve_category_id_keeps_explicit_per_note_category(): void
    {
        $generator = new InspectionReportWordGenerator;
        $method = new ReflectionMethod(InspectionReportWordGenerator::class, 'resolveCategoryId');
        $method->setAccessible(true);

        $categories = [
            [
                'id' => 1,
                'name' => 'سيراميك',
                'templates' => ['سيراميك'],
            ],
            [
                'id' => 2,
                'name' => 'جدران',
                'templates' => ['تشقق'],
            ],
        ];

        $catA = $method->invoke($generator, [
            'text' => 'ملاحظة أولى',
            'category_id' => 1,
        ], $categories);

        $catB = $method->invoke($generator, [
            'text' => 'ملاحظة ثانية',
            'category_id' => '2',
        ], $categories);

        $this->assertSame(1, $catA);
        $this->assertSame(2, $catB);
    }
}
