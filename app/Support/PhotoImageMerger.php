<?php

namespace App\Support;

use App\Models\InspectionPhoto;
use App\Services\DriveMediaService;
use GdImage;
use Throwable;

/**
 * دمج صور المصدر أفقياً على السيرفر — يستخدم composite (مع الأسهم) عند وجوده.
 */
final class PhotoImageMerger
{
    /**
     * @param  list<InspectionPhoto>  $photos
     */
    public static function mergePhotos(array $photos, int $targetHeight = 900, int $gap = 8, int $quality = 85): ?string
    {
        if (count($photos) < 2 || ! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $targetHeight = max(200, min(1600, $targetHeight));
        $quality = max(60, min(95, $quality));

        /** @var list<array{gd: GdImage, w: int, h: int}> $layers */
        $layers = [];

        try {
            foreach ($photos as $photo) {
                if (! $photo instanceof InspectionPhoto) {
                    continue;
                }

                $absolute = self::resolveSourceAbsolute($photo);
                if ($absolute === null) {
                    self::destroyLayers($layers);

                    return null;
                }

                $gd = self::loadGdImage($absolute);
                if ($gd === null) {
                    self::destroyLayers($layers);

                    return null;
                }

                $w = imagesx($gd);
                $h = imagesy($gd);
                if ($w < 1 || $h < 1) {
                    imagedestroy($gd);
                    self::destroyLayers($layers);

                    return null;
                }

                $layers[] = ['gd' => $gd, 'w' => $w, 'h' => $h];
            }

            if (count($layers) < 2) {
                self::destroyLayers($layers);

                return null;
            }

            $widths = array_map(
                fn (array $layer): int => max(1, (int) round($layer['w'] * ($targetHeight / $layer['h']))),
                $layers
            );

            $totalW = array_sum($widths) + ($gap * (count($layers) - 1));
            $canvas = imagecreatetruecolor($totalW, $targetHeight);
            if ($canvas === false) {
                self::destroyLayers($layers);

                return null;
            }

            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefill($canvas, 0, 0, $white);

            $x = 0;
            foreach ($layers as $i => $layer) {
                $destW = $widths[$i];
                imagecopyresampled(
                    $canvas,
                    $layer['gd'],
                    $x,
                    0,
                    0,
                    0,
                    $destW,
                    $targetHeight,
                    $layer['w'],
                    $layer['h']
                );
                $x += $destW + $gap;
            }

            self::destroyLayers($layers);

            $dir = storage_path('app/temp/uploads');
            if (! is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }

            $tmp = $dir.DIRECTORY_SEPARATOR.'merge-'.uniqid('', true).'.jpg';
            if (! imagejpeg($canvas, $tmp, $quality)) {
                imagedestroy($canvas);
                @unlink($tmp);

                return null;
            }

            imagedestroy($canvas);

            return is_readable($tmp) ? $tmp : null;
        } catch (Throwable) {
            self::destroyLayers($layers);

            return null;
        }
    }

    public static function preferComposite(InspectionPhoto $photo): bool
    {
        return filled($photo->composite_path)
            || filled($photo->drive_composite_file_id)
            || $photo->tip_x !== null
            || $photo->tip_y !== null
            || ! empty($photo->annotations_json);
    }

    private static function resolveSourceAbsolute(InspectionPhoto $photo): ?string
    {
        DriveMediaService::ensureStorageDirectories();

        $preferComposite = self::preferComposite($photo);
        $absolute = DriveMediaService::resolveImageForDisplay($photo, $preferComposite);

        if ($absolute === null && $preferComposite) {
            $absolute = DriveMediaService::resolveImageForDisplay($photo, false);
        }

        return ($absolute !== null && is_readable($absolute)) ? $absolute : null;
    }

    private static function loadGdImage(string $absolute): ?GdImage
    {
        $info = @getimagesize($absolute);
        if ($info === false) {
            return null;
        }

        $type = $info[2] ?? 0;

        $img = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($absolute),
            IMAGETYPE_PNG => @imagecreatefrompng($absolute),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($absolute) : false,
            IMAGETYPE_GIF => @imagecreatefromgif($absolute),
            default => false,
        };

        return ($img instanceof GdImage) ? $img : null;
    }

    /**
     * @param  list<array{gd: GdImage, w: int, h: int}>  $layers
     */
    private static function destroyLayers(array $layers): void
    {
        foreach ($layers as $layer) {
            if ($layer['gd'] instanceof GdImage) {
                imagedestroy($layer['gd']);
            }
        }
    }
}
