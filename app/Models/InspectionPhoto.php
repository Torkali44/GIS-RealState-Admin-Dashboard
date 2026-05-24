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
     */
    public function combinedDescription(): string
    {
        $parts = $this->notesList();
        $desc = trim((string) $this->description);
        if ($desc !== '' && ! in_array($desc, $parts, true)) {
            $parts[] = $desc;
        }

        return implode("\n", $parts);
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
