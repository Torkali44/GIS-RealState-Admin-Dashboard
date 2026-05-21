<?php

return [

    /*
    |--------------------------------------------------------------------------
    | مسارات أصول PDF (نسبية من مجلد public أو مسار مطلق على السيرفر)
    |--------------------------------------------------------------------------
    | مثال نسبي: images/company-logo.png
    | مثال مطلق: /home/username/public_html/images/company-logo.png
    */
    'logo_path' => env('REPORT_LOGO_PATH', 'images/company-logo.png'),

    'whatsapp_icon_path' => env('REPORT_WHATSAPP_ICON_PATH', 'images/whatsapp_icon.png'),

    'email_icon_path' => env('REPORT_EMAIL_ICON_PATH', 'images/email_icon.png'),

    'footer_phone' => env('REPORT_FOOTER_PHONE', '36698895'),

    'footer_email' => env('REPORT_FOOTER_EMAIL', 'infogisguif@gmail.com'),

    'footer_web' => env('REPORT_FOOTER_WEB', 'gis.Bahrain'),

    'footer_address' => env('REPORT_FOOTER_ADDRESS', 'Seef District - Kingdom of Bahrain'),

    'company_name_en' => env('REPORT_COMPANY_NAME', 'GIS VALUATION AND EVALUATION'),

    'company_name_ar' => env('REPORT_COMPANY_NAME_AR', 'جي إي إس للتقييم والتثمين العقاري'),

    'cr_number' => env('REPORT_CR_NUMBER', 'C.R. 160528-1'),

];
