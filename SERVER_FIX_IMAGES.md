# إصلاح ظهور الصور على gisbahrain.com (يدوي)

## مسار المشروع على السيرفر (مهم)

من ملف اللوج، Laravel موجود هنا:

```
/home/gisbjquz/public_html/app/myApp/
```

**مجلد الكاش الصحيح:**

```
/home/gisbjquz/public_html/app/myApp/storage/app/drive-cache
```

لو أنشأت `drive-cache` في مكان تاني (مثلاً `public_html/storage/`) **لن يعمل**.

---

## ماذا تقول قاعدة البيانات؟

| id | drive_file_id | الحالة |
|----|---------------|--------|
| 719, 720 | NULL | لم تُرفع لـ Drive — تحتاج ملف محلي أو إعادة رفع |
| 721, 722, 723, 1517 | موجود | يجب أن تظهر بعد الإصلاح |

---

## الخطوة 1: صلاحيات المجلدات (cPanel File Manager)

1. ادخل: `public_html/app/myApp/storage`
2. أنشئ مجلد `app` داخله إن لم يكن موجوداً
3. داخل `storage/app` أنشئ: **`drive-cache`**
4. صلاحيات **775** على:
   - `storage`
   - `storage/app`
   - `storage/app/drive-cache`
   - `storage/app/public`
   - `storage/logs`

---

## الخطوة 2: رفع ملفات PHP المحدّثة

استبدل هذه الملفات **داخل `myApp`**:

```
app/Services/GoogleDriveService.php          ← عرض الصورة عبر ملف كاش + BinaryFileResponse
app/Services/DriveMediaService.php
app/Http/Controllers/Admin/InspectionPhotoController.php
resources/views/admin/houses/show.blade.php   ← رابط صورة كامل https://
resources/views/admin/photos/edit.blade.php
```

### صلاحيات 777

تعمل مؤقتاً. بعد ما الصور تظهر، يُفضّل **775** على `storage` و`drive-cache` (أكثر أماناً).

تأكد أن `InspectionPhotoController.php` السطر الأول للدالة `store` يحتوي:

```php
public function store(...): RedirectResponse|JsonResponse
```

(لو السيرفر قديم بدون `|JsonResponse` — رفع الصور يفشل حسب اللوج.)

---

## الخطوة 3: مسح الكاش

احذف من File Manager:

```
myApp/bootstrap/cache/config.php
```

(إن وُجد)

---

## الخطوة 4: اختبار رابط صورة واحدة

1. سجّل دخول الموقع
2. افتح في تاب جديد (مثال صورة 721):

```
https://gisbahrain.com/admin/houses/15/photos/721/image
```

| النتيجة | المعنى |
|---------|--------|
| تظهر الصورة | الإصلاح نجح |
| 404 أو صفحة خطأ | راجع الخطوة 5 |
| صفحة تسجيل دخول | افتح الرابط وأنت مسجّل |

---

## الخطوة 5: الصور 719 و 720 (بدون drive_file_id)

### أ) تحقق من الملف المحلي

من FTP ادخل:

```
myApp/storage/app/public/inspections/15/
```

- لو الملفات **موجودة** (نفس أسماء `original_path` في DB):
  1. افتح المنزل في الموقع
  2. اضغط **«مزامنة X صورة قديمة مع Drive»**
  3. حدّث الصفحة

- لو المجلد **فاضي** أو الملفات **مش موجودة**:
  - لا يمكن استرجاعها إلا من **نسخة احتياطية** أو **إعادة رفع** الصور

### ب) بعد المزامنة — تحقق من DB

```sql
SELECT id, drive_file_id FROM inspection_photos WHERE id IN (719, 720);
```

يجب أن يصبح `drive_file_id` فيه قيمة وليس NULL.

---

## الخطوة 6: ملف OAuth (لازم للعرض من Drive)

تأكد من وجود:

```
myApp/storage/app/google-drive-oauth-token.json
```

بدون هذا الملف لا يمكن تحميل الصور من Drive للعرض.

---

## الخطوة 7: symlink للصور القديمة (اختياري)

لو صور 719/720 لها ملفات في `storage/app/public` لكن لا تظهر:

من cPanel Terminal (إن وُجد) أو اطلب من الدعم:

```bash
cd /home/gisbjquz/public_html/app/myApp
php artisan storage:link
```

أو تأكد أن `public_html/storage` يشير لـ `myApp/storage/app/public`.

---

## ملخص سريع

1. `drive-cache` داخل **`myApp/storage/app/`**
2. رفع 3 ملفات PHP
3. حذف `bootstrap/cache/config.php`
4. اختبار `/admin/houses/15/photos/721/image`
5. مزامنة 719 و 720 إن الملفات المحلية موجودة
