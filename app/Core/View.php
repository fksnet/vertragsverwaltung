<?php

declare(strict_types=1);

namespace App\Core;

/**
 * View-System für PHP-Templates ohne Template-Engine
 * 
 * @package App\Core
 * @author GenSpark AI Developer
 */
class View
{
    private static string $viewPath = '';
    private static array $sharedData = [];
    
    /**
     * View-Pfad initialisieren
     */
    public static function init(): void
    {
        if (empty(self::$viewPath)) {
            self::$viewPath = config('views.path', app_path('Views'));
        }
    }

    /**
     * View rendern
     */
    public static function render(string $view, array $data = []): string
    {
        self::init();
        
        $viewFile = self::findViewFile($view);
        
        if (!$viewFile) {
            throw new \InvalidArgumentException("View '$view' not found");
        }
        
        // Daten zusammenführen
        $data = array_merge(self::$sharedData, $data);
        
        // Helper-Funktionen verfügbar machen
        $data['__view'] = new ViewHelper();
        
        return self::renderFile($viewFile, $data);
    }

    /**
     * Daten für alle Views teilen
     */
    public static function share(array $data): void
    {
        self::$sharedData = array_merge(self::$sharedData, $data);
    }

    /**
     * View-Fragment für htmx rendern
     */
    public static function fragment(string $view, array $data = []): string
    {
        // Für htmx-Requests ohne Layout
        return self::render($view, $data);
    }

    /**
     * View mit Layout rendern
     */
    public static function layout(string $layout, string $content, array $data = []): string
    {
        $data['content'] = $content;
        return self::render("layouts.$layout", $data);
    }

    /**
     * View-Datei finden
     */
    private static function findViewFile(string $view): ?string
    {
        $path = str_replace('.', '/', $view) . '.php';
        $fullPath = self::$viewPath . '/' . $path;
        
        return file_exists($fullPath) ? $fullPath : null;
    }

    /**
     * View-Datei rendern
     */
    private static function renderFile(string $file, array $data): string
    {
        extract($data, EXTR_SKIP);
        
        ob_start();
        
        try {
            include $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw new \RuntimeException(
                "Error rendering view '$file': " . $e->getMessage(),
                0,
                $e
            );
        }
        
        return ob_get_clean();
    }
}

/**
 * View-Helper-Klasse für Template-Funktionen
 */
class ViewHelper
{
    /**
     * Partial includieren
     */
    public function include(string $partial, array $data = []): void
    {
        echo View::render("partials.$partial", $data);
    }

    /**
     * Asset-URL generieren
     */
    public function asset(string $path): string
    {
        return asset($path);
    }

    /**
     * URL generieren
     */
    public function url(string $path = ''): string
    {
        return url($path);
    }

    /**
     * CSRF-Token-Field
     */
    public function csrf(): string
    {
        return csrf_field();
    }

    /**
     * Flash-Messages anzeigen
     */
    public function flash(): string
    {
        if (!isset($_SESSION['_flash'])) {
            return '';
        }

        $html = '';
        foreach ($_SESSION['_flash'] as $type => $message) {
            $alertClass = match($type) {
                'success' => 'alert-success',
                'error' => 'alert-error',
                'warning' => 'alert-warning',
                'info' => 'alert-info',
                default => 'alert-info'
            };
            
            $html .= sprintf(
                '<div class="alert %s mb-4">
                    <div class="flex items-center">
                        <span>%s</span>
                        <button class="btn btn-sm btn-ghost ml-auto" onclick="this.parentElement.parentElement.remove()">✕</button>
                    </div>
                </div>',
                $alertClass,
                e($message)
            );
        }
        
        // Flash-Messages nach Anzeige löschen
        unset($_SESSION['_flash']);
        
        return $html;
    }

    /**
     * Validierungsfehler anzeigen
     */
    public function errors(?string $field = null): string
    {
        if (!isset($_SESSION['_errors'])) {
            return '';
        }
        
        $errors = $_SESSION['_errors'];
        
        if ($field) {
            $error = $errors[$field] ?? null;
            return $error ? sprintf('<div class="text-error text-sm mt-1">%s</div>', e($error)) : '';
        }
        
        if (empty($errors)) {
            return '';
        }
        
        $html = '<div class="alert alert-error mb-4"><ul class="list-disc list-inside">';
        foreach ($errors as $error) {
            $html .= sprintf('<li>%s</li>', e($error));
        }
        $html .= '</ul></div>';
        
        return $html;
    }

    /**
     * Alten Input-Wert wiederherstellen
     */
    public function old(string $field, mixed $default = ''): string
    {
        return e(old($field, $default));
    }

    /**
     * Ausgewählte Option prüfen
     */
    public function selected(mixed $current, mixed $value): string
    {
        return $current == $value ? 'selected' : '';
    }

    /**
     * Checked-Attribut prüfen
     */
    public function checked(mixed $current, mixed $value = true): string
    {
        return $current == $value ? 'checked' : '';
    }

    /**
     * Active CSS-Klasse für Navigation
     */
    public function active(string $route, string $class = 'active'): string
    {
        $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        return str_starts_with($currentPath, $route) ? $class : '';
    }

    /**
     * Formatierter Betrag
     */
    public function currency(int $cents): string
    {
        return format_currency($cents);
    }

    /**
     * Formatiertes Datum
     */
    public function date(?string $date, string $format = 'd.m.Y'): string
    {
        return format_date($date, $format);
    }

    /**
     * Relativer Zeitstempel
     */
    public function timeAgo(?string $date): string
    {
        if (!$date) {
            return '';
        }
        
        $time = strtotime($date);
        $diff = time() - $time;
        
        if ($diff < 60) {
            return 'vor wenigen Sekunden';
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            return "vor $minutes Minute" . ($minutes > 1 ? 'n' : '');
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return "vor $hours Stunde" . ($hours > 1 ? 'n' : '');
        } elseif ($diff < 2592000) {
            $days = floor($diff / 86400);
            return "vor $days Tag" . ($days > 1 ? 'en' : '');
        } else {
            return format_date($date);
        }
    }

    /**
     * Truncate Text mit Ellipsis
     */
    public function truncate(string $text, int $length = 100): string
    {
        if (mb_strlen($text) <= $length) {
            return e($text);
        }
        
        return e(mb_substr($text, 0, $length)) . '...';
    }

    /**
     * Pluralisierung
     */
    public function plural(int $count, string $singular, string $plural): string
    {
        return $count === 1 ? $singular : $plural;
    }

    /**
     * JSON für JavaScript
     */
    public function json(mixed $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Status-Badge CSS-Klasse
     */
    public function statusBadge(string $status): string
    {
        return match($status) {
            'laufend' => 'badge-success',
            'gekündigt' => 'badge-warning',
            'beendet' => 'badge-info',
            'storniert' => 'badge-error',
            default => 'badge-ghost'
        };
    }

    /**
     * Avatar mit Initialen generieren
     */
    public function avatar(string $name, int $size = 40): string
    {
        $initials = '';
        $words = explode(' ', trim($name));
        
        foreach ($words as $word) {
            if (!empty($word)) {
                $initials .= strtoupper($word[0]);
            }
            if (strlen($initials) >= 2) break;
        }
        
        return sprintf(
            '<div class="avatar placeholder">
                <div class="bg-neutral text-neutral-content rounded-full" style="width: %dpx; height: %dpx;">
                    <span class="text-xs">%s</span>
                </div>
            </div>',
            $size,
            $size,
            e($initials)
        );
    }
}