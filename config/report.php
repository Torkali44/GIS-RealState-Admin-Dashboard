<?php

return [

    /*
    |--------------------------------------------------------------------------
    | مسارات أصول PDF (نسبية من مجلد public أو مسار مطلق على السيرفر)
    |--------------------------------------------------------------------------
    | مثال نسبي: images/company-logo.png
    | مثال مطلق: /home/username/public_html/images/company-logo.png
    */
    'logo_path' => env('REPORT_LOGO_PATH', 'images/company-logo_new.png'),

    /** عرض الشعار في PDF (مم) */
    'logo_width_mm' => (float) env('REPORT_LOGO_WIDTH_MM', 44),

    /** ارتفاع الشعار في PDF (مم) */
    'logo_height_mm' => (float) env('REPORT_LOGO_HEIGHT_MM', 20),

    /** أقل عرض بالبكسل قبل التضخيم للطباعة الواضحة في PDF */
    'logo_embed_min_width' => (int) env('REPORT_LOGO_EMBED_MIN_WIDTH', 420),

    'logo_embed_dpi' => (int) env('REPORT_LOGO_EMBED_DPI', 200),

    'whatsapp_icon_path' => env('REPORT_WHATSAPP_ICON_PATH', 'images/whatsapp_icon.png'),

    'email_icon_path' => env('REPORT_EMAIL_ICON_PATH', 'images/email_icon.png'),

    'footer_phone' => env('REPORT_FOOTER_PHONE', '36698895'),

    'footer_email' => env('REPORT_FOOTER_EMAIL', 'infogisguif@gmail.com'),

    'footer_web' => env('REPORT_FOOTER_WEB', 'gis.Bahrain'),

    'footer_address' => env('REPORT_FOOTER_ADDRESS', 'Seef District - Kingdom of Bahrain'),

    'company_name_en' => env('REPORT_COMPANY_NAME', 'GIS VALUATION AND EVALUATION'),

    'company_name_ar' => env('REPORT_COMPANY_NAME_AR', 'جي إي إس للتقييم والتثمين العقاري'),

    'cr_number' => env('REPORT_CR_NUMBER', 'C.R. 160528-1'),

    /*
    |--------------------------------------------------------------------------
    | أداء تقرير PDF
    |--------------------------------------------------------------------------
    */
    /** فوق هذا العدد من الصور: بناء PDF لكل قسم ثم دمج */
    'section_build_photo_threshold' => (int) env('REPORT_SECTION_THRESHOLD', 50),

    'image_max_dimension' => (int) env('REPORT_IMAGE_MAX_DIM', 1100),

    'jpeg_quality' => (int) env('REPORT_JPEG_QUALITY', 65),

    /** عدد ملفات PDF الجزئية في دفعة الدمج الواحدة (FPDI) */
    'pdf_merge_chunk_size' => (int) env('REPORT_PDF_MERGE_CHUNK', 12),

    /** تنظيف الذاكرة كل N صور أثناء التوليد */
    'gc_every_n_photos' => (int) env('REPORT_GC_EVERY_N_PHOTOS', 4),

];
