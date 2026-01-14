<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // BỔ SUNG: Google Sheets export (đồng bộ báo cáo/thống kê)
    // Lưu ý: dùng Service Account, share spreadsheet cho email service account.
    'google_sheets' => [
        'credentials_path' => env('GOOGLE_SHEETS_CREDENTIALS_PATH'),
        // Có thể truyền JSON raw hoặc base64(JSON)
        'credentials_json' => env('GOOGLE_SHEETS_CREDENTIALS_JSON'),
        'spreadsheet_id' => env('GOOGLE_SHEETS_SPREADSHEET_ID'),
        'statistics_sheet' => env('GOOGLE_SHEETS_STATISTICS_SHEET', 'Statistics'),
        'payroll_sheet' => env('GOOGLE_SHEETS_PAYROLL_SHEET', 'Payroll'),
        // append: luôn thêm dòng (lưu lịch sử) | replace: thay thế dữ liệu trong kỳ (chuẩn vận hành)
        'export_mode' => env('GOOGLE_SHEETS_EXPORT_MODE', 'replace'),

        // Auto-sync (scheduler) - tắt mặc định
        'auto_sync_enabled' => env('GOOGLE_SHEETS_AUTO_SYNC_ENABLED', false),
        // Ví dụ: 23:55 (chỉ áp dụng khi auto_sync_enabled=true)
        'auto_sync_daily_at' => env('GOOGLE_SHEETS_AUTO_SYNC_DAILY_AT', '23:55'),
        // Payroll chạy ngày mấy trong tháng (1-28 cho an toàn) và giờ chạy
        'auto_sync_payroll_day' => env('GOOGLE_SHEETS_AUTO_SYNC_PAYROLL_DAY', 1),
        'auto_sync_payroll_at' => env('GOOGLE_SHEETS_AUTO_SYNC_PAYROLL_AT', '00:10'),
    ],

];
