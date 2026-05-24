# إعداد Google Drive (Gmail شخصي — OAuth)

## لماذا الفولدرات موجودة لكن الصور والتقارير لا؟

من 2024، **Service Account** لا يستطيع رفع **ملفات** إلى Google Drive الشخصي (Gmail).  
قد ينجح إنشاء الفولدرات فقط، ثم يفشل رفع الصور والتقارير مع رسالة في اللوج:

`Service Accounts do not have storage quota`

**الحل:** ربط حساب Gmail عبر **OAuth** (زر «ربط Google Drive» في لوحة التحكم).

---

## الخطوة 1: Google Cloud

1. [Google Cloud Console](https://console.cloud.google.com/) → مشروعك (مثلاً `GIS-Inspection`)
2. فعّل **Google Drive API**
3. **APIs & Services → Credentials → Create Credentials → OAuth client ID**
   - نوع التطبيق: **Web application**
   - **Authorized redirect URIs** (محلي):
     ```
     http://127.0.0.1:8000/admin/google-drive/callback
     ```
     (أو المنفذ الذي تستخدمه مع `php artisan serve`)
4. انسخ **Client ID** و **Client Secret**

---

## الخطوة 2: فولدر Drive

1. من [Google Drive](https://drive.google.com) أنشئ فولدر **ALLHOUSES** (أو استخدم الموجود)
2. من الرابط خذ الـ ID:
   ```
   https://drive.google.com/drive/folders/XXXXXXXX
   ```

لا حاجة لمشاركة Service Account عند استخدام OAuth — الملفات تُرفع بحسابك مباشرة.

---

## الخطوة 3: ملف `.env`

```env
GOOGLE_DRIVE_AUTH_MODE=oauth
GOOGLE_DRIVE_OAUTH_CLIENT_ID=xxxxx.apps.googleusercontent.com
GOOGLE_DRIVE_OAUTH_CLIENT_SECRET=GOCSPX-xxxxx
GOOGLE_DRIVE_OAUTH_REDIRECT_URI=http://127.0.0.1:8000/admin/google-drive/callback
GOOGLE_DRIVE_ROOT_FOLDER_ID=1PJ9RmvUaGS3zJUi8AsXJOZBWemVx-vAp
GOOGLE_DRIVE_ALL_HOUSES_FOLDER_NAME=.
GOOGLE_DRIVE_DELETE_LOCAL_AFTER_UPLOAD=true
GOOGLE_DRIVE_SHARE_EMAIL=work77896@gmail.com
```

| المتغير | المعنى |
|---------|--------|
| `AUTH_MODE` | `oauth` للـ Gmail (افتراضي) — `service_account` فقط لـ Workspace/Shared Drive |
| `ROOT_FOLDER_ID` | ID فولدر ALLHOUSES |
| `ALL_HOUSES_FOLDER_NAME` | `.` إذا كان ROOT هو ALLHOUSES نفسه |
| `DELETE_LOCAL_AFTER_UPLOAD` | `true` = حذف من `storage` بعد رفع ناجح لـ Drive |

ثم:

```bash
php artisan config:clear
```

---

## الخطوة 4: ربط الحساب

1. شغّل `php artisan serve`
2. ادخل لوحة التحكم → أي منزل
3. اضغط **ربط Google Drive** ووافق بحساب Gmail نفسه صاحب الفولدر
4. ارفع صورة → يجب أن تظهر في الفولدر على Drive

---

## هيكل الملفات على Drive (تلقائي)

```
ALLHOUSES/
└── House #4 - عنوان/
    ├── تقرير المعاينة - ....pdf
    ├── التقرير الكتابي - ....html
    └── 01 - الفناء الخارجي/
        ├── photo-12.jpg
        ├── photo-12-notes.txt
        └── photo-12-edited.jpg
```

---

## كيف يعمل التطبيق

| الإجراء | ماذا يحدث |
|---------|-----------|
| حفظ الصور | رفع تلقائي + `.txt` للملاحظات |
| تعديل صورة / أسهم | تحديث الملف على Drive |
| تحميل PDF / Word | رفع التقرير لمجلد المنزل |

الصور تظهر في الموقع من `storage` أو من كاش `storage/app/drive-cache`.

---

## السيرفر (لاحقاً)

1. أنشئ OAuth client جديد (أو أضف redirect):
   ```
   https://your-domain.com/admin/google-drive/callback
   ```
2. نفس متغيرات `.env` على السيرفر
3. بعد النشر: ادخل الموقع مرة واحدة واضغط **ربط Google Drive**
4. يُحفظ التوكن في `storage/app/google-drive-oauth-token.json` (لا ترفعه على Git)

---

## استكشاف الأخطاء

| المشكلة | الحل |
|---------|------|
| فولدرات فقط بدون صور | `GOOGLE_DRIVE_AUTH_MODE=oauth` + زر ربط الحساب |
| `redirect_uri_mismatch` | طابق `OAUTH_REDIRECT_URI` مع Google Console |
| `Service Accounts do not have storage quota` | لا تستخدم Service Account على Gmail — استخدم OAuth |
| رسالة نجاح بدون ملفات على Drive | راجع `storage/logs/laravel.log` — الصور تبقى محلياً عند الفشل |

---

## Service Account (اختياري — Workspace فقط)

للحسابات الشخصية Gmail استخدم OAuth فقط.  
`service_account` يعمل مع **Shared Drive** في Google Workspace وليس مع Drive الشخصي.
