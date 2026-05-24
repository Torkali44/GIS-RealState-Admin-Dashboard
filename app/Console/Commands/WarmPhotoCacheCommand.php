<?php

namespace App\Console\Commands;

use App\Models\InspectionPhoto;
use App\Services\DriveMediaService;
use App\Services\GoogleDriveService;
use Illuminate\Console\Command;

class WarmPhotoCacheCommand extends Command
{
    protected $signature = 'photos:warm-cache
                            {--house= : معرّف المنزل فقط}
                            {--photo= : معرّف صورة واحدة}
                            {--force : إعادة بناء الكاش حتى لو موجوداً}';

    protected $description = 'بناء كاش محلي للعرض من Google Drive (لإصلاح الصور على السيرفر)';

    public function handle(): int
    {
        if (! GoogleDriveService::isConfigured()) {
            $this->error('Google Drive غير مضبوط (OAuth أو root_folder_id).');

            return self::FAILURE;
        }

        $query = InspectionPhoto::query()
            ->where(function ($q): void {
                $q->whereNotNull('drive_file_id')
                    ->orWhereNotNull('drive_composite_file_id');
            });

        if ($houseId = $this->option('house')) {
            $query->whereHas('inspectionArea', fn ($q) => $q->where('property_house_id', (int) $houseId));
        }

        if ($photoId = $this->option('photo')) {
            $query->whereKey((int) $photoId);
        }

        $ok = 0;
        $fail = 0;
        $force = (bool) $this->option('force');

        $query->orderBy('id')->chunkById(25, function ($photos) use (&$ok, &$fail, $force): void {
            foreach ($photos as $photo) {
                if ($force) {
                    DriveMediaService::invalidatePersistentCache($photo, false);
                    DriveMediaService::invalidatePersistentCache($photo, true);
                }

                $built = DriveMediaService::buildPersistentCache($photo, false);
                if ($photo->drive_composite_file_id || $photo->composite_path) {
                    $built = DriveMediaService::buildPersistentCache($photo->fresh(), true) || $built;
                }

                if ($built) {
                    $ok++;
                    $this->line("OK photo #{$photo->id}");
                } else {
                    $fail++;
                    $this->warn("FAIL photo #{$photo->id}: ".(DriveMediaService::lastError() ?? 'unknown'));
                }
            }
        });

        $this->info("انتهى: {$ok} نجح، {$fail} فشل.");

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }
}
