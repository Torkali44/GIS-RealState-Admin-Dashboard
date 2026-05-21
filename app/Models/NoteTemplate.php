<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoteTemplate extends Model
{
    public const LOCATION_PLACEHOLDER = '(الموقع)';

    protected $fillable = [
        'note_category_id',
        'text',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(NoteCategory::class, 'note_category_id');
    }

    /**
     * Replace the (الموقع) placeholder with the area name. If the placeholder is
     * absent, append "في {areaName}" to the end of the note.
     */
    public static function applyLocation(string $text, ?string $areaName): string
    {
        $text = trim($text);
        $areaName = trim((string) $areaName);

        if ($areaName === '') {
            return $text;
        }

        if (mb_strpos($text, self::LOCATION_PLACEHOLDER) !== false) {
            return trim(str_replace(self::LOCATION_PLACEHOLDER, $areaName, $text));
        }

        // Avoid double-suffixing if the area name already appears in the text.
        if (mb_stripos($text, $areaName) !== false) {
            return $text;
        }

        $text = rtrim($text, " .،,");

        return $text . ' في ' . $areaName . '.';
    }
}
