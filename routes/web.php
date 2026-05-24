<?php

use App\Http\Controllers\Admin\InspectionAreaController;
use App\Http\Controllers\Admin\InspectionPhotoController;
use App\Http\Controllers\Admin\NoteCategoryController;
use App\Http\Controllers\Admin\NoteTemplateController;
use App\Http\Controllers\Admin\PropertyHouseController;
use App\Http\Controllers\Admin\SectionTemplateController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\GoogleDriveAuthController;
use App\Http\Controllers\Admin\HouseDriveController;

Route::get('/', HomeController::class)->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:12,1');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// صور المنازل — توكن HMAC (يعمل داخل img بدون session على cPanel)
Route::get('admin/houses/{house}/photos/{photo}/image', [InspectionPhotoController::class, 'image'])
    ->name('admin.houses.photos.image');

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', fn () => redirect()->route('admin.houses.index'));
    Route::post('houses/{house}/report/finalize', [\App\Http\Controllers\Admin\ReportFinalizeController::class, 'finalize'])->name('houses.report.finalize');
    Route::get('houses/{house}/report.pdf', [PropertyHouseController::class, 'report'])->name('houses.report');
    Route::get('houses/{house}/report-word', [PropertyHouseController::class, 'reportWord'])->name('houses.report.word');
    Route::resource('houses', PropertyHouseController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);
    Route::post('houses/{house}/areas', [InspectionAreaController::class, 'store'])->name('houses.areas.store');
    Route::patch('houses/{house}/areas/{area}', [InspectionAreaController::class, 'update'])->name('houses.areas.update');
    Route::patch('houses/{house}/areas/{area}/move', [InspectionAreaController::class, 'move'])->name('houses.areas.move');
    Route::delete('houses/{house}/areas/{area}', [InspectionAreaController::class, 'destroy'])->name('houses.areas.destroy');
    Route::post('houses/{house}/areas/{area}/photos', [InspectionPhotoController::class, 'store'])->name('houses.areas.photos.store');
    Route::post('houses/{house}/areas/{area}/photos/merge', [InspectionPhotoController::class, 'storeMerged'])->name('houses.areas.photos.merge');
    Route::post('houses/{house}/areas/{area}/photos/deduplicate', [InspectionPhotoController::class, 'deduplicate'])->name('houses.areas.photos.deduplicate');
    Route::delete('houses/{house}/areas/{area}/photos/bulk', [InspectionPhotoController::class, 'bulkDestroy'])->name('houses.areas.photos.bulk_destroy');
    Route::get('houses/{house}/photos/{photo}/edit', [InspectionPhotoController::class, 'edit'])->name('houses.photos.edit');
    Route::patch('houses/{house}/photos/{photo}/move', [InspectionPhotoController::class, 'move'])->name('houses.photos.move');
    Route::patch('houses/{house}/photos/{photo}', [InspectionPhotoController::class, 'update'])->name('houses.photos.update');
    Route::patch('houses/{house}/photos/{photo}/notes', [InspectionPhotoController::class, 'updateNotes'])->name('houses.photos.notes.update');
    Route::delete('houses/{house}/photos/{photo}', [InspectionPhotoController::class, 'destroy'])->name('houses.photos.destroy');

    Route::get('note-categories', [NoteCategoryController::class, 'index'])->name('note-categories.index');
    Route::post('note-categories', [NoteCategoryController::class, 'store'])->name('note-categories.store');
    Route::patch('note-categories/{category}', [NoteCategoryController::class, 'update'])->name('note-categories.update');
    Route::delete('note-categories/{category}', [NoteCategoryController::class, 'destroy'])->name('note-categories.destroy');

    Route::post('note-categories/{category}/templates', [NoteTemplateController::class, 'store'])->name('note-categories.templates.store');
    Route::patch('note-categories/{category}/templates/{template}', [NoteTemplateController::class, 'update'])->name('note-categories.templates.update');
    Route::delete('note-categories/{category}/templates/{template}', [NoteTemplateController::class, 'destroy'])->name('note-categories.templates.destroy');

    Route::get('section-templates', [SectionTemplateController::class, 'index'])->name('section-templates.index');
    Route::post('section-templates', [SectionTemplateController::class, 'store'])->name('section-templates.store');
    Route::patch('section-templates/{sectionTemplate}', [SectionTemplateController::class, 'update'])->name('section-templates.update');
    Route::patch('section-templates/{sectionTemplate}/move', [SectionTemplateController::class, 'move'])->name('section-templates.move');
    Route::delete('section-templates/{sectionTemplate}', [SectionTemplateController::class, 'destroy'])->name('section-templates.destroy');

    Route::get('google-drive/connect', [GoogleDriveAuthController::class, 'connect'])->name('google-drive.connect');
    Route::get('google-drive/callback', [GoogleDriveAuthController::class, 'callback'])->name('google-drive.callback');
    Route::post('google-drive/disconnect', [GoogleDriveAuthController::class, 'disconnect'])->name('google-drive.disconnect');

    Route::post('houses/{house}/drive/sync', [HouseDriveController::class, 'syncAll'])->name('houses.drive.sync');
    Route::post('houses/{house}/drive/repair-cache', [HouseDriveController::class, 'repairDisplayCache'])->name('houses.drive.repair-cache');
    Route::post('houses/{house}/drive/upload-pdf', [HouseDriveController::class, 'uploadPdf'])->name('houses.drive.upload-pdf');

    Route::get('migrate-db', function (): \Illuminate\Http\Response {
        $lines = [];
        try {
            $lines[] = \App\Services\DriveMediaService::ensureCacheDatabaseColumns()
                ? 'OK: local_cached_path + processed_cache_path'
                : 'WARN: cache columns';
        } catch (\Throwable $e) {
            $lines[] = 'cache columns: '.$e->getMessage();
        }
        try {
            $output = new \Symfony\Component\Console\Output\BufferedOutput();
            \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true], $output);
            $lines[] = trim($output->fetch()) ?: 'migrate: nothing pending';
        } catch (\Throwable $e) {
            $lines[] = 'migrate note: '.$e->getMessage();
        }

        return response('<pre>'.e(implode("\n", $lines)).'</pre>', 200);
    })->name('migrate-db');
});
