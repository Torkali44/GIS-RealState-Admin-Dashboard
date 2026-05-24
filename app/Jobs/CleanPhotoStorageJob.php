<?php

namespace App\Jobs;

use App\Services\DriveMediaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * حذف ملفات temp والكاش الأقدم من المدة المحددة (افتراضي 15 يوم).
 * عند طلب صورة بعد الحذف يُعاد بناء الكاش من Google Drive.
 */
class CleanPhotoStorageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        try {
            $stats = DriveMediaService::cleanExpiredStorage();
            Log::info('CleanPhotoStorageJob finished', $stats);
        } catch (Throwable $e) {
            Log::error('CleanPhotoStorageJob failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
