<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Freigabe;
use App\Models\User;
use App\Models\Vertrag;
use App\Models\Korrespondent;
use App\Core\Auth;
use App\Core\Logger;
use App\Core\Mail;
use DateTime;

/**
 * FreigabenController - Verwaltet Freigaben für Freunde/Familie
 * 
 * Ermöglicht das sichere Teilen von Vertragsinformationen mit vertrauenswürdigen Personen.
 * Unterstützt verschiedene Berechtigungsebenen und temporäre Zugriffe.
 * 
 * @package App\Controllers
 * @version 1.0.0
 * @author Vertragsverwaltung System
 */
class FreigabenController extends BaseController
{
    private const ALLOWED_PERMISSIONS = [
        'view_basic' => 'Grunddaten anzeigen',
        'view_details' => 'Detailansicht',
        'view_documents' => 'Dokumente anzeigen',
        'view_credentials' => 'Zugangsdaten anzeigen',
        'manage_contracts' => 'Verträge verwalten',
        'full_access' => 'Vollzugriff'
    ];

    private const MAX_ACTIVE_FREIGABEN = 10;
    private const DEFAULT_EXPIRY_DAYS = 30;
    private const MAX_EXPIRY_DAYS = 365;

    /**
     * Zeigt die Übersicht aller Freigaben
     */
    public function index(): void
    {
        try {
            $this->authorize(['freigaben:view']);
            
            $user = Auth::user();
            $page = (int)($_GET['page'] ?? 1);
            $filter = $_GET['filter'] ?? 'active';
            $search = $_GET['search'] ?? '';
            
            // Query für Freigaben
            $query = Freigabe::query()
                ->where('owner_user_id', $user->id);
            
            // Filter anwenden
            switch ($filter) {
                case 'active':
                    $query->where('status', 'active')
                          ->where('expires_at', '>', date('Y-m-d H:i:s'));
                    break;
                case 'expired':
                    $query->where('expires_at', '<=', date('Y-m-d H:i:s'));
                    break;
                case 'revoked':
                    $query->where('status', 'revoked');
                    break;
                case 'pending':
                    $query->where('status', 'pending');
                    break;
            }
            
            // Suchfilter
            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('friend_name', 'LIKE', "%{$search}%")
                      ->orWhere('friend_email', 'LIKE', "%{$search}%")
                      ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }
            
            // Sortierung
            $query->orderBy($_GET['sort'] ?? 'created_at', $_GET['order'] ?? 'desc');
            
            // Pagination
            $total = $query->count();
            $freigaben = $this->paginate($query, $page);
            
            // Statistiken
            $stats = [
                'total' => Freigabe::where('owner_user_id', $user->id)->count(),
                'active' => Freigabe::where('owner_user_id', $user->id)
                    ->where('status', 'active')
                    ->where('expires_at', '>', date('Y-m-d H:i:s'))
                    ->count(),
                'expired' => Freigabe::where('owner_user_id', $user->id)
                    ->where('expires_at', '<=', date('Y-m-d H:i:s'))
                    ->count(),
                'pending' => Freigabe::where('owner_user_id', $user->id)
                    ->where('status', 'pending')
                    ->count()
            ];
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'freigaben' => $freigaben,
                    'pagination' => $this->getPaginationData($total, $page),
                    'stats' => $stats,
                    'filters' => [
                        'current' => $filter,
                        'search' => $search
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Laden der Freigaben-Übersicht', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Laden der Freigaben'], 500);
        }
    }

    /**
     * Zeigt Details einer Freigabe
     */
    public function show(int $id): void
    {
        try {
            $this->authorize(['freigaben:view']);
            
            $freigabe = Freigabe::find($id);
            if (!$freigabe || $freigabe->owner_user_id !== Auth::id()) {
                $this->jsonResponse(['error' => 'Freigabe nicht gefunden'], 404);
                return;
            }
            
            // Zusätzliche Daten laden
            $freigabe->loadRelations(['owner', 'friend_user', 'vertraege', 'korrespondenten']);
            
            // Zugriffslogs der letzten 30 Tage
            $accessLogs = $freigabe->getAccessLogs(30);
            
            // Verfügbare Verträge und Korrespondenten für Erweiterung
            $availableVertraege = Vertrag::where('user_id', Auth::id())
                ->whereNotIn('id', $freigabe->getVertragIds())
                ->get(['id', 'titel', 'korrespondent_name']);
                
            $availableKorrespondenten = Korrespondent::where('user_id', Auth::id())
                ->whereNotIn('id', $freigabe->getKorrespondentIds())
                ->get(['id', 'name', 'typ']);
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'freigabe' => $freigabe,
                    'access_logs' => $accessLogs,
                    'available_vertraege' => $availableVertraege,
                    'available_korrespondenten' => $availableKorrespondenten,
                    'permissions' => self::ALLOWED_PERMISSIONS
                ]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Laden der Freigabe-Details', [
                'freigabe_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Laden der Freigabe'], 500);
        }
    }

    /**
     * Zeigt das Formular für neue Freigabe
     */
    public function create(): void
    {
        try {
            $this->authorize(['freigaben:create']);
            
            $user = Auth::user();
            
            // Prüfen ob Limit erreicht ist
            $activeFreigaben = Freigabe::where('owner_user_id', $user->id)
                ->where('status', 'active')
                ->where('expires_at', '>', date('Y-m-d H:i:s'))
                ->count();
                
            if ($activeFreigaben >= self::MAX_ACTIVE_FREIGABEN) {
                $this->jsonResponse([
                    'error' => 'Maximale Anzahl aktiver Freigaben erreicht (' . self::MAX_ACTIVE_FREIGABEN . ')'
                ], 400);
                return;
            }
            
            // Verfügbare Verträge und Korrespondenten
            $vertraege = Vertrag::where('user_id', $user->id)
                ->orderBy('titel')
                ->get(['id', 'titel', 'korrespondent_name', 'status']);
                
            $korrespondenten = Korrespondent::where('user_id', $user->id)
                ->orderBy('name')
                ->get(['id', 'name', 'typ']);
            
            // Vorlagen für schnelle Erstellung
            $templates = [
                'basic' => [
                    'name' => 'Grundzugriff',
                    'description' => 'Nur Grunddaten und Status einsehen',
                    'permissions' => ['view_basic'],
                    'expiry_days' => 30
                ],
                'extended' => [
                    'name' => 'Erweitert',
                    'description' => 'Grunddaten und Dokumente einsehen',
                    'permissions' => ['view_basic', 'view_details', 'view_documents'],
                    'expiry_days' => 14
                ],
                'emergency' => [
                    'name' => 'Notfall',
                    'description' => 'Vollzugriff für Notfälle',
                    'permissions' => ['view_basic', 'view_details', 'view_documents', 'view_credentials'],
                    'expiry_days' => 7
                ]
            ];
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'vertraege' => $vertraege,
                    'korrespondenten' => $korrespondenten,
                    'permissions' => self::ALLOWED_PERMISSIONS,
                    'templates' => $templates,
                    'limits' => [
                        'max_active' => self::MAX_ACTIVE_FREIGABEN,
                        'current_active' => $activeFreigaben,
                        'max_expiry_days' => self::MAX_EXPIRY_DAYS,
                        'default_expiry_days' => self::DEFAULT_EXPIRY_DAYS
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Laden des Freigabe-Formulars', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Laden des Formulars'], 500);
        }
    }

    /**
     * Erstellt eine neue Freigabe
     */
    public function store(): void
    {
        try {
            $this->authorize(['freigaben:create']);
            
            // Eingaben validieren
            $rules = [
                'friend_name' => 'required|string|max:100',
                'friend_email' => 'required|email|max:255',
                'description' => 'string|max:500',
                'permissions' => 'required|array|min:1',
                'permissions.*' => 'string|in:' . implode(',', array_keys(self::ALLOWED_PERMISSIONS)),
                'expiry_days' => 'required|integer|min:1|max:' . self::MAX_EXPIRY_DAYS,
                'vertrag_ids' => 'array',
                'vertrag_ids.*' => 'integer|exists:vertraege,id',
                'korrespondent_ids' => 'array',
                'korrespondent_ids.*' => 'integer|exists:korrespondenten,id',
                'notify_friend' => 'boolean',
                'require_confirmation' => 'boolean'
            ];
            
            $data = $this->validate($_POST, $rules);
            
            $user = Auth::user();
            
            // Prüfen ob Limit erreicht ist
            $activeFreigaben = Freigabe::where('owner_user_id', $user->id)
                ->where('status', 'active')
                ->where('expires_at', '>', date('Y-m-d H:i:s'))
                ->count();
                
            if ($activeFreigaben >= self::MAX_ACTIVE_FREIGABEN) {
                $this->jsonResponse([
                    'error' => 'Maximale Anzahl aktiver Freigaben erreicht'
                ], 400);
                return;
            }
            
            // Prüfen ob bereits eine aktive Freigabe für diese E-Mail existiert
            $existingFreigabe = Freigabe::where('owner_user_id', $user->id)
                ->where('friend_email', $data['friend_email'])
                ->where('status', 'active')
                ->where('expires_at', '>', date('Y-m-d H:i:s'))
                ->first();
                
            if ($existingFreigabe) {
                $this->jsonResponse([
                    'error' => 'Für diese E-Mail-Adresse existiert bereits eine aktive Freigabe'
                ], 400);
                return;
            }
            
            // Verträge validieren
            if (!empty($data['vertrag_ids'])) {
                $validVertraege = Vertrag::where('user_id', $user->id)
                    ->whereIn('id', $data['vertrag_ids'])
                    ->pluck('id')->toArray();
                    
                if (count($validVertraege) !== count($data['vertrag_ids'])) {
                    $this->jsonResponse(['error' => 'Ungültige Vertrags-IDs'], 400);
                    return;
                }
            }
            
            // Korrespondenten validieren
            if (!empty($data['korrespondent_ids'])) {
                $validKorrespondenten = Korrespondent::where('user_id', $user->id)
                    ->whereIn('id', $data['korrespondent_ids'])
                    ->pluck('id')->toArray();
                    
                if (count($validKorrespondenten) !== count($data['korrespondent_ids'])) {
                    $this->jsonResponse(['error' => 'Ungültige Korrespondenten-IDs'], 400);
                    return;
                }
            }
            
            // Prüfen ob Freund bereits registriert ist
            $friendUser = User::where('email', $data['friend_email'])->first();
            
            // Freigabe erstellen
            $expiresAt = new DateTime();
            $expiresAt->modify("+{$data['expiry_days']} days");
            
            $freigabeData = [
                'owner_user_id' => $user->id,
                'friend_user_id' => $friendUser ? $friendUser->id : null,
                'friend_name' => $data['friend_name'],
                'friend_email' => $data['friend_email'],
                'description' => $data['description'] ?? '',
                'permissions' => $data['permissions'],
                'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
                'status' => ($data['require_confirmation'] ?? false) ? 'pending' : 'active',
                'access_token' => Freigabe::generateAccessToken(),
                'confirmation_token' => ($data['require_confirmation'] ?? false) ? Freigabe::generateConfirmationToken() : null,
                'vertrag_ids' => $data['vertrag_ids'] ?? [],
                'korrespondent_ids' => $data['korrespondent_ids'] ?? []
            ];
            
            $freigabe = Freigabe::create($freigabeData);
            
            // E-Mail-Benachrichtigung senden
            if ($data['notify_friend'] ?? true) {
                $this->sendFreigabeNotification($freigabe);
            }
            
            // Logging
            Logger::info('Neue Freigabe erstellt', [
                'freigabe_id' => $freigabe->id,
                'friend_email' => $freigabe->friend_email,
                'permissions' => $freigabe->permissions,
                'user_id' => $user->id
            ]);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Freigabe erfolgreich erstellt',
                'data' => [
                    'freigabe' => $freigabe,
                    'access_url' => $freigabe->getAccessUrl()
                ]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Erstellen der Freigabe', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Erstellen der Freigabe'], 500);
        }
    }

    /**
     * Aktualisiert eine Freigabe
     */
    public function update(int $id): void
    {
        try {
            $this->authorize(['freigaben:edit']);
            
            $freigabe = Freigabe::find($id);
            if (!$freigabe || $freigabe->owner_user_id !== Auth::id()) {
                $this->jsonResponse(['error' => 'Freigabe nicht gefunden'], 404);
                return;
            }
            
            // Validierung
            $rules = [
                'description' => 'string|max:500',
                'permissions' => 'array|min:1',
                'permissions.*' => 'string|in:' . implode(',', array_keys(self::ALLOWED_PERMISSIONS)),
                'expiry_days' => 'integer|min:1|max:' . self::MAX_EXPIRY_DAYS,
                'vertrag_ids' => 'array',
                'vertrag_ids.*' => 'integer|exists:vertraege,id',
                'korrespondent_ids' => 'array',
                'korrespondent_ids.*' => 'integer|exists:korrespondenten,id'
            ];
            
            $data = $this->validate($_POST, $rules);
            
            // Nur aktive oder ausstehende Freigaben können bearbeitet werden
            if (!in_array($freigabe->status, ['active', 'pending'])) {
                $this->jsonResponse(['error' => 'Diese Freigabe kann nicht bearbeitet werden'], 400);
                return;
            }
            
            $oldData = $freigabe->toArray();
            
            // Freigabe aktualisieren
            $updateData = [];
            
            if (isset($data['description'])) {
                $updateData['description'] = $data['description'];
            }
            
            if (isset($data['permissions'])) {
                $updateData['permissions'] = $data['permissions'];
            }
            
            if (isset($data['expiry_days'])) {
                $expiresAt = new DateTime();
                $expiresAt->modify("+{$data['expiry_days']} days");
                $updateData['expires_at'] = $expiresAt->format('Y-m-d H:i:s');
            }
            
            if (isset($data['vertrag_ids'])) {
                $updateData['vertrag_ids'] = $data['vertrag_ids'];
            }
            
            if (isset($data['korrespondent_ids'])) {
                $updateData['korrespondent_ids'] = $data['korrespondent_ids'];
            }
            
            $freigabe->update($updateData);
            
            // Änderungen protokollieren
            Logger::info('Freigabe aktualisiert', [
                'freigabe_id' => $freigabe->id,
                'changes' => array_diff_assoc($updateData, $oldData),
                'user_id' => Auth::id()
            ]);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Freigabe erfolgreich aktualisiert',
                'data' => ['freigabe' => $freigabe]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Aktualisieren der Freigabe', [
                'freigabe_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Aktualisieren der Freigabe'], 500);
        }
    }

    /**
     * Widerruft eine Freigabe
     */
    public function revoke(int $id): void
    {
        try {
            $this->authorize(['freigaben:delete']);
            
            $freigabe = Freigabe::find($id);
            if (!$freigabe || $freigabe->owner_user_id !== Auth::id()) {
                $this->jsonResponse(['error' => 'Freigabe nicht gefunden'], 404);
                return;
            }
            
            $reason = $_POST['reason'] ?? '';
            $notifyFriend = $_POST['notify_friend'] ?? false;
            
            // Freigabe widerrufen
            $freigabe->revoke($reason);
            
            // Benachrichtigung senden
            if ($notifyFriend) {
                $this->sendRevocationNotification($freigabe, $reason);
            }
            
            Logger::info('Freigabe widerrufen', [
                'freigabe_id' => $freigabe->id,
                'reason' => $reason,
                'user_id' => Auth::id()
            ]);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Freigabe erfolgreich widerrufen'
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Widerrufen der Freigabe', [
                'freigabe_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Widerrufen der Freigabe'], 500);
        }
    }

    /**
     * Verlängert eine Freigabe
     */
    public function extend(int $id): void
    {
        try {
            $this->authorize(['freigaben:edit']);
            
            $freigabe = Freigabe::find($id);
            if (!$freigabe || $freigabe->owner_user_id !== Auth::id()) {
                $this->jsonResponse(['error' => 'Freigabe nicht gefunden'], 404);
                return;
            }
            
            $rules = [
                'days' => 'required|integer|min:1|max:' . self::MAX_EXPIRY_DAYS
            ];
            
            $data = $this->validate($_POST, $rules);
            
            // Neue Ablaufzeit berechnen
            $currentExpiry = new DateTime($freigabe->expires_at);
            $now = new DateTime();
            
            // Von aktuellem Datum oder Ablaufzeit verlängern (je nachdem, was später ist)
            $baseDate = $currentExpiry > $now ? $currentExpiry : $now;
            $baseDate->modify("+{$data['days']} days");
            
            // Maximale Laufzeit prüfen
            $maxExpiry = new DateTime();
            $maxExpiry->modify('+' . self::MAX_EXPIRY_DAYS . ' days');
            
            if ($baseDate > $maxExpiry) {
                $this->jsonResponse([
                    'error' => 'Maximale Laufzeit von ' . self::MAX_EXPIRY_DAYS . ' Tagen würde überschritten'
                ], 400);
                return;
            }
            
            $oldExpiry = $freigabe->expires_at;
            
            // Freigabe verlängern
            $freigabe->update([
                'expires_at' => $baseDate->format('Y-m-d H:i:s'),
                'status' => 'active' // Reaktivieren falls abgelaufen
            ]);
            
            Logger::info('Freigabe verlängert', [
                'freigabe_id' => $freigabe->id,
                'old_expiry' => $oldExpiry,
                'new_expiry' => $freigabe->expires_at,
                'user_id' => Auth::id()
            ]);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Freigabe erfolgreich verlängert',
                'data' => [
                    'new_expiry' => $freigabe->expires_at,
                    'days_added' => $data['days']
                ]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Verlängern der Freigabe', [
                'freigabe_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Verlängern der Freigabe'], 500);
        }
    }

    /**
     * Bestätigt eine ausstehende Freigabe
     */
    public function confirm(int $id): void
    {
        try {
            $token = $_POST['token'] ?? $_GET['token'] ?? '';
            
            if (empty($token)) {
                $this->jsonResponse(['error' => 'Bestätigungstoken erforderlich'], 400);
                return;
            }
            
            $freigabe = Freigabe::find($id);
            if (!$freigabe) {
                $this->jsonResponse(['error' => 'Freigabe nicht gefunden'], 404);
                return;
            }
            
            // Token validieren
            if (!$freigabe->validateConfirmationToken($token)) {
                $this->jsonResponse(['error' => 'Ungültiger oder abgelaufener Token'], 400);
                return;
            }
            
            // Freigabe aktivieren
            $freigabe->update([
                'status' => 'active',
                'confirmed_at' => date('Y-m-d H:i:s'),
                'confirmation_token' => null // Token löschen nach Verwendung
            ]);
            
            Logger::info('Freigabe bestätigt', [
                'freigabe_id' => $freigabe->id,
                'friend_email' => $freigabe->friend_email
            ]);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Freigabe erfolgreich bestätigt',
                'data' => [
                    'access_url' => $freigabe->getAccessUrl()
                ]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Bestätigen der Freigabe', [
                'freigabe_id' => $id,
                'error' => $e->getMessage()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Bestätigen der Freigabe'], 500);
        }
    }

    /**
     * Zeigt Freigabe-Statistiken
     */
    public function stats(): void
    {
        try {
            $this->authorize(['freigaben:view']);
            
            $user = Auth::user();
            $timeframe = $_GET['timeframe'] ?? '30'; // Tage
            
            $startDate = date('Y-m-d H:i:s', strtotime("-{$timeframe} days"));
            
            // Basis-Statistiken
            $stats = [
                'total_freigaben' => Freigabe::where('owner_user_id', $user->id)->count(),
                'active_freigaben' => Freigabe::where('owner_user_id', $user->id)
                    ->where('status', 'active')
                    ->where('expires_at', '>', date('Y-m-d H:i:s'))
                    ->count(),
                'pending_freigaben' => Freigabe::where('owner_user_id', $user->id)
                    ->where('status', 'pending')
                    ->count(),
                'expired_freigaben' => Freigabe::where('owner_user_id', $user->id)
                    ->where('expires_at', '<=', date('Y-m-d H:i:s'))
                    ->count(),
                'total_accesses' => Freigabe::where('owner_user_id', $user->id)
                    ->where('last_accessed_at', '>=', $startDate)
                    ->sum('access_count'),
                'unique_friends' => Freigabe::where('owner_user_id', $user->id)
                    ->distinct('friend_email')
                    ->count(),
                'most_accessed' => Freigabe::where('owner_user_id', $user->id)
                    ->where('last_accessed_at', '>=', $startDate)
                    ->orderBy('access_count', 'desc')
                    ->limit(5)
                    ->get(['id', 'friend_name', 'access_count', 'last_accessed_at'])
            ];
            
            // Zeitbasierte Statistiken
            $dailyStats = [];
            for ($i = intval($timeframe); $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                $dailyStats[$date] = [
                    'date' => $date,
                    'new_freigaben' => Freigabe::where('owner_user_id', $user->id)
                        ->whereDate('created_at', $date)
                        ->count(),
                    'accesses' => Freigabe::where('owner_user_id', $user->id)
                        ->whereDate('last_accessed_at', $date)
                        ->sum('access_count')
                ];
            }
            
            // Berechtigungsverteilung
            $permissionStats = [];
            foreach (self::ALLOWED_PERMISSIONS as $key => $label) {
                $permissionStats[$key] = [
                    'label' => $label,
                    'count' => Freigabe::where('owner_user_id', $user->id)
                        ->whereJsonContains('permissions', $key)
                        ->count()
                ];
            }
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'stats' => $stats,
                    'daily_stats' => array_values($dailyStats),
                    'permission_stats' => $permissionStats,
                    'timeframe' => $timeframe
                ]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Laden der Freigabe-Statistiken', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Laden der Statistiken'], 500);
        }
    }

    /**
     * Bulk-Operationen für mehrere Freigaben
     */
    public function bulk(): void
    {
        try {
            $this->authorize(['freigaben:edit']);
            
            $rules = [
                'action' => 'required|string|in:revoke,extend,delete',
                'freigabe_ids' => 'required|array|min:1',
                'freigabe_ids.*' => 'integer',
                'reason' => 'string|max:500', // für revoke
                'days' => 'integer|min:1|max:' . self::MAX_EXPIRY_DAYS, // für extend
                'notify' => 'boolean'
            ];
            
            $data = $this->validate($_POST, $rules);
            
            $user = Auth::user();
            $action = $data['action'];
            $freigabeIds = $data['freigabe_ids'];
            
            // Freigaben validieren
            $freigaben = Freigabe::where('owner_user_id', $user->id)
                ->whereIn('id', $freigabeIds)
                ->get();
                
            if ($freigaben->count() !== count($freigabeIds)) {
                $this->jsonResponse(['error' => 'Einige Freigaben wurden nicht gefunden'], 404);
                return;
            }
            
            $results = [
                'success' => 0,
                'failed' => 0,
                'errors' => []
            ];
            
            foreach ($freigaben as $freigabe) {
                try {
                    switch ($action) {
                        case 'revoke':
                            if (in_array($freigabe->status, ['active', 'pending'])) {
                                $freigabe->revoke($data['reason'] ?? '');
                                if ($data['notify'] ?? false) {
                                    $this->sendRevocationNotification($freigabe, $data['reason'] ?? '');
                                }
                                $results['success']++;
                            } else {
                                $results['errors'][] = "Freigabe {$freigabe->id} kann nicht widerrufen werden";
                                $results['failed']++;
                            }
                            break;
                            
                        case 'extend':
                            if (isset($data['days'])) {
                                $expiresAt = new DateTime($freigabe->expires_at);
                                $expiresAt->modify("+{$data['days']} days");
                                
                                $freigabe->update([
                                    'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
                                    'status' => 'active'
                                ]);
                                $results['success']++;
                            } else {
                                $results['errors'][] = "Anzahl Tage für Freigabe {$freigabe->id} nicht angegeben";
                                $results['failed']++;
                            }
                            break;
                            
                        case 'delete':
                            if ($freigabe->status === 'revoked' || $freigabe->isExpired()) {
                                $freigabe->delete();
                                $results['success']++;
                            } else {
                                $results['errors'][] = "Freigabe {$freigabe->id} kann nicht gelöscht werden (noch aktiv)";
                                $results['failed']++;
                            }
                            break;
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = "Fehler bei Freigabe {$freigabe->id}: " . $e->getMessage();
                    $results['failed']++;
                }
            }
            
            Logger::info('Bulk-Operation für Freigaben ausgeführt', [
                'action' => $action,
                'freigabe_ids' => $freigabeIds,
                'results' => $results,
                'user_id' => $user->id
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
            Logger::error('Fehler bei Bulk-Operation für Freigaben', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler bei Bulk-Operation'], 500);
        }
    }

    /**
     * Sendet Benachrichtigung über neue Freigabe
     */
    private function sendFreigabeNotification(Freigabe $freigabe): void
    {
        try {
            $owner = $freigabe->owner;
            $accessUrl = $freigabe->getAccessUrl();
            
            $subject = "Zugriff auf Vertragsverwaltung von {$owner->name}";
            
            $message = "Hallo {$freigabe->friend_name},\n\n";
            $message .= "{$owner->name} hat Ihnen Zugriff auf ausgewählte Vertragsdaten gewährt.\n\n";
            
            if ($freigabe->description) {
                $message .= "Beschreibung: {$freigabe->description}\n\n";
            }
            
            $message .= "Berechtigungen:\n";
            foreach ($freigabe->permissions as $permission) {
                if (isset(self::ALLOWED_PERMISSIONS[$permission])) {
                    $message .= "- " . self::ALLOWED_PERMISSIONS[$permission] . "\n";
                }
            }
            
            $message .= "\nGültig bis: " . date('d.m.Y H:i', strtotime($freigabe->expires_at)) . "\n\n";
            
            if ($freigabe->status === 'pending') {
                $confirmUrl = $freigabe->getConfirmationUrl();
                $message .= "Bitte bestätigen Sie den Zugriff über folgenden Link:\n{$confirmUrl}\n\n";
            } else {
                $message .= "Zugriff über folgenden Link:\n{$accessUrl}\n\n";
            }
            
            $message .= "Mit freundlichen Grüßen\n";
            $message .= "Ihr Vertragsverwaltung-System";
            
            Mail::send($freigabe->friend_email, $subject, $message);
            
        } catch (\Exception $e) {
            Logger::warning('E-Mail-Benachrichtigung für Freigabe konnte nicht gesendet werden', [
                'freigabe_id' => $freigabe->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Sendet Benachrichtigung über Widerruf
     */
    private function sendRevocationNotification(Freigabe $freigabe, string $reason = ''): void
    {
        try {
            $owner = $freigabe->owner;
            
            $subject = "Zugriff auf Vertragsverwaltung wurde widerrufen";
            
            $message = "Hallo {$freigabe->friend_name},\n\n";
            $message .= "{$owner->name} hat den Zugriff auf die Vertragsdaten widerrufen.\n\n";
            
            if ($reason) {
                $message .= "Grund: {$reason}\n\n";
            }
            
            $message .= "Der Zugriff ist ab sofort nicht mehr möglich.\n\n";
            $message .= "Mit freundlichen Grüßen\n";
            $message .= "Ihr Vertragsverwaltung-System";
            
            Mail::send($freigabe->friend_email, $subject, $message);
            
        } catch (\Exception $e) {
            Logger::warning('E-Mail-Benachrichtigung für Widerruf konnte nicht gesendet werden', [
                'freigabe_id' => $freigabe->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}