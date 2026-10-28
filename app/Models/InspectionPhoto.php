<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class InspectionPhoto extends Model
{
    protected $fillable = [
        'inspection_area_id',
        'original_path',
        'composite_path',
        'tip_x',
        'tip_y',
        'annotations_json',
        'description',
        'notes_json',
        'sort_order',
        'original_filename',
        'upload_batch_id',
        'upload_file_key',
        'drive_file_id',
        'drive_composite_file_id',
        'drive_notes_file_id',
        'local_cached_path',
        'processed_cache_path',
    ];

    protected function casts(): array
    {
        return [
            'tip_x' => 'float',
            'tip_y' => 'float',
            'annotations_json' => 'array',
            'notes_json' => 'array',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Returns normalized notes with optional category id.
     *
     * @return array<int, array{text:string,category_id:int|null}>
     */
    public function notesEntries(): array
    {
        $raw = $this->notes_json;
        if (! is_array($raw)) {
            return [];
        }

        $items = array_is_list($raw) ? $raw : array_values($raw);

        $clean = [];
        foreach ($items as $note) {
            $text = null;
            $categoryId = null;

            if (is_string($note)) {
                $text = trim($note);
            } elseif (is_array($note)) {
                $text = trim((string) ($note['text'] ?? ''));
                $cat = $note['category_id'] ?? null;
                if (is_numeric($cat) && (int) $cat > 0) {
                    $categoryId = (int) $cat;
                }
            }

            if ($text === null || $text === '') {
                continue;
            }

            $clean[] = [
                'text' => $text,
                'category_id' => $categoryId,
            ];
        }

        return $clean;
    }

    /**
     * Returns the list of note strings stored on this photo.
     *
     * @return array<int, string>
     */
    public function notesList(): array
    {
        return array_values(array_map(
            fn (array $entry): string => $entry['text'],
            $this->notesEntries()
        ));
    }

    /**
     * Combined description and notes used for display/PDF.
     * Deduplicates notes by normalised text so the same note added under
     * multiple categories never appears more than once in the PDF box.
     */
    public function combinedDescription(): string
    {
        $seen  = [];
        $parts = [];

        foreach ($this->notesList() as $note) {
            $key = mb_strtolower(preg_replace('/\s+/u', ' ', trim($note)) ?? trim($note));
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $parts[]    = $note;
        }

        $desc = trim((string) $this->description);
        if ($desc !== '') {
            $key = mb_strtolower(preg_replace('/\s+/u', ' ', $desc) ?? $desc);
            if (! isset($seen[$key])) {
                $parts[] = $desc;
            }
        }

        return implode("\n", $parts);
    }

    /**
     * Scope: order photos by original_filename with natural sorting.
     */
    public function scopeOrderByFilename($query)
    {
        return $query->orderByRaw('LENGTH(original_filename) ASC')
                     ->orderBy('original_filename', 'ASC');
    }

    /**
     * Compare two filenames according to specific rules:
     * 1. Simple numbers first (e.g. 1.jpg, 2.jpg)
     * 2. WhatsApp images (e.g. WhatsApp Image 2026-05-23 at 16.56.43 (1).jpeg) by date-time and duplicate suffix
     * 3. Other names natural sorted
     */
    public static function compareFilenames(?string $filenameA, ?string $filenameB): int
    {
        $filenameA = $filenameA ?? '';
        $filenameB = $filenameB ?? '';

        $nameA = pathinfo($filenameA, PATHINFO_FILENAME);
        $nameB = pathinfo($filenameB, PATHINFO_FILENAME);

        $isNumA = is_numeric($nameA);
        $isNumB = is_numeric($nameB);

        // Group 1: Purely numeric names
        if ($isNumA && !$isNumB) {
            return -1;
        }
        if (!$isNumA && $isNumB) {
            return 1;
        }
        if ($isNumA && $isNumB) {
            $valA = floatval($nameA);
            $valB = floatval($nameB);
            if ($valA !== $valB) {
                return $valA <=> $valB;
            }
            return strnatcasecmp($nameA, $nameB);
        }

        // WhatsApp name regex pattern:
        // Matches: WhatsApp Image 2026-05-23 at 16.56.43
        // Matches: WhatsApp Image 2026-05-23 at 16.56.43 (1)
        $waPattern = '/^WhatsApp Image (\d{4}-\d{2}-\d{2}) at (\d{2})\.(\d{2})\.(\d{2})(?:\s*\((\d+)\))?$/i';

        $isWaA = preg_match($waPattern, $nameA, $matchesA);
        $isWaB = preg_match($waPattern, $nameB, $matchesB);

        // Group 2: WhatsApp images
        if ($isWaA && !$isWaB) {
            return -1;
        }
        if (!$isWaA && $isWaB) {
            return 1;
        }
        if ($isWaA && $isWaB) {
            // Compare datetime
            $dateTimeA = $matchesA[1] . ' ' . $matchesA[2] . ':' . $matchesA[3] . ':' . $matchesA[4];
            $dateTimeB = $matchesB[1] . ' ' . $matchesB[2] . ':' . $matchesB[3] . ':' . $matchesB[4];

            $dtDiff = strcmp($dateTimeA, $dateTimeB);
            if ($dtDiff !== 0) {
                return $dtDiff;
            }

            // Compare duplicate suffix: (1), (2), etc.
            $suffixA = isset($matchesA[5]) ? intval($matchesA[5]) : 0;
            $suffixB = isset($matchesB[5]) ? intval($matchesB[5]) : 0;

            if ($suffixA !== $suffixB) {
                return $suffixA <=> $suffixB;
            }
            return strnatcasecmp($nameA, $nameB);
        }

        // Group 3: Other names
        return strnatcasecmp($nameA, $nameB);
    }

    public function inspectionArea(): BelongsTo
    {
        return $this->belongsTo(InspectionArea::class);
    }

    /**
     * Whether the photo was edited beyond the raw upload (arrows, composite, or description).
     */
    public function hasUserEdits(): bool
    {
        return $this->tip_x !== null
            || $this->tip_y !== null
            || filled($this->description)
            || filled($this->composite_path)
            || ! empty($this->annotations_json)
            || ! empty($this->notesList());
    }

    public function reportImagePath(): ?string
    {
        return \App\Services\DriveMediaService::resolveImagePath($this, true)
            ?? \App\Services\DriveMediaService::resolveImagePath($this, false);
    }

    public function deleteStoredFiles(): void
    {
        $this->loadMissing('inspectionArea');
        $houseId = (int) ($this->inspectionArea->property_house_id ?? 0);

        if ($houseId > 0) {
            \App\Support\HouseMediaCleanup::purgePhotoFiles($this, $houseId);

            return;
        }

        $disk = Storage::disk('public');
        foreach ([$this->original_path, $this->composite_path] as $p) {
            if ($p && $disk->exists($p)) {
                $disk->delete($p);
            }
        }

        if (\App\Services\DriveMediaService::enabled()) {
            \App\Services\DriveMediaService::deleteDriveAssets($this);
        }
    }

    protected static function booted(): void
    {
        static::deleting(function (InspectionPhoto $photo): void {
            $photo->deleteStoredFiles();
        });
    }
}
