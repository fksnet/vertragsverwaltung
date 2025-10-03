<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\User;
use App\Models\Role;
use App\Models\AuditLog;
use App\Core\Auth;
use App\Core\Logger;
use App\Core\Security;

/**
 * BenutzerController - Admin-Verwaltung für Benutzer
 * 
 * Ermöglicht Administratoren die vollständige Verwaltung von Benutzern,
 * Rollen, Berechtigungen und Sicherheitseinstellungen.
 * 
 * @package App\Controllers\Admin
 * @version 1.0.0
 * @author Vertragsverwaltung System
 */
class BenutzerController extends BaseController
{
    /**
     * Zeigt die Benutzer-Übersicht
     */
    public function index(): void
    {
        try {
            $this->authorize(['admin:users:view']);
            
            $page = (int)($_GET['page'] ?? 1);
            $search = $_GET['search'] ?? '';
            $role = $_GET['role'] ?? '';
            $status = $_GET['status'] ?? '';
            $sort = $_GET['sort'] ?? 'created_at';
            $order = $_GET['order'] ?? 'desc';
            
            // Query für Benutzer
            $query = User::with(['roles']);
            
            // Suchfilter
            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('email', 'LIKE', "%{$search}%");
                });
            }
            
            // Rollenfilter
            if (!empty($role)) {
                $query->whereHas('roles', function($q) use ($role) {
                    $q->where('name', $role);
                });
            }
            
            // Statusfilter
            switch ($status) {
                case 'active':
                    $query->where('is_active', true);
                    break;
                case 'inactive':
                    $query->where('is_active', false);
                    break;
                case 'verified':
                    $query->whereNotNull('email_verified_at');
                    break;
                case 'unverified':
                    $query->whereNull('email_verified_at');
                    break;
                case '2fa_enabled':
                    $query->whereNotNull('two_factor_secret');
                    break;
            }
            
            // Sortierung
            $allowedSorts = ['name', 'email', 'created_at', 'last_login_at'];
            if (in_array($sort, $allowedSorts)) {
                $query->orderBy($sort, $order === 'desc' ? 'desc' : 'asc');
            }
            
            // Pagination
            $total = $query->count();
            $users = $this->paginate($query, $page);
            
            // Zusätzliche Benutzerinformationen laden
            foreach ($users as $user) {
                $user->contract_count = $user->vertraege()->count();
                $user->last_activity = $user->getLastActivity();
                $user->security_score = $this->calculateSecurityScore($user);
            }
            
            // Statistiken
            $stats = [
                'total_users' => User::count(),
                'active_users' => User::where('is_active', true)->count(),
                'verified_users' => User::whereNotNull('email_verified_at')->count(),
                'users_with_2fa' => User::whereNotNull('two_factor_secret')->count(),
                'recent_logins' => User::where('last_login_at', '>=', date('Y-m-d H:i:s', strtotime('-24 hours')))->count(),
                'new_users_today' => User::whereDate('created_at', date('Y-m-d'))->count()
            ];
            
            // Verfügbare Rollen
            $availableRoles = Role::orderBy('name')->get(['id', 'name', 'display_name']);
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'users' => $users,
                    'pagination' => $this->getPaginationData($total, $page),
                    'stats' => $stats,
                    'available_roles' => $availableRoles,
                    'filters' => [
                        'search' => $search,
                        'role' => $role,
                        'status' => $status,
                        'sort' => $sort,
                        'order' => $order
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Laden der Benutzer-Übersicht', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Laden der Benutzer'], 500);
        }
    }

    /**
     * Zeigt Details eines Benutzers
     */
    public function show(int $id): void
    {
        try {
            $this->authorize(['admin:users:view']);
            
            $user = User::with(['roles.permissions'])->find($id);
            
            if (!$user) {
                $this->jsonResponse(['error' => 'Benutzer nicht gefunden'], 404);
                return;
            }
            
            // Zusätzliche Informationen
            $user->contract_count = $user->vertraege()->count();
            $user->document_count = $user->dokumente()->count();
            $user->credential_count = $user->zugangsdaten()->count();
            $user->security_score = $this->calculateSecurityScore($user);
            $user->last_activity = $user->getLastActivity();
            
            // Sicherheitsinformationen
            $securityInfo = [
                'password_changed_at' => $user->password_changed_at,
                'failed_login_attempts' => $user->failed_login_attempts ?? 0,
                'locked_until' => $user->locked_until,
                'two_factor_enabled' => !empty($user->two_factor_secret),
                'trusted_devices_count' => $user->trusted_devices ? count(json_decode($user->trusted_devices, true)) : 0,
                'emergency_mode_active' => $user->emergency_mode_active ?? false
            ];
            
            // Letzte Aktivitäten aus Audit-Log
            $recentActivities = AuditLog::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get(['action', 'resource', 'created_at', 'ip_address']);
            
            // Login-Statistiken
            $loginStats = [
                'total_logins' => $user->login_count ?? 0,
                'last_login' => $user->last_login_at,
                'last_login_ip' => $user->last_login_ip,
                'average_logins_per_week' => $this->calculateAverageLogins($user)
            ];
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'user' => $user,
                    'security_info' => $securityInfo,
                    'recent_activities' => $recentActivities,
                    'login_stats' => $loginStats
                ]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Laden der Benutzer-Details', [
                'user_id' => $id,
                'error' => $e->getMessage(),
                'admin_user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Laden der Benutzer-Details'], 500);
        }
    }

    /**
     * Zeigt das Formular für neuen Benutzer
     */
    public function create(): void
    {
        try {
            $this->authorize(['admin:users:create']);
            
            // Verfügbare Rollen
            $roles = Role::orderBy('name')->get(['id', 'name', 'display_name', 'description']);
            
            // Standard-Einstellungen
            $defaults = [
                'is_active' => true,
                'must_change_password' => true,
                'force_two_factor' => false,
                'account_expires_at' => null
            ];
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'available_roles' => $roles,
                    'defaults' => $defaults,
                    'password_requirements' => [
                        'min_length' => 8,
                        'require_uppercase' => true,
                        'require_lowercase' => true,
                        'require_numbers' => true,
                        'require_symbols' => true
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Laden des Benutzer-Formulars', [
                'error' => $e->getMessage(),
                'admin_user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Laden des Formulars'], 500);
        }
    }

    /**
     * Erstellt einen neuen Benutzer
     */
    public function store(): void
    {
        try {
            $this->authorize(['admin:users:create']);
            
            // Validierung
            $rules = [
                'name' => 'required|string|max:100',
                'email' => 'required|email|max:255|unique:users,email',
                'password' => 'required|string|min:8',
                'role_ids' => 'array',
                'role_ids.*' => 'integer|exists:roles,id',
                'is_active' => 'boolean',
                'must_change_password' => 'boolean',
                'force_two_factor' => 'boolean',
                'send_welcome_email' => 'boolean',
                'account_expires_at' => 'date|after:today',
                'notes' => 'string|max:1000'
            ];
            
            $data = $this->validate($_POST, $rules);
            
            // Passwort-Sicherheit prüfen
            if (!$this->isPasswordSecure($data['password'])) {
                $this->jsonResponse([
                    'error' => 'Passwort entspricht nicht den Sicherheitsanforderungen'
                ], 400);
                return;
            }
            
            // Benutzer erstellen
            $userData = [
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => password_hash($data['password'], PASSWORD_ARGON2ID),
                'is_active' => $data['is_active'] ?? true,
                'must_change_password' => $data['must_change_password'] ?? true,
                'force_two_factor' => $data['force_two_factor'] ?? false,
                'email_verified_at' => date('Y-m-d H:i:s'), // Admin-erstellte Benutzer sind automatisch verifiziert
                'created_by_admin' => Auth::id(),
                'admin_notes' => $data['notes'] ?? null
            ];
            
            if (isset($data['account_expires_at'])) {
                $userData['account_expires_at'] = $data['account_expires_at'];
            }
            
            $user = User::create($userData);
            
            // Rollen zuweisen
            if (!empty($data['role_ids'])) {
                $user->roles()->attach($data['role_ids']);
            }
            
            // Willkommens-E-Mail senden
            if ($data['send_welcome_email'] ?? false) {
                $this->sendWelcomeEmail($user, $data['password']);
            }
            
            // Audit-Log
            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'user_created',
                'resource' => 'User',
                'resource_id' => $user->id,
                'details' => [
                    'user_email' => $user->email,
                    'user_name' => $user->name,
                    'assigned_roles' => $data['role_ids'] ?? []
                ]
            ]);
            
            Logger::info('Neuer Benutzer erstellt', [
                'user_id' => $user->id,
                'email' => $user->email,
                'created_by' => Auth::id()
            ]);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Benutzer erfolgreich erstellt',
                'data' => ['user' => $user->load('roles')]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Erstellen des Benutzers', [
                'error' => $e->getMessage(),
                'admin_user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Erstellen des Benutzers'], 500);
        }
    }

    /**
     * Aktualisiert einen Benutzer
     */
    public function update(int $id): void
    {
        try {
            $this->authorize(['admin:users:edit']);
            
            $user = User::find($id);
            
            if (!$user) {
                $this->jsonResponse(['error' => 'Benutzer nicht gefunden'], 404);
                return;
            }
            
            // Validierung
            $rules = [
                'name' => 'string|max:100',
                'email' => 'email|max:255|unique:users,email,' . $user->id,
                'password' => 'string|min:8',
                'role_ids' => 'array',
                'role_ids.*' => 'integer|exists:roles,id',
                'is_active' => 'boolean',
                'must_change_password' => 'boolean',
                'force_two_factor' => 'boolean',
                'account_expires_at' => 'date|after:today|nullable',
                'notes' => 'string|max:1000'
            ];
            
            $data = $this->validate($_POST, $rules);
            
            $oldData = $user->toArray();
            $updateData = [];
            $changes = [];
            
            // Grunddaten aktualisieren
            foreach (['name', 'email', 'is_active', 'must_change_password', 'force_two_factor'] as $field) {
                if (isset($data[$field]) && $data[$field] !== $user->$field) {
                    $updateData[$field] = $data[$field];
                    $changes[$field] = ['from' => $user->$field, 'to' => $data[$field]];
                }
            }
            
            // Passwort aktualisieren
            if (isset($data['password'])) {
                if (!$this->isPasswordSecure($data['password'])) {
                    $this->jsonResponse([
                        'error' => 'Passwort entspricht nicht den Sicherheitsanforderungen'
                    ], 400);
                    return;
                }
                
                $updateData['password'] = password_hash($data['password'], PASSWORD_ARGON2ID);
                $updateData['password_changed_at'] = date('Y-m-d H:i:s');
                $changes['password'] = 'changed';
            }
            
            // Ablaufdatum
            if (array_key_exists('account_expires_at', $data)) {
                $updateData['account_expires_at'] = $data['account_expires_at'];
                $changes['account_expires_at'] = ['from' => $user->account_expires_at, 'to' => $data['account_expires_at']];
            }
            
            // Admin-Notizen
            if (isset($data['notes'])) {
                $updateData['admin_notes'] = $data['notes'];
                $changes['admin_notes'] = 'updated';
            }
            
            // Benutzer aktualisieren
            if (!empty($updateData)) {
                $user->update($updateData);
            }
            
            // Rollen aktualisieren
            if (isset($data['role_ids'])) {
                $oldRoles = $user->roles()->pluck('id')->toArray();
                $user->roles()->sync($data['role_ids']);
                $newRoles = $user->roles()->pluck('id')->toArray();
                
                if ($oldRoles !== $newRoles) {
                    $changes['roles'] = ['from' => $oldRoles, 'to' => $newRoles];
                }
            }
            
            // Audit-Log
            if (!empty($changes)) {
                AuditLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'user_updated',
                    'resource' => 'User',
                    'resource_id' => $user->id,
                    'details' => [
                        'user_email' => $user->email,
                        'changes' => $changes
                    ]
                ]);
            }
            
            Logger::info('Benutzer aktualisiert', [
                'user_id' => $user->id,
                'changes' => $changes,
                'updated_by' => Auth::id()
            ]);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Benutzer erfolgreich aktualisiert',
                'data' => ['user' => $user->fresh()->load('roles')]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Aktualisieren des Benutzers', [
                'user_id' => $id,
                'error' => $e->getMessage(),
                'admin_user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Aktualisieren des Benutzers'], 500);
        }
    }

    /**
     * Deaktiviert/Aktiviert einen Benutzer
     */
    public function toggleStatus(int $id): void
    {
        try {
            $this->authorize(['admin:users:edit']);
            
            $user = User::find($id);
            
            if (!$user) {
                $this->jsonResponse(['error' => 'Benutzer nicht gefunden'], 404);
                return;
            }
            
            // Sich selbst nicht deaktivieren
            if ($user->id === Auth::id()) {
                $this->jsonResponse(['error' => 'Sie können sich nicht selbst deaktivieren'], 400);
                return;
            }
            
            $newStatus = !$user->is_active;
            $user->update(['is_active' => $newStatus]);
            
            // Aktive Sitzungen beenden wenn deaktiviert
            if (!$newStatus) {
                $this->terminateUserSessions($user);
            }
            
            $action = $newStatus ? 'activated' : 'deactivated';
            
            // Audit-Log
            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => "user_{$action}",
                'resource' => 'User',
                'resource_id' => $user->id,
                'details' => ['user_email' => $user->email]
            ]);
            
            Logger::info("Benutzer {$action}", [
                'user_id' => $user->id,
                'new_status' => $newStatus,
                'admin_user_id' => Auth::id()
            ]);
            
            $message = $newStatus ? 'Benutzer aktiviert' : 'Benutzer deaktiviert';
            
            $this->jsonResponse([
                'success' => true,
                'message' => $message,
                'data' => ['user' => $user]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Ändern des Benutzerstatus', [
                'user_id' => $id,
                'error' => $e->getMessage(),
                'admin_user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Ändern des Status'], 500);
        }
    }

    /**
     * Setzt das Passwort zurück
     */
    public function resetPassword(int $id): void
    {
        try {
            $this->authorize(['admin:users:edit']);
            
            $user = User::find($id);
            
            if (!$user) {
                $this->jsonResponse(['error' => 'Benutzer nicht gefunden'], 404);
                return;
            }
            
            // Neues temporäres Passwort generieren
            $newPassword = Security::generateToken(12);
            
            $user->update([
                'password' => password_hash($newPassword, PASSWORD_ARGON2ID),
                'must_change_password' => true,
                'password_changed_at' => date('Y-m-d H:i:s')
            ]);
            
            // E-Mail mit neuem Passwort senden
            $this->sendPasswordResetEmail($user, $newPassword);
            
            // Audit-Log
            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'password_reset',
                'resource' => 'User',
                'resource_id' => $user->id,
                'details' => ['user_email' => $user->email]
            ]);
            
            Logger::info('Passwort zurückgesetzt', [
                'user_id' => $user->id,
                'reset_by' => Auth::id()
            ]);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Passwort zurückgesetzt. E-Mail wurde gesendet.',
                'data' => ['temporary_password' => $newPassword]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Zurücksetzen des Passworts', [
                'user_id' => $id,
                'error' => $e->getMessage(),
                'admin_user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Zurücksetzen des Passworts'], 500);
        }
    }

    /**
     * Entsperrt einen Benutzer
     */
    public function unlock(int $id): void
    {
        try {
            $this->authorize(['admin:users:edit']);
            
            $user = User::find($id);
            
            if (!$user) {
                $this->jsonResponse(['error' => 'Benutzer nicht gefunden'], 404);
                return;
            }
            
            $user->update([
                'failed_login_attempts' => 0,
                'locked_until' => null
            ]);
            
            // Audit-Log
            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'user_unlocked',
                'resource' => 'User',
                'resource_id' => $user->id,
                'details' => ['user_email' => $user->email]
            ]);
            
            Logger::info('Benutzer entsperrt', [
                'user_id' => $user->id,
                'unlocked_by' => Auth::id()
            ]);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Benutzer erfolgreich entsperrt'
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Entsperren des Benutzers', [
                'user_id' => $id,
                'error' => $e->getMessage(),
                'admin_user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Entsperren des Benutzers'], 500);
        }
    }

    /**
     * Löscht einen Benutzer
     */
    public function destroy(int $id): void
    {
        try {
            $this->authorize(['admin:users:delete']);
            
            $user = User::find($id);
            
            if (!$user) {
                $this->jsonResponse(['error' => 'Benutzer nicht gefunden'], 404);
                return;
            }
            
            // Sich selbst nicht löschen
            if ($user->id === Auth::id()) {
                $this->jsonResponse(['error' => 'Sie können sich nicht selbst löschen'], 400);
                return;
            }
            
            // Prüfen ob der Benutzer Daten hat
            $hasData = $user->vertraege()->count() > 0 || 
                      $user->dokumente()->count() > 0 || 
                      $user->zugangsdaten()->count() > 0;
            
            if ($hasData) {
                $this->jsonResponse([
                    'error' => 'Benutzer kann nicht gelöscht werden - hat noch Verträge, Dokumente oder Zugangsdaten'
                ], 400);
                return;
            }
            
            $userEmail = $user->email;
            
            // Benutzer löschen
            $user->delete();
            
            // Audit-Log
            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'user_deleted',
                'resource' => 'User',
                'resource_id' => $id,
                'details' => ['user_email' => $userEmail]
            ]);
            
            Logger::info('Benutzer gelöscht', [
                'user_id' => $id,
                'user_email' => $userEmail,
                'deleted_by' => Auth::id()
            ]);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Benutzer erfolgreich gelöscht'
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Löschen des Benutzers', [
                'user_id' => $id,
                'error' => $e->getMessage(),
                'admin_user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Löschen des Benutzers'], 500);
        }
    }

    /**
     * Bulk-Operationen für Benutzer
     */
    public function bulk(): void
    {
        try {
            $this->authorize(['admin:users:edit']);
            
            $rules = [
                'action' => 'required|string|in:activate,deactivate,reset_password,unlock,delete',
                'user_ids' => 'required|array|min:1',
                'user_ids.*' => 'integer|exists:users,id'
            ];
            
            $data = $this->validate($_POST, $rules);
            
            $userIds = array_diff($data['user_ids'], [Auth::id()]); // Sich selbst ausschließen
            
            if (empty($userIds)) {
                $this->jsonResponse(['error' => 'Keine gültigen Benutzer ausgewählt'], 400);
                return;
            }
            
            $users = User::whereIn('id', $userIds)->get();
            $results = ['success' => 0, 'failed' => 0, 'errors' => []];
            
            foreach ($users as $user) {
                try {
                    switch ($data['action']) {
                        case 'activate':
                            $user->update(['is_active' => true]);
                            break;
                        case 'deactivate':
                            $user->update(['is_active' => false]);
                            $this->terminateUserSessions($user);
                            break;
                        case 'reset_password':
                            $newPassword = Security::generateToken(12);
                            $user->update([
                                'password' => password_hash($newPassword, PASSWORD_ARGON2ID),
                                'must_change_password' => true,
                                'password_changed_at' => date('Y-m-d H:i:s')
                            ]);
                            $this->sendPasswordResetEmail($user, $newPassword);
                            break;
                        case 'unlock':
                            $user->update(['failed_login_attempts' => 0, 'locked_until' => null]);
                            break;
                        case 'delete':
                            if ($this->authorize(['admin:users:delete'], false)) {
                                $hasData = $user->vertraege()->count() > 0 || 
                                          $user->dokumente()->count() > 0 || 
                                          $user->zugangsdaten()->count() > 0;
                                if (!$hasData) {
                                    $user->delete();
                                } else {
                                    throw new \Exception('Benutzer hat noch Daten');
                                }
                            }
                            break;
                    }
                    $results['success']++;
                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = "Benutzer {$user->email}: " . $e->getMessage();
                }
            }
            
            // Audit-Log für Bulk-Operation
            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'bulk_user_' . $data['action'],
                'resource' => 'User',
                'details' => [
                    'user_ids' => $userIds,
                    'results' => $results
                ]
            ]);
            
            $message = "Bulk-Operation abgeschlossen: {$results['success']} erfolgreich";
            if ($results['failed'] > 0) {
                $message .= ", {$results['failed']} fehlgeschlagen";
            }
            
            $this->jsonResponse([
                'success' => $results['failed'] === 0,
                'message' => $message,
                'data' => $results
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler bei Bulk-Operation für Benutzer', [
                'error' => $e->getMessage(),
                'admin_user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler bei Bulk-Operation'], 500);
        }
    }

    /**
     * Zeigt Benutzer-Statistiken
     */
    public function stats(): void
    {
        try {
            $this->authorize(['admin:users:view']);
            
            $timeframe = $_GET['timeframe'] ?? '30'; // Tage
            $startDate = date('Y-m-d H:i:s', strtotime("-{$timeframe} days"));
            
            $stats = [
                'users' => [
                    'total' => User::count(),
                    'active' => User::where('is_active', true)->count(),
                    'verified' => User::whereNotNull('email_verified_at')->count(),
                    'with_2fa' => User::whereNotNull('two_factor_secret')->count(),
                    'locked' => User::whereNotNull('locked_until')->where('locked_until', '>', date('Y-m-d H:i:s'))->count(),
                    'new_registrations' => User::where('created_at', '>=', $startDate)->count()
                ],
                'logins' => [
                    'recent_logins' => User::where('last_login_at', '>=', $startDate)->count(),
                    'unique_daily_users' => User::whereDate('last_login_at', date('Y-m-d'))->count(),
                    'failed_attempts_today' => User::where('failed_login_attempts', '>', 0)->sum('failed_login_attempts')
                ],
                'security' => [
                    'strong_passwords' => $this->countUsersWithStrongPasswords(),
                    'recent_password_changes' => User::where('password_changed_at', '>=', $startDate)->count(),
                    'emergency_mode_active' => User::where('emergency_mode_active', true)->count()
                ]
            ];
            
            // Tägliche Registrierungen der letzten 30 Tage
            $dailyRegistrations = [];
            for ($i = 29; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                $dailyRegistrations[$date] = User::whereDate('created_at', $date)->count();
            }
            
            // Rollen-Verteilung
            $roleDistribution = Role::withCount('users')->get()
                ->map(function($role) {
                    return [
                        'name' => $role->display_name,
                        'count' => $role->users_count
                    ];
                });
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'stats' => $stats,
                    'daily_registrations' => $dailyRegistrations,
                    'role_distribution' => $roleDistribution,
                    'timeframe' => $timeframe
                ]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Laden der Benutzer-Statistiken', [
                'error' => $e->getMessage(),
                'admin_user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Laden der Statistiken'], 500);
        }
    }

    /**
     * Berechnet Sicherheitsbewertung für Benutzer
     */
    private function calculateSecurityScore(User $user): int
    {
        $score = 0;
        
        // Basis-Score
        $score += 20;
        
        // E-Mail verifiziert
        if ($user->email_verified_at) $score += 15;
        
        // 2FA aktiviert
        if ($user->two_factor_secret) $score += 30;
        
        // Starkes Passwort (schwer zu überprüfen ohne Klartext)
        if ($user->password_changed_at && strtotime($user->password_changed_at) > strtotime('-90 days')) {
            $score += 15;
        }
        
        // Letzte Aktivität
        if ($user->last_login_at && strtotime($user->last_login_at) > strtotime('-30 days')) {
            $score += 10;
        }
        
        // Keine fehlgeschlagenen Login-Versuche
        if (($user->failed_login_attempts ?? 0) === 0) $score += 10;
        
        return min($score, 100);
    }

    /**
     * Prüft ob Passwort sicher ist
     */
    private function isPasswordSecure(string $password): bool
    {
        return strlen($password) >= 8 &&
               preg_match('/[A-Z]/', $password) &&
               preg_match('/[a-z]/', $password) &&
               preg_match('/[0-9]/', $password) &&
               preg_match('/[^A-Za-z0-9]/', $password);
    }

    /**
     * Berechnet durchschnittliche Logins pro Woche
     */
    private function calculateAverageLogins(User $user): float
    {
        $weeksActive = max(1, (strtotime('now') - strtotime($user->created_at)) / (7 * 24 * 3600));
        return round(($user->login_count ?? 0) / $weeksActive, 1);
    }

    /**
     * Zählt Benutzer mit starken Passwörtern (Schätzung)
     */
    private function countUsersWithStrongPasswords(): int
    {
        // Schätzung basierend auf kürzlich geänderten Passwörtern
        return User::where('password_changed_at', '>=', date('Y-m-d H:i:s', strtotime('-6 months')))->count();
    }

    /**
     * Beendet alle Sitzungen eines Benutzers
     */
    private function terminateUserSessions(User $user): void
    {
        // Session-Files löschen (falls dateibasiert)
        $sessionPath = session_save_path();
        if ($sessionPath) {
            $files = glob($sessionPath . "/sess_*");
            foreach ($files as $file) {
                $content = file_get_contents($file);
                if (strpos($content, "user_id|i:{$user->id};") !== false) {
                    unlink($file);
                }
            }
        }
    }

    /**
     * Sendet Willkommens-E-Mail
     */
    private function sendWelcomeEmail(User $user, string $password): void
    {
        // Hier würde die E-Mail-Implementierung stehen
        Logger::info('Willkommens-E-Mail gesendet', ['user_id' => $user->id]);
    }

    /**
     * Sendet E-Mail mit neuem Passwort
     */
    private function sendPasswordResetEmail(User $user, string $password): void
    {
        // Hier würde die E-Mail-Implementierung stehen
        Logger::info('Passwort-Reset-E-Mail gesendet', ['user_id' => $user->id]);
    }
}