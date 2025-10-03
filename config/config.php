<?php

/**
 * Haupt-Konfigurationsdatei
 * 
 * @package Config
 * @author GenSpark AI Developer
 */

return [
    // Application
    'app' => [
        'name' => env('APP_NAME', 'Vertragsverwaltung'),
        'env' => env('APP_ENV', 'production'),
        'debug' => env('APP_DEBUG', false),
        'url' => env('APP_URL', 'http://localhost:8000'),
        'timezone' => env('APP_TIMEZONE', 'Europe/Berlin'),
        'locale' => env('APP_LOCALE', 'de_DE'),
        'currency' => env('APP_CURRENCY', 'EUR'),
        'key' => env('APP_KEY'),
        'encryption_key' => env('ENCRYPTION_KEY'),
    ],

    // Datenbank
    'database' => [
        'driver' => 'mysql',
        'host' => env('DB_HOST', 'localhost'),
        'port' => env('DB_PORT', 3306),
        'database' => env('DB_DATABASE'),
        'username' => env('DB_USERNAME'),
        'password' => env('DB_PASSWORD'),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ],
    ],

    // Session
    'session' => [
        'lifetime' => (int) env('SESSION_LIFETIME', 7200),
        'path' => '/',
        'domain' => null,
        'secure' => env('SESSION_SECURE', false),
        'httponly' => env('SESSION_HTTPONLY', true),
        'samesite' => env('SESSION_SAMESITE', 'Lax'),
    ],

    // Mail
    'mail' => [
        'mailer' => env('MAIL_MAILER', 'smtp'),
        'host' => env('MAIL_HOST', 'localhost'),
        'port' => (int) env('MAIL_PORT', 587),
        'username' => env('MAIL_USERNAME'),
        'password' => env('MAIL_PASSWORD'),
        'encryption' => env('MAIL_ENCRYPTION', 'tls'),
        'from' => [
            'address' => env('MAIL_FROM_ADDRESS', 'noreply@vertragsverwaltung.local'),
            'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Vertragsverwaltung')),
        ],
    ],

    // Security
    'security' => [
        'csrf_lifetime' => (int) env('CSRF_TOKEN_LIFETIME', 3600),
        'password_algo' => PASSWORD_ARGON2ID,
        'password_options' => [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,       // 4 iterations
            'threads' => 3,         // 3 threads
        ],
        'login_rate_limit' => (int) env('LOGIN_RATE_LIMIT', 5),
        'login_rate_window' => (int) env('LOGIN_RATE_WINDOW', 900),
    ],

    // File Upload
    'upload' => [
        'max_size' => (int) env('MAX_UPLOAD_SIZE', 26214400), // 25MB
        'allowed_mimes' => explode(',', env('ALLOWED_UPLOAD_MIMES', 'pdf')),
        'path' => env('UPLOAD_PATH', 'uploads'),
    ],

    // 2FA/TOTP
    'totp' => [
        'issuer' => env('TOTP_ISSUER', env('APP_NAME', 'Vertragsverwaltung')),
        'digits' => (int) env('TOTP_DIGITS', 6),
        'algorithm' => env('TOTP_ALGORITHM', 'sha1'),
        'period' => 30,
    ],

    // Notifications
    'notifications' => [
        'reminder_days' => array_map('intval', explode(',', env('NOTIFICATION_REMINDER_DAYS', '30,14,7,1'))),
        'email_enabled' => env('NOTIFICATION_EMAIL_ENABLED', true),
    ],

    // Audit Log
    'audit' => [
        'enabled' => env('AUDIT_LOG_ENABLED', true),
        'retention_days' => (int) env('AUDIT_LOG_RETENTION_DAYS', 365),
    ],

    // Emergency Mode
    'emergency' => [
        'access_lifetime' => (int) env('EMERGENCY_ACCESS_LIFETIME', 24),
        'notification_email' => env('EMERGENCY_NOTIFICATION_EMAIL'),
    ],

    // Cache
    'cache' => [
        'driver' => env('CACHE_DRIVER', 'file'),
        'path' => __DIR__ . '/../storage/cache',
    ],

    // Views
    'views' => [
        'path' => __DIR__ . '/../app/Views',
        'cache' => env('VIEW_CACHE', false),
    ],

    // Logging
    'log' => [
        'driver' => env('LOG_DRIVER', 'file'),
        'path' => __DIR__ . '/../storage/logs',
        'level' => env('LOG_LEVEL', 'info'),
        'max_files' => (int) env('LOG_MAX_FILES', 5),
    ],

    // Content Security Policy
    'csp' => [
        'script_src' => [
            "'self'",
            "'unsafe-inline'", // Für Alpine.js inline scripts
            "https://cdn.jsdelivr.net",
            "https://unpkg.com",
        ],
        'style_src' => [
            "'self'",
            "'unsafe-inline'", // Für Tailwind utilities
            "https://cdn.jsdelivr.net",
        ],
        'img_src' => [
            "'self'",
            "data:",
            "https:",
        ],
        'font_src' => [
            "'self'",
            "https://fonts.gstatic.com",
        ],
        'connect_src' => [
            "'self'",
        ],
    ],

    // Routes
    'routes' => [
        'web' => __DIR__ . '/../routes/web.php',
        'api' => __DIR__ . '/../routes/api.php',
    ],
];