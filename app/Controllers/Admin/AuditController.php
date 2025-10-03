<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AuditLog;
use App\Models\User;
use App\Core\Auth;
use App\Core\Logger;

/**
 * AuditController - Admin-Verwaltung für Audit-Logs
 * 
 * Ermöglicht die Einsicht und Analyse von System-Audit-Logs
 * für Sicherheits- und Compliance-Zwecke.
 * 
 * @package App\Controllers\Admin
 * @version 1.0.0
 * @author Vertragsverwaltung System
 */
class AuditController extends BaseController
{
    private const ALLOWED_ACTIONS = [
        'user_login', 'user_logout', 'user_created', 'user_updated', 'user_deleted',
        'password_changed', 'password_reset', 'account_locked', 'account_unlocked',
        'role_created', 'role_updated', 'role_deleted', 'permissions_changed',
        'contract_created', 'contract_updated', 'contract_deleted',
        'document_uploaded', 'document_downloaded', 'document_deleted',
        'credential_created', 'credential_updated', 'credential_deleted', 'credential_accessed',
        'share_created', 'share_accessed', 'share_revoked',
        'emergency_request', 'emergency_approved', 'emergency_denied',
        'system_backup', 'system_restore', 'system_update'
    ];

    private const RISK_LEVELS = [
        'low' => ['user_login', 'document_downloaded', 'contract_viewed'],
        'medium' => ['user_created', 'contract_created', 'document_uploaded'],
        'high' => ['password_reset', 'role_updated', 'credential_accessed'],
        'critical' => ['user_deleted', 'system_restore', 'emergency_approved']
    ];

    /**
     * Zeigt die Audit-Log-Übersicht
     */
    public function index(): void
    {
        try {
            $this->authorize(['admin:audit:view']);
            
            $page = (int)($_GET['page'] ?? 1);
            $search = $_GET['search'] ?? '';
            $action = $_GET['action'] ?? '';
            $user_id = $_GET['user_id'] ?? '';
            $risk_level = $_GET['risk_level'] ?? '';
            $date_from = $_GET['date_from'] ?? '';
            $date_to = $_GET['date_to'] ?? '';
            $sort = $_GET['sort'] ?? 'created_at';
            $order = $_GET['order'] ?? 'desc';
            
            // Query für Audit-Logs
            $query = AuditLog::with(['user']);
            
            // Filter anwenden
            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('action', 'LIKE', "%{$search}%")
                      ->orWhere('resource', 'LIKE', "%{$search}%")
                      ->orWhere('ip_address', 'LIKE', "%{$search}%")
                      ->orWhere('user_agent', 'LIKE', "%{$search}%");
                });
            }
            
            if (!empty($action)) {
                $query->where('action', $action);
            }
            
            if (!empty($user_id) && is_numeric($user_id)) {
                $query->where('user_id', $user_id);
            }
            
            if (!empty($risk_level) && array_key_exists($risk_level, self::RISK_LEVELS)) {
                $query->whereIn('action', self::RISK_LEVELS[$risk_level]);
            }
            
            if (!empty($date_from)) {
                $query->where('created_at', '>=', $date_from . ' 00:00:00');
            }
            
            if (!empty($date_to)) {
                $query->where('created_at', '<=', $date_to . ' 23:59:59');
            }
            
            // Sortierung
            $allowedSorts = ['created_at', 'action', 'user_id', 'ip_address'];
            if (in_array($sort, $allowedSorts)) {
                $query->orderBy($sort, $order === 'desc' ? 'desc' : 'asc');
            }
            
            // Pagination
            $total = $query->count();
            $auditLogs = $this->paginate($query, $page);
            
            // Risiko-Level für jeden Log berechnen
            foreach ($auditLogs as $log) {
                $log->risk_level = $this->calculateRiskLevel($log->action);
                $log->severity = $this->calculateSeverity($log);
                $log->details_summary = $this->formatDetailsSummary($log->details);
            }
            
            // Verfügbare Filter-Optionen
            $filterOptions = [
                'actions' => AuditLog::distinct('action')
                    ->orderBy('action')
                    ->pluck('action')
                    ->toArray(),
                'users' => User::select('id', 'name', 'email')
                    ->whereHas('auditLogs')
                    ->orderBy('name')
                    ->get(),
                'risk_levels' => array_keys(self::RISK_LEVELS)
            ];
            
            // Schnellfilter für häufige Abfragen
            $quickFilters = [
                'today' => [
                    'label' => 'Heute',
                    'date_from' => date('Y-m-d'),
                    'date_to' => date('Y-m-d')
                ],
                'week' => [
                    'label' => 'Diese Woche',
                    'date_from' => date('Y-m-d', strtotime('monday this week')),
                    'date_to' => date('Y-m-d')
                ],
                'critical' => [
                    'label' => 'Kritische Ereignisse',
                    'risk_level' => 'critical'
                ],
                'failed_logins' => [
                    'label' => 'Fehlgeschlagene Logins',
                    'action' => 'login_failed'
                ]
            ];
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'audit_logs' => $auditLogs,
                    'pagination' => $this->getPaginationData($total, $page),
                    'filter_options' => $filterOptions,
                    'quick_filters' => $quickFilters,
                    'current_filters' => [
                        'search' => $search,
                        'action' => $action,
                        'user_id' => $user_id,
                        'risk_level' => $risk_level,
                        'date_from' => $date_from,
                        'date_to' => $date_to,
                        'sort' => $sort,
                        'order' => $order
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Laden der Audit-Logs', [
                'error' => $e->getMessage(),
                'admin_user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Laden der Audit-Logs'], 500);
        }
    }

    /**
     * Zeigt Details eines Audit-Log-Eintrags
     */
    public function show(int $id): void
    {
        try {
            $this->authorize(['admin:audit:view']);
            
            $auditLog = AuditLog::with(['user'])->find($id);
            
            if (!$auditLog) {
                $this->jsonResponse(['error' => 'Audit-Log-Eintrag nicht gefunden'], 404);
                return;
            }
            
            // Zusätzliche Analysedaten
            $auditLog->risk_level = $this->calculateRiskLevel($auditLog->action);
            $auditLog->severity = $this->calculateSeverity($auditLog);
            
            // Ähnliche Ereignisse in zeitlicher Nähe
            $similarEvents = AuditLog::where('id', '!=', $auditLog->id)
                ->where('user_id', $auditLog->user_id)
                ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime($auditLog->created_at . ' -1 hour')))
                ->where('created_at', '<=', date('Y-m-d H:i:s', strtotime($auditLog->created_at . ' +1 hour')))
                ->orderBy('created_at')
                ->limit(10)
                ->get();
            
            // IP-Adresse Geolocation (falls verfügbar)
            $ipInfo = $this->getIpInfo($auditLog->ip_address);
            
            // User-Agent Analyse
            $userAgentInfo = $this->parseUserAgent($auditLog->user_agent);
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'audit_log' => $auditLog,
                    'similar_events' => $similarEvents,
                    'ip_info' => $ipInfo,
                    'user_agent_info' => $userAgentInfo,
                    'context' => $this->getEventContext($auditLog)
                ]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Laden der Audit-Log-Details', [
                'audit_log_id' => $id,
                'error' => $e->getMessage(),
                'admin_user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Laden der Audit-Log-Details'], 500);
        }
    }

    /**
     * Zeigt Audit-Statistiken und Dashboards
     */
    public function dashboard(): void
    {
        try {
            $this->authorize(['admin:audit:view']);
            
            $timeframe = $_GET['timeframe'] ?? '30'; // Tage
            $startDate = date('Y-m-d H:i:s', strtotime("-{$timeframe} days"));
            
            // Basis-Statistiken
            $stats = [
                'total_events' => AuditLog::where('created_at', '>=', $startDate)->count(),
                'unique_users' => AuditLog::where('created_at', '>=', $startDate)
                    ->distinct('user_id')
                    ->count('user_id'),
                'unique_ips' => AuditLog::where('created_at', '>=', $startDate)
                    ->whereNotNull('ip_address')
                    ->distinct('ip_address')
                    ->count('ip_address'),
                'critical_events' => AuditLog::where('created_at', '>=', $startDate)
                    ->whereIn('action', self::RISK_LEVELS['critical'])
                    ->count(),
                'failed_logins' => AuditLog::where('created_at', '>=', $startDate)
                    ->where('action', 'login_failed')
                    ->count()
            ];
            
            // Top-Aktivitäten
            $topActivities = AuditLog::where('created_at', '>=', $startDate)
                ->groupBy('action')
                ->selectRaw('action, COUNT(*) as count')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get()
                ->map(function($item) {
                    return [
                        'action' => $item->action,
                        'count' => $item->count,
                        'risk_level' => $this->calculateRiskLevel($item->action),
                        'display_name' => $this->getActionDisplayName($item->action)
                    ];
                });
            
            // Top-Benutzer nach Aktivität
            $topUsers = AuditLog::where('created_at', '>=', $startDate)
                ->with(['user:id,name,email'])
                ->groupBy('user_id')
                ->selectRaw('user_id, COUNT(*) as count')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get()
                ->map(function($item) {
                    return [
                        'user' => $item->user,
                        'count' => $item->count,
                        'risk_score' => $this->calculateUserRiskScore($item->user_id)
                    ];
                });
            
            // Zeitliche Verteilung (letzte 24 Stunden)
            $hourlyActivity = [];
            for ($i = 23; $i >= 0; $i--) {
                $hour = date('Y-m-d H:00:00', strtotime("-{$i} hours"));
                $hourEnd = date('Y-m-d H:59:59', strtotime("-{$i} hours"));
                
                $hourlyActivity[] = [
                    'hour' => date('H:00', strtotime($hour)),
                    'count' => AuditLog::whereBetween('created_at', [$hour, $hourEnd])->count(),
                    'critical_count' => AuditLog::whereBetween('created_at', [$hour, $hourEnd])
                        ->whereIn('action', self::RISK_LEVELS['critical'])
                        ->count()
                ];
            }
            
            // Tägliche Aktivität
            $dailyActivity = [];
            for ($i = intval($timeframe) - 1; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                $dateStart = $date . ' 00:00:00';
                $dateEnd = $date . ' 23:59:59';
                
                $dailyActivity[] = [
                    'date' => $date,
                    'count' => AuditLog::whereBetween('created_at', [$dateStart, $dateEnd])->count(),
                    'unique_users' => AuditLog::whereBetween('created_at', [$dateStart, $dateEnd])
                        ->distinct('user_id')
                        ->count('user_id'),
                    'critical_events' => AuditLog::whereBetween('created_at', [$dateStart, $dateEnd])
                        ->whereIn('action', self::RISK_LEVELS['critical'])
                        ->count()
                ];
            }
            
            // Verdächtige Aktivitäten
            $suspiciousActivities = $this->detectSuspiciousActivities($startDate);
            
            // IP-Adressen Analyse
            $ipAnalysis = AuditLog::where('created_at', '>=', $startDate)
                ->whereNotNull('ip_address')
                ->groupBy('ip_address')
                ->selectRaw('ip_address, COUNT(*) as count, COUNT(DISTINCT user_id) as user_count')
                ->orderBy('count', 'desc')
                ->limit(20)
                ->get()
                ->map(function($item) {
                    return [
                        'ip' => $item->ip_address,
                        'count' => $item->count,
                        'user_count' => $item->user_count,
                        'risk_score' => $this->calculateIpRiskScore($item->ip_address),
                        'location' => $this->getIpInfo($item->ip_address)['country'] ?? 'Unbekannt'
                    ];
                });
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'stats' => $stats,
                    'top_activities' => $topActivities,
                    'top_users' => $topUsers,
                    'hourly_activity' => $hourlyActivity,
                    'daily_activity' => $dailyActivity,
                    'suspicious_activities' => $suspiciousActivities,
                    'ip_analysis' => $ipAnalysis,
                    'timeframe' => $timeframe
                ]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Laden des Audit-Dashboards', [
                'error' => $e->getMessage(),
                'admin_user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Laden des Dashboards'], 500);
        }
    }

    /**
     * Exportiert Audit-Logs als CSV
     */
    public function export(): void
    {
        try {
            $this->authorize(['admin:audit:export']);
            
            $format = $_GET['format'] ?? 'csv';
            $date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
            $date_to = $_GET['date_to'] ?? date('Y-m-d');
            $action = $_GET['action'] ?? '';
            $user_id = $_GET['user_id'] ?? '';
            
            // Query für Export
            $query = AuditLog::with(['user:id,name,email'])
                ->where('created_at', '>=', $date_from . ' 00:00:00')
                ->where('created_at', '<=', $date_to . ' 23:59:59');
            
            if (!empty($action)) {
                $query->where('action', $action);
            }
            
            if (!empty($user_id) && is_numeric($user_id)) {
                $query->where('user_id', $user_id);
            }
            
            $auditLogs = $query->orderBy('created_at', 'desc')->get();
            
            if ($format === 'csv') {
                $this->exportToCsv($auditLogs, $date_from, $date_to);
            } elseif ($format === 'json') {
                $this->exportToJson($auditLogs, $date_from, $date_to);
            } else {
                $this->jsonResponse(['error' => 'Ungültiges Export-Format'], 400);
            }
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Exportieren der Audit-Logs', [
                'error' => $e->getMessage(),
                'admin_user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Export'], 500);
        }
    }

    /**
     * Bereinigt alte Audit-Logs
     */
    public function cleanup(): void
    {
        try {
            $this->authorize(['admin:system:manage']);
            
            $rules = [
                'retention_days' => 'required|integer|min:30|max:3650',
                'confirm_cleanup' => 'required|boolean|accepted'
            ];
            
            $data = $this->validate($_POST, $rules);
            
            if (!($data['confirm_cleanup'] ?? false)) {
                $this->jsonResponse(['error' => 'Bereinigung muss bestätigt werden'], 400);
                return;
            }
            
            $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$data['retention_days']} days"));
            
            // Anzahl zu löschender Einträge ermitteln
            $countToDelete = AuditLog::where('created_at', '<', $cutoffDate)->count();
            
            if ($countToDelete === 0) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Keine Audit-Logs zu bereinigen'
                ]);
                return;
            }
            
            // Kritische Ereignisse behalten (optional)
            $keepCritical = $_POST['keep_critical'] ?? true;
            
            if ($keepCritical) {
                // Nur nicht-kritische Ereignisse löschen
                $deleted = AuditLog::where('created_at', '<', $cutoffDate)
                    ->whereNotIn('action', self::RISK_LEVELS['critical'])
                    ->delete();
            } else {
                // Alle alten Ereignisse löschen
                $deleted = AuditLog::where('created_at', '<', $cutoffDate)->delete();
            }
            
            // Bereinigung protokollieren
            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'audit_logs_cleaned',
                'resource' => 'AuditLog',
                'details' => [
                    'retention_days' => $data['retention_days'],
                    'cutoff_date' => $cutoffDate,
                    'deleted_count' => $deleted,
                    'keep_critical' => $keepCritical
                ]
            ]);
            
            Logger::info('Audit-Logs bereinigt', [
                'deleted_count' => $deleted,
                'cutoff_date' => $cutoffDate,
                'admin_user_id' => Auth::id()
            ]);
            
            $this->jsonResponse([
                'success' => true,
                'message' => "{$deleted} Audit-Log-Einträge wurden bereinigt",
                'data' => [
                    'deleted_count' => $deleted,
                    'cutoff_date' => $cutoffDate,
                    'remaining_count' => AuditLog::count()
                ]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Bereinigen der Audit-Logs', [
                'error' => $e->getMessage(),
                'admin_user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Bereinigen'], 500);
        }
    }

    /**
     * Berechnet Risiko-Level für eine Aktion
     */
    private function calculateRiskLevel(string $action): string
    {
        foreach (self::RISK_LEVELS as $level => $actions) {
            if (in_array($action, $actions)) {
                return $level;
            }
        }
        return 'medium';
    }

    /**
     * Berechnet Severity-Score
     */
    private function calculateSeverity(AuditLog $log): int
    {
        $score = 1;
        
        // Basis-Score nach Risiko-Level
        switch ($this->calculateRiskLevel($log->action)) {
            case 'critical':
                $score = 10;
                break;
            case 'high':
                $score = 7;
                break;
            case 'medium':
                $score = 4;
                break;
            case 'low':
                $score = 1;
                break;
        }
        
        // Modifikatoren
        if (!$log->user_id) $score += 2; // Anonyme Aktionen
        if (isset($log->details['failed']) && $log->details['failed']) $score += 3;
        
        return min($score, 10);
    }

    /**
     * Formatiert Details-Summary
     */
    private function formatDetailsSummary($details): string
    {
        if (!is_array($details)) {
            return 'Keine Details verfügbar';
        }
        
        $summary = [];
        foreach ($details as $key => $value) {
            if (is_string($value) && strlen($value) < 50) {
                $summary[] = "{$key}: {$value}";
            } elseif (is_numeric($value)) {
                $summary[] = "{$key}: {$value}";
            } elseif (is_bool($value)) {
                $summary[] = "{$key}: " . ($value ? 'ja' : 'nein');
            }
        }
        
        $result = implode(', ', array_slice($summary, 0, 3));
        if (count($summary) > 3) {
            $result .= '...';
        }
        
        return $result ?: 'Komplexe Datenstruktur';
    }

    /**
     * Analysiert User-Agent
     */
    private function parseUserAgent(?string $userAgent): array
    {
        if (!$userAgent) {
            return ['browser' => 'Unbekannt', 'os' => 'Unbekannt', 'device' => 'Unbekannt'];
        }
        
        $info = [
            'browser' => 'Unbekannt',
            'os' => 'Unbekannt',
            'device' => 'Desktop'
        ];
        
        // Einfache Browser-Erkennung
        if (strpos($userAgent, 'Chrome') !== false) $info['browser'] = 'Chrome';
        elseif (strpos($userAgent, 'Firefox') !== false) $info['browser'] = 'Firefox';
        elseif (strpos($userAgent, 'Safari') !== false) $info['browser'] = 'Safari';
        elseif (strpos($userAgent, 'Edge') !== false) $info['browser'] = 'Edge';
        
        // OS-Erkennung
        if (strpos($userAgent, 'Windows') !== false) $info['os'] = 'Windows';
        elseif (strpos($userAgent, 'Mac') !== false) $info['os'] = 'macOS';
        elseif (strpos($userAgent, 'Linux') !== false) $info['os'] = 'Linux';
        elseif (strpos($userAgent, 'Android') !== false) $info['os'] = 'Android';
        elseif (strpos($userAgent, 'iOS') !== false) $info['os'] = 'iOS';
        
        // Device-Typ
        if (strpos($userAgent, 'Mobile') !== false || strpos($userAgent, 'Android') !== false) {
            $info['device'] = 'Mobile';
        } elseif (strpos($userAgent, 'Tablet') !== false || strpos($userAgent, 'iPad') !== false) {
            $info['device'] = 'Tablet';
        }
        
        return $info;
    }

    /**
     * Holt IP-Informationen
     */
    private function getIpInfo(string $ip): array
    {
        // Basis-Info
        $info = [
            'ip' => $ip,
            'country' => 'Unbekannt',
            'region' => 'Unbekannt',
            'city' => 'Unbekannt',
            'is_local' => $this->isLocalIp($ip),
            'is_vpn' => false
        ];
        
        // Lokale IPs
        if ($info['is_local']) {
            $info['country'] = 'Lokal';
            $info['city'] = 'Lokal';
            return $info;
        }
        
        // Hier könnte eine externe IP-Geolocation-API aufgerufen werden
        // Für Demo-Zwecke statische Werte
        
        return $info;
    }

    /**
     * Prüft ob IP lokal ist
     */
    private function isLocalIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    /**
     * Holt Event-Kontext
     */
    private function getEventContext(AuditLog $log): array
    {
        return [
            'session_events' => AuditLog::where('user_id', $log->user_id)
                ->where('ip_address', $log->ip_address)
                ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime($log->created_at . ' -2 hours')))
                ->where('created_at', '<=', date('Y-m-d H:i:s', strtotime($log->created_at . ' +2 hours')))
                ->count(),
            'previous_action' => AuditLog::where('user_id', $log->user_id)
                ->where('id', '<', $log->id)
                ->orderBy('id', 'desc')
                ->value('action'),
            'next_action' => AuditLog::where('user_id', $log->user_id)
                ->where('id', '>', $log->id)
                ->orderBy('id', 'asc')
                ->value('action')
        ];
    }

    /**
     * Erkennt verdächtige Aktivitäten
     */
    private function detectSuspiciousActivities(string $since): array
    {
        $suspicious = [];
        
        // Mehrfache fehlgeschlagene Logins
        $failedLogins = AuditLog::where('created_at', '>=', $since)
            ->where('action', 'login_failed')
            ->groupBy('ip_address')
            ->selectRaw('ip_address, COUNT(*) as count')
            ->having('count', '>=', 5)
            ->get();
            
        foreach ($failedLogins as $ip) {
            $suspicious[] = [
                'type' => 'Multiple Failed Logins',
                'description' => "IP {$ip->ip_address}: {$ip->count} fehlgeschlagene Login-Versuche",
                'severity' => 'high',
                'ip' => $ip->ip_address,
                'count' => $ip->count
            ];
        }
        
        // Ungewöhnliche Aktivitätszeiten
        $nightActivity = AuditLog::where('created_at', '>=', $since)
            ->whereRaw('HOUR(created_at) BETWEEN 0 AND 5')
            ->groupBy('user_id')
            ->selectRaw('user_id, COUNT(*) as count')
            ->having('count', '>=', 10)
            ->with(['user:id,name,email'])
            ->get();
            
        foreach ($nightActivity as $activity) {
            $suspicious[] = [
                'type' => 'Unusual Activity Hours',
                'description' => "Benutzer {$activity->user->name}: {$activity->count} Aktionen zwischen 0-5 Uhr",
                'severity' => 'medium',
                'user' => $activity->user,
                'count' => $activity->count
            ];
        }
        
        return $suspicious;
    }

    /**
     * Berechnet Benutzer-Risiko-Score
     */
    private function calculateUserRiskScore(int $userId): int
    {
        $recentLogs = AuditLog::where('user_id', $userId)
            ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-7 days')))
            ->get();
            
        $score = 0;
        foreach ($recentLogs as $log) {
            $riskLevel = $this->calculateRiskLevel($log->action);
            switch ($riskLevel) {
                case 'critical':
                    $score += 10;
                    break;
                case 'high':
                    $score += 5;
                    break;
                case 'medium':
                    $score += 2;
                    break;
                case 'low':
                    $score += 1;
                    break;
            }
        }
        
        return min($score, 100);
    }

    /**
     * Berechnet IP-Risiko-Score
     */
    private function calculateIpRiskScore(string $ip): int
    {
        $score = 0;
        
        // Anzahl verschiedener Benutzer
        $userCount = AuditLog::where('ip_address', $ip)
            ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-24 hours')))
            ->distinct('user_id')
            ->count('user_id');
            
        if ($userCount > 5) $score += 20;
        elseif ($userCount > 3) $score += 10;
        
        // Fehlgeschlagene Login-Versuche
        $failedLogins = AuditLog::where('ip_address', $ip)
            ->where('action', 'login_failed')
            ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-24 hours')))
            ->count();
            
        $score += min($failedLogins * 5, 50);
        
        return min($score, 100);
    }

    /**
     * Holt Action Display Name
     */
    private function getActionDisplayName(string $action): string
    {
        $displayNames = [
            'user_login' => 'Benutzer-Login',
            'user_logout' => 'Benutzer-Logout',
            'user_created' => 'Benutzer erstellt',
            'user_updated' => 'Benutzer aktualisiert',
            'user_deleted' => 'Benutzer gelöscht',
            'password_changed' => 'Passwort geändert',
            'password_reset' => 'Passwort zurückgesetzt',
            'login_failed' => 'Login fehlgeschlagen',
            'contract_created' => 'Vertrag erstellt',
            'document_uploaded' => 'Dokument hochgeladen',
            'credential_accessed' => 'Zugangsdaten aufgerufen'
        ];
        
        return $displayNames[$action] ?? ucfirst(str_replace('_', ' ', $action));
    }

    /**
     * Exportiert als CSV
     */
    private function exportToCsv($auditLogs, string $dateFrom, string $dateTo): void
    {
        $filename = "audit_logs_{$dateFrom}_to_{$dateTo}.csv";
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // CSV Header
        fputcsv($output, [
            'ID', 'Datum/Zeit', 'Benutzer', 'E-Mail', 'Aktion', 'Ressource', 'IP-Adresse', 'User-Agent', 'Details'
        ]);
        
        // Daten
        foreach ($auditLogs as $log) {
            fputcsv($output, [
                $log->id,
                $log->created_at,
                $log->user->name ?? 'System',
                $log->user->email ?? '',
                $log->action,
                $log->resource ?? '',
                $log->ip_address ?? '',
                $log->user_agent ?? '',
                json_encode($log->details, JSON_UNESCAPED_UNICODE)
            ]);
        }
        
        fclose($output);
    }

    /**
     * Exportiert als JSON
     */
    private function exportToJson($auditLogs, string $dateFrom, string $dateTo): void
    {
        $filename = "audit_logs_{$dateFrom}_to_{$dateTo}.json";
        
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $data = [
            'export_info' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'exported_at' => date('Y-m-d H:i:s'),
                'exported_by' => Auth::user()->email,
                'total_records' => $auditLogs->count()
            ],
            'audit_logs' => $auditLogs->toArray()
        ];
        
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}