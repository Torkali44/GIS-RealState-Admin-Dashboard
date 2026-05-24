# رفع يدوي على السيرفر (بدون Terminal وبدون GitHub)

دليل آمن لاستضافة فيها **FTP / cPanel File Manager** فقط (مثل Hostinger، cPanel، DirectAdmin).

---

## قبل أي شيء — نسخة احتياطية

1. من **phpMyAdmin** → قاعدة البيانات → **Export** → حفظ ملف `.sql`
2. من FTP: حمّل نسخة من مجلد المشروع على السيرفر (أو على الأقل `app` + `routes` + `.env`)

لو حصل خطأ، ترجع النسخة الاحتياطية.

---

## الخطوة 1: قاعدة البيانات (phpMyAdmin → SQL)

افتح **phpMyAdmin** → اختر قاعدة بيانات المشروع → تبويب **SQL** → الصق الأوامر **واحداً واحداً**.

إذا ظهر خطأ `Duplicate column name` → العمود موجود مسبقاً، **تخطّى** هذا السطر وكمل.

### جدول `property_houses`

```sql
ALTER TABLE property_houses
  ADD COLUMN drive_folder_id VARCHAR(255) NULL AFTER notes;

ALTER TABLE property_houses
  ADD COLUMN drive_pdf_id VARCHAR(255) NULL AFTER drive_folder_id;

ALTER TABLE property_houses
  ADD COLUMN drive_word_file_id VARCHAR(255) NULL AFTER drive_pdf_id;
```

### جدول `inspection_areas`

```sql
ALTER TABLE inspection_areas
  ADD COLUMN drive_folder_id VARCHAR(255) NULL AFTER sort_order;
```

### جدول `inspection_photos`

```sql
ALTER TABLE inspection_photos
  ADD COLUMN drive_file_id VARCHAR(255) NULL AFTER upload_file_key;

ALTER TABLE inspection_photos
  ADD COLUMN drive_composite_file_id VARCHAR(255) NULL AFTER drive_file_id;

ALTER TABLE inspection_photos
  ADD COLUMN drive_notes_file_id VARCHAR(255) NULL AFTER drive_composite_file_id;
```

> لو عمود `upload_file_key` غير موجود عندك، استبدل `AFTER upload_file_key` بـ `AFTER original_filename` أو احذف `AFTER ...` تماماً.

### (اختياري) تسجيل الـ migrations

لو عندك جدول `migrations` وتريد تجنب تعارض لاحقاً:

```sql
INSERT INTO migrations (migration, batch) VALUES
('2026_05_22_000001_add_drive_columns_to_all_tables', 99),
('2026_05_22_100000_add_drive_composite_and_notes_ids', 99);
```

نفّذ فقط إذا الصفين **غير موجودين** مسبقاً في الجدول.

---

## الخطوة 2: رفع الملفات عبر FTP

اتصل بـ **FileZilla** أو **cPanel → File Manager** واذهب لمجلد المشروع على السيرفر (مثلاً `public_html/gis` أو `domains/xxx.com/public_html`).

### أ) ملفات جديدة — ارفعها كما هي

| من جهازك (محلي) | إلى السيرفر (نفس المسار داخل المشروع) |
|-----------------|----------------------------------------|
| `app/Services/GoogleDriveService.php` | `app/Services/GoogleDriveService.php` |
| `app/Services/DriveMediaService.php` | `app/Services/DriveMediaService.php` |
| `app/Services/DriveReportSyncService.php` | `app/Services/DriveReportSyncService.php` |
| `app/Http/Controllers/Admin/GoogleDriveAuthController.php` | نفس المسار |
| `app/Http/Controllers/Admin/HouseDriveController.php` | نفس المسار (إن وُجد) |
| `app/Jobs/UploadPhotoToDriveJob.php` | نفس المسار (اختياري) |
| `config/google-drive.php` | `config/google-drive.php` |

### ب) ملفات معدّلة — استبدل القديم بالجديد

| ملف |
|-----|
| `app/Http/Controllers/Admin/InspectionPhotoController.php` |
| `app/Http/Controllers/Admin/InspectionAreaController.php` |
| `app/Http/Controllers/Admin/PropertyHouseController.php` |
| `app/Models/PropertyHouse.php` |
| `app/Models/InspectionArea.php` |
| `app/Models/InspectionPhoto.php` |
| `routes/web.php` |
| `resources/views/admin/houses/show.blade.php` |
| `resources/views/admin/houses/index.blade.php` |
| `composer.json` |
| `composer.lock` |

> **لا ترفع** ملف `.env` من جهازك فوق `.env` السيرفر — عدّل السيرفر يدوياً (الخطوة 4).

### ج) مكتبة Google (مهم جداً)

المشروع يحتاج `google/apiclient` من Composer.

**اختر واحدة:**

**الطريقة الأسهل (موصى بها):** من جهازك المحلي بعد `composer install`:

1. اضغط مجلد `vendor` كامل ZIP (قد يكون كبيراً 50–150 MB)
2. ارفع ZIP للسيرفر وفكّه **فوق** مجلد `vendor` الموجود (استبدال/دمج)
3. أو ارفع فقط `vendor/google` + `vendor/composer` + `vendor/guzzlehttp` + `vendor/monolog` + `vendor/psr` + `vendor/firebase` إن عرفت المسارات — الأسهل رفع `vendor` كامل

**من cPanel:** إن وُجد **Terminal** أو **Composer** في cPanel:

```bash
cd /path/to/project
composer install --no-dev --optimize-autoloader
```

**بدون vendor:** الموقع سيعطي خطأ `Class Google\Client not found`.

---

## الخطوة 3: مجلدات على السيرفر (File Manager)

أنشئ إن لم تكن موجودة (صلاحيات **755** أو **775**):

```
storage/app/drive-cache
storage/app/reports/tmp
```

تأكد أن هذه قابلة للكتابة:

```
storage
storage/app
storage/framework
storage/logs
bootstrap/cache
```

---

## الخطوة 4: تعديل `.env` على السيرفر فقط

افتح `.env` على السيرفر (محرر cPanel) وأضف/عدّل:

```env
GOOGLE_DRIVE_AUTH_MODE=oauth
GOOGLE_DRIVE_OAUTH_CLIENT_ID=399417641883-jc05q69gr9792nvlou9a0d1iupag809a.apps.googleusercontent.com
GOOGLE_DRIVE_OAUTH_CLIENT_SECRET=ضع_السر_الحقيقي_هنا
GOOGLE_DRIVE_OAUTH_REDIRECT_URI=https://YOUR-DOMAIN.com/admin/google-drive/callback
GOOGLE_DRIVE_ROOT_FOLDER_ID=1PJ9RmvUaGS3zJUi8AsXJOZBWemVx-vAp
GOOGLE_DRIVE_ALL_HOUSES_FOLDER_NAME=.
GOOGLE_DRIVE_DELETE_LOCAL_AFTER_UPLOAD=true
GOOGLE_DRIVE_SHARE_EMAIL=work77896@gmail.com
```

- استبدل `YOUR-DOMAIN.com` بدومين السيرفر الحقيقي (مع `https://`)
- في **Google Cloud Console** أضف نفس الرابط في **Authorized redirect URIs**

---

## الخطوة 5: مسح الكاش (بدون Terminal)

### أ) من File Manager

احذف محتويات (ليس المجلدات نفسها):

- `bootstrap/cache/` → احذف كل الملفات داخلها **ما عدا** `.gitignore`
- `storage/framework/views/` → احذف كل ملفات `.php` داخلها

### ب) أو ملف مؤقت (مرة واحدة)

1. أنشئ على السيرفر: `public/clear-once.php`

```php
<?php
// احذف هذا الملف فوراً بعد التشغيل
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\Artisan::call('config:clear');
Illuminate\Support\Facades\Artisan::call('route:clear');
Illuminate\Support\Facades\Artisan::call('view:clear');
echo 'OK - delete this file now';
```

2. افتح في المتصفح: `https://YOUR-DOMAIN.com/clear-once.php`
3. **احذف** `clear-once.php` فوراً من السيرفر

---

## الخطوة 6: ربط Google Drive على السيرفر

1. افتح الموقع: `https://YOUR-DOMAIN.com/admin`
2. سجّل دخول أدمن
3. اضغط **ربط Google Drive** ووافق بحساب Gmail
4. يُنشأ ملف (لا ترفعه على FTP للعامة):

   `storage/app/google-drive-oauth-token.json`

---

## الخطوة 7: اختبار آمن

| # | اختبار | النتيجة المتوقعة |
|---|--------|------------------|
| 1 | افتح قائمة المنازل | تظهر بدون خطأ 500 |
| 2 | افتح منزل قديم | يعمل كما قبل |
| 3 | ارفع صورة واحدة | تظهر في الموقع + على Drive خلال دقيقة |
| 4 | بعد 30–60 ث | PDF و doc على Drive محدثان |
| 5 | حذف صورة من الموقع | تختفي من Drive |

لو **500 error**: افتح `storage/logs/laravel.log` آخر سطور — أو فعّل مؤقتاً `APP_DEBUG=true` ثم أرجعه `false`.

---

## ما لا تفعله (تجنب الأعطال)

| ❌ لا | ✅ نعم |
|------|--------|
| رفع `.env` المحلي فوق سيرفر الإنتاج | تعديل `.env` على السيرفر فقط |
| حذف مجلد `storage` كامل | إضافة مجلدات فرعية فقط |
| حذف قاعدة البيانات | ALTER فقط للأعمدة الجديدة |
| ترك `clear-once.php` على السيرفر | احذفه بعد الاستخدام |
| رفع `google-drive-oauth-token.json` لمجلد عام | يبقى داخل `storage/app` |

---

## قائمة تحقق سريعة

- [ ] نسخة احتياطية DB + ملفات
- [ ] SQL ALTER (7 أعمدة)
- [ ] رفع ملفات `app` + `config` + `routes` + `resources`
- [ ] رفع/تحديث `vendor` (google apiclient)
- [ ] مجلدات `drive-cache` و `reports/tmp`
- [ ] `.env` على السيرفر + redirect URI في Google
- [ ] مسح كاش
- [ ] ربط Drive من المتصفح
- [ ] اختبار رفع صورة
- [ ] حذف `clear-once.php` إن استخدمته

---

## لو السيرفر قديم وما فيهش التحديثات الأخرى

إذا الموقع على السيرفر **أقدم** من نسختك المحلية، ارفع **كل** المشروع (ما عدا `.env` و `node_modules` و `.git`) أو قارن تاريخ الملفات — الأهم الملفات في الجدولين أعلاه + `vendor`.

---

## مساعدة عند خطأ شائع

| الرسالة | الحل |
|---------|------|
| `Class Google\Client not found` | ارفع `vendor` بعد `composer install` محلياً |
| `invalid_client` | Client ID/Secret في `.env` السيرفر + redirect URI |
| `Column not found: drive_...` | نفّذ SQL ALTER |
| صفحة بيضاء 500 | راجع `storage/logs/laravel.log` |
| Drive قديم بعد رفع صورة | انتظر دقيقة؛ التقرير يتحدث في الخلفية |
