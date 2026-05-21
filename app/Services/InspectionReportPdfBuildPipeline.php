<?php

namespace App\Services;

use App\Models\PropertyHouse;
use App\Support\PdfMerger;
use Illuminate\Support\Facades\File;

/**
 * بناء التقرير على دفعات (طلب HTTP لكل دفعة) لتجاوز حد وقت/ذاكرة الاستضافة المشتركة.
 */
class InspectionReportPdfBuildPipeline
{
    public const LARGE_HOUSE_PHOTO_THRESHOLD = 25;

    public function __construct(
        private readonly InspectionReportPdfGenerator $generator
    ) {}

    public function shouldUsePipeline(int $photoCount): bool
    {
        return $photoCount >= self::LARGE_HOUSE_PHOTO_THRESHOLD;
    }

    public function workDir(PropertyHouse $house, int $photoCount): string
    {
        return storage_path('app/reports/build/h'.$house->id.'-pc'.$photoCount);
    }

    public function start(PropertyHouse $house): void
    {
        $photoCount = $house->photos()->count();
        $dir = $this->workDir($house, $photoCount);
        File::deleteDirectory($dir);
        File::ensureDirectoryExists($dir);

        $parts = $this->generator->planParts($house);
        if ($parts === []) {
            throw new \RuntimeException('لا توجد صور لبناء التقرير.');
        }

        file_put_contents(
            $dir.DIRECTORY_SEPARATOR.'manifest.json',
            json_encode(['parts' => $parts], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
        );
    }

    /**
     * @return array{total: int, done: int}
     */
    public function renderPart(PropertyHouse $house, int $index): array
    {
        $photoCount = $house->photos()->count();
        $dir = $this->workDir($house, $photoCount);
        $manifestPath = $dir.DIRECTORY_SEPARATOR.'manifest.json';

        if (! is_readable($manifestPath)) {
            throw new \RuntimeException('لم يبدأ بناء التقرير بعد.');
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        $parts = $manifest['parts'] ?? [];
        $total = count($parts);

        if ($index < 0 || $index >= $total) {
            throw new \RuntimeException('رقم الدفعة غير صالح.');
        }

        $partPath = $dir.DIRECTORY_SEPARATOR.sprintf('part-%04d.pdf', $index);
        $this->generator->renderPart($house, $parts[$index], $partPath);

        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }

        return ['total' => $total, 'done' => $index + 1];
    }

    public function mergeTo(PropertyHouse $house, string $absolutePath): void
    {
        $photoCount = $house->photos()->count();
        $dir = $this->workDir($house, $photoCount);
        $files = glob($dir.DIRECTORY_SEPARATOR.'part-*.pdf') ?: [];

        if ($files === []) {
            throw new \RuntimeException('لم تُنشأ أجزاء التقرير بعد.');
        }

        sort($files, SORT_STRING);
        PdfMerger::merge($files, $absolutePath);
    }

    public function cleanup(PropertyHouse $house): void
    {
        $photoCount = $house->photos()->count();
        File::deleteDirectory($this->workDir($house, $photoCount));
    }
}
