<?php

return [

    /**
     * oauth = حساب Gmail شخصي (مطلوب لرفع الصور والتقارير)
     * service_account = للسيرفرات Workspace فقط (لا يرفع ملفات على Gmail عادي)
     */
    'auth_mode' => env('GOOGLE_DRIVE_AUTH_MODE', 'oauth'),

    'credentials' => env('GOOGLE_DRIVE_CREDENTIALS', 'storage/app/google-drive-credentials.json'),

    'oauth_client_id' => env('GOOGLE_DRIVE_OAUTH_CLIENT_ID'),
    'oauth_client_secret' => env('GOOGLE_DRIVE_OAUTH_CLIENT_SECRET'),
    'oauth_redirect_uri' => env('GOOGLE_DRIVE_OAUTH_REDIRECT_URI'),
    'oauth_token_path' => env('GOOGLE_DRIVE_OAUTH_TOKEN_PATH', 'storage/app/google-drive-oauth-token.json'),

    'root_folder_id' => env('GOOGLE_DRIVE_ROOT_FOLDER_ID'),

    'all_houses_folder_name' => env('GOOGLE_DRIVE_ALL_HOUSES_FOLDER_NAME', 'ALLHOUSES'),

    'delete_local_after_upload' => env('GOOGLE_DRIVE_DELETE_LOCAL_AFTER_UPLOAD', true),

    /** جعل الملف «أي شخص لديه الرابط» يقرأه — يساعد التحميل من السيرفر (ليس للعرض المباشر في img) */
    'public_read_on_upload' => env('GOOGLE_DRIVE_PUBLIC_READ_ON_UPLOAD', true),

    /** أيام الاحتفاظ بكاش العرض المحلي قبل إعادة التحميل من Drive */
    'cache_ttl_days' => (int) env('GOOGLE_DRIVE_CACHE_TTL_DAYS', 15),

    'share_with_email' => env('GOOGLE_DRIVE_SHARE_EMAIL'),

];
