<?php

namespace Tests\Unit;

use App\Models\InspectionPhoto;
use App\Support\MergedPhotoNotes;
use PHPUnit\Framework\TestCase;

class MergedPhotoNotesTest extends TestCase
{
    public function test_merges_notes_from_multiple_sources_without_duplicates(): void
    {
        $a = new InspectionPhoto([
            'notes_json' => [
                ['text' => 'تشقق', 'category_id' => 1],
                ['text' => 'رطوبة', 'category_id' => 2],
            ],
            'description' => 'وصف أ',
        ]);
        $b = new InspectionPhoto([
            'notes_json' => [
                ['text' => 'رطوبة', 'category_id' => 2],
                'ملاحظة نصية',
            ],
            'description' => 'وصف ب',
        ]);

        $merged = MergedPhotoNotes::fromSources([$a, $b]);

        $this->assertCount(3, $merged['notes_json']);
        $this->assertStringContainsString('وصف أ', (string) $merged['description']);
        $this->assertStringContainsString('وصف ب', (string) $merged['description']);
    }

    public function test_form_description_is_included(): void
    {
        $source = new InspectionPhoto([
            'notes_json' => [['text' => 'ملاحظة', 'category_id' => null]],
        ]);

        $merged = MergedPhotoNotes::fromSources([$source], 'من الدمج');

        $this->assertStringContainsString('من الدمج', (string) $merged['description']);
    }
}
