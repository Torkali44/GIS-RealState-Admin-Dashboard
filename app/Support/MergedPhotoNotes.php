<?php

namespace App\Support;

use App\Models\InspectionPhoto;

/**
 * دمج ملاحظات الصور المصدر عند دمج صورتين أو أكثر.
 */
final class MergedPhotoNotes
{
    /**
     * @param  iterable<InspectionPhoto>  $sources
     * @return array{notes_json: list<array{text: string, category_id: int|null}>, description: ?string}
     */
    public static function fromSources(iterable $sources, ?string $formDescription = null): array
    {
        $notesByKey = [];
        $descriptions = [];

        foreach ($sources as $source) {
            if (! $source instanceof InspectionPhoto) {
                continue;
            }

            foreach ($source->notesEntries() as $entry) {
                $text = trim($entry['text']);
                if ($text === '') {
                    continue;
                }
                $cat = $entry['category_id'] ?? null;
                $key = ($cat ?? '0')."\0".$text;
                if (! isset($notesByKey[$key])) {
                    $notesByKey[$key] = [
                        'text' => $text,
                        'category_id' => $cat,
                    ];
                }
            }

            $desc = trim((string) $source->description);
            if ($desc !== '') {
                $descriptions[] = $desc;
            }
        }

        $formDescription = trim((string) $formDescription);
        if ($formDescription !== '') {
            $descriptions[] = $formDescription;
        }

        $description = $descriptions !== []
            ? implode("\n", array_values(array_unique($descriptions)))
            : null;

        return [
            'notes_json' => array_values($notesByKey),
            'description' => $description,
        ];
    }
}
