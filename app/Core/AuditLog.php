<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Audit-Logging für Sicherheitsereignisse
 * 
 * @package App\Core
 * @author GenSpark AI Developer
 */
class AuditLog
{
    /**
     * Audit-Event loggen
     */
    public static function log(
        string $event,
        string $resourceType,
        ?int $resourceId = null,
        ?array $details = null
    ): void {
        if (!config('audit.enabled', true)) {
            return;
        }
        
        $user = Auth::user();
        
        try {
            Database::table('audit_log')->insert([
                'user_id' => $user ? $user->id : null,
                'event' => $event,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'details' => $details ? json_encode($details) : null,
                'ip_address' => self::getClientIp(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // Audit-Logging sollte nicht die Anwendung zum Absturz bringen
            error_log("Audit logging failed: " . $e->getMessage());
        }
    }
    
    /**
     * Login-Event loggen
     */
    public static function logLogin(int $userId, bool $twoFactor = false): void
    {
        self::log($twoFactor ? 'login_2fa' : 'login', 'user', $userId, [
            'method' => $twoFactor ? '2fa' : 'password',
            'session_id' => session_id(),
        ]);
    }
    
    /**
     * Logout-Event loggen
     */
    public static function logLogout(int $userId): void
    {
        self::log('logout', 'user', $userId, [
            'session_id' => session_id(),
        ]);
    }
    
    /**
     * Login-Fehlschlag loggen
     */
    public static function logLoginFailed(string $email, string $reason = 'invalid_credentials'): void
    {
        self::log('login_failed', 'auth', null, [
            'email' => $email,
            'reason' => $reason,
        ]);
    }
    
    /**
     * CRUD-Operation loggen
     */
    public static function logCrud(
        string $action,
        string $resourceType,
        int $resourceId,
        ?array $oldData = null,
        ?array $newData = null
    ): void {
        $details = [];
        
        if ($oldData) {
            $details['old_data'] = $oldData;
        }
        
        if ($newData) {
            $details['new_data'] = $newData;
        }
        
        // Sensitive Daten aus Log entfernen
        $details = self::sanitizeLogData($details);
        
        self::log("${resourceType}_${action}", $resourceType, $resourceId, $details);
    }
    
    /**
     * Datei-Upload loggen
     */
    public static function logFileUpload(string $filename, string $path, int $size): void
    {
        self::log('file_upload', 'file', null, [
            'filename' => $filename,
            'path' => $path,
            'size' => $size,
            'mime_type' => mime_content_type($path) ?: 'unknown',
        ]);
    }
    
    /**
     * Datei-Download loggen
     */
    public static function logFileDownload(int $documentId, string $filename): void
    {
        self::log('file_download', 'document', $documentId, [
            'filename' => $filename,
        ]);
    }
    
    /**
     * Passwort-Änderung loggen
     */
    public static function logPasswordChange(int $userId, string $method = 'self'): void
    {
        self::log('password_change', 'user', $userId, [
            'method' => $method, // 'self', 'admin', 'reset'
        ]);
    }
    
    /**
     * 2FA-Events loggen
     */
    public static function log2FA(string $action, int $userId): void
    {
        self::log("2fa_${action}", 'user', $userId, [
            'action' => $action, // 'enable', 'disable', 'backup_codes_generated'
        ]);
    }
    
    /**
     * Freigabe-Events loggen
     */
    public static function logFreigabe(string $action, int $vertragId, int $freundUserId): void
    {
        self::log("freigabe_${action}", 'vertrag', $vertragId, [
            'freund_user_id' => $freundUserId,
            'action' => $action, // 'created', 'deleted'
        ]);
    }
    
    /**
     * Notfall-Zugriff loggen
     */
    public static function logNotfallZugriff(array $vertraegeIds, string $notfallEmail): void
    {
        self::log('notfall_zugriff', 'system', null, [
            'vertraege_count' => count($vertraegeIds),
            'vertraege_ids' => $vertraegeIds,
            'notfall_email' => $notfallEmail,
        ]);
    }
    
    /**
     * Admin-Aktion loggen
     */
    public static function logAdminAction(string $action, ?string $targetType = null, ?int $targetId = null, ?array $details = null): void
    {
        self::log("admin_${action}", $targetType ?? 'admin', $targetId, $details);
    }
    
    /**
     * Security-Event loggen
     */
    public static function logSecurityEvent(string $event, ?array $details = null): void
    {
        self::log($event, 'security', null, array_merge($details ?? [], [
            'severity' => self::getEventSeverity($event),
        ]));
    }
    
    /**
     * Audit-Logs abrufen (mit Paginierung)
     */
    public static function getLogs(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $query = Database::table('audit_log as al')
            ->select([
                'al.*',
                'u.email as user_email',
                'u.name as user_name'
            ])
            ->leftJoin('benutzer as u', 'al.user_id', '=', 'u.id')
            ->orderBy('al.created_at', 'DESC');
        
        // Filter anwenden
        if (!empty($filters['event'])) {
            $query->where('al.event', 'LIKE', '%' . $filters['event'] . '%');
        }
        
        if (!empty($filters['user_id'])) {
            $query->where('al.user_id', $filters['user_id']);
        }
        
        if (!empty($filters['resource_type'])) {
            $query->where('al.resource_type', $filters['resource_type']);
        }
        
        if (!empty($filters['date_from'])) {
            $query->where('al.created_at', '>=', $filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $query->where('al.created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }
        
        // Pagination
        $total = $query->count();
        $offset = ($page - 1) * $perPage;
        
        $logs = $query->limit($perPage)->offset($offset)->get();
        
        // Details dekodieren
        foreach ($logs as &$log) {
            if ($log['details']) {
                $log['details'] = json_decode($log['details'], true);
            }
        }
        
        return [
            'data' => $logs,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage),
        ];
    }
    
    /**
     * Alte Audit-Logs bereinigen
     */
    public static function cleanup(): int
    {
        $retentionDays = config('audit.retention_days', 365);
        $cutoffDate = date('Y-m-d', time() - ($retentionDays * 24 * 60 * 60));
        
        $deletedCount = Database::table('audit_log')
            ->where('created_at', '<', $cutoffDate)
            ->count();
        
        Database::table('audit_log')
            ->where('created_at', '<', $cutoffDate)
            ->delete();
        
        self::log('audit_cleanup', 'system', null, [
            'deleted_records' => $deletedCount,
            'retention_days' => $retentionDays,
        ]);
        
        return $deletedCount;
    }
    
    /**
     * Client-IP ermitteln (auch hinter Proxy/Load Balancer)
     */
    private static function getClientIp(): ?string
    {
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                return trim($ips[0]);
            }
        }
        
        return null;
    }
    
    /**
     * Sensitive Daten aus Log-Details entfernen
     */
    private static function sanitizeLogData(array $data): array
    {
        $sensitiveFields = [
            'passwort',
            'password',
            'passwort_hash',
            'password_hash',
            'passwort_enc',
            'password_enc',
            'totp_secret',
            '2fa_secret_enc',
            'recovery_codes_enc',
            'remember_token',
            'reset_token'
        ];
        
        return self::recursiveUnset($data, $sensitiveFields);
    }
    
    /**
     * Rekursiv sensitive Felder entfernen
     */
    private static function recursiveUnset(array $array, array $keysToUnset): array
    {
        foreach ($array as $key => &$value) {
            if (in_array($key, $keysToUnset)) {
                $array[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $value = self::recursiveUnset($value, $keysToUnset);
            }
        }
        
        return $array;
    }
    
    /**
     * Event-Schweregrad bestimmen
     */
    private static function getEventSeverity(string $event): string
    {
        $highSeverityEvents = [
            'login_failed',
            'csrf_failure',
            'unauthorized_access',
            'admin_login',
            'password_reset',
            'notfall_zugriff',
        ];
        
        $mediumSeverityEvents = [
            'login',
            'logout',
            '2fa_disable',
            'freigabe_created',
            'file_upload',
        ];
        
        if (in_array($event, $highSeverityEvents) || str_contains($event, 'admin_')) {
            return 'high';
        }
        
        if (in_array($event, $mediumSeverityEvents)) {
            return 'medium';
        }
        
        return 'low';
    }
}