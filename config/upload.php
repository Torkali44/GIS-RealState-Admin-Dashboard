<?php

return [

    /*
    |--------------------------------------------------------------------------
    | رفع صور المعاينة (متصفح + سيرفر)
    |--------------------------------------------------------------------------
    */

    /** عدد الصور في طلب HTTP واحد (رفع عادي) */
    'chunk_size' => (int) env('UPLOAD_CHUNK_SIZE', 20),

    /** طلبات رفع متوازية (رفع عادي) */
    'parallel_requests' => (int) env('UPLOAD_PARALLEL_REQUESTS', 2),

    /** فوق هذا العدد = وضع الدفعة الكبيرة (أسرع) */
    'bulk_threshold' => (int) env('UPLOAD_BULK_THRESHOLD', 40),

    /** دفعة أكبر + توازي أعلى للدفعات الكبيرة */
    'bulk_chunk_size' => (int) env('UPLOAD_BULK_CHUNK_SIZE', 25),

    'bulk_parallel_requests' => (int) env('UPLOAD_BULK_PARALLEL', 3),

    /** تخطي ضغط المتصفح للدفعات الكبيرة (يوفر دقائق على 300+ صورة) */
    'bulk_skip_client_process' => env('UPLOAD_BULK_SKIP_PROCESS', true),

    /** معالجة الصور في المتصفح قبل الرفع (الدفعات الصغيرة) */
    'client_max_dimension' => (int) env('UPLOAD_CLIENT_MAX_DIM', 1400),

    'client_jpeg_quality' => (float) env('UPLOAD_CLIENT_JPEG_QUALITY', 0.8),

    'client_skip_process_below_bytes' => (int) env('UPLOAD_SKIP_PROCESS_BELOW', 400_000),

    /** معالجة متوازية في المتصفح */
    'client_process_concurrency' => (int) env('UPLOAD_PROCESS_CONCURRENCY', 10),

    /** رفع Drive بعد الاستجابة */
    'defer_drive_push' => env('UPLOAD_DEFER_DRIVE', true),

    /** ارتفاع صورة الدمج في المتصفح (أقل = أسرع) */
    'merge_max_height' => (int) env('UPLOAD_MERGE_MAX_HEIGHT', 900),

    'merge_jpeg_quality' => (float) env('UPLOAD_MERGE_JPEG_QUALITY', 0.85),

];
