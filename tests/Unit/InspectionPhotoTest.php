<?php

namespace Tests\Unit;

use App\Models\InspectionPhoto;
use PHPUnit\Framework\TestCase;

class InspectionPhotoTest extends TestCase
{
    public function test_has_user_edits_detects_arrow_and_notes(): void
    {
        $photo = new InspectionPhoto([
            'tip_x' => 0.5,
            'notes_json' => [['text' => 'ملاحظة', 'category_id' => 1]],
        ]);

        $this->assertTrue($photo->hasUserEdits());
    }

    public function test_has_user_edits_false_for_plain_upload(): void
    {
        $photo = new InspectionPhoto([
            'original_path' => 'inspections/1/a.jpg',
            'original_filename' => 'a.jpg',
        ]);

        $this->assertFalse($photo->hasUserEdits());
    }

    public function test_notes_entries_normalizes_strings_and_arrays(): void
    {
        $photo = new InspectionPhoto([
            'notes_json' => [
                'نص قديم',
                ['text' => '  ملاحظة  ', 'category_id' => '2'],
                ['text' => '', 'category_id' => 0],
            ],
        ]);

        $entries = $photo->notesEntries();

        $this->assertCount(2, $entries);
        $this->assertSame('نص قديم', $entries[0]['text']);
        $this->assertNull($entries[0]['category_id']);
        $this->assertSame('ملاحظة', $entries[1]['text']);
        $this->assertSame(2, $entries[1]['category_id']);
    }
}
