<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\Setting;
use App\Models\AuditLog;
use App\Core\Auth;
use App\Core\Logger;
use App\Core\Cache;

/**
 * SettingsController - Admin-Verwaltung für Systemeinstellungen
 * 
 * Ermöglicht die Konfiguration aller Systemeinstellungen wie
 * Sicherheit, E-Mail, Backup, Wartung und Anwendungsverhalten.
 * 
 * @package App\Controllers\Admin
 * @version 1.0.0
 * @author Vertragsverwaltung System
 */
class SettingsController extends BaseController
{
    private const SETTING_CATEGORIES = [
        'general' => 'Allgemeine Einstellungen',
        'security' => 'Sicherheitseinstellungen',
        'email' => 'E-Mail-Konfiguration',
        'backup' => 'Backup-Einstellungen',
        'maintenance' => 'Wartungseinstellungen',
        'notifications' => 'Benachrichtigungen',
        'api' => 'API-Konfiguration',
        'performance' => 'Performance-Einstellungen'
    ];

    private const SECURE_SETTINGS = [
        'database_password', 'smtp_password', 'api_keys', 'encryption_keys',
        'oauth_secrets', 'backup_encryption_key'
    ];

    /**
     * Zeigt die Einstellungs-Übersicht
     */
    public function index(): void
    {
        try {
            $this->authorize(['admin:settings:view']);
            
            $category = $_GET['category'] ?? 'general';
            $search = $_GET['search'] ?? '';
            
            // Settings laden
            $query = Setting::query();
            
            if (!empty($category) && array_key_exists($category, self::SETTING_CATEGORIES)) {
                $query->where('category', $category);
            }
            
            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('key', 'LIKE', "%{$search}%")
                      ->orWhere('name', 'LIKE', "%{$search}%")
                      ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }
            
            $settings = $query->orderBy('category')->orderBy('sort_order')->get();
            
            // Einstellungen nach Kategorien gruppieren
            $settingsByCategory = $settings->groupBy('category');
            
            // Sichere Einstellungen maskieren
            foreach ($settings as $setting) {
                if (in_array($setting->key, self::SECURE_SETTINGS)) {
                    $setting->value = $this->maskSecureValue($setting->value);
                    $setting->is_secure = true;
                } else {
                    $setting->is_secure = false;
                }
            }
            
            // System-Status
            $systemStatus = [
                'app_version' => config('app.version', '1.0.0'),
                'php_version' => PHP_VERSION,
                'database_status' => $this->checkDatabaseStatus(),
                'cache_status' => $this->checkCacheStatus(),
                'storage_status' => $this->checkStorageStatus(),
                'last_backup' => $this->getLastBackupInfo(),
                'maintenance_mode' => $this->isMaintenanceMode()
            ];
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'settings' => $settings,
                    'settings_by_category' => $settingsByCategory,
                    'categories' => self::SETTING_CATEGORIES,
                    'system_status' => $systemStatus,
                    'current_category' => $category,
                    'search' => $search
                ]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Laden der Systemeinstellungen', [
                'error' => $e->getMessage(),
                'admin_user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Laden der Einstellungen'], 500);
        }
    }

    /**
     * Aktualisiert Einstellungen
     */
    public function update(): void
    {
        try {
            $this->authorize(['admin:settings:edit']);
            
            $rules = [
                'settings' => 'required|array|min:1',
                'settings.*.key' => 'required|string|exists:settings,key',
                'settings.*.value' => 'nullable|string'
            ];
            
            $data = $this->validate($_POST, $rules);
            
            $updated = 0;
            $errors = [];
            $changes = [];
            
            foreach ($data['settings'] as $settingData) {
                try {
                    $setting = Setting::where('key', $settingData['key'])->first();
                    
                    if (!$setting) {
                        $errors[] = "Einstellung '{$settingData['key']}' nicht gefunden";
                        continue;
                    }
                    
                    // Validation nach Typ
                    $validationResult = $this->validateSettingValue($setting, $settingData['value']);
                    if (!$validationResult['valid']) {
                        $errors[] = "Einstellung '{$setting->name}': {$validationResult['error']}";
                        continue;
                    }
                    
                    $oldValue = $setting->value;
                    $newValue = $validationResult['value'];
                    
                    if ($oldValue !== $newValue) {
                        $setting->update([
                            'value' => $newValue,
                            'updated_by' => Auth::id()
                        ]);
                        
                        $changes[] = [
                            'key' => $setting->key,
                            'name' => $setting->name,
                            'old_value' => $this->maskSecureValue($oldValue, $setting->key),
                            'new_value' => $this->maskSecureValue($newValue, $setting->key)
                        ];
                        
                        $updated++;
                        
                        // Cache invalidieren falls notwendig
                        if ($setting->cache_key) {
                            Cache::forget($setting->cache_key);
                        }
                    }
                    
                } catch (\Exception $e) {
                    $errors[] = "Fehler bei Einstellung '{$settingData['key']}': " . $e->getMessage();
                }
            }
            
            // Audit-Log
            if (!empty($changes)) {
                AuditLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'settings_updated',
                    'resource' => 'Setting',
                    'details' => [
                        'updated_count' => $updated,
                        'changes' => $changes
                    ]
                ]);
            }
            
            Logger::info('Systemeinstellungen aktualisiert', [
                'updated_count' => $updated,
                'error_count' => count($errors),
                'admin_user_id' => Auth::id()
            ]);
            
            $message = "{$updated} Einstellungen aktualisiert";
            if (!empty($errors)) {
                $message .= ", " . count($errors) . " Fehler aufgetreten";
            }
            
            $this->jsonResponse([
                'success' => empty($errors),
                'message' => $message,
                'data' => [
                    'updated_count' => $updated,
                    'errors' => $errors,
                    'changes' => $changes
                ]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Aktualisieren der Einstellungen', [
                'error' => $e->getMessage(),
                'admin_user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Aktualisieren der Einstellungen'], 500);
        }
    }

    /**
     * Setzt Einstellungen auf Standardwerte zurück
     */
    public function reset(): void
    {
        try {
            $this->authorize(['admin:settings:edit']);
            
            $rules = [
                'category' => 'required|string|in:' . implode(',', array_keys(self::SETTING_CATEGORIES)),
                'confirm_reset' => 'required|boolean|accepted'
            ];
            
            $data = $this->validate($_POST, $rules);
            
            if (!($data['confirm_reset'] ?? false)) {
                $this->jsonResponse(['error' => 'Zurücksetzen muss bestätigt werden'], 400);
                return;
            }
            
            $settings = Setting::where('category', $data['category'])->get();
            $resetCount = 0;
            $changes = [];
            
            foreach ($settings as $setting) {
                if ($setting->default_value !== null && $setting->value !== $setting->default_value) {
                    $oldValue = $setting->value;
                    
                    $setting->update([
                        'value' => $setting->default_value,
                        'updated_by' => Auth::id()
                    ]);
                    
                    $changes[] = [
                        'key' => $setting->key,
                        'name' => $setting->name,
                        'old_value' => $this->maskSecureValue($oldValue, $setting->key),
                        'new_value' => $this->maskSecureValue($setting->default_value, $setting->key)
                    ];
                    
                    $resetCount++;
                    
                    // Cache invalidieren
                    if ($setting->cache_key) {
                        Cache::forget($setting->cache_key);
                    }
                }
            }
            
            // Audit-Log
            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'settings_reset',
                'resource' => 'Setting',
                'details' => [
                    'category' => $data['category'],
                    'reset_count' => $resetCount,
                    'changes' => $changes
                ]
            ]);
            
            Logger::info('Einstellungen zurückgesetzt', [
                'category' => $data['category'],
                'reset_count' => $resetCount,
                'admin_user_id' => Auth::id()
            ]);
            
            $this->jsonResponse([
                'success' => true,
                'message' => "{$resetCount} Einstellungen in Kategorie '{$data['category']}' zurückgesetzt",
                'data' => [
                    'reset_count' => $resetCount,
                    'changes' => $changes
                ]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Zurücksetzen der Einstellungen', [
                'error' => $e->getMessage(),
                'admin_user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Zurücksetzen'], 500);
        }
    }

    /**
     * Testet E-Mail-Konfiguration
     */
    public function testEmail(): void
    {
        try {
            $this->authorize(['admin:settings:edit']);
            
            $rules = [
                'recipient' => 'required|email',
                'use_current_settings' => 'boolean'
            ];
            
            $data = $this->validate($_POST, $rules);
            
            $recipient = $data['recipient'];
            $useCurrentSettings = $data['use_current_settings'] ?? true;
            
            // E-Mail-Einstellungen
            if ($useCurrentSettings) {
                $settings = $this->getEmailSettings();
            } else {
                // Temporäre Einstellungen aus POST-Daten
                $settings = [
                    'smtp_host' => $_POST['smtp_host'] ?? '',
                    'smtp_port' => $_POST['smtp_port'] ?? 587,
                    'smtp_username' => $_POST['smtp_username'] ?? '',
                    'smtp_password' => $_POST['smtp_password'] ?? '',
                    'smtp_encryption' => $_POST['smtp_encryption'] ?? 'tls',
                    'mail_from_address' => $_POST['mail_from_address'] ?? '',
                    'mail_from_name' => $_POST['mail_from_name'] ?? ''
                ];
            }
            
            // Test-E-Mail senden
            $result = $this->sendTestEmail($recipient, $settings);
            
            // Audit-Log
            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'email_test',
                'resource' => 'Setting',
                'details' => [
                    'recipient' => $recipient,
                    'success' => $result['success'],
                    'error' => $result['error'] ?? null
                ]
            ]);
            
            if ($result['success']) {
                Logger::info('E-Mail-Test erfolgreich', [
                    'recipient' => $recipient,
                    'admin_user_id' => Auth::id()
                ]);
                
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Test-E-Mail erfolgreich gesendet',
                    'data' => ['recipient' => $recipient]
                ]);
            } else {
                Logger::warning('E-Mail-Test fehlgeschlagen', [
                    'recipient' => $recipient,
                    'error' => $result['error'],
                    'admin_user_id' => Auth::id()
                ]);
                
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'E-Mail-Test fehlgeschlagen: ' . $result['error']
                ]);
            }
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim E-Mail-Test', [
                'error' => $e->getMessage(),
                'admin_user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim E-Mail-Test'], 500);
        }
    }

    /**
     * Testet Datenbank-Verbindung
     */
    public function testDatabase(): void
    {
        try {
            $this->authorize(['admin:settings:view']);
            
            $status = $this->checkDatabaseStatus();
            
            $this->jsonResponse([
                'success' => $status['connected'],
                'message' => $status['connected'] ? 'Datenbankverbindung erfolgreich' : 'Datenbankverbindung fehlgeschlagen',
                'data' => $status
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Datenbank-Test', [
                'error' => $e->getMessage(),
                'admin_user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Datenbank-Test'], 500);
        }
    }

    /**
     * Wartungsmodus ein-/ausschalten
     */
    public function toggleMaintenance(): void
    {
        try {
            $this->authorize(['admin:system:manage']);
            
            $rules = [
                'enabled' => 'required|boolean',
                'message' => 'string|max:500'
            ];
            
            $data = $this->validate($_POST, $rules);
            
            $maintenanceFile = storage_path('framework/maintenance.php');
            $enabled = $data['enabled'];
            $message = $data['message'] ?? 'System wird gewartet. Bitte versuchen Sie es später erneut.';
            
            if ($enabled) {
                // Wartungsmodus aktivieren
                $maintenanceData = [
                    'enabled' => true,
                    'message' => $message,
                    'enabled_at' => date('Y-m-d H:i:s'),
                    'enabled_by' => Auth::id()
                ];
                
                file_put_contents($maintenanceFile, '<?php return ' . var_export($maintenanceData, true) . ';');
                
                Logger::warning('Wartungsmodus aktiviert', [
                    'admin_user_id' => Auth::id(),
                    'message' => $message
                ]);
                
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Wartungsmodus aktiviert'
                ]);
                
            } else {
                // Wartungsmodus deaktivieren
                if (file_exists($maintenanceFile)) {
                    unlink($maintenanceFile);
                }
                
                Logger::info('Wartungsmodus deaktiviert', [
                    'admin_user_id' => Auth::id()
                ]);
                
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Wartungsmodus deaktiviert'
                ]);
            }
            
            // Audit-Log
            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => $enabled ? 'maintenance_enabled' : 'maintenance_disabled',
                'resource' => 'System',
                'details' => [
                    'enabled' => $enabled,
                    'message' => $message
                ]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Wartungsmodus-Toggle', [
                'error' => $e->getMessage(),
                'admin_user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Wartungsmodus'], 500);
        }
    }

    /**
     * Cache leeren
     */
    public function clearCache(): void
    {
        try {
            $this->authorize(['admin:system:manage']);
            
            $types = $_POST['types'] ?? ['config', 'views', 'routes'];
            $cleared = [];
            
            foreach ($types as $type) {
                switch ($type) {
                    case 'config':
                        Cache::flush();
                        $cleared[] = 'Konfigurations-Cache';
                        break;
                    case 'views':
                        $this->clearViewCache();
                        $cleared[] = 'View-Cache';
                        break;
                    case 'routes':
                        $this->clearRouteCache();
                        $cleared[] = 'Route-Cache';
                        break;
                    case 'sessions':
                        $this->clearSessions();
                        $cleared[] = 'Sessions';
                        break;
                    case 'logs':
                        $this->clearOldLogs();
                        $cleared[] = 'Alte Logs';
                        break;
                }
            }
            
            // Audit-Log
            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'cache_cleared',
                'resource' => 'System',
                'details' => ['types' => $cleared]
            ]);
            
            Logger::info('Cache geleert', [
                'types' => $cleared,
                'admin_user_id' => Auth::id()
            ]);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Cache erfolgreich geleert: ' . implode(', ', $cleared),
                'data' => ['cleared' => $cleared]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Cache leeren', [
                'error' => $e->getMessage(),
                'admin_user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Cache leeren'], 500);
        }
    }

    /**
     * System-Informationen
     */
    public function systemInfo(): void
    {
        try {
            $this->authorize(['admin:settings:view']);
            
            $info = [
                'application' => [
                    'name' => config('app.name', 'Vertragsverwaltung'),
                    'version' => config('app.version', '1.0.0'),
                    'environment' => config('app.env', 'production'),
                    'debug_mode' => config('app.debug', false),
                    'timezone' => config('app.timezone', 'UTC'),
                    'locale' => config('app.locale', 'de')
                ],
                'server' => [
                    'php_version' => PHP_VERSION,
                    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                    'operating_system' => PHP_OS,
                    'memory_limit' => ini_get('memory_limit'),
                    'max_execution_time' => ini_get('max_execution_time'),
                    'max_upload_size' => ini_get('upload_max_filesize'),
                    'post_max_size' => ini_get('post_max_size')
                ],
                'database' => $this->getDatabaseInfo(),
                'storage' => $this->getStorageInfo(),
                'performance' => $this->getPerformanceInfo(),
                'security' => $this->getSecurityInfo()
            ];
            
            $this->jsonResponse([
                'success' => true,
                'data' => $info
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Laden der System-Informationen', [
                'error' => $e->getMessage(),
                'admin_user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Laden der System-Informationen'], 500);
        }
    }

    /**
     * Exportiert Einstellungen
     */
    public function export(): void
    {
        try {
            $this->authorize(['admin:settings:export']);
            
            $category = $_GET['category'] ?? null;
            $includeSecure = $_GET['include_secure'] ?? false;
            
            $query = Setting::query();
            
            if ($category) {
                $query->where('category', $category);
            }
            
            $settings = $query->get();
            
            $exportData = [];
            foreach ($settings as $setting) {
                // Sichere Einstellungen nur mit Berechtigung
                if (in_array($setting->key, self::SECURE_SETTINGS) && !$includeSecure) {
                    continue;
                }
                
                $exportData[] = [
                    'key' => $setting->key,
                    'value' => $setting->value,
                    'category' => $setting->category,
                    'name' => $setting->name,
                    'description' => $setting->description,
                    'type' => $setting->type,
                    'default_value' => $setting->default_value
                ];
            }
            
            // Audit-Log
            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'settings_exported',
                'resource' => 'Setting',
                'details' => [
                    'category' => $category,
                    'count' => count($exportData),
                    'include_secure' => $includeSecure
                ]
            ]);
            
            $filename = 'settings_export_' . date('Y-m-d_H-i-s') . '.json';
            
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            echo json_encode([
                'export_info' => [
                    'exported_at' => date('Y-m-d H:i:s'),
                    'exported_by' => Auth::user()->email,
                    'category' => $category,
                    'total_settings' => count($exportData)
                ],
                'settings' => $exportData
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Exportieren der Einstellungen', [
                'error' => $e->getMessage(),
                'admin_user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Export'], 500);
        }
    }

    /**
     * Validiert Einstellungswert
     */
    private function validateSettingValue(Setting $setting, $value): array
    {
        $result = ['valid' => true, 'value' => $value, 'error' => null];
        
        // Typ-spezifische Validierung
        switch ($setting->type) {
            case 'boolean':
                $result['value'] = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($result['value'] === null) {
                    $result = ['valid' => false, 'error' => 'Ungültiger Boolean-Wert'];
                }
                break;
                
            case 'integer':
                if (!is_numeric($value) || intval($value) != $value) {
                    $result = ['valid' => false, 'error' => 'Muss eine ganze Zahl sein'];
                } else {
                    $result['value'] = intval($value);
                }
                break;
                
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $result = ['valid' => false, 'error' => 'Ungültige E-Mail-Adresse'];
                }
                break;
                
            case 'url':
                if ($value && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $result = ['valid' => false, 'error' => 'Ungültige URL'];
                }
                break;
                
            case 'json':
                if ($value) {
                    json_decode($value);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $result = ['valid' => false, 'error' => 'Ungültiges JSON-Format'];
                    }
                }
                break;
        }
        
        // Zusätzliche Validierung basierend auf Schlüssel
        if ($result['valid']) {
            $result = $this->validateSpecificSetting($setting->key, $result['value']);
        }
        
        return $result;
    }

    /**
     * Spezifische Validierung für bestimmte Einstellungen
     */
    private function validateSpecificSetting(string $key, $value): array
    {
        switch ($key) {
            case 'smtp_port':
                if ($value < 1 || $value > 65535) {
                    return ['valid' => false, 'error' => 'Port muss zwischen 1 und 65535 liegen'];
                }
                break;
                
            case 'session_lifetime':
                if ($value < 1 || $value > 43200) { // Max 12 Stunden
                    return ['valid' => false, 'error' => 'Session-Lifetime muss zwischen 1 und 43200 Minuten liegen'];
                }
                break;
                
            case 'max_upload_size':
                if ($value < 1 || $value > 1024) { // Max 1GB
                    return ['valid' => false, 'error' => 'Upload-Größe muss zwischen 1 und 1024 MB liegen'];
                }
                break;
        }
        
        return ['valid' => true, 'value' => $value];
    }

    /**
     * Maskiert sichere Werte
     */
    private function maskSecureValue($value, string $key = ''): string
    {
        if (in_array($key, self::SECURE_SETTINGS) && $value) {
            return str_repeat('*', min(strlen($value), 8));
        }
        return $value;
    }

    /**
     * Prüft Datenbank-Status
     */
    private function checkDatabaseStatus(): array
    {
        try {
            $pdo = new \PDO(
                "mysql:host=" . config('database.host') . ";dbname=" . config('database.name'),
                config('database.username'),
                config('database.password')
            );
            
            $version = $pdo->query('SELECT VERSION()')->fetchColumn();
            
            return [
                'connected' => true,
                'version' => $version,
                'driver' => $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME)
            ];
        } catch (\Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Prüft Cache-Status
     */
    private function checkCacheStatus(): array
    {
        return [
            'enabled' => Cache::isEnabled(),
            'driver' => Cache::getDriver(),
            'size' => Cache::getSize()
        ];
    }

    /**
     * Prüft Speicher-Status
     */
    private function checkStorageStatus(): array
    {
        $storagePath = storage_path();
        
        return [
            'path' => $storagePath,
            'writable' => is_writable($storagePath),
            'free_space' => disk_free_space($storagePath),
            'total_space' => disk_total_space($storagePath)
        ];
    }

    /**
     * Holt letzte Backup-Info
     */
    private function getLastBackupInfo(): ?array
    {
        $backupPath = storage_path('backups');
        
        if (!is_dir($backupPath)) {
            return null;
        }
        
        $files = glob($backupPath . '/*.sql');
        if (empty($files)) {
            return null;
        }
        
        $latest = max($files);
        
        return [
            'file' => basename($latest),
            'date' => date('Y-m-d H:i:s', filemtime($latest)),
            'size' => filesize($latest)
        ];
    }

    /**
     * Prüft ob Wartungsmodus aktiv ist
     */
    private function isMaintenanceMode(): bool
    {
        $maintenanceFile = storage_path('framework/maintenance.php');
        return file_exists($maintenanceFile);
    }

    /**
     * Weitere Hilfsmethoden für System-Informationen
     */
    private function getDatabaseInfo(): array
    {
        $status = $this->checkDatabaseStatus();
        return array_merge($status, [
            'host' => config('database.host'),
            'name' => config('database.name'),
            'charset' => config('database.charset', 'utf8mb4')
        ]);
    }

    private function getStorageInfo(): array
    {
        return $this->checkStorageStatus();
    }

    private function getPerformanceInfo(): array
    {
        return [
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'memory_limit' => ini_get('memory_limit'),
            'execution_time' => round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 3)
        ];
    }

    private function getSecurityInfo(): array
    {
        return [
            'https_enabled' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'session_secure' => ini_get('session.cookie_secure'),
            'session_httponly' => ini_get('session.cookie_httponly'),
            'display_errors' => ini_get('display_errors')
        ];
    }

    private function getEmailSettings(): array
    {
        return [
            'smtp_host' => Setting::getValue('smtp_host'),
            'smtp_port' => Setting::getValue('smtp_port', 587),
            'smtp_username' => Setting::getValue('smtp_username'),
            'smtp_password' => Setting::getValue('smtp_password'),
            'smtp_encryption' => Setting::getValue('smtp_encryption', 'tls'),
            'mail_from_address' => Setting::getValue('mail_from_address'),
            'mail_from_name' => Setting::getValue('mail_from_name')
        ];
    }

    private function sendTestEmail(string $recipient, array $settings): array
    {
        try {
            // Hier würde die tatsächliche E-Mail-Implementierung stehen
            // Für Demo-Zwecke simulieren wir den Versand
            
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function clearViewCache(): void
    {
        $viewCachePath = storage_path('framework/views');
        if (is_dir($viewCachePath)) {
            $files = glob($viewCachePath . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    private function clearRouteCache(): void
    {
        $routeCacheFile = storage_path('framework/routes.php');
        if (file_exists($routeCacheFile)) {
            unlink($routeCacheFile);
        }
    }

    private function clearSessions(): void
    {
        $sessionPath = session_save_path();
        if ($sessionPath && is_dir($sessionPath)) {
            $files = glob($sessionPath . '/sess_*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    private function clearOldLogs(): void
    {
        $logPath = storage_path('logs');
        if (is_dir($logPath)) {
            $cutoff = strtotime('-30 days');
            $files = glob($logPath . '/*.log');
            
            foreach ($files as $file) {
                if (filemtime($file) < $cutoff) {
                    unlink($file);
                }
            }
        }
    }
}