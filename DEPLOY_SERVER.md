# رفع التحديثات على السيرفر (GIS + Google Drive)

## 1) من جهازك — رفع الكود

```bash
cd c:\Users\SAMA\Downloads\GIS
git status
git add -A
git commit -m "Drive OAuth, auto report sync, word .doc on Drive"
git push origin main
```

(استبدل `main` باسم الفرع عندك إن كان مختلفاً.)

---

## 2) على السيرفر — سحب التحديثات

```bash
cd /path/to/GIS
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

---

## 3) ملف `.env` على السيرفر

```env
GOOGLE_DRIVE_AUTH_MODE=oauth
GOOGLE_DRIVE_OAUTH_CLIENT_ID=399417641883-....apps.googleusercontent.com
GOOGLE_DRIVE_OAUTH_CLIENT_SECRET=GOCSPX-....
GOOGLE_DRIVE_OAUTH_REDIRECT_URI=https://your-domain.com/admin/google-drive/callback
GOOGLE_DRIVE_ROOT_FOLDER_ID=فولدر_ALLHOUSES_على_حساب_الشركة
GOOGLE_DRIVE_ALL_HOUSES_FOLDER_NAME=.
GOOGLE_DRIVE_DELETE_LOCAL_AFTER_UPLOAD=true
GOOGLE_DRIVE_SHARE_EMAIL=email@company.com
```

في **Google Cloud Console** أضف redirect للسيرفر:

```
https://your-domain.com/admin/google-drive/callback
```

---

## 4) OAuth token على السيرفر

1. افتح الموقع من المتصفح → سجّل دخول أدمن
2. **ربط Google Drive** (مرة واحدة لكل سيرفر)
3. يُنشأ الملف (لا ترفعه على Git):

   `storage/app/google-drive-oauth-token.json`

```bash
chmod 600 storage/app/google-drive-oauth-token.json
chown -R www-data:www-data storage bootstrap/cache
```

(استبدل `www-data` بمستخدم الويب عندك.)

---

## 5) صلاحيات المجلدات

```bash
mkdir -p storage/app/drive-cache storage/app/reports/tmp
chmod -R 775 storage bootstrap/cache
```

---

## 6) اختبار بعد الرفع

| اختبار | المتوقع |
|--------|---------|
| رفع صورة | تظهر في فولدر القسم على Drive |
| بعد ~30 ثانية | PDF و `.doc` على Drive محدّثان |
| «التقرير PDF على Drive» | نفس محتوى «عرض التقرير» |
| حذف صورة من الموقع | تُحذف من Drive |

---

## 7) ملفات التحديث الأخيرة (مرجع)

- `app/Services/DriveReportSyncService.php` — مزامنة تقارير Drive تلقائياً
- `app/Services/GoogleDriveService.php` — OAuth + Word `.doc`
- `app/Services/DriveMediaService.php`
- `app/Http/Controllers/Admin/GoogleDriveAuthController.php`
- `app/Http/Controllers/Admin/InspectionPhotoController.php`
- `app/Http/Controllers/Admin/InspectionAreaController.php`
- `app/Http/Controllers/Admin/PropertyHouseController.php`
- `config/google-drive.php`
- `routes/web.php`
- `database/migrations/2026_05_22_*`
- `resources/views/admin/houses/show.blade.php`
- `GOOGLE_DRIVE_SETUP.md`

---

## 8) أداء على السيرفر

- مزامنة التقرير تعمل **بعد** إرسال الصفحة (`afterResponse`) — لا تبطئ زر «حفظ الصور» كثيراً.
- توليد PDF ثقيل؛ على استضافة ضعيفة قد يستغرق 1–3 دقائق لمنزل فيه صور كثيرة.
- لو التقرير على Drive تأخر: انتظر ثم حدّث صفحة Drive (F5).
