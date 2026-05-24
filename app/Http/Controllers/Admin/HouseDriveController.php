<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PropertyHouse;
use App\Services\DriveMediaService;
use App\Services\DriveReportSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class HouseDriveController extends Controller
{
    /**
     * رفع الصور المحلية غير المزامنة + تحديث التقارير على Drive.
     */
    public function syncAll(PropertyHouse $house): RedirectResponse
    {
        try {
            DriveMediaService::ensureStorageDirectories();
            DriveMediaService::ensureCacheDatabaseColumns();

            $house->load(['inspectionAreas.photos']);

            $uploaded = 0;
            $cached = 0;
            $skipped = 0;
            $missing = 0;
            $failed = 0;

            foreach ($house->inspectionAreas as $area) {
                foreach ($area->photos as $photo) {
                    $needsCache = ($photo->drive_file_id && ! DriveMediaService::hasValidPersistentCache($photo, false))
                        || ($photo->drive_composite_file_id && ! DriveMediaService::hasValidPersistentCache($photo, true));

                    if ($needsCache) {
                        $built = false;
                        if ($photo->drive_file_id) {
                            $built = DriveMediaService::buildPersistentCache($photo, false) || $built;
                        }
                        if ($photo->drive_composite_file_id) {
                            $built = DriveMediaService::buildPersistentCache($photo->fresh(), true) || $built;
                        }
                        if ($built) {
                            $cached++;

                            continue;
                        }
                    }

                    if ($photo->drive_file_id || $photo->drive_composite_file_id) {
                        $skipped++;

                        continue;
                    }

                    if (! DriveMediaService::hasLocalCopy($photo)) {
                        $missing++;

                        continue;
                    }

                    if (DriveMediaService::pushPhoto($photo)) {
                        $uploaded++;
                    } else {
                        $failed++;
                    }
                }
            }

            if (DriveMediaService::enabled()) {
                DriveReportSyncService::syncNow($house);
            }

            $msg = "مزامنة Drive: {$uploaded} صورة جديدة";
            if ($cached > 0) {
                $msg .= "، {$cached} كاش عرض محلي";
            }
            if ($skipped > 0) {
                $msg .= "، {$skipped} موجودة مسبقاً على Drive";
            }
            if ($missing > 0) {
                $msg .= "، {$missing} بدون ملف محلي (ارفعها من جديد)";
            }
            if ($failed > 0) {
                $msg .= "، {$failed} فشلت";
                if (DriveMediaService::lastError()) {
                    $msg .= ' — '.DriveMediaService::lastError();
                }
            }

            return redirect()
                ->route('admin.houses.show', $house)
                ->with($failed > 0 ? 'error' : 'status', $msg);

        } catch (Throwable $e) {
            Log::error('Drive sync failed', ['house_id' => $house->id, 'error' => $e->getMessage()]);

            return redirect()
                ->route('admin.houses.show', $house)
                ->with('error', 'فشل المزامنة مع Drive: '.$e->getMessage());
        }
    }

    /**
     * إصلاح عرض الصور من الويب (بدون Terminal): migration + كاش محلي من Drive.
     */
    public function repairDisplayCache(PropertyHouse $house): RedirectResponse
    {
        @ini_set('max_execution_time', '600');
        @ini_set('memory_limit', '512M');

        try {
            if (! DriveMediaService::enabled()) {
                return redirect()
                    ->route('admin.houses.show', $house)
                    ->with('error', 'Google Drive غير مضبوط — اربط الحساب أولاً.');
            }

            // تشغيل الهجرات تلقائياً لتحديث قاعدة البيانات على السيرفر المشترك (cPanel)
            try {
                \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            } catch (Throwable $migrationError) {
                Log::warning('Auto-migration failed inside repairDisplayCache', ['error' => $migrationError->getMessage()]);
            }

            $result = DriveMediaService::repairHouseDisplayCache($house);

            $msg = 'إصلاح عرض الصور: '.$result['ok'].' صورة جاهزة للعرض';
            if ($result['fail'] > 0) {
                $msg .= '، '.$result['fail'].' فشلت';
                if (DriveMediaService::lastError()) {
                    $msg .= ' — '.DriveMediaService::lastError();
                }
                foreach ($result['failures'] ?? [] as $row) {
                    $msg .= ' | صورة #'.$row['photo_id'].': '.$row['error'];
                }
            }
            if ($result['migrated']) {
                $msg .= ' (تم تحديث قاعدة البيانات)';
            }

            return redirect()
                ->route('admin.houses.show', $house)
                ->with($result['fail'] > 0 ? 'error' : 'status', $msg);
        } catch (Throwable $e) {
            Log::error('repairDisplayCache failed', ['house_id' => $house->id, 'error' => $e->getMessage()]);

            return redirect()
                ->route('admin.houses.show', $house)
                ->with('error', 'فشل إصلاح العرض: '.$e->getMessage());
        }
    }

    /**
     * @deprecated استخدم syncAll
     */
    public function uploadPdf(PropertyHouse $house): RedirectResponse
    {
        return $this->syncAll($house);
    }
}
