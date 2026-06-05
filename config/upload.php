<?php

return [

    /*
    |--------------------------------------------------------------------------
    | رفع صور المعاينة (متصفح + سيرفر)
    |--------------------------------------------------------------------------
    | مناسب لـ Namecheap Shared — ارفع الملفات عبر FTP ثم افتح «صيانة السيرفر» من لوحة التحكم.
    */

    /** عدد الصور في طلب HTTP واحد */
    'chunk_size' => (int) env('UPLOAD_CHUNK_SIZE', 30),

    /** طلبات رفع متوازية من المتصفح (2–3 أنسب للاستضافة المشتركة) */
    'parallel_requests' => (int) env('UPLOAD_PARALLEL_REQUESTS', 2),

    /** معالجة الصور في المتصفح قبل الرفع */
    'client_max_dimension' => (int) env('UPLOAD_CLIENT_MAX_DIM', 1400),

    'client_jpeg_quality' => (float) env('UPLOAD_CLIENT_JPEG_QUALITY', 0.8),

    /** أقل من هذا الحجم (بايت) + JPEG = بدون إعادة ضغط في المتصفح */
    'client_skip_process_below_bytes' => (int) env('UPLOAD_SKIP_PROCESS_BELOW', 400_000),

    /** رفع Drive بعد الاستجابة (لا يبطئ حفظ الصور على السيرفر) */
    'defer_drive_push' => env('UPLOAD_DEFER_DRIVE', true),

];
