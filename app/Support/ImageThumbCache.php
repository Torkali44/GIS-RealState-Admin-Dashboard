<?php

namespace App\Support;

use Throwable;

/**
 * توليد وكاش صور مصغّرة للشبكة — أسرع بكثير من تحميل الصورة الكاملة.
 */
final class ImageThumbCache
{
    public static function path(string $sourceAbsolute, int $maxSize): ?string
    {
        if ($maxSize < 80 || $maxSize > 800 || ! is_readable($sourceAbsolute)) {
            return null;
        }

        $mtime = (int) @filemtime($sourceAbsolute);
        $dir = storage_path('app/cache/thumbs');
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $cacheFile = $dir.DIRECTORY_SEPARATOR.hash('sha256', $sourceAbsolute.'|'.$mtime.'|'.$maxSize).'.jpg';
        if (is_file($cacheFile) && filesize($cacheFile) > 0) {
            return $cacheFile;
        }

        $built = self::build($sourceAbsolute, $cacheFile, $maxSize);

        return $built && is_file($cacheFile) ? $cacheFile : null;
    }

    private static function build(string $source, string $dest, int $maxSize): bool
    {
        try {
            if (! function_exists('imagecreatefromjpeg')) {
                return false;
            }

            $info = @getimagesize($source);
            if ($info === false) {
                return false;
            }

            [$w, $h, $type] = $info;
            if ($w < 1 || $h < 1) {
                return false;
            }

            $src = match ($type) {
                IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
                IMAGETYPE_PNG => @imagecreatefrompng($source),
                IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false,
                IMAGETYPE_GIF => @imagecreatefromgif($source),
                default => false,
            };

            if ($src === false) {
                return false;
            }

            $scale = min(1.0, $maxSize / max($w, $h));
            $nw = max(1, (int) round($w * $scale));
            $nh = max(1, (int) round($h * $scale));

            $dst = imagecreatetruecolor($nw, $nh);
            if ($dst === false) {
                imagedestroy($src);

                return false;
            }

            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($src);

            $ok = imagejpeg($dst, $dest, 82);
            imagedestroy($dst);

            return $ok;
        } catch (Throwable) {
            return false;
        }
    }
}
