<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\NotfallKontakt;
use App\Models\NotfallZugriff;
use App\Models\User;
use App\Models\Vertrag;
use App\Core\Auth;
use App\Core\Logger;
use App\Core\Mail;
use App\Core\Security;
use DateTime;

/**
 * NotfallController - Verwaltet Notfallkontakte und Notfallzugriffe
 * 
 * Ermöglicht die Einrichtung von Notfallkontakten, die im Ernstfall Zugriff auf
 * wichtige Vertragsdaten erhalten können. Unterstützt mehrstufige Verifizierung
 * und zeitlich begrenzte Zugriffe.
 * 
 * @package App\Controllers
 * @version 1.0.0
 * @author Vertragsverwaltung System
 */
class NotfallController extends BaseController
{
    private const MAX_NOTFALL_KONTAKTE = 5;
    private const VERIFICATION_TIMEOUT = 1800; // 30 Minuten
    private const ACCESS_DURATION = 168; // 7 Tage in Stunden
    private const MIN_VERIFICATION_DELAY = 24; // 24 Stunden Wartezeit
    private const MAX_ACCESS_ATTEMPTS = 3;

    /**
     * Zeigt die Notfall-Übersicht
     */
    public function index(): void
    {
        try {
            $this->authorize(['notfall:view']);
            
            $user = Auth::user();
            
            // Notfallkontakte laden
            $kontakte = NotfallKontakt::where('user_id', $user->id)
                ->orderBy('priority', 'asc')
                ->get();
            
            // Aktive Notfallzugriffe
            $activeZugriffe = NotfallZugriff::where('user_id', $user->id)
                ->where('status', 'active')
                ->where('expires_at', '>', date('Y-m-d H:i:s'))
                ->with(['kontakt'])
                ->orderBy('created_at', 'desc')
                ->get();
            
            // Ausstehende Anfragen
            $pendingZugriffe = NotfallZugriff::where('user_id', $user->id)
                ->whereIn('status', ['pending', 'verification_sent'])
                ->with(['kontakt'])
                ->orderBy('created_at', 'desc')
                ->get();
            
            // Statistiken
            $stats = [
                'total_kontakte' => $kontakte->count(),
                'verified_kontakte' => $kontakte->where('is_verified', true)->count(),
                'active_zugriffe' => $activeZugriffe->count(),
                'pending_zugriffe' => $pendingZugriffe->count(),
                'total_zugriffe_last_year' => NotfallZugriff::where('user_id', $user->id)
                    ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-1 year')))
                    ->count(),
                'emergency_mode_active' => $user->emergency_mode_active ?? false
            ];
            
            // Systemeinstellungen
            $settings = [
                'max_kontakte' => self::MAX_NOTFALL_KONTAKTE,
                'access_duration_hours' => self::ACCESS_DURATION,
                'verification_delay_hours' => self::MIN_VERIFICATION_DELAY,
                'max_attempts' => self::MAX_ACCESS_ATTEMPTS
            ];
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'kontakte' => $kontakte,
                    'active_zugriffe' => $activeZugriffe,
                    'pending_zugriffe' => $pendingZugriffe,
                    'stats' => $stats,
                    'settings' => $settings
                ]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Laden der Notfall-Übersicht', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Laden der Notfall-Übersicht'], 500);
        }
    }

    /**
     * Zeigt Notfallkontakte
     */
    public function kontakte(): void
    {
        try {
            $this->authorize(['notfall:view']);
            
            $user = Auth::user();
            
            $kontakte = NotfallKontakt::where('user_id', $user->id)
                ->orderBy('priority', 'asc')
                ->get();
            
            // Verfügbare Prioritäten berechnen
            $usedPriorities = $kontakte->pluck('priority')->toArray();
            $availablePriorities = array_diff(range(1, self::MAX_NOTFALL_KONTAKTE), $usedPriorities);
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'kontakte' => $kontakte,
                    'available_priorities' => array_values($availablePriorities),
                    'max_kontakte' => self::MAX_NOTFALL_KONTAKTE
                ]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Laden der Notfallkontakte', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Laden der Notfallkontakte'], 500);
        }
    }

    /**
     * Erstellt einen neuen Notfallkontakt
     */
    public function createKontakt(): void
    {
        try {
            $this->authorize(['notfall:create']);
            
            $user = Auth::user();
            
            // Prüfen ob Limit erreicht
            $currentCount = NotfallKontakt::where('user_id', $user->id)->count();
            if ($currentCount >= self::MAX_NOTFALL_KONTAKTE) {
                $this->jsonResponse([
                    'error' => 'Maximale Anzahl Notfallkontakte erreicht (' . self::MAX_NOTFALL_KONTAKTE . ')'
                ], 400);
                return;
            }
            
            // Validierung
            $rules = [
                'name' => 'required|string|max:100',
                'email' => 'required|email|max:255',
                'phone' => 'string|max:20',
                'relationship' => 'required|string|max:100',
                'priority' => 'required|integer|min:1|max:' . self::MAX_NOTFALL_KONTAKTE,
                'can_access_contracts' => 'boolean',
                'can_access_credentials' => 'boolean',
                'can_access_documents' => 'boolean',
                'notification_preference' => 'required|string|in:email,phone,both'
            ];
            
            $data = $this->validate($_POST, $rules);
            
            // Prüfen ob E-Mail bereits verwendet wird
            $existingKontakt = NotfallKontakt::where('user_id', $user->id)
                ->where('email', $data['email'])
                ->first();
                
            if ($existingKontakt) {
                $this->jsonResponse(['error' => 'Diese E-Mail-Adresse wird bereits verwendet'], 400);
                return;
            }
            
            // Prüfen ob Priorität bereits vergeben ist
            $existingPriority = NotfallKontakt::where('user_id', $user->id)
                ->where('priority', $data['priority'])
                ->first();
                
            if ($existingPriority) {
                $this->jsonResponse(['error' => 'Diese Priorität ist bereits vergeben'], 400);
                return;
            }
            
            // Notfallkontakt erstellen
            $kontaktData = [
                'user_id' => $user->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'relationship' => $data['relationship'],
                'priority' => $data['priority'],
                'can_access_contracts' => $data['can_access_contracts'] ?? false,
                'can_access_credentials' => $data['can_access_credentials'] ?? false,
                'can_access_documents' => $data['can_access_documents'] ?? false,
                'notification_preference' => $data['notification_preference'],
                'verification_token' => Security::generateToken(32),
                'is_verified' => false
            ];
            
            $kontakt = NotfallKontakt::create($kontaktData);
            
            // Verifizierungs-E-Mail senden
            $this->sendVerificationEmail($kontakt);
            
            Logger::info('Notfallkontakt erstellt', [
                'kontakt_id' => $kontakt->id,
                'email' => $kontakt->email,
                'priority' => $kontakt->priority,
                'user_id' => $user->id
            ]);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Notfallkontakt erstellt. Verifizierungs-E-Mail wurde gesendet.',
                'data' => ['kontakt' => $kontakt]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Erstellen des Notfallkontakts', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Erstellen des Notfallkontakts'], 500);
        }
    }

    /**
     * Aktualisiert einen Notfallkontakt
     */
    public function updateKontakt(int $id): void
    {
        try {
            $this->authorize(['notfall:edit']);
            
            $user = Auth::user();
            $kontakt = NotfallKontakt::find($id);
            
            if (!$kontakt || $kontakt->user_id !== $user->id) {
                $this->jsonResponse(['error' => 'Notfallkontakt nicht gefunden'], 404);
                return;
            }
            
            // Validierung
            $rules = [
                'name' => 'string|max:100',
                'email' => 'email|max:255',
                'phone' => 'string|max:20',
                'relationship' => 'string|max:100',
                'priority' => 'integer|min:1|max:' . self::MAX_NOTFALL_KONTAKTE,
                'can_access_contracts' => 'boolean',
                'can_access_credentials' => 'boolean',
                'can_access_documents' => 'boolean',
                'notification_preference' => 'string|in:email,phone,both'
            ];
            
            $data = $this->validate($_POST, $rules);
            
            $needsReVerification = false;
            $updateData = [];
            
            // E-Mail-Änderung
            if (isset($data['email']) && $data['email'] !== $kontakt->email) {
                // Prüfen ob neue E-Mail bereits verwendet wird
                $existingKontakt = NotfallKontakt::where('user_id', $user->id)
                    ->where('email', $data['email'])
                    ->where('id', '!=', $kontakt->id)
                    ->first();
                    
                if ($existingKontakt) {
                    $this->jsonResponse(['error' => 'Diese E-Mail-Adresse wird bereits verwendet'], 400);
                    return;
                }
                
                $updateData['email'] = $data['email'];
                $needsReVerification = true;
            }
            
            // Priorität ändern
            if (isset($data['priority']) && $data['priority'] !== $kontakt->priority) {
                // Prüfen ob neue Priorität bereits vergeben ist
                $existingPriority = NotfallKontakt::where('user_id', $user->id)
                    ->where('priority', $data['priority'])
                    ->where('id', '!=', $kontakt->id)
                    ->first();
                    
                if ($existingPriority) {
                    $this->jsonResponse(['error' => 'Diese Priorität ist bereits vergeben'], 400);
                    return;
                }
                
                $updateData['priority'] = $data['priority'];
            }
            
            // Andere Felder
            $otherFields = ['name', 'phone', 'relationship', 'can_access_contracts', 
                           'can_access_credentials', 'can_access_documents', 'notification_preference'];
                           
            foreach ($otherFields as $field) {
                if (isset($data[$field])) {
                    $updateData[$field] = $data[$field];
                }
            }
            
            // Re-Verifizierung erforderlich?
            if ($needsReVerification) {
                $updateData['is_verified'] = false;
                $updateData['verification_token'] = Security::generateToken(32);
                $updateData['verified_at'] = null;
            }
            
            $kontakt->update($updateData);
            
            // Neue Verifizierungs-E-Mail senden falls erforderlich
            if ($needsReVerification) {
                $this->sendVerificationEmail($kontakt);
            }
            
            Logger::info('Notfallkontakt aktualisiert', [
                'kontakt_id' => $kontakt->id,
                'changes' => $updateData,
                'needs_reverification' => $needsReVerification,
                'user_id' => $user->id
            ]);
            
            $message = 'Notfallkontakt erfolgreich aktualisiert';
            if ($needsReVerification) {
                $message .= '. Neue Verifizierungs-E-Mail wurde gesendet.';
            }
            
            $this->jsonResponse([
                'success' => true,
                'message' => $message,
                'data' => ['kontakt' => $kontakt]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Aktualisieren des Notfallkontakts', [
                'kontakt_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Aktualisieren des Notfallkontakts'], 500);
        }
    }

    /**
     * Löscht einen Notfallkontakt
     */
    public function deleteKontakt(int $id): void
    {
        try {
            $this->authorize(['notfall:delete']);
            
            $user = Auth::user();
            $kontakt = NotfallKontakt::find($id);
            
            if (!$kontakt || $kontakt->user_id !== $user->id) {
                $this->jsonResponse(['error' => 'Notfallkontakt nicht gefunden'], 404);
                return;
            }
            
            // Prüfen ob aktive Notfallzugriffe bestehen
            $activeZugriffe = NotfallZugriff::where('kontakt_id', $kontakt->id)
                ->where('status', 'active')
                ->where('expires_at', '>', date('Y-m-d H:i:s'))
                ->count();
                
            if ($activeZugriffe > 0) {
                $this->jsonResponse([
                    'error' => 'Notfallkontakt kann nicht gelöscht werden - aktive Zugriffe vorhanden'
                ], 400);
                return;
            }
            
            // Ausstehende Zugriffe widerrufen
            NotfallZugriff::where('kontakt_id', $kontakt->id)
                ->whereIn('status', ['pending', 'verification_sent'])
                ->update([
                    'status' => 'cancelled',
                    'cancelled_reason' => 'Notfallkontakt gelöscht'
                ]);
            
            $kontakt->delete();
            
            Logger::info('Notfallkontakt gelöscht', [
                'kontakt_id' => $kontakt->id,
                'email' => $kontakt->email,
                'user_id' => $user->id
            ]);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Notfallkontakt erfolgreich gelöscht'
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Löschen des Notfallkontakts', [
                'kontakt_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Löschen des Notfallkontakts'], 500);
        }
    }

    /**
     * Verifiziert einen Notfallkontakt
     */
    public function verifyKontakt(): void
    {
        try {
            $token = $_GET['token'] ?? $_POST['token'] ?? '';
            
            if (empty($token)) {
                $this->jsonResponse(['error' => 'Verifizierungstoken erforderlich'], 400);
                return;
            }
            
            $kontakt = NotfallKontakt::where('verification_token', $token)
                ->where('is_verified', false)
                ->first();
                
            if (!$kontakt) {
                $this->jsonResponse(['error' => 'Ungültiger oder bereits verwendeter Token'], 400);
                return;
            }
            
            // Token-Alter prüfen (24 Stunden gültig)
            $tokenAge = time() - strtotime($kontakt->updated_at);
            if ($tokenAge > 86400) {
                $this->jsonResponse(['error' => 'Token ist abgelaufen'], 400);
                return;
            }
            
            // Kontakt verifizieren
            $kontakt->update([
                'is_verified' => true,
                'verified_at' => date('Y-m-d H:i:s'),
                'verification_token' => null
            ]);
            
            Logger::info('Notfallkontakt verifiziert', [
                'kontakt_id' => $kontakt->id,
                'email' => $kontakt->email
            ]);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Notfallkontakt erfolgreich verifiziert',
                'data' => [
                    'kontakt_name' => $kontakt->name,
                    'user_name' => $kontakt->user->name ?? 'Unbekannt'
                ]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler bei der Verifizierung des Notfallkontakts', [
                'error' => $e->getMessage()
            ]);
            $this->jsonResponse(['error' => 'Fehler bei der Verifizierung'], 500);
        }
    }

    /**
     * Sendet Verifizierungs-E-Mail erneut
     */
    public function resendVerification(int $id): void
    {
        try {
            $this->authorize(['notfall:edit']);
            
            $user = Auth::user();
            $kontakt = NotfallKontakt::find($id);
            
            if (!$kontakt || $kontakt->user_id !== $user->id) {
                $this->jsonResponse(['error' => 'Notfallkontakt nicht gefunden'], 404);
                return;
            }
            
            if ($kontakt->is_verified) {
                $this->jsonResponse(['error' => 'Kontakt ist bereits verifiziert'], 400);
                return;
            }
            
            // Neuen Token generieren
            $kontakt->update([
                'verification_token' => Security::generateToken(32)
            ]);
            
            // E-Mail senden
            $this->sendVerificationEmail($kontakt);
            
            Logger::info('Verifizierungs-E-Mail erneut gesendet', [
                'kontakt_id' => $kontakt->id,
                'email' => $kontakt->email,
                'user_id' => $user->id
            ]);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Verifizierungs-E-Mail wurde erneut gesendet'
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim erneuten Senden der Verifizierungs-E-Mail', [
                'kontakt_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Senden der E-Mail'], 500);
        }
    }

    /**
     * Initiiert einen Notfallzugriff
     */
    public function requestAccess(): void
    {
        try {
            // Keine Auth erforderlich - externe Anfrage
            
            $rules = [
                'email' => 'required|email',
                'user_identifier' => 'required|string', // Name oder E-Mail des Hauptbenutzers
                'reason' => 'required|string|max:500',
                'urgency' => 'required|string|in:low,medium,high,critical'
            ];
            
            $data = $this->validate($_POST, $rules);
            
            // Hauptbenutzer finden
            $user = User::where('email', $data['user_identifier'])
                ->orWhere('name', $data['user_identifier'])
                ->first();
                
            if (!$user) {
                $this->jsonResponse(['error' => 'Benutzer nicht gefunden'], 404);
                return;
            }
            
            // Notfallkontakt finden
            $kontakt = NotfallKontakt::where('user_id', $user->id)
                ->where('email', $data['email'])
                ->where('is_verified', true)
                ->first();
                
            if (!$kontakt) {
                $this->jsonResponse(['error' => 'Nicht als Notfallkontakt registriert oder nicht verifiziert'], 404);
                return;
            }
            
            // Prüfen ob bereits eine aktive oder ausstehende Anfrage existiert
            $existingZugriff = NotfallZugriff::where('kontakt_id', $kontakt->id)
                ->whereIn('status', ['active', 'pending', 'verification_sent'])
                ->where('expires_at', '>', date('Y-m-d H:i:s'))
                ->first();
                
            if ($existingZugriff) {
                $this->jsonResponse([
                    'error' => 'Es existiert bereits eine aktive oder ausstehende Notfallanfrage'
                ], 400);
                return;
            }
            
            // Prüfen ob zu viele Versuche in letzter Zeit
            $recentAttempts = NotfallZugriff::where('kontakt_id', $kontakt->id)
                ->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-24 hours')))
                ->count();
                
            if ($recentAttempts >= self::MAX_ACCESS_ATTEMPTS) {
                $this->jsonResponse([
                    'error' => 'Zu viele Notfallanfragen in den letzten 24 Stunden'
                ], 429);
                return;
            }
            
            // Notfallzugriff erstellen
            $expiresAt = new DateTime();
            $expiresAt->modify('+' . self::ACCESS_DURATION . ' hours');
            
            $zugriffData = [
                'user_id' => $user->id,
                'kontakt_id' => $kontakt->id,
                'reason' => $data['reason'],
                'urgency' => $data['urgency'],
                'status' => 'pending',
                'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
                'verification_code' => $this->generateVerificationCode(),
                'access_token' => Security::generateToken(64),
                'request_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'request_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ];
            
            $zugriff = NotfallZugriff::create($zugriffData);
            
            // Benachrichtigungen senden
            $this->sendEmergencyNotifications($zugriff);
            
            Logger::warning('Notfallzugriff angefordert', [
                'zugriff_id' => $zugriff->id,
                'kontakt_email' => $kontakt->email,
                'user_id' => $user->id,
                'urgency' => $data['urgency'],
                'ip' => $zugriffData['request_ip']
            ]);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Notfallzugriff angefordert. Benachrichtigungen wurden gesendet.',
                'data' => [
                    'request_id' => $zugriff->id,
                    'verification_required' => true,
                    'estimated_approval_time' => self::MIN_VERIFICATION_DELAY . ' Stunden'
                ]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler bei Notfallzugriff-Anfrage', [
                'error' => $e->getMessage(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            $this->jsonResponse(['error' => 'Fehler bei der Notfallzugriff-Anfrage'], 500);
        }
    }

    /**
     * Zeigt ausstehende Notfallzugriffe für Genehmigung
     */
    public function pendingAccess(): void
    {
        try {
            $this->authorize(['notfall:approve']);
            
            $user = Auth::user();
            
            $pendingZugriffe = NotfallZugriff::where('user_id', $user->id)
                ->whereIn('status', ['pending', 'verification_sent'])
                ->with(['kontakt'])
                ->orderBy('urgency', 'desc')
                ->orderBy('created_at', 'asc')
                ->get();
            
            // Urgency-Mapping für Sortierung
            $urgencyOrder = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
            
            $pendingZugriffe = $pendingZugriffe->sort(function($a, $b) use ($urgencyOrder) {
                $urgencyA = $urgencyOrder[$a->urgency] ?? 0;
                $urgencyB = $urgencyOrder[$b->urgency] ?? 0;
                
                if ($urgencyA === $urgencyB) {
                    return strtotime($a->created_at) - strtotime($b->created_at);
                }
                
                return $urgencyB - $urgencyA;
            })->values();
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'pending_zugriffe' => $pendingZugriffe,
                    'verification_delay_hours' => self::MIN_VERIFICATION_DELAY
                ]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Laden ausstehender Notfallzugriffe', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Laden der ausstehenden Zugriffe'], 500);
        }
    }

    /**
     * Genehmigt oder lehnt Notfallzugriff ab
     */
    public function approveAccess(int $id): void
    {
        try {
            $this->authorize(['notfall:approve']);
            
            $user = Auth::user();
            $zugriff = NotfallZugriff::find($id);
            
            if (!$zugriff || $zugriff->user_id !== $user->id) {
                $this->jsonResponse(['error' => 'Notfallzugriff nicht gefunden'], 404);
                return;
            }
            
            if (!in_array($zugriff->status, ['pending', 'verification_sent'])) {
                $this->jsonResponse(['error' => 'Zugriff kann nicht mehr genehmigt werden'], 400);
                return;
            }
            
            $rules = [
                'action' => 'required|string|in:approve,deny',
                'verification_code' => 'required|string',
                'reason' => 'string|max:500'
            ];
            
            $data = $this->validate($_POST, $rules);
            
            // Verifizierungscode prüfen
            if (!$zugriff->verifyCode($data['verification_code'])) {
                $this->jsonResponse(['error' => 'Ungültiger Verifizierungscode'], 400);
                return;
            }
            
            // Wartezeit prüfen (außer bei kritischen Fällen)
            if ($zugriff->urgency !== 'critical') {
                $hoursSinceRequest = (time() - strtotime($zugriff->created_at)) / 3600;
                if ($hoursSinceRequest < self::MIN_VERIFICATION_DELAY) {
                    $remainingHours = ceil(self::MIN_VERIFICATION_DELAY - $hoursSinceRequest);
                    $this->jsonResponse([
                        'error' => "Wartezeit noch nicht abgelaufen. Noch {$remainingHours} Stunden warten."
                    ], 400);
                    return;
                }
            }
            
            if ($data['action'] === 'approve') {
                // Zugriff genehmigen
                $zugriff->update([
                    'status' => 'active',
                    'approved_at' => date('Y-m-d H:i:s'),
                    'approved_by' => $user->id
                ]);
                
                // Notfallmodus aktivieren
                $user->update(['emergency_mode_active' => true]);
                
                $message = 'Notfallzugriff genehmigt';
                
                Logger::warning('Notfallzugriff genehmigt', [
                    'zugriff_id' => $zugriff->id,
                    'kontakt_id' => $zugriff->kontakt_id,
                    'user_id' => $user->id
                ]);
                
            } else {
                // Zugriff ablehnen
                $zugriff->update([
                    'status' => 'denied',
                    'denied_at' => date('Y-m-d H:i:s'),
                    'denied_by' => $user->id,
                    'denial_reason' => $data['reason'] ?? ''
                ]);
                
                $message = 'Notfallzugriff abgelehnt';
                
                Logger::info('Notfallzugriff abgelehnt', [
                    'zugriff_id' => $zugriff->id,
                    'reason' => $data['reason'] ?? '',
                    'user_id' => $user->id
                ]);
            }
            
            // Benachrichtigung senden
            $this->sendApprovalNotification($zugriff, $data['action'] === 'approve');
            
            $this->jsonResponse([
                'success' => true,
                'message' => $message,
                'data' => [
                    'zugriff' => $zugriff,
                    'emergency_mode_active' => $user->emergency_mode_active ?? false
                ]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler bei Genehmigung des Notfallzugriffs', [
                'zugriff_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler bei der Genehmigung'], 500);
        }
    }

    /**
     * Zugriff auf Daten über Notfalltoken
     */
    public function accessData(): void
    {
        try {
            $token = $_GET['token'] ?? $_POST['token'] ?? '';
            
            if (empty($token)) {
                $this->jsonResponse(['error' => 'Zugriffs-Token erforderlich'], 400);
                return;
            }
            
            $zugriff = NotfallZugriff::where('access_token', $token)
                ->where('status', 'active')
                ->where('expires_at', '>', date('Y-m-d H:i:s'))
                ->with(['kontakt', 'user'])
                ->first();
                
            if (!$zugriff) {
                $this->jsonResponse(['error' => 'Ungültiger oder abgelaufener Token'], 404);
                return;
            }
            
            $kontakt = $zugriff->kontakt;
            $user = $zugriff->user;
            
            // Zugriffszähler erhöhen und letzten Zugriff aktualisieren
            $zugriff->increment('access_count');
            $zugriff->update(['last_accessed_at' => date('Y-m-d H:i:s')]);
            
            // Verfügbare Daten basierend auf Berechtigungen
            $data = [
                'access_info' => [
                    'kontakt_name' => $kontakt->name,
                    'user_name' => $user->name,
                    'expires_at' => $zugriff->expires_at,
                    'access_count' => $zugriff->access_count,
                    'permissions' => [
                        'contracts' => $kontakt->can_access_contracts,
                        'credentials' => $kontakt->can_access_credentials,
                        'documents' => $kontakt->can_access_documents
                    ]
                ],
                'contracts' => [],
                'credentials' => [],
                'documents' => []
            ];
            
            // Verträge laden falls berechtigt
            if ($kontakt->can_access_contracts) {
                $data['contracts'] = Vertrag::where('user_id', $user->id)
                    ->select(['id', 'titel', 'korrespondent_name', 'status', 'start_datum', 'ende_datum'])
                    ->get();
            }
            
            // Zugangsdaten laden falls berechtigt (ohne Passwörter)
            if ($kontakt->can_access_credentials) {
                $data['credentials'] = $user->zugangsdaten()
                    ->select(['id', 'titel', 'benutzername', 'url', 'notizen'])
                    ->get();
            }
            
            // Dokumente laden falls berechtigt
            if ($kontakt->can_access_documents) {
                $data['documents'] = $user->dokumente()
                    ->select(['id', 'titel', 'dateiname', 'dateityp', 'groesse', 'created_at'])
                    ->limit(50) // Begrenzte Anzahl für Performance
                    ->get();
            }
            
            Logger::info('Notfallzugriff auf Daten', [
                'zugriff_id' => $zugriff->id,
                'kontakt_email' => $kontakt->email,
                'user_id' => $user->id,
                'access_count' => $zugriff->access_count
            ]);
            
            $this->jsonResponse([
                'success' => true,
                'data' => $data
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Notfallzugriff auf Daten', [
                'error' => $e->getMessage(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Zugriff auf Daten'], 500);
        }
    }

    /**
     * Deaktiviert den Notfallmodus
     */
    public function deactivateEmergencyMode(): void
    {
        try {
            $this->authorize(['notfall:manage']);
            
            $user = Auth::user();
            
            if (!$user->emergency_mode_active) {
                $this->jsonResponse(['error' => 'Notfallmodus ist nicht aktiv'], 400);
                return;
            }
            
            // Notfallmodus deaktivieren
            $user->update(['emergency_mode_active' => false]);
            
            // Alle aktiven Notfallzugriffe beenden
            NotfallZugriff::where('user_id', $user->id)
                ->where('status', 'active')
                ->update([
                    'status' => 'deactivated',
                    'deactivated_at' => date('Y-m-d H:i:s')
                ]);
            
            Logger::warning('Notfallmodus deaktiviert', [
                'user_id' => $user->id
            ]);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Notfallmodus deaktiviert. Alle aktiven Zugriffe wurden beendet.'
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Deaktivieren des Notfallmodus', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Deaktivieren des Notfallmodus'], 500);
        }
    }

    /**
     * Zeigt Notfall-Statistiken
     */
    public function stats(): void
    {
        try {
            $this->authorize(['notfall:view']);
            
            $user = Auth::user();
            $timeframe = $_GET['timeframe'] ?? '365'; // Tage
            
            $startDate = date('Y-m-d H:i:s', strtotime("-{$timeframe} days"));
            
            $stats = [
                'kontakte' => [
                    'total' => NotfallKontakt::where('user_id', $user->id)->count(),
                    'verified' => NotfallKontakt::where('user_id', $user->id)
                        ->where('is_verified', true)->count(),
                    'unverified' => NotfallKontakt::where('user_id', $user->id)
                        ->where('is_verified', false)->count()
                ],
                'zugriffe' => [
                    'total' => NotfallZugriff::where('user_id', $user->id)
                        ->where('created_at', '>=', $startDate)->count(),
                    'approved' => NotfallZugriff::where('user_id', $user->id)
                        ->where('status', 'active')
                        ->where('created_at', '>=', $startDate)->count(),
                    'denied' => NotfallZugriff::where('user_id', $user->id)
                        ->where('status', 'denied')
                        ->where('created_at', '>=', $startDate)->count(),
                    'pending' => NotfallZugriff::where('user_id', $user->id)
                        ->whereIn('status', ['pending', 'verification_sent'])->count()
                ],
                'emergency_mode_active' => $user->emergency_mode_active ?? false,
                'last_emergency_access' => NotfallZugriff::where('user_id', $user->id)
                    ->where('status', 'active')
                    ->orderBy('created_at', 'desc')
                    ->value('created_at')
            ];
            
            // Monatsweise Statistiken
            $monthlyStats = [];
            for ($i = 11; $i >= 0; $i--) {
                $monthStart = date('Y-m-01', strtotime("-{$i} months"));
                $monthEnd = date('Y-m-t', strtotime("-{$i} months"));
                
                $monthlyStats[] = [
                    'month' => date('Y-m', strtotime($monthStart)),
                    'requests' => NotfallZugriff::where('user_id', $user->id)
                        ->whereBetween('created_at', [$monthStart, $monthEnd . ' 23:59:59'])
                        ->count(),
                    'approved' => NotfallZugriff::where('user_id', $user->id)
                        ->where('status', 'active')
                        ->whereBetween('created_at', [$monthStart, $monthEnd . ' 23:59:59'])
                        ->count()
                ];
            }
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'stats' => $stats,
                    'monthly_stats' => $monthlyStats,
                    'timeframe' => $timeframe
                ]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Laden der Notfall-Statistiken', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Laden der Statistiken'], 500);
        }
    }

    /**
     * Sendet Verifizierungs-E-Mail für Notfallkontakt
     */
    private function sendVerificationEmail(NotfallKontakt $kontakt): void
    {
        try {
            $user = $kontakt->user;
            $verificationUrl = url("/notfall/verify?token=" . $kontakt->verification_token);
            
            $subject = "Verifizierung als Notfallkontakt für {$user->name}";
            
            $message = "Hallo {$kontakt->name},\n\n";
            $message .= "Sie wurden von {$user->name} ({$user->email}) als Notfallkontakt eingetragen.\n\n";
            $message .= "Ihre Rolle: {$kontakt->relationship}\n";
            $message .= "Priorität: {$kontakt->priority}\n\n";
            $message .= "Berechtigungen:\n";
            
            if ($kontakt->can_access_contracts) $message .= "- Verträge einsehen\n";
            if ($kontakt->can_access_credentials) $message .= "- Zugangsdaten einsehen\n";
            if ($kontakt->can_access_documents) $message .= "- Dokumente einsehen\n";
            
            $message .= "\nBitte bestätigen Sie diese Rolle durch Klick auf folgenden Link:\n";
            $message .= $verificationUrl . "\n\n";
            $message .= "Dieser Link ist 24 Stunden gültig.\n\n";
            $message .= "Falls Sie diese E-Mail nicht erwartet haben, ignorieren Sie sie bitte.\n\n";
            $message .= "Mit freundlichen Grüßen\n";
            $message .= "Ihr Vertragsverwaltung-System";
            
            Mail::send($kontakt->email, $subject, $message);
            
        } catch (\Exception $e) {
            Logger::warning('Verifizierungs-E-Mail für Notfallkontakt konnte nicht gesendet werden', [
                'kontakt_id' => $kontakt->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Sendet Benachrichtigungen bei Notfallanfrage
     */
    private function sendEmergencyNotifications(NotfallZugriff $zugriff): void
    {
        try {
            $kontakt = $zugriff->kontakt;
            $user = $zugriff->user;
            
            // E-Mail an Hauptbenutzer
            $subject = "🚨 NOTFALLZUGRIFF ANGEFORDERT - {$kontakt->name}";
            
            $message = "ACHTUNG: Es wurde ein Notfallzugriff auf Ihre Vertragsverwaltung angefordert!\n\n";
            $message .= "Antragsteller: {$kontakt->name} ({$kontakt->email})\n";
            $message .= "Dringlichkeit: " . strtoupper($zugriff->urgency) . "\n";
            $message .= "Grund: {$zugriff->reason}\n\n";
            $message .= "Angefordert am: " . date('d.m.Y H:i:s') . "\n";
            $message .= "IP-Adresse: {$zugriff->request_ip}\n\n";
            
            if ($zugriff->urgency === 'critical') {
                $message .= "⚠️  KRITISCHER NOTFALL - Sofortige Genehmigung möglich\n\n";
            } else {
                $message .= "Wartezeit: " . self::MIN_VERIFICATION_DELAY . " Stunden\n\n";
            }
            
            $message .= "Verifizierungscode: {$zugriff->verification_code}\n\n";
            $message .= "Loggen Sie sich ein, um die Anfrage zu bearbeiten.\n\n";
            $message .= "Falls Sie diese Anfrage nicht erwartet haben, lehnen Sie sie umgehend ab!";
            
            Mail::send($user->email, $subject, $message);
            
            // SMS falls Telefonnummer vorhanden
            if ($user->phone) {
                $smsMessage = "🚨 NOTFALLZUGRIFF von {$kontakt->name}. Code: {$zugriff->verification_code}. Loggen Sie sich sofort ein!";
                // SMS::send($user->phone, $smsMessage);
            }
            
        } catch (\Exception $e) {
            Logger::warning('Notfall-Benachrichtigung konnte nicht gesendet werden', [
                'zugriff_id' => $zugriff->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Sendet Benachrichtigung über Genehmigungsstatus
     */
    private function sendApprovalNotification(NotfallZugriff $zugriff, bool $approved): void
    {
        try {
            $kontakt = $zugriff->kontakt;
            $user = $zugriff->user;
            
            if ($approved) {
                $subject = "✅ Notfallzugriff genehmigt";
                $message = "Hallo {$kontakt->name},\n\n";
                $message .= "Ihr Notfallzugriff wurde von {$user->name} genehmigt.\n\n";
                $message .= "Zugriff über folgenden Link:\n";
                $message .= url("/notfall/access?token=" . $zugriff->access_token) . "\n\n";
                $message .= "Gültig bis: " . date('d.m.Y H:i:s', strtotime($zugriff->expires_at)) . "\n\n";
                $message .= "Verwenden Sie den Zugriff verantwortungsbewusst.";
            } else {
                $subject = "❌ Notfallzugriff abgelehnt";
                $message = "Hallo {$kontakt->name},\n\n";
                $message .= "Ihr Notfallzugriff wurde von {$user->name} abgelehnt.\n\n";
                
                if ($zugriff->denial_reason) {
                    $message .= "Grund: {$zugriff->denial_reason}\n\n";
                }
                
                $message .= "Bei weiteren Fragen wenden Sie sich direkt an {$user->name}.";
            }
            
            Mail::send($kontakt->email, $subject, $message);
            
        } catch (\Exception $e) {
            Logger::warning('Genehmigungs-Benachrichtigung konnte nicht gesendet werden', [
                'zugriff_id' => $zugriff->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Generiert einen 6-stelligen Verifizierungscode
     */
    private function generateVerificationCode(): string
    {
        return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}