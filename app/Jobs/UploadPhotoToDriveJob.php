<?php

namespace App\Jobs;

use App\Models\InspectionPhoto;
use App\Services\GoogleDriveService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Job لرفع صورة واحدة للـ Drive في الخلفية.
 * لو الـ queue مش مفعّل، بيشتغل synchronously تلقائياً.
 */
class UploadPhotoToDriveJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(public readonly int $photoId) {}

    public function handle(): void
    {
        $photo = InspectionPhoto::find($this->photoId);
        if (! $photo) {
            return;
        }
        GoogleDriveService::uploadPhoto($photo);
    }

    public function failed(Throwable $e): void
    {
        \Illuminate\Support\Facades\Log::error('UploadPhotoToDriveJob failed', [
            'photo_id' => $this->photoId,
            'error'    => $e->getMessage(),
        ]);
    }
}
