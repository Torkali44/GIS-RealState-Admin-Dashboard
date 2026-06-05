<?php

namespace App\Support;

use App\Models\InspectionPhoto;
use Throwable;

/**
 * كاش دائم لصور PDF الجاهزة — يُسرّع إعادة توليد التقارير الكبيرة (مئات الصفحات).
 */
final class ReportImageEmbedCache
{
    public static function cachePath(InspectionPhoto $photo, string $sourcePath, int $maxDim, int $quality): string
    {
        $mtime = @filemtime($sourcePath) ?: 0;
        $updated = $photo->updated_at?->getTimestamp() ?? 0;
        $fingerprint = implode('-', [
            (int) $photo->id,
            $updated,
            $mtime,
            filesize($sourcePath) ?: 0,
            $maxDim,
            $quality,
        ]);

        return storage_path('app/reports/embed-cache/'.hash('sha256', $fingerprint).'.jpg');
    }

    /**
     * يُعيد مسار JPEG جاهز للتضمين، أو null عند الفشل.
     * المُنشئ يُعيد مسار ملف مؤقت؛ يُنسخ إلى الكاش ولا يُضاف للملفات المؤقتة القابلة للحذف.
     */
    public static function resolve(
        InspectionPhoto $photo,
        string $sourcePath,
        int $maxDim,
        int $quality,
        callable $buildTempJpeg
    ): ?string {
        if (! is_readable($sourcePath)) {
            return null;
        }

        $cachePath = self::cachePath($photo, $sourcePath, $maxDim, $quality);
        if (self::isValidCachedFile($cachePath)) {
            return $cachePath;
        }

        try {
            $built = $buildTempJpeg($sourcePath);
            if (! is_string($built) || ! is_readable($built) || filesize($built) < 100) {
                return null;
            }

            $dir = dirname($cachePath);
            if (! is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }

            if ($built === $cachePath) {
                return $cachePath;
            }

            if (! @copy($built, $cachePath)) {
                return $built;
            }

            if (str_starts_with($built, sys_get_temp_dir())) {
                @unlink($built);
            }

            return self::isValidCachedFile($cachePath) ? $cachePath : null;
        } catch (Throwable) {
            return null;
        }
    }

    public static function isValidCachedFile(string $path): bool
    {
        return is_file($path) && filesize($path) > 100 && @getimagesize($path) !== false;
    }

}
