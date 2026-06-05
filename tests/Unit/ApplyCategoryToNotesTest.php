<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\InspectionPhotoController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ApplyCategoryToNotesTest extends TestCase
{
    public function test_apply_category_updates_all_notes_and_merges_description(): void
    {
        $controller = new InspectionPhotoController;
        $method = new ReflectionMethod(InspectionPhotoController::class, 'applyCategoryToNotes');
        $method->setAccessible(true);

        $notes = [
            ['text' => 'ملاحظة يدوية', 'category_id' => null],
        ];

        $result = $method->invoke($controller, $notes, 3, 'وصف إضافي');

        $this->assertCount(2, $result);
        $this->assertSame(3, $result[0]['category_id']);
        $this->assertSame('ملاحظة يدوية', $result[0]['text']);
        $this->assertSame(3, $result[1]['category_id']);
        $this->assertSame('وصف إضافي', $result[1]['text']);
    }

    public function test_apply_category_does_not_duplicate_description_in_notes(): void
    {
        $controller = new InspectionPhotoController;
        $method = new ReflectionMethod(InspectionPhotoController::class, 'applyCategoryToNotes');
        $method->setAccessible(true);

        $notes = [
            ['text' => 'وصف إضافي', 'category_id' => null],
        ];

        $result = $method->invoke($controller, $notes, 2, 'وصف إضافي');

        $this->assertCount(1, $result);
        $this->assertSame(2, $result[0]['category_id']);
    }
}
