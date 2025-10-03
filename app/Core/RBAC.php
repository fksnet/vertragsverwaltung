<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Role-Based Access Control mit regex-basierten Filtern
 * 
 * @package App\Core
 * @author GenSpark AI Developer
 */
class RBAC
{
    private static array $policies = [];
    
    /**
     * Berechtigung prüfen
     */
    public static function can(string $action, string $resource, ?array $context = null): bool
    {
        $user = Auth::user();
        
        if (!$user) {
            return false;
        }
        
        // Super-Admin hat immer alle Rechte
        if ($user->isSuperAdmin()) {
            return true;
        }
        
        $policy = self::getPolicy($user->rolle_id, $resource);
        
        if (!$policy) {
            return false; // Kein Policy -> Kein Zugriff
        }
        
        // Basis-Berechtigungen prüfen
        $allowed = match($action) {
            'read', 'view', 'show', 'index' => $policy['can_read'],
            'create', 'store' => $policy['can_create'],
            'update', 'edit' => $policy['can_update'],
            'delete', 'destroy' => $policy['can_delete'],
            default => false
        };
        
        if (!$allowed) {
            return false;
        }
        
        // Readonly-Check
        if ($policy['readonly'] && in_array($action, ['create', 'store', 'update', 'edit', 'delete', 'destroy'])) {
            return false;
        }
        
        // Regex-Filter prüfen
        if ($context && !empty($policy['field_value_regex_json'])) {
            return self::checkRegexFilters($policy['field_value_regex_json'], $context);
        }
        
        return true;
    }
    
    /**
     * Daten nach RBAC-Regeln filtern
     */
    public static function filterQuery(string $resource, $query = null, ?array $context = null)
    {
        $user = Auth::user();
        
        if (!$user) {
            return null; // Kein User -> Kein Zugriff
        }
        
        // Super-Admin sieht alles
        if ($user->isSuperAdmin()) {
            return $query ?? Database::table(self::getTableName($resource));
        }
        
        $policy = self::getPolicy($user->rolle_id, $resource);
        
        if (!$policy || !$policy['can_read']) {
            return null; // Kein Leserecht
        }
        
        // Query-Objekt initialisieren falls nicht übergeben
        if ($query === null) {
            $query = Database::table(self::getTableName($resource));
        }
        
        // Regex-Filter anwenden
        if (!empty($policy['field_value_regex_json'])) {
            $query = self::applyRegexFiltersToQuery($query, $policy['field_value_regex_json']);
        }
        
        // Freund-Spezial-Filter für Verträge
        if ($resource === 'vertrag' && $user->rolle_name === 'freund') {
            $query = self::applyFreundFilter($query, $user->id);
        }
        
        return $query;
    }
    
    /**
     * RBAC-Middleware
     */
    public static function middleware($route, $next)
    {
        // Nur für authentifizierte Benutzer
        if (!Auth::check()) {
            return $next(); // Auth-Middleware wird das handhaben
        }
        
        $user = Auth::user();
        $path = $route->getPath();
        
        // Admin-Bereich -> Administrator-Rolle erforderlich
        if (str_starts_with($path, '/admin') && !$user->isAdmin()) {
            if (self::isAjaxRequest()) {
                json_response(['error' => 'Access denied'], 403);
                return;
            }
            
            flash('error', 'Zugriff verweigert: Unzureichende Berechtigung.');
            redirect('/dashboard');
            return;
        }
        
        return $next();
    }
    
    /**
     * Policy für Rolle und Ressource abrufen
     */
    private static function getPolicy(int $roleId, string $resource): ?array
    {
        if (empty(self::$policies)) {
            self::loadPolicies();
        }
        
        return self::$policies[$roleId][$resource] ?? null;
    }
    
    /**
     * Policies aus Datenbank laden
     */
    private static function loadPolicies(): void
    {
        $policies = Database::table('rollen_objekt_policies as rop')
            ->select(['rop.*'])
            ->get();
        
        foreach ($policies as $policy) {
            $fieldRegex = $policy['field_value_regex_json'] ? 
                json_decode($policy['field_value_regex_json'], true) : [];
            
            self::$policies[$policy['rolle_id']][$policy['objekt']] = [
                'can_read' => (bool) $policy['can_read'],
                'can_create' => (bool) $policy['can_create'],
                'can_update' => (bool) $policy['can_update'],
                'can_delete' => (bool) $policy['can_delete'],
                'readonly' => (bool) $policy['readonly'],
                'field_value_regex_json' => $fieldRegex
            ];
        }
    }
    
    /**
     * Regex-Filter auf Kontext anwenden
     */
    private static function checkRegexFilters(array $regexFilters, array $context): bool
    {
        foreach ($regexFilters as $field => $pattern) {
            $value = $context[$field] ?? null;
            
            if ($value !== null && !preg_match("/$pattern/", (string) $value)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Regex-Filter auf Query anwenden
     */
    private static function applyRegexFiltersToQuery($query, array $regexFilters)
    {
        foreach ($regexFilters as $field => $pattern) {
            // MYSQL REGEXP für Datenbank-Filter
            $query = $query->where(function($query) use ($field, $pattern) {
                $query->whereRaw("$field REGEXP ?", [$pattern]);
            });
        }
        
        return $query;
    }
    
    /**
     * Freund-Filter für Verträge anwenden
     */
    private static function applyFreundFilter($query, int $userId)
    {
        return $query->join('freigaben as f', 'vertraege.id', '=', 'f.vertrag_id')
            ->where('f.freund_user_id', $userId);
    }
    
    /**
     * Tabellennamen aus Ressource ableiten
     */
    private static function getTableName(string $resource): string
    {
        return match($resource) {
            'korrespondent' => 'korrespondenten',
            'vertrag' => 'vertraege',
            'zugangsdaten' => 'zugangsdaten',
            'dokument' => 'dokumente',
            'benutzer' => 'benutzer',
            'rolle' => 'rollen',
            default => $resource
        };
    }
    
    /**
     * Standard-Policies für neue Rolle erstellen
     */
    public static function createDefaultPolicies(int $roleId, string $roleName): void
    {
        $policies = match($roleName) {
            'administrator' => [
                ['objekt' => 'korrespondent', 'can_read' => 1, 'can_create' => 1, 'can_update' => 1, 'can_delete' => 1, 'readonly' => 0],
                ['objekt' => 'vertrag', 'can_read' => 1, 'can_create' => 1, 'can_update' => 1, 'can_delete' => 1, 'readonly' => 0],
                ['objekt' => 'zugangsdaten', 'can_read' => 1, 'can_create' => 1, 'can_update' => 1, 'can_delete' => 1, 'readonly' => 0],
                ['objekt' => 'dokument', 'can_read' => 1, 'can_create' => 1, 'can_update' => 1, 'can_delete' => 1, 'readonly' => 0],
                ['objekt' => 'benutzer', 'can_read' => 1, 'can_create' => 1, 'can_update' => 1, 'can_delete' => 1, 'readonly' => 0],
                ['objekt' => 'rolle', 'can_read' => 1, 'can_create' => 1, 'can_update' => 1, 'can_delete' => 1, 'readonly' => 0],
            ],
            'benutzer' => [
                ['objekt' => 'korrespondent', 'can_read' => 1, 'can_create' => 1, 'can_update' => 1, 'can_delete' => 1, 'readonly' => 0],
                ['objekt' => 'vertrag', 'can_read' => 1, 'can_create' => 1, 'can_update' => 1, 'can_delete' => 1, 'readonly' => 0],
                ['objekt' => 'zugangsdaten', 'can_read' => 1, 'can_create' => 1, 'can_update' => 1, 'can_delete' => 1, 'readonly' => 0],
                ['objekt' => 'dokument', 'can_read' => 1, 'can_create' => 1, 'can_update' => 1, 'can_delete' => 1, 'readonly' => 0],
                ['objekt' => 'benutzer', 'can_read' => 0, 'can_create' => 0, 'can_update' => 0, 'can_delete' => 0, 'readonly' => 1],
                ['objekt' => 'rolle', 'can_read' => 0, 'can_create' => 0, 'can_update' => 0, 'can_delete' => 0, 'readonly' => 1],
            ],
            'freund' => [
                ['objekt' => 'korrespondent', 'can_read' => 1, 'can_create' => 0, 'can_update' => 0, 'can_delete' => 0, 'readonly' => 1],
                ['objekt' => 'vertrag', 'can_read' => 1, 'can_create' => 0, 'can_update' => 0, 'can_delete' => 0, 'readonly' => 1],
                ['objekt' => 'zugangsdaten', 'can_read' => 0, 'can_create' => 0, 'can_update' => 0, 'can_delete' => 0, 'readonly' => 1],
                ['objekt' => 'dokument', 'can_read' => 1, 'can_create' => 0, 'can_update' => 0, 'can_delete' => 0, 'readonly' => 1],
                ['objekt' => 'benutzer', 'can_read' => 0, 'can_create' => 0, 'can_update' => 0, 'can_delete' => 0, 'readonly' => 1],
                ['objekt' => 'rolle', 'can_read' => 0, 'can_create' => 0, 'can_update' => 0, 'can_delete' => 0, 'readonly' => 1],
            ],
            default => []
        };
        
        foreach ($policies as $policy) {
            Database::table('rollen_objekt_policies')->insert([
                'rolle_id' => $roleId,
                'objekt' => $policy['objekt'],
                'can_read' => $policy['can_read'],
                'can_create' => $policy['can_create'],
                'can_update' => $policy['can_update'],
                'can_delete' => $policy['can_delete'],
                'readonly' => $policy['readonly'],
                'field_value_regex_json' => null,
            ]);
        }
        
        // Policies-Cache löschen
        self::$policies = [];
    }
    
    /**
     * AJAX-Request erkennen
     */
    private static function isAjaxRequest(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Admin-Check mit Berechtigung für spezifische Aktion
     */
    public static function requireAdmin(string $action = 'admin'): void
    {
        $user = Auth::user();
        
        if (!$user || !$user->isAdmin()) {
            if (self::isAjaxRequest()) {
                json_response(['error' => 'Admin access required'], 403);
                exit;
            }
            
            flash('error', 'Admin-Berechtigung erforderlich.');
            redirect('/dashboard');
            exit;
        }
    }
    
    /**
     * Berechtigungs-Check mit Exception
     */
    public static function authorize(string $action, string $resource, ?array $context = null): void
    {
        if (!self::can($action, $resource, $context)) {
            if (self::isAjaxRequest()) {
                json_response(['error' => "Access denied for $action on $resource"], 403);
                exit;
            }
            
            flash('error', 'Zugriff verweigert: Unzureichende Berechtigung.');
            redirect('/dashboard');
            exit;
        }
    }
}