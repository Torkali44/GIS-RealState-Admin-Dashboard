<?php

namespace App\Support;

final class AreaNameSanitizer
{
    /**
     * تنظيف اسم القسم القادم من اسم فولدر على الجهاز.
     */
    public static function sanitize(string $name): string
    {
        $name = trim($name);
        if ($name === '' || str_starts_with($name, '.') || str_starts_with($name, '$')) {
            return '';
        }

        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
        $name = preg_replace('/[<>:"\/\\\\|?*]/u', '', $name) ?? '';
        $name = preg_replace('/\s+/u', ' ', $name) ?? '';
        $name = trim($name);

        if ($name === '') {
            return '';
        }

        return mb_substr($name, 0, 255);
    }
}
