<?php

namespace App\Console\Commands;

use App\Models\PropertyHouse;
use App\Services\InspectionReportPdfGenerator;
use App\Support\InspectionReportCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BuildInspectionReportPdf extends Command
{
    protected $signature = 'inspection:build-pdf {house : معرّف المنزل}';

    protected $description = 'توليد تقرير PDF لمنزل (للمنازل الكبيرة أو التشغيل في الخلفية)';

    public function handle(InspectionReportPdfGenerator $generator): int
    {
        $house = PropertyHouse::query()->findOrFail((int) $this->argument('house'));

        $relative = InspectionReportCache::relativePath($house);
        $absolute = InspectionReportCache::absolutePath($house);

        $dir = dirname($absolute);
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $tmp = $dir.DIRECTORY_SEPARATOR.'.building-'.$house->id.'-'.uniqid('', true).'.pdf';

        $this->info('Building PDF for house #'.$house->id.' ('.$house->photos()->count().' photos)...');

        $generator->renderToFile($house, $tmp);

        $stream = fopen($tmp, 'r');
        if ($stream === false) {
            $this->error('Could not read temp PDF.');

            return self::FAILURE;
        }

        Storage::disk('public')->put($relative, $stream);
        fclose($stream);
        @unlink($tmp);

        $this->info('Saved: '.$relative);

        return self::SUCCESS;
    }
}
