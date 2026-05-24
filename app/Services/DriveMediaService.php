<?php

namespace App\Services;

use App\Models\InspectionPhoto;
use App\Models\PropertyHouse;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * رفع → Google Drive → كاش محلي دائم للعرض (لا روابط Drive في المتصفح).
 */
class DriveMediaService
{
    private static ?string $lastError = null;

    private static ?bool $cacheColumnsAvailable = null;

    public static function cacheColumnsAvailable(): bool
    {
        if (self::$cacheColumnsAvailable === null) {
            self::$cacheColumnsAvailable = Schema::hasColumn('inspection_photos', 'local_cached_path');
        }

        return self::$cacheColumnsAvailable;
    }

    /**
     * إنشاء أعمدة الكاش من الويب (بدون Terminal) — يُستدعى من زر «إصلاح عرض الصور».
     */
    public static function ensureCacheDatabaseColumns(): bool
    {
        if (self::cacheColumnsAvailable()) {
            return true;
        }

        try {
            Schema::table('inspection_photos', function (Blueprint $table): void {
                if (! Schema::hasColumn('inspection_photos', 'local_cached_path')) {
                    $table->string('local_cached_path', 500)->nullable()->after('drive_notes_file_id');
                }
                if (! Schema::hasColumn('inspection_photos', 'processed_cache_path')) {
                    $table->string('processed_cache_path', 500)->nullable()->after('local_cached_path');
                }
            });
            self::$cacheColumnsAvailable = true;

            return true;
        } catch (Throwable $e) {
            Log::error('ensureCacheDatabaseColumns failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public static function ensureStorageDirectories(): void
    {
        foreach ([
            storage_path('app/cache/photos'),
            storage_path('app/temp/uploads'),
            storage_path('app/drive-cache'),
        ] as $dir) {
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    public static function enabled(): bool
    {
        return GoogleDriveService::isConfigured();
    }

    public static function lastError(): ?string
    {
        return self::$lastError;
    }

    public static function cacheTtlSeconds(): int
    {
        $days = max(1, (int) config('google-drive.cache_ttl_days', 15));

        return $days * 86400;
    }

    public static function cacheRelativePath(InspectionPhoto $photo, bool $composite): string
    {
        $suffix = $composite ? 'processed' : 'original';

        return 'cache/photos/photo-'.$photo->id.'-'.$suffix.'.jpg';
    }

    public static function cacheAbsolutePath(InspectionPhoto $photo, bool $composite): string
    {
        return storage_path('app/'.self::cacheRelativePath($photo, $composite));
    }

    public static function tempRelativePath(int $houseId, string $filename): string
    {
        return 'temp/uploads/house-'.$houseId.'/'.$filename;
    }

    public static function absoluteFromStoragePath(?string $relative): ?string
    {
        if (! $relative) {
            return null;
        }

        $absolute = storage_path('app/'.ltrim(str_replace('\\', '/', $relative), '/'));
        if (self::isValidImageFile($absolute)) {
            return $absolute;
        }

        if (is_file($absolute) && ! self::isValidImageFile($absolute)) {
            @unlink($absolute);
        }

        return null;
    }

    /**
     * مسار جاهز للعرض في المتصفح / PDF — يبني الكاش من Drive عند الحاجة.
     */
    public static function resolveImageForDisplay(InspectionPhoto $photo, bool $preferComposite = false): ?string
    {
        if ($preferComposite) {
            $path = self::resolveImageForDisplayInternal($photo, true);
            if ($path !== null) {
                return $path;
            }
        }

        return self::resolveImageForDisplayInternal($photo, false);
    }

    private static function resolveImageForDisplayInternal(InspectionPhoto $photo, bool $preferComposite): ?string
    {
        $path = self::resolveExistingLocalPath($photo, $preferComposite);
        if ($path !== null) {
            return $path;
        }

        if (! self::enabled()) {
            return null;
        }

        $fileId = $preferComposite && $photo->drive_composite_file_id
            ? $photo->drive_composite_file_id
            : $photo->drive_file_id;

        if (! $fileId) {
            return null;
        }

        return self::buildPersistentCache($photo, $preferComposite) ?: null;
    }

    public static function resolveImagePath(InspectionPhoto $photo, bool $preferComposite = false): ?string
    {
        return self::resolveImageForDisplay($photo, $preferComposite);
    }

    private static function resolveExistingLocalPath(InspectionPhoto $photo, bool $preferComposite): ?string
    {
        $dbPath = $preferComposite ? $photo->processed_cache_path : $photo->local_cached_path;
        $absolute = self::absoluteFromStoragePath($dbPath);
        if ($absolute !== null) {
            if (self::cacheFileIsFresh($absolute)) {
                return $absolute;
            }
            self::invalidatePersistentCache($photo, $preferComposite);
        }

        $diskCache = self::cacheAbsolutePath($photo, $preferComposite);
        if (is_file($diskCache) && self::isValidImageFile($diskCache) && self::cacheFileIsFresh($diskCache)) {
            return $diskCache;
        }
        if (is_file($diskCache) && ! self::isValidImageFile($diskCache)) {
            @unlink($diskCache);
        }

        $disk = Storage::disk('public');
        $relative = $preferComposite && ($photo->composite_path || $photo->drive_composite_file_id)
            ? ($photo->composite_path ?: $photo->original_path)
            : $photo->original_path;

        if ($relative && $disk->exists($relative)) {
            $local = $disk->path($relative);
            if (self::isValidImageFile($local)) {
                return $local;
            }
        }

        $hosted = self::resolveHostedPublicPath($photo, $preferComposite);
        if ($hosted !== null) {
            return $hosted;
        }

        $legacy = self::legacyCacheAbsolutePath($photo, $preferComposite);
        if (is_file($legacy) && self::isValidImageFile($legacy) && self::cacheFileIsFresh($legacy)) {
            return $legacy;
        }
        if (is_file($legacy) && ! self::isValidImageFile($legacy)) {
            @unlink($legacy);
        }

        $dbPath = $preferComposite ? $photo->processed_cache_path : $photo->local_cached_path;
        if ($dbPath) {
            $absolute = self::absoluteFromStoragePath($dbPath);
            if ($absolute !== null) {
                return $absolute;
            }
        }

        return null;
    }

    private static function cacheFileIsFresh(string $absolute): bool
    {
        if (! is_file($absolute)) {
            return false;
        }

        return filemtime($absolute) >= time() - self::cacheTtlSeconds();
    }

    public static function legacyCacheAbsolutePath(InspectionPhoto $photo, bool $composite): string
    {
        $suffix = $composite ? '-composite' : '-original';

        return storage_path('app/drive-cache/photo-'.$photo->id.$suffix.'.jpg');
    }

    /**
     * تحميل من Drive → storage/app/cache/photos/ → تحديث DB.
     */
    public static function buildPersistentCache(InspectionPhoto $photo, bool $composite): bool
    {
        self::$lastError = null;

        if (self::seedCacheFromLocalSources($photo, $composite)) {
            return true;
        }

        if (! self::enabled()) {
            return false;
        }

        $fileId = $composite && $photo->drive_composite_file_id
            ? $photo->drive_composite_file_id
            : $photo->drive_file_id;

        if (! $fileId) {
            self::$lastError = 'لا يوجد drive_file_id للصورة #'.$photo->id;

            return false;
        }

        $absolute = self::cacheAbsolutePath($photo, $composite);
        $dir = dirname($absolute);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        try {
            GoogleDriveService::downloadToPathReliable($fileId, $absolute);
        } catch (Throwable $e) {
            self::$lastError = $e->getMessage();
            if (is_file($absolute)) {
                @unlink($absolute);
            }
            Log::warning('Drive persistent cache download failed', [
                'photo_id' => $photo->id,
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if (! self::isValidImageFile($absolute)) {
            @unlink($absolute);
            self::$lastError = 'ملف الكاش ليس صورة صالحة (ربما HTML من Drive)';

            return false;
        }

        self::persistCachePathToDb($photo, $composite);

        return true;
    }

    private static function persistCachePathToDb(InspectionPhoto $photo, bool $composite): void
    {
        if (! self::cacheColumnsAvailable()) {
            return;
        }

        $relative = self::cacheRelativePath($photo, $composite);
        $column = $composite ? 'processed_cache_path' : 'local_cached_path';
        $photo->update([$column => $relative]);
    }

    public static function hasValidPersistentCache(InspectionPhoto $photo, bool $composite = false): bool
    {
        $path = $composite ? $photo->processed_cache_path : $photo->local_cached_path;
        if (self::absoluteFromStoragePath($path) !== null) {
            return true;
        }

        return self::isValidImageFile(self::cacheAbsolutePath($photo, $composite));
    }

    public static function invalidatePersistentCache(InspectionPhoto $photo, bool $composite): void
    {
        $column = $composite ? 'processed_cache_path' : 'local_cached_path';
        $relative = $photo->{$column};
        if ($relative) {
            $absolute = storage_path('app/'.ltrim($relative, '/'));
            if (is_file($absolute)) {
                @unlink($absolute);
            }
        }

        $absolute = self::cacheAbsolutePath($photo, $composite);
        if (is_file($absolute)) {
            @unlink($absolute);
        }

        $legacy = self::legacyCacheAbsolutePath($photo, $composite);
        if (is_file($legacy)) {
            @unlink($legacy);
        }

        if (self::cacheColumnsAvailable() && $photo->{$column}) {
            try {
                $photo->update([$column => null]);
            } catch (Throwable $e) {
                Log::warning('invalidatePersistentCache db clear failed', [
                    'photo_id' => $photo->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * نسخ من public/hosted إلى كاش العرض إن وُجد (أسرع من Drive).
     */
    public static function seedCacheFromLocalSources(InspectionPhoto $photo, bool $composite): bool
    {
        $disk = Storage::disk('public');
        $relative = $composite && ($photo->composite_path || $photo->drive_composite_file_id)
            ? ($photo->composite_path ?: $photo->original_path)
            : $photo->original_path;

        $source = null;
        if ($relative && $disk->exists($relative)) {
            $candidate = $disk->path($relative);
            if (self::isValidImageFile($candidate)) {
                $source = $candidate;
            }
        }

        $source ??= self::resolveHostedPublicPath($photo, $composite);
        if (! $source) {
            return false;
        }

        $dest = self::cacheAbsolutePath($photo, $composite);
        $dir = dirname($dest);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (! @copy($source, $dest) || ! self::isValidImageFile($dest)) {
            if (is_file($dest)) {
                @unlink($dest);
            }

            return false;
        }

        self::persistCachePathToDb($photo, $composite);

        return true;
    }

    /**
     * إصلاح كاش عرض كل صور المنزل (بدون Terminal).
     *
     * @return array{ok: int, fail: int, migrated: bool, failures: list<array{photo_id: int, error: string}>}
     */
    public static function repairHouseDisplayCache(PropertyHouse $house): array
    {
        @ini_set('max_execution_time', '600');
        @ini_set('memory_limit', '512M');

        self::$lastError = null;
        self::ensureStorageDirectories();
        $migrated = self::ensureCacheDatabaseColumns();

        $ok = 0;
        $fail = 0;
        $failures = [];

        $house->load(['inspectionAreas.photos']);

        foreach ($house->inspectionAreas as $area) {
            foreach ($area->photos as $photo) {
                self::invalidatePersistentCache($photo, false);
                self::invalidatePersistentCache($photo, true);

                if (! $photo->drive_file_id && ! $photo->drive_composite_file_id) {
                    if (self::seedCacheFromLocalSources($photo, false)) {
                        $ok++;
                    }

                    continue;
                }

                $built = false;
                if ($photo->drive_file_id) {
                    $built = self::buildPersistentCache($photo, false) || $built;
                }
                if ($photo->drive_composite_file_id && $photo->drive_composite_file_id !== $photo->drive_file_id) {
                    $built = self::buildPersistentCache($photo->fresh(), true) || $built;
                }

                if ($built) {
                    $ok++;
                } else {
                    $fail++;
                    $failures[] = [
                        'photo_id' => (int) $photo->id,
                        'error' => (string) (self::$lastError ?? 'فشل غير معروف'),
                    ];
                }
            }
        }

        return ['ok' => $ok, 'fail' => $fail, 'migrated' => $migrated, 'failures' => $failures];
    }

    public static function pushPhoto(InspectionPhoto $photo): bool
    {
        self::$lastError = null;

        if (! self::enabled()) {
            return false;
        }

        if (! self::ensureOnPublicDisk($photo)) {
            self::$lastError = 'ملف الصورة غير موجود على السيرفر (photo #'.$photo->id.')';

            return false;
        }

        try {
            GoogleDriveService::syncPhoto($photo->fresh());
            $photo = $photo->fresh();

            $cacheOk = true;
            if ($photo->drive_file_id) {
                $cacheOk = self::buildPersistentCache($photo, false);
            }
            if ($photo->drive_composite_file_id) {
                $cacheOk = self::buildPersistentCache($photo->fresh(), true) && $cacheOk;
            }

            if (! $cacheOk && $photo->drive_file_id) {
                self::$lastError = self::$lastError ?? 'فشل بناء كاش العرض المحلي';
                Log::warning('Drive pushPhoto: cache not built, keeping local copy', [
                    'photo_id' => $photo->id,
                ]);

                return false;
            }

            $canDeleteLocal = (! $photo->drive_file_id || self::hasValidPersistentCache($photo, false))
                && (! $photo->drive_composite_file_id || self::hasValidPersistentCache($photo, true));

            if (config('google-drive.delete_local_after_upload', true)
                && ($photo->drive_file_id || $photo->drive_composite_file_id)
                && $canDeleteLocal) {
                self::purgePublicCopies($photo);
            }

            return true;
        } catch (Throwable $e) {
            self::$lastError = $e->getMessage();
            Log::error('Drive pushPhoto failed', [
                'photo_id' => $photo->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * حفظ ملف مؤقت قبل الرفع لـ Drive.
     */
    public static function storeTempUpload(int $houseId, string $sourceAbsolute, string $extension = 'jpg'): string
    {
        $filename = uniqid('up_', true).'.'.ltrim($extension, '.');
        $relative = self::tempRelativePath($houseId, $filename);
        $dest = storage_path('app/'.$relative);
        $dir = dirname($dest);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        copy($sourceAbsolute, $dest);

        return $relative;
    }

    public static function promoteTempToPublic(string $tempRelative, int $houseId): string
    {
        $disk = Storage::disk('public');
        $basename = basename($tempRelative);
        $publicRelative = "inspections/{$houseId}/{$basename}";
        $source = storage_path('app/'.$tempRelative);
        $disk->put($publicRelative, (string) file_get_contents($source));
        @unlink($source);

        return $publicRelative;
    }

    public static function hasLocalCopy(InspectionPhoto $photo): bool
    {
        $disk = Storage::disk('public');
        foreach ([$photo->original_path, $photo->composite_path] as $path) {
            if ($path && $disk->exists($path)) {
                return true;
            }
        }

        if (self::hasValidPersistentCache($photo, false) || self::hasValidPersistentCache($photo, true)) {
            return true;
        }

        return self::resolveHostedPublicPath($photo, true) !== null
            || self::resolveHostedPublicPath($photo, false) !== null;
    }

    public static function resolveHostedPublicPath(InspectionPhoto $photo, bool $preferComposite): ?string
    {
        $photo->loadMissing('inspectionArea');
        $houseId = $photo->inspectionArea?->property_house_id;
        if (! $houseId) {
            return null;
        }

        $relative = $preferComposite && ($photo->composite_path || $photo->drive_composite_file_id)
            ? ($photo->composite_path ?: $photo->original_path)
            : $photo->original_path;

        if (! $relative) {
            return null;
        }

        $basename = basename(str_replace('\\', '/', $relative));
        foreach (self::hostedPathCandidates((int) $houseId, $basename) as $absolute) {
            if (self::isValidImageFile($absolute)) {
                return $absolute;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function hostedPathCandidates(int $houseId, string $basename): array
    {
        return [
            public_path('storage/inspections/'.$houseId.'/'.$basename),
            dirname(base_path(), 2).'/storage/inspections/'.$houseId.'/'.$basename,
        ];
    }

    public static function ensureOnPublicDisk(InspectionPhoto $photo): bool
    {
        $disk = Storage::disk('public');
        $relative = $photo->composite_path ?? $photo->original_path;
        if (! $relative) {
            return false;
        }

        if (str_starts_with($relative, 'temp/')) {
            $tempAbs = storage_path('app/'.$relative);
            if (is_file($tempAbs) && self::isValidImageFile($tempAbs)) {
                $houseId = $photo->inspectionArea?->property_house_id;
                if ($houseId) {
                    $publicRelative = self::promoteTempToPublic($relative, (int) $houseId);
                    $photo->update(['original_path' => $publicRelative]);

                    return true;
                }
            }

            return false;
        }

        if ($disk->exists($relative)) {
            return true;
        }

        $hosted = self::resolveHostedPublicPath($photo, (bool) $photo->composite_path);
        if (! $hosted) {
            return self::hasValidPersistentCache($photo, false);
        }

        $disk->put($relative, (string) file_get_contents($hosted));

        return $disk->exists($relative);
    }

    public static function needsDriveUpload(InspectionPhoto $photo): bool
    {
        if ($photo->drive_file_id || $photo->drive_composite_file_id) {
            return false;
        }

        return self::hasLocalCopy($photo);
    }

    public static function resolveDriveFileId(InspectionPhoto $photo, bool $preferComposite = false): ?string
    {
        if ($preferComposite && $photo->drive_composite_file_id) {
            return $photo->drive_composite_file_id;
        }

        return $photo->drive_file_id ?: $photo->drive_composite_file_id;
    }

    public static function isValidImageFile(string $path): bool
    {
        if (! is_file($path) || filesize($path) < 100) {
            return false;
        }

        $head = (string) @file_get_contents($path, false, null, 0, 512);
        if ($head !== '' && (stripos($head, '<html') !== false || stripos($head, '<!DOCTYPE') !== false)) {
            return false;
        }

        $info = @getimagesize($path);

        return is_array($info) && ($info[0] ?? 0) > 0;
    }

    public static function purgePublicCopies(InspectionPhoto $photo): void
    {
        $disk = Storage::disk('public');
        foreach ([$photo->original_path, $photo->composite_path] as $path) {
            if ($path && $disk->exists($path)) {
                $disk->delete($path);
            }
        }
    }

    public static function refreshCache(InspectionPhoto $photo): void
    {
        if (! self::enabled()) {
            return;
        }

        $photo = $photo->fresh();
        self::invalidatePersistentCache($photo, false);
        self::invalidatePersistentCache($photo, true);

        if ($photo->drive_file_id) {
            self::buildPersistentCache($photo, false);
        }
        if ($photo->drive_composite_file_id) {
            self::buildPersistentCache($photo->fresh(), true);
        }
    }

    /**
     * @return array{temp_deleted: int, cache_deleted: int}
     */
    public static function cleanExpiredStorage(): array
    {
        $maxAge = self::cacheTtlSeconds();
        $cutoff = time() - $maxAge;
        $tempDeleted = 0;
        $cacheDeleted = 0;

        foreach ([storage_path('app/temp'), storage_path('app/cache/photos'), storage_path('app/drive-cache')] as $dir) {
            if (! is_dir($dir)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $item) {
                if (! $item->isFile()) {
                    continue;
                }
                if ($item->getMTime() >= $cutoff) {
                    continue;
                }
                if (@unlink($item->getPathname())) {
                    if (str_contains($item->getPath(), 'temp')) {
                        $tempDeleted++;
                    } else {
                        $cacheDeleted++;
                    }
                }
            }
        }

        return ['temp_deleted' => $tempDeleted, 'cache_deleted' => $cacheDeleted];
    }

    public static function deleteDriveAssets(InspectionPhoto $photo): void
    {
        if ($photo->drive_file_id) {
            GoogleDriveService::deleteFile($photo->drive_file_id);
        }
        if ($photo->drive_composite_file_id) {
            GoogleDriveService::deleteFile($photo->drive_composite_file_id);
        }
        if ($photo->drive_notes_file_id) {
            GoogleDriveService::deleteFile($photo->drive_notes_file_id);
        }

        self::invalidatePersistentCache($photo, false);
        self::invalidatePersistentCache($photo, true);
    }
}
