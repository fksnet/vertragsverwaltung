<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use App\Models\AuditLog;
use App\Core\Auth;
use App\Core\Logger;

/**
 * RollenController - Admin-Verwaltung für Rollen und Berechtigungen
 * 
 * Ermöglicht die vollständige Verwaltung des RBAC-Systems mit
 * Rollen, Berechtigungen und Zuweisungen.
 * 
 * @package App\Controllers\Admin
 * @version 1.0.0
 * @author Vertragsverwaltung System
 */
class RollenController extends BaseController
{
    /**
     * Zeigt die Rollen-Übersicht
     */
    public function index(): void
    {
        try {
            $this->authorize(['admin:roles:view']);
            
            $page = (int)($_GET['page'] ?? 1);
            $search = $_GET['search'] ?? '';
            $sort = $_GET['sort'] ?? 'name';
            $order = $_GET['order'] ?? 'asc';
            
            // Query für Rollen
            $query = Role::with(['permissions', 'users']);
            
            // Suchfilter
            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('display_name', 'LIKE', "%{$search}%")
                      ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }
            
            // Sortierung
            $allowedSorts = ['name', 'display_name', 'created_at'];
            if (in_array($sort, $allowedSorts)) {
                $query->orderBy($sort, $order === 'desc' ? 'desc' : 'asc');
            }
            
            // Pagination
            $total = $query->count();
            $roles = $this->paginate($query, $page);
            
            // Zusätzliche Informationen für jede Rolle
            foreach ($roles as $role) {
                $role->users_count = $role->users->count();
                $role->permissions_count = $role->permissions->count();
                $role->is_system_role = in_array($role->name, ['admin', 'user']);
                $role->can_edit = !$role->is_system_role || Auth::user()->hasRole('super_admin');
            }
            
            // Statistiken
            $stats = [
                'total_roles' => Role::count(),
                'system_roles' => Role::whereIn('name', ['admin', 'user', 'super_admin'])->count(),
                'custom_roles' => Role::whereNotIn('name', ['admin', 'user', 'super_admin'])->count(),
                'roles_with_users' => Role::has('users')->count(),
                'total_permissions' => Permission::count()
            ];
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'roles' => $roles,
                    'pagination' => $this->getPaginationData($total, $page),
                    'stats' => $stats,
                    'filters' => [
                        'search' => $search,
                        'sort' => $sort,
                        'order' => $order
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Laden der Rollen-Übersicht', [
                'error' => $e->getMessage(),
                'admin_user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Laden der Rollen'], 500);
        }
    }

    /**
     * Zeigt Details einer Rolle
     */
    public function show(int $id): void
    {
        try {
            $this->authorize(['admin:roles:view']);
            
            $role = Role::with(['permissions', 'users'])->find($id);
            
            if (!$role) {
                $this->jsonResponse(['error' => 'Rolle nicht gefunden'], 404);
                return;
            }
            
            // Zusätzliche Informationen
            $role->is_system_role = in_array($role->name, ['admin', 'user', 'super_admin']);
            $role->can_edit = !$role->is_system_role || Auth::user()->hasRole('super_admin');
            
            // Berechtigungen nach Kategorien gruppieren
            $permissionsByCategory = $role->permissions->groupBy('category');
            
            // Benutzer mit dieser Rolle
            $usersWithRole = $role->users()->select(['id', 'name', 'email', 'is_active', 'last_login_at'])
                ->orderBy('name')->get();
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'role' => $role,
                    'permissions_by_category' => $permissionsByCategory,
                    'users_with_role' => $usersWithRole
                ]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Laden der Rollen-Details', [
                'role_id' => $id,
                'error' => $e->getMessage(),
                'admin_user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Laden der Rollen-Details'], 500);
        }
    }

    /**
     * Zeigt das Formular für neue Rolle
     */
    public function create(): void
    {
        try {
            $this->authorize(['admin:roles:create']);
            
            // Verfügbare Berechtigungen nach Kategorien gruppiert
            $permissions = Permission::orderBy('category')->orderBy('name')->get();
            $permissionsByCategory = $permissions->groupBy('category');
            
            // Vorlagen für schnelle Erstellung
            $templates = [
                'viewer' => [
                    'name' => 'Betrachter',
                    'description' => 'Kann Daten einsehen, aber nicht ändern',
                    'suggested_permissions' => ['contracts:view', 'documents:view']
                ],
                'editor' => [
                    'name' => 'Bearbeiter',
                    'description' => 'Kann Daten einsehen und bearbeiten',
                    'suggested_permissions' => ['contracts:view', 'contracts:create', 'contracts:edit', 'documents:view', 'documents:upload']
                ],
                'manager' => [
                    'name' => 'Manager',
                    'description' => 'Vollzugriff auf eigene Bereiche',
                    'suggested_permissions' => ['contracts:*', 'documents:*', 'credentials:*']
                ]
            ];
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'permissions_by_category' => $permissionsByCategory,
                    'templates' => $templates,
                    'naming_conventions' => [
                        'format' => 'snake_case (z.B. content_manager)',
                        'display_name_format' => 'Titel Case (z.B. Content Manager)',
                        'reserved_names' => ['admin', 'user', 'super_admin', 'guest']
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Laden des Rollen-Formulars', [
                'error' => $e->getMessage(),
                'admin_user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Laden des Formulars'], 500);
        }
    }

    /**
     * Erstellt eine neue Rolle
     */
    public function store(): void
    {
        try {
            $this->authorize(['admin:roles:create']);
            
            // Validierung
            $rules = [
                'name' => 'required|string|max:50|regex:/^[a-z_]+$/|unique:roles,name',
                'display_name' => 'required|string|max:100',
                'description' => 'string|max:500',
                'permission_ids' => 'array',
                'permission_ids.*' => 'integer|exists:permissions,id',
                'color' => 'string|regex:/^#[0-9A-Fa-f]{6}$/',
                'is_default' => 'boolean'
            ];
            
            $data = $this->validate($_POST, $rules);
            
            // Reservierte Namen prüfen
            $reservedNames = ['admin', 'user', 'super_admin', 'guest', 'system', 'root'];
            if (in_array($data['name'], $reservedNames)) {
                $this->jsonResponse(['error' => 'Dieser Rollenname ist reserviert'], 400);
                return;
            }
            
            // Rolle erstellen
            $roleData = [
                'name' => $data['name'],
                'display_name' => $data['display_name'],
                'description' => $data['description'] ?? '',
                'color' => $data['color'] ?? '#6366f1',
                'is_default' => $data['is_default'] ?? false,
                'created_by' => Auth::id()
            ];
            
            // Wenn neue Standardrolle, alte deaktivieren
            if ($roleData['is_default']) {
                Role::where('is_default', true)->update(['is_default' => false]);
            }
            
            $role = Role::create($roleData);
            
            // Berechtigungen zuweisen
            if (!empty($data['permission_ids'])) {
                $role->permissions()->attach($data['permission_ids']);
            }
            
            // Audit-Log
            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'role_created',
                'resource' => 'Role',
                'resource_id' => $role->id,
                'details' => [
                    'role_name' => $role->name,
                    'permissions_assigned' => $data['permission_ids'] ?? []
                ]
            ]);
            
            Logger::info('Neue Rolle erstellt', [
                'role_id' => $role->id,
                'role_name' => $role->name,
                'created_by' => Auth::id()
            ]);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Rolle erfolgreich erstellt',
                'data' => ['role' => $role->load('permissions')]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Erstellen der Rolle', [
                'error' => $e->getMessage(),
                'admin_user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Erstellen der Rolle'], 500);
        }
    }

    /**
     * Aktualisiert eine Rolle
     */
    public function update(int $id): void
    {
        try {
            $this->authorize(['admin:roles:edit']);
            
            $role = Role::find($id);
            
            if (!$role) {
                $this->jsonResponse(['error' => 'Rolle nicht gefunden'], 404);
                return;
            }
            
            // System-Rollen nur von Super-Admin ändern
            $isSystemRole = in_array($role->name, ['admin', 'user', 'super_admin']);
            if ($isSystemRole && !Auth::user()->hasRole('super_admin')) {
                $this->jsonResponse(['error' => 'Keine Berechtigung zur Bearbeitung von System-Rollen'], 403);
                return;
            }
            
            // Validierung
            $rules = [
                'display_name' => 'string|max:100',
                'description' => 'string|max:500',
                'permission_ids' => 'array',
                'permission_ids.*' => 'integer|exists:permissions,id',
                'color' => 'string|regex:/^#[0-9A-Fa-f]{6}$/',
                'is_default' => 'boolean'
            ];
            
            // Name nur bei nicht-System-Rollen ändern
            if (!$isSystemRole) {
                $rules['name'] = 'string|max:50|regex:/^[a-z_]+$/|unique:roles,name,' . $role->id;
            }
            
            $data = $this->validate($_POST, $rules);
            
            $oldData = $role->toArray();
            $updateData = [];
            $changes = [];
            
            // Felder aktualisieren
            foreach (['name', 'display_name', 'description', 'color', 'is_default'] as $field) {
                if (isset($data[$field]) && $data[$field] !== $role->$field) {
                    // Name nur bei nicht-System-Rollen
                    if ($field === 'name' && $isSystemRole) continue;
                    
                    $updateData[$field] = $data[$field];
                    $changes[$field] = ['from' => $role->$field, 'to' => $data[$field]];
                }
            }
            
            // Wenn neue Standardrolle, alte deaktivieren
            if (isset($data['is_default']) && $data['is_default'] && !$role->is_default) {
                Role::where('id', '!=', $role->id)->update(['is_default' => false]);
            }
            
            // Rolle aktualisieren
            if (!empty($updateData)) {
                $role->update($updateData);
            }
            
            // Berechtigungen aktualisieren
            if (isset($data['permission_ids'])) {
                $oldPermissions = $role->permissions()->pluck('id')->toArray();
                $role->permissions()->sync($data['permission_ids']);
                $newPermissions = $role->permissions()->pluck('id')->toArray();
                
                if ($oldPermissions !== $newPermissions) {
                    $changes['permissions'] = [
                        'added' => array_diff($newPermissions, $oldPermissions),
                        'removed' => array_diff($oldPermissions, $newPermissions)
                    ];
                }
            }
            
            // Audit-Log
            if (!empty($changes)) {
                AuditLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'role_updated',
                    'resource' => 'Role',
                    'resource_id' => $role->id,
                    'details' => [
                        'role_name' => $role->name,
                        'changes' => $changes
                    ]
                ]);
            }
            
            Logger::info('Rolle aktualisiert', [
                'role_id' => $role->id,
                'changes' => $changes,
                'updated_by' => Auth::id()
            ]);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Rolle erfolgreich aktualisiert',
                'data' => ['role' => $role->fresh()->load('permissions')]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Aktualisieren der Rolle', [
                'role_id' => $id,
                'error' => $e->getMessage(),
                'admin_user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Aktualisieren der Rolle'], 500);
        }
    }

    /**
     * Löscht eine Rolle
     */
    public function destroy(int $id): void
    {
        try {
            $this->authorize(['admin:roles:delete']);
            
            $role = Role::find($id);
            
            if (!$role) {
                $this->jsonResponse(['error' => 'Rolle nicht gefunden'], 404);
                return;
            }
            
            // System-Rollen nicht löschen
            if (in_array($role->name, ['admin', 'user', 'super_admin'])) {
                $this->jsonResponse(['error' => 'System-Rollen können nicht gelöscht werden'], 400);
                return;
            }
            
            // Prüfen ob Benutzer diese Rolle haben
            $usersWithRole = $role->users()->count();
            if ($usersWithRole > 0) {
                $this->jsonResponse([
                    'error' => "Rolle kann nicht gelöscht werden - {$usersWithRole} Benutzer haben diese Rolle"
                ], 400);
                return;
            }
            
            $roleName = $role->name;
            
            // Rolle löschen
            $role->delete();
            
            // Audit-Log
            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'role_deleted',
                'resource' => 'Role',
                'resource_id' => $id,
                'details' => ['role_name' => $roleName]
            ]);
            
            Logger::info('Rolle gelöscht', [
                'role_id' => $id,
                'role_name' => $roleName,
                'deleted_by' => Auth::id()
            ]);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Rolle erfolgreich gelöscht'
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Löschen der Rolle', [
                'role_id' => $id,
                'error' => $e->getMessage(),
                'admin_user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Löschen der Rolle'], 500);
        }
    }

    /**
     * Zeigt alle verfügbaren Berechtigungen
     */
    public function permissions(): void
    {
        try {
            $this->authorize(['admin:roles:view']);
            
            $search = $_GET['search'] ?? '';
            $category = $_GET['category'] ?? '';
            
            $query = Permission::query();
            
            // Filter
            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('display_name', 'LIKE', "%{$search}%")
                      ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }
            
            if (!empty($category)) {
                $query->where('category', $category);
            }
            
            $permissions = $query->orderBy('category')->orderBy('name')->get();
            
            // Nach Kategorien gruppieren
            $permissionsByCategory = $permissions->groupBy('category');
            
            // Verfügbare Kategorien
            $categories = Permission::distinct('category')->pluck('category')->sort()->values();
            
            // Statistiken
            $stats = [
                'total_permissions' => Permission::count(),
                'categories_count' => $categories->count(),
                'most_assigned' => Permission::withCount('roles')
                    ->orderBy('roles_count', 'desc')
                    ->limit(5)
                    ->get(['name', 'display_name', 'roles_count'])
            ];
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'permissions' => $permissions,
                    'permissions_by_category' => $permissionsByCategory,
                    'categories' => $categories,
                    'stats' => $stats,
                    'filters' => [
                        'search' => $search,
                        'category' => $category
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Laden der Berechtigungen', [
                'error' => $e->getMessage(),
                'admin_user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Laden der Berechtigungen'], 500);
        }
    }

    /**
     * Weist Rollen an Benutzer zu
     */
    public function assignRoles(): void
    {
        try {
            $this->authorize(['admin:roles:assign']);
            
            $rules = [
                'user_ids' => 'required|array|min:1',
                'user_ids.*' => 'integer|exists:users,id',
                'role_ids' => 'required|array|min:1',
                'role_ids.*' => 'integer|exists:roles,id',
                'replace_existing' => 'boolean'
            ];
            
            $data = $this->validate($_POST, $rules);
            
            $users = User::whereIn('id', $data['user_ids'])->get();
            $roles = Role::whereIn('id', $data['role_ids'])->get();
            
            $results = ['success' => 0, 'failed' => 0, 'errors' => []];
            
            foreach ($users as $user) {
                try {
                    if ($data['replace_existing'] ?? false) {
                        // Alle Rollen ersetzen
                        $user->roles()->sync($data['role_ids']);
                    } else {
                        // Rollen hinzufügen
                        $existingRoles = $user->roles()->pluck('id')->toArray();
                        $newRoles = array_unique(array_merge($existingRoles, $data['role_ids']));
                        $user->roles()->sync($newRoles);
                    }
                    $results['success']++;
                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = "Benutzer {$user->email}: " . $e->getMessage();
                }
            }
            
            // Audit-Log
            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'roles_assigned',
                'resource' => 'User',
                'details' => [
                    'user_ids' => $data['user_ids'],
                    'role_ids' => $data['role_ids'],
                    'replace_existing' => $data['replace_existing'] ?? false,
                    'results' => $results
                ]
            ]);
            
            $message = "Rollenzuweisung abgeschlossen: {$results['success']} erfolgreich";
            if ($results['failed'] > 0) {
                $message .= ", {$results['failed']} fehlgeschlagen";
            }
            
            $this->jsonResponse([
                'success' => $results['failed'] === 0,
                'message' => $message,
                'data' => $results
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler bei der Rollenzuweisung', [
                'error' => $e->getMessage(),
                'admin_user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler bei der Rollenzuweisung'], 500);
        }
    }

    /**
     * Zeigt Rollen-Matrix (Benutzer vs. Rollen)
     */
    public function matrix(): void
    {
        try {
            $this->authorize(['admin:roles:view']);
            
            $users = User::with('roles')->where('is_active', true)->orderBy('name')->get();
            $roles = Role::orderBy('name')->get();
            
            // Matrix erstellen
            $matrix = [];
            foreach ($users as $user) {
                $userRoles = $user->roles->pluck('id')->toArray();
                $matrix[] = [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email
                    ],
                    'roles' => array_fill_keys($roles->pluck('id')->toArray(), false)
                ];
                
                // Benutzer-Rollen markieren
                foreach ($userRoles as $roleId) {
                    if (isset($matrix[count($matrix) - 1]['roles'][$roleId])) {
                        $matrix[count($matrix) - 1]['roles'][$roleId] = true;
                    }
                }
            }
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'matrix' => $matrix,
                    'roles' => $roles,
                    'total_users' => count($users),
                    'total_roles' => $roles->count()
                ]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Laden der Rollen-Matrix', [
                'error' => $e->getMessage(),
                'admin_user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Laden der Rollen-Matrix'], 500);
        }
    }

    /**
     * Synchronisiert Berechtigungen (für System-Updates)
     */
    public function syncPermissions(): void
    {
        try {
            $this->authorize(['admin:system:manage']);
            
            // Basis-Berechtigungen definieren
            $permissions = [
                // Verträge
                'contracts:view' => ['Verträge anzeigen', 'contracts'],
                'contracts:create' => ['Verträge erstellen', 'contracts'],
                'contracts:edit' => ['Verträge bearbeiten', 'contracts'],
                'contracts:delete' => ['Verträge löschen', 'contracts'],
                
                // Dokumente
                'documents:view' => ['Dokumente anzeigen', 'documents'],
                'documents:upload' => ['Dokumente hochladen', 'documents'],
                'documents:download' => ['Dokumente herunterladen', 'documents'],
                'documents:delete' => ['Dokumente löschen', 'documents'],
                
                // Zugangsdaten
                'credentials:view' => ['Zugangsdaten anzeigen', 'credentials'],
                'credentials:create' => ['Zugangsdaten erstellen', 'credentials'],
                'credentials:edit' => ['Zugangsdaten bearbeiten', 'credentials'],
                'credentials:delete' => ['Zugangsdaten löschen', 'credentials'],
                
                // Liquidität
                'liquidity:view' => ['Liquiditätsplanung anzeigen', 'liquidity'],
                'liquidity:manage' => ['Liquiditätsplanung verwalten', 'liquidity'],
                
                // Freigaben
                'shares:view' => ['Freigaben anzeigen', 'shares'],
                'shares:create' => ['Freigaben erstellen', 'shares'],
                'shares:manage' => ['Freigaben verwalten', 'shares'],
                
                // Notfall
                'emergency:view' => ['Notfall-System anzeigen', 'emergency'],
                'emergency:manage' => ['Notfall-Kontakte verwalten', 'emergency'],
                'emergency:approve' => ['Notfall-Zugriffe genehmigen', 'emergency'],
                
                // Profil
                'profile:view' => ['Profil anzeigen', 'profile'],
                'profile:edit' => ['Profil bearbeiten', 'profile'],
                
                // 2FA
                'twofactor:manage' => ['2FA verwalten', 'security'],
                
                // Admin - Benutzer
                'admin:users:view' => ['Benutzer anzeigen', 'admin'],
                'admin:users:create' => ['Benutzer erstellen', 'admin'],
                'admin:users:edit' => ['Benutzer bearbeiten', 'admin'],
                'admin:users:delete' => ['Benutzer löschen', 'admin'],
                
                // Admin - Rollen
                'admin:roles:view' => ['Rollen anzeigen', 'admin'],
                'admin:roles:create' => ['Rollen erstellen', 'admin'],
                'admin:roles:edit' => ['Rollen bearbeiten', 'admin'],
                'admin:roles:delete' => ['Rollen löschen', 'admin'],
                'admin:roles:assign' => ['Rollen zuweisen', 'admin'],
                
                // Admin - System
                'admin:audit:view' => ['Audit-Logs anzeigen', 'admin'],
                'admin:settings:view' => ['Systemeinstellungen anzeigen', 'admin'],
                'admin:settings:edit' => ['Systemeinstellungen bearbeiten', 'admin'],
                'admin:system:manage' => ['System verwalten', 'admin']
            ];
            
            $created = 0;
            $updated = 0;
            
            foreach ($permissions as $name => [$displayName, $category]) {
                $permission = Permission::firstOrNew(['name' => $name]);
                
                if (!$permission->exists) {
                    $permission->fill([
                        'display_name' => $displayName,
                        'category' => $category,
                        'description' => "Berechtigung: {$displayName}"
                    ]);
                    $permission->save();
                    $created++;
                } else {
                    $permission->update([
                        'display_name' => $displayName,
                        'category' => $category
                    ]);
                    $updated++;
                }
            }
            
            // Audit-Log
            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'permissions_synced',
                'resource' => 'Permission',
                'details' => [
                    'permissions_created' => $created,
                    'permissions_updated' => $updated,
                    'total_permissions' => Permission::count()
                ]
            ]);
            
            Logger::info('Berechtigungen synchronisiert', [
                'created' => $created,
                'updated' => $updated,
                'admin_user_id' => Auth::id()
            ]);
            
            $this->jsonResponse([
                'success' => true,
                'message' => "Berechtigungen synchronisiert: {$created} erstellt, {$updated} aktualisiert",
                'data' => [
                    'created' => $created,
                    'updated' => $updated,
                    'total' => Permission::count()
                ]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Synchronisieren der Berechtigungen', [
                'error' => $e->getMessage(),
                'admin_user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Synchronisieren der Berechtigungen'], 500);
        }
    }

    /**
     * Zeigt Rollen-Statistiken
     */
    public function stats(): void
    {
        try {
            $this->authorize(['admin:roles:view']);
            
            $stats = [
                'roles' => [
                    'total' => Role::count(),
                    'system_roles' => Role::whereIn('name', ['admin', 'user', 'super_admin'])->count(),
                    'custom_roles' => Role::whereNotIn('name', ['admin', 'user', 'super_admin'])->count(),
                    'with_users' => Role::has('users')->count(),
                    'default_role' => Role::where('is_default', true)->value('display_name') ?? 'Keine'
                ],
                'permissions' => [
                    'total' => Permission::count(),
                    'categories' => Permission::distinct('category')->count('category'),
                    'most_used' => Permission::withCount('roles')
                        ->orderBy('roles_count', 'desc')
                        ->limit(5)
                        ->get(['display_name', 'roles_count'])
                ],
                'assignments' => [
                    'users_with_roles' => User::has('roles')->count(),
                    'users_without_roles' => User::doesntHave('roles')->count(),
                    'average_roles_per_user' => $this->calculateAverageRolesPerUser(),
                    'role_distribution' => Role::withCount('users')
                        ->orderBy('users_count', 'desc')
                        ->get(['display_name', 'users_count'])
                ]
            ];
            
            $this->jsonResponse([
                'success' => true,
                'data' => $stats
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Laden der Rollen-Statistiken', [
                'error' => $e->getMessage(),
                'admin_user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Laden der Statistiken'], 500);
        }
    }

    /**
     * Berechnet durchschnittliche Anzahl Rollen pro Benutzer
     */
    private function calculateAverageRolesPerUser(): float
    {
        $totalUsers = User::count();
        $totalAssignments = \DB::table('user_roles')->count();
        
        return $totalUsers > 0 ? round($totalAssignments / $totalUsers, 1) : 0;
    }
}