<?php

namespace App\Services;

use App\Models\InspectionArea;
use App\Models\InspectionPhoto;
use App\Models\PropertyHouse;
use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\Permission;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Google Drive — OAuth (Gmail شخصي) أو Service Account (Workspace فقط)
 *
 * ALLHOUSES/
 *   └── House #1 - Title/
 *       ├── تقرير المعاينة.pdf
 *       ├── التقرير الكتابي.html
 *       └── 01 - المطبخ/
 *           ├── photo-123.jpg
 *           ├── photo-123-notes.txt
 *           └── photo-123-edited.jpg
 */
class GoogleDriveService
{
    private static ?Drive $service = null;

    public static function authMode(): string
    {
        return (string) config('google-drive.auth_mode', 'oauth');
    }

    public static function credentialsPath(): string
    {
        return base_path(config('google-drive.credentials', 'storage/app/google-drive-credentials.json'));
    }

    public static function tokenPath(): string
    {
        return base_path(config('google-drive.oauth_token_path', 'storage/app/google-drive-oauth-token.json'));
    }

    public static function oauthClientConfigured(): bool
    {
        return filled(config('google-drive.oauth_client_id'))
            && filled(config('google-drive.oauth_client_secret'));
    }

    public static function isOAuthConnected(): bool
    {
        $token = self::loadOAuthToken();

        return is_array($token)
            && (filled($token['refresh_token'] ?? null) || filled($token['access_token'] ?? null));
    }

    public static function isConfigured(): bool
    {
        if (! filled(config('google-drive.root_folder_id'))) {
            return false;
        }

        if (self::authMode() === 'service_account') {
            return is_readable(self::credentialsPath());
        }

        return self::oauthClientConfigured() && self::isOAuthConnected();
    }

    public static function resetClient(): void
    {
        self::$service = null;
    }

    /** @return array<string, mixed>|null */
    public static function loadOAuthToken(): ?array
    {
        $path = self::tokenPath();
        if (! is_readable($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : null;
    }

    /** @param array<string, mixed> $token */
    public static function saveOAuthToken(array $token): void
    {
        $path = self::tokenPath();
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $existing = self::loadOAuthToken();
        if ($existing && ! isset($token['refresh_token']) && isset($existing['refresh_token'])) {
            $token['refresh_token'] = $existing['refresh_token'];
        }

        file_put_contents($path, json_encode($token, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public static function clearOAuthToken(): void
    {
        $path = self::tokenPath();
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public static function buildOAuthClient(): Client
    {
        if (! self::oauthClientConfigured()) {
            throw new \RuntimeException('ضبط GOOGLE_DRIVE_OAUTH_CLIENT_ID و GOOGLE_DRIVE_OAUTH_CLIENT_SECRET في .env');
        }

        $client = new Client();
        $client->setClientId((string) config('google-drive.oauth_client_id'));
        $client->setClientSecret((string) config('google-drive.oauth_client_secret'));
        $redirect = config('google-drive.oauth_redirect_uri');
        $client->setRedirectUri($redirect ?: url('/admin/google-drive/callback'));
        $client->setApplicationName('GIS Inspection System');
        $client->setScopes([Drive::DRIVE]);
        $client->setAccessType('offline');
        $client->setHttpClient(new \GuzzleHttp\Client([
            'timeout' => 120,
            'connect_timeout' => 30,
        ]));

        return $client;
    }

    private static function buildAuthenticatedClient(): Client
    {
        if (self::authMode() === 'service_account') {
            $credPath = self::credentialsPath();
            if (! is_file($credPath)) {
                throw new \RuntimeException('ملف اعتماد Google Drive غير موجود: '.$credPath);
            }

            $client = new Client();
            $client->setAuthConfig($credPath);
            $client->setScopes([Drive::DRIVE]);

            return $client;
        }

        $client = self::buildOAuthClient();
        $token = self::loadOAuthToken();
        if (! is_array($token)) {
            throw new \RuntimeException('Google Drive غير مربوط — اضغط «ربط Google Drive» من لوحة التحكم.');
        }

        $client->setAccessToken($token);

        if ($client->isAccessTokenExpired()) {
            $refresh = $token['refresh_token'] ?? null;
            if (! is_string($refresh) || $refresh === '') {
                throw new \RuntimeException('انتهت صلاحية ربط Google Drive — أعد الربط من لوحة التحكم.');
            }

            $newToken = $client->fetchAccessTokenWithRefreshToken($refresh);
            if (isset($newToken['error'])) {
                throw new \RuntimeException((string) ($newToken['error_description'] ?? $newToken['error']));
            }

            self::saveOAuthToken($newToken);
            $client->setAccessToken(self::loadOAuthToken());
        }

        return $client;
    }

    private static function client(): Drive
    {
        if (self::$service) {
            return self::$service;
        }

        $googleClient = self::buildAuthenticatedClient();
        $googleClient->setApplicationName('GIS Inspection System');

        self::$service = new Drive($googleClient);

        return self::$service;
    }

    /** @return array<string, mixed> */
    private static function driveOpts(): array
    {
        return ['supportsAllDrives' => true];
    }

    public static function getAllHousesFolderId(): string
    {
        $rootId = (string) config('google-drive.root_folder_id');
        if ($rootId === '') {
            throw new \RuntimeException('GOOGLE_DRIVE_ROOT_FOLDER_ID غير مضبوط في .env');
        }

        $name = trim((string) config('google-drive.all_houses_folder_name', 'ALLHOUSES'));
        // إذا فولدر .env هو ALLHOUSES نفسه: ALL_HOUSES_FOLDER_NAME=.
        if ($name === '' || $name === '.') {
            return $rootId;
        }

        return self::findOrCreateFolder($name, $rootId);
    }

    public static function findOrCreateFolder(string $name, string $parentId): string
    {
        $service = self::client();

        $q = 'name = '.json_encode($name, JSON_UNESCAPED_UNICODE)
            ." and mimeType = 'application/vnd.google-apps.folder'"
            ." and '{$parentId}' in parents"
            .' and trashed = false';

        $results = $service->files->listFiles([
            'q' => $q,
            'fields' => 'files(id, name)',
            'spaces' => 'drive',
            'includeItemsFromAllDrives' => true,
            'supportsAllDrives' => true,
        ]);

        if (count($results->getFiles()) > 0) {
            return $results->getFiles()[0]->getId();
        }

        $meta = new DriveFile([
            'name' => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => [$parentId],
        ]);

        $folder = $service->files->create($meta, [
            'fields' => 'id',
            'supportsAllDrives' => true,
        ]);

        self::shareResource($folder->getId());

        return $folder->getId();
    }

    public static function getOrCreateHouseFolder(PropertyHouse $house): string
    {
        if ($house->drive_folder_id) {
            return $house->drive_folder_id;
        }

        $parentId = self::getAllHousesFolderId();
        $name = 'House #'.$house->id.' - '.($house->title ?: 'بدون عنوان');
        $folderId = self::findOrCreateFolder($name, $parentId);

        $house->update(['drive_folder_id' => $folderId]);

        return $folderId;
    }

    public static function getOrCreateAreaFolder(InspectionArea $area): string
    {
        if ($area->drive_folder_id) {
            return $area->drive_folder_id;
        }

        $area->loadMissing('propertyHouse');
        $houseFolderId = self::getOrCreateHouseFolder($area->propertyHouse);

        $order = str_pad((string) ((int) $area->sort_order), 2, '0', STR_PAD_LEFT);
        $name = $order.' - '.($area->name ?: 'قسم');
        $folderId = self::findOrCreateFolder($name, $houseFolderId);

        $area->update(['drive_folder_id' => $folderId]);

        return $folderId;
    }

    /**
     * @return string Drive file ID
     */
    public static function uploadFile(
        string $absolutePath,
        string $fileName,
        string $mimeType,
        string $parentFolderId
    ): string {
        $service = self::client();

        $content = file_get_contents($absolutePath);
        if ($content === false) {
            throw new \RuntimeException('تعذر قراءة الملف: '.$absolutePath);
        }

        $meta = new DriveFile([
            'name' => $fileName,
            'parents' => [$parentFolderId],
        ]);

        $file = $service->files->create($meta, [
            'data' => $content,
            'mimeType' => $mimeType,
            'uploadType' => 'multipart',
            'fields' => 'id',
            'supportsAllDrives' => true,
        ]);

        $id = $file->getId();
        self::shareResource($id);
        if (config('google-drive.public_read_on_upload', true)) {
            self::ensureAnyoneCanRead($id);
        }

        return $id;
    }

    /**
     * رابط مشاهدة (للإدارة فقط — المتصفح قد لا يعرضه داخل img بدون كاش محلي).
     */
    public static function publicViewUrl(string $fileId): string
    {
        return 'https://drive.google.com/uc?export=view&id='.urlencode($fileId);
    }

  /**
     * @return array{id: string, webViewLink: ?string, webContentLink: ?string, publicViewUrl: string}
     */
    public static function fileLinks(string $fileId): array
    {
        $meta = self::client()->files->get($fileId, [
            'fields' => 'id,webViewLink,webContentLink',
            'supportsAllDrives' => true,
        ]);

        return [
            'id' => $fileId,
            'webViewLink' => $meta->getWebViewLink(),
            'webContentLink' => $meta->getWebContentLink(),
            'publicViewUrl' => self::publicViewUrl($fileId),
        ];
    }

    public static function ensureAnyoneCanRead(string $fileId): void
    {
        try {
            self::client()->permissions->create($fileId, new Permission([
                'type' => 'anyone',
                'role' => 'reader',
            ]), [
                'sendNotificationEmail' => false,
                'supportsAllDrives' => true,
            ]);
        } catch (Throwable $e) {
            Log::debug('Drive anyone-read may already exist', ['file_id' => $fileId, 'error' => $e->getMessage()]);
        }
    }

    public static function updateFileContent(string $fileId, string $absolutePath, string $mimeType): void
    {
        $service = self::client();
        $content = file_get_contents($absolutePath);
        if ($content === false) {
            throw new \RuntimeException('تعذر قراءة الملف: '.$absolutePath);
        }

        $service->files->update($fileId, new DriveFile(), [
            'data' => $content,
            'mimeType' => $mimeType,
            'uploadType' => 'multipart',
            'supportsAllDrives' => true,
        ]);
    }

    /**
     * رفع/تحديث صورة + ملف ملاحظات .txt
     */
    public static function syncPhoto(InspectionPhoto $photo): void
    {
        $photo->loadMissing('inspectionArea.propertyHouse');
        $areaFolderId = self::getOrCreateAreaFolder($photo->inspectionArea);
        $disk = \Illuminate\Support\Facades\Storage::disk('public');

        $imagePath = $photo->composite_path ?? $photo->original_path;
        if (! $imagePath || ! $disk->exists($imagePath)) {
            throw new \RuntimeException('ملف الصورة غير موجود محلياً للرفع (photo #'.$photo->id.')');
        }

        $abs = $disk->path($imagePath);
        $baseName = 'photo-'.$photo->id;
        $ext = pathinfo($abs, PATHINFO_EXTENSION) ?: 'jpg';
        $fileName = $baseName.($photo->composite_path ? '-edited' : '').'.'.$ext;
        $mime = mime_content_type($abs) ?: 'image/jpeg';

        $driveImageId = $photo->composite_path && $photo->drive_composite_file_id
            ? $photo->drive_composite_file_id
            : $photo->drive_file_id;

        if ($driveImageId) {
            self::updateFileContent($driveImageId, $abs, $mime);
        } else {
            $driveImageId = self::uploadFile($abs, $fileName, $mime, $areaFolderId);
        }

        $updates = [];
        if ($photo->composite_path) {
            $updates['drive_composite_file_id'] = $driveImageId;
        } else {
            $updates['drive_file_id'] = $driveImageId;
        }
        $photo->update($updates);

        self::syncPhotoNotesFile($photo, $areaFolderId);
    }

    public static function syncPhotoNotesFile(InspectionPhoto $photo, ?string $areaFolderId = null): void
    {
        $notesText = self::formatNotesForTxt($photo);
        if ($notesText === '') {
            if ($photo->drive_notes_file_id) {
                self::deleteFile($photo->drive_notes_file_id);
                $photo->update(['drive_notes_file_id' => null]);
            }

            return;
        }

        $photo->loadMissing('inspectionArea');
        $areaFolderId ??= self::getOrCreateAreaFolder($photo->inspectionArea);

        $tmp = sys_get_temp_dir().'/notes-'.$photo->id.'-'.uniqid('', true).'.txt';
        file_put_contents($tmp, $notesText);

        $fileName = 'photo-'.$photo->id.'-notes.txt';

        try {
            if ($photo->drive_notes_file_id) {
                self::updateFileContent($photo->drive_notes_file_id, $tmp, 'text/plain');
            } else {
                $id = self::uploadFile($tmp, $fileName, 'text/plain', $areaFolderId);
                $photo->update(['drive_notes_file_id' => $id]);
            }
        } finally {
            @unlink($tmp);
        }
    }

    public static function formatNotesForTxt(InspectionPhoto $photo): string
    {
        $lines = [];
        $desc = trim((string) $photo->description);
        if ($desc !== '') {
            $lines[] = 'وصف: '.$desc;
        }
        foreach ($photo->notesEntries() as $i => $entry) {
            $lines[] = ($i + 1).'. '.$entry['text'];
        }

        return implode("\n", $lines);
    }

    public static function uploadPdfReport(PropertyHouse $house, string $absolutePdfPath): string
    {
        $folderId = self::getOrCreateHouseFolder($house);
        $fileName = 'تقرير المعاينة - '.($house->title ?: 'House#'.$house->id).'.pdf';

        if ($house->drive_pdf_id) {
            self::updateFileContent($house->drive_pdf_id, $absolutePdfPath, 'application/pdf');
            $fileId = $house->drive_pdf_id;
        } else {
            $fileId = self::uploadFile($absolutePdfPath, $fileName, 'application/pdf', $folderId);
            $house->update(['drive_pdf_id' => $fileId]);
        }

        return $fileId;
    }

    public static function uploadWordReport(PropertyHouse $house, string $htmlContent): string
    {
        $folderId = self::getOrCreateHouseFolder($house);
        $fileName = 'التقرير الكتابي - '.($house->title ?: 'House#'.$house->id).'.doc';
        $mime = 'application/msword';
        $tmpPath = sys_get_temp_dir().'/'.uniqid('word_', true).'.doc';
        file_put_contents($tmpPath, $htmlContent);

        try {
            if ($house->drive_word_file_id) {
                try {
                    self::updateFileContent($house->drive_word_file_id, $tmpPath, $mime);

                    return $house->drive_word_file_id;
                } catch (Throwable) {
                    self::deleteFile($house->drive_word_file_id);
                    $house->update(['drive_word_file_id' => null]);
                    $house = $house->fresh();
                }
            }

            $fileId = self::uploadFile($tmpPath, $fileName, $mime, $folderId);
            $house->update(['drive_word_file_id' => $fileId]);

            return $fileId;
        } finally {
            @unlink($tmpPath);
        }
    }

    /**
     * @return array{mime: string, bytes: string}
     */
    public static function fetchImageBytes(string $fileId): array
    {
        $service = self::client();
        $meta = $service->files->get($fileId, [
            'fields' => 'id,mimeType',
            'supportsAllDrives' => true,
        ]);
        $mime = $meta->getMimeType() ?: 'image/jpeg';

        $response = $service->files->get($fileId, [
            'alt' => 'media',
            'supportsAllDrives' => true,
        ]);

        $bytes = $response->getBody()->getContents();
        if (strlen($bytes) < 100) {
            throw new \RuntimeException('استجابة Drive فارغة لملف الصورة.');
        }

        if (@getimagesizefromstring($bytes) === false) {
            throw new \RuntimeException('بيانات الصورة من Drive غير صالحة (ليست صورة).');
        }

        return ['mime' => $mime, 'bytes' => $bytes];
    }

    /**
     * عرض صورة من Drive مباشرة في المتصفح — بدون كتابة على القرص.
     */
    public static function imageBinaryResponse(string $fileId): \Illuminate\Http\Response
    {
        $data = self::fetchImageBytes($fileId);

        return response($data['bytes'], 200, [
            'Content-Type' => $data['mime'],
            'Content-Length' => (string) strlen($data['bytes']),
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @deprecated استخدم imageBinaryResponse */
    public static function imageResponse(string $fileId): \Illuminate\Http\Response
    {
        return self::imageBinaryResponse($fileId);
    }

    /**
     * تحميل موثوق: تدفق → ذاكرة → صلاحية عامة ثم إعادة المحاولة (مناسب لـ cPanel).
     */
    public static function downloadToPathReliable(string $fileId, string $absolutePath): void
    {
        $errors = [];

        try {
            self::downloadToPath($fileId, $absolutePath);

            return;
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
            if (is_file($absolutePath)) {
                @unlink($absolutePath);
            }
        }

        try {
            $data = self::fetchImageBytes($fileId);
            file_put_contents($absolutePath, $data['bytes']);
            if (! is_file($absolutePath) || @getimagesize($absolutePath) === false) {
                throw new \RuntimeException('فشل حفظ الصورة من الذاكرة.');
            }

            return;
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
            if (is_file($absolutePath)) {
                @unlink($absolutePath);
            }
        }

        if (config('google-drive.public_read_on_upload', true)) {
            self::ensureAnyoneCanRead($fileId);
            try {
                self::downloadToPath($fileId, $absolutePath);

                return;
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
                if (is_file($absolutePath)) {
                    @unlink($absolutePath);
                }
            }
        }

        throw new \RuntimeException(implode(' | ', array_unique($errors)));
    }

    /**
     * تحميل ملف من Drive إلى مسار محلي (للعرض / PDF)
     */
    public static function downloadToPath(string $fileId, string $absolutePath): void
    {
        $dir = dirname($absolutePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $response = self::client()->files->get($fileId, [
            'alt' => 'media',
            'supportsAllDrives' => true,
        ]);

        $body = $response->getBody();
        $handle = fopen($absolutePath, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('تعذر فتح مسار الكاش للكتابة: '.$absolutePath);
        }

        try {
            while (! $body->eof()) {
                $chunk = $body->read(8192);
                if ($chunk === '' && $body->eof()) {
                    break;
                }
                fwrite($handle, $chunk);
            }
        } finally {
            fclose($handle);
        }

        if (! is_file($absolutePath) || filesize($absolutePath) < 100) {
            @unlink($absolutePath);
            throw new \RuntimeException('ملف Drive فارغ بعد التحميل: '.$absolutePath);
        }

        $head = (string) @file_get_contents($absolutePath, false, null, 0, 512);
        if ($head !== '' && (stripos($head, '<html') !== false || stripos($head, '<!DOCTYPE') !== false)) {
            @unlink($absolutePath);
            throw new \RuntimeException('استجابة Drive ليست صورة (HTML/خطأ مصادقة).');
        }

        if (@getimagesize($absolutePath) === false) {
            @unlink($absolutePath);
            throw new \RuntimeException('بيانات الصورة من Drive غير صالحة بعد التحميل.');
        }
    }

    public static function shareResource(string $fileOrFolderId): void
    {
        $email = config('google-drive.share_with_email');
        if (! filled($email)) {
            return;
        }

        try {
            $perm = new Permission([
                'type' => 'user',
                'role' => 'writer',
                'emailAddress' => $email,
            ]);
            self::client()->permissions->create($fileOrFolderId, $perm, [
                'sendNotificationEmail' => false,
                'supportsAllDrives' => true,
            ]);
        } catch (Throwable $e) {
            Log::warning('Drive share failed', ['id' => $fileOrFolderId, 'error' => $e->getMessage()]);
        }
    }

    public static function getShareLink(string $fileId): string
    {
        return 'https://drive.google.com/file/d/'.$fileId.'/view?usp=sharing';
    }

    public static function getFolderLink(string $folderId): string
    {
        return 'https://drive.google.com/drive/folders/'.$folderId;
    }

    public static function deleteFile(string $fileId): void
    {
        try {
            self::client()->files->delete($fileId, self::driveOpts());
        } catch (Throwable $e) {
            Log::warning('Google Drive delete failed', ['file_id' => $fileId, 'error' => $e->getMessage()]);
        }
    }

    /** @deprecated استخدم syncPhoto */
    public static function uploadPhoto(InspectionPhoto $photo): void
    {
        self::syncPhoto($photo);
    }
}
