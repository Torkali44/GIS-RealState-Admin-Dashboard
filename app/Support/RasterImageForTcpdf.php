<?php

namespace App\Support;

/**
 * يحوّل الصور (PNG/WebP/…) إلى JPEG بخلفية بيضاء حتى يعمل TCPDF بدون Imagick عند وجود قناة شفافة.
 */
final class RasterImageForTcpdf
{
    /** أقصى بعد للصورة داخل PDF — يقلّل استهلاك الذاكرة مع مئات الصور */
    private const MAX_EMBED_PX = 800;

    /**
     * @return array{0: string, 1: bool} المسار المطلق للاستخدام، وهل يجب حذف الملف بعد الاستخدام
     */
    public static function prepareForEmbedding(string $absolutePath): array
    {
        if (! is_readable($absolutePath)) {
            return [$absolutePath, false];
        }

        $mime = @mime_content_type($absolutePath) ?: '';
        [$origW, $origH] = @getimagesize($absolutePath) ?: [0, 0];
        $needsResize = $origW > 0 && $origH > 0 && max($origW, $origH) > self::MAX_EMBED_PX;

        if (in_array($mime, ['image/jpeg', 'image/jpg'], true) && ! $needsResize) {
            return [$absolutePath, false];
        }

        if (! extension_loaded('gd')) {
            return [$absolutePath, false];
        }

        $binary = @file_get_contents($absolutePath);
        if ($binary === false) {
            return [$absolutePath, false];
        }

        $src = @imagecreatefromstring($binary);
        unset($binary);
        if ($src === false) {
            return [$absolutePath, false];
        }

        $w = imagesx($src);
        $h = imagesy($src);
        if ($w < 1 || $h < 1) {
            imagedestroy($src);

            return [$absolutePath, false];
        }

        [$w, $h] = self::fitWithinMaxSide($w, $h, self::MAX_EMBED_PX);

        $dst = imagecreatetruecolor($w, $h);
        if ($dst === false) {
            imagedestroy($src);

            return [$absolutePath, false];
        }

        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, imagesx($src), imagesy($src));
        imagedestroy($src);

        $tmp = tempnam(sys_get_temp_dir(), 'tcpdf_raster');
        if ($tmp === false) {
            imagedestroy($dst);

            return [$absolutePath, false];
        }

        $jpg = $tmp.'.jpg';
        @unlink($tmp);
        imagejpeg($dst, $jpg, 85);
        imagedestroy($dst);

        return [$jpg, true];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private static function fitWithinMaxSide(int $w, int $h, int $maxSide): array
    {
        $longest = max($w, $h);
        if ($longest <= $maxSide) {
            return [$w, $h];
        }

        $scale = $maxSide / $longest;

        return [
            max(1, (int) round($w * $scale)),
            max(1, (int) round($h * $scale)),
        ];
    }

    /**
     * يستبدل الملف في التخزين بنسخة JPEG عند الحاجة (يفيد قبل TCPDF ولعرض متّسق).
     *
     * @return string|null المسار النسبي الجديد إن تغيّر، أو null
     */
    public static function convertStoredFileToJpegIfNeeded(\Illuminate\Contracts\Filesystem\Filesystem $disk, string $relativePath): ?string
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        if (! $disk->exists($relativePath)) {
            return null;
        }

        $full = $disk->path($relativePath);
        $ext = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg'], true)) {
            return null;
        }

        $binary = @file_get_contents($full);
        if ($binary === false) {
            return null;
        }

        $src = @imagecreatefromstring($binary);
        if ($src === false) {
            return null;
        }

        $w = imagesx($src);
        $h = imagesy($src);
        if ($w < 1 || $h < 1) {
            imagedestroy($src);

            return null;
        }

        $dst = imagecreatetruecolor($w, $h);
        if ($dst === false) {
            imagedestroy($src);

            return null;
        }

        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);
        imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);
        imagedestroy($src);

        $dir = dirname($relativePath);
        $base = pathinfo($relativePath, PATHINFO_FILENAME);
        $newRelative = ($dir !== '.' ? $dir.'/' : '').$base.'.jpg';

        $tmp = tempnam(sys_get_temp_dir(), 'upjpg');
        if ($tmp === false) {
            imagedestroy($dst);

            return null;
        }
        $tmpJpg = $tmp.'.jpg';
        @unlink($tmp);
        imagejpeg($dst, $tmpJpg, 92);
        imagedestroy($dst);

        $disk->put($newRelative, file_get_contents($tmpJpg));
        @unlink($tmpJpg);
        if ($newRelative !== $relativePath) {
            $disk->delete($relativePath);
        }

        return $newRelative;
    }
}
