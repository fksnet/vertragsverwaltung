<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Zugangsdaten;
use App\Models\Korrespondent;
use App\Models\Vertrag;
use App\Core\TOTP;

/**
 * Zugangsdaten Controller für sichere Credential-Verwaltung
 * 
 * @package App\Controllers
 * @author GenSpark AI Developer
 */
class ZugangsdatenController extends BaseController
{
    /**
     * Zugangsdaten-Liste anzeigen
     */
    public function index(): string
    {
        $this->authorize('read', 'zugangsdaten');
        
        // Filter aus Request
        $filters = [
            'search' => $_GET['q'] ?? '',
            'korrespondent_id' => $_GET['korrespondent_id'] ?? '',
            'vertrag_id' => $_GET['vertrag_id'] ?? '',
            'page' => (int) ($_GET['page'] ?? 1),
            'limit' => 20
        ];
        
        // Zugangsdaten laden
        $zugangsdaten = Zugangsdaten::all($filters);
        $paginatedData = $this->paginate($zugangsdaten, $filters['limit']);
        
        // Korrespondenten und Verträge für Filter
        $korrespondenten = Korrespondent::all(['limit' => 100]);
        $vertraege = Vertrag::all(['limit' => 100, 'status' => 'laufend']);
        
        // Für htmx-Requests nur die Tabelle zurückgeben
        if (!empty($_SERVER['HTTP_HX_REQUEST'])) {
            return $this->view('partials/zugangsdaten/table', [
                'zugangsdaten' => $paginatedData['data'],
                'pagination' => $paginatedData['pagination']
            ]);
        }
        
        return $this->view('pages/zugangsdaten/index', [
            'page_title' => 'Zugangsdaten',
            'zugangsdaten' => $paginatedData['data'],
            'pagination' => $paginatedData['pagination'],
            'filters' => $filters,
            'korrespondenten' => $korrespondenten,
            'vertraege' => $vertraege,
            'can_create' => rbac_check('create', 'zugangsdaten')
        ]);
    }
    
    /**
     * Zugangsdaten-Details anzeigen
     */
    public function show(): string
    {
        $id = (int) ($_GET['id'] ?? 0);
        $zugangsdaten = Zugangsdaten::find($id);
        
        if (!$zugangsdaten) {
            http_response_code(404);
            return $this->view('errors/404');
        }
        
        // Korrespondent und Vertrag laden
        $korrespondent = $zugangsdaten->getKorrespondent();
        $vertrag = $zugangsdaten->getVertrag();
        
        // Passwort-Stärke bewerten
        $passwordStrength = null;
        if (rbac_check('reveal', 'zugangsdaten', ['id' => $id])) {
            $passwordStrength = $zugangsdaten->getPasswordStrength();
        }
        
        return $this->view('pages/zugangsdaten/show', [
            'page_title' => 'Zugangsdaten: ' . $zugangsdaten->webseite,
            'zugangsdaten' => $zugangsdaten,
            'korrespondent' => $korrespondent,
            'vertrag' => $vertrag,
            'password_strength' => $passwordStrength,
            'can_edit' => rbac_check('update', 'zugangsdaten', ['id' => $id]),
            'can_delete' => rbac_check('delete', 'zugangsdaten', ['id' => $id]),
            'can_reveal' => rbac_check('reveal', 'zugangsdaten', ['id' => $id])
        ]);
    }
    
    /**
     * Neue Zugangsdaten erstellen - Formular
     */
    public function create(): string
    {
        $this->authorize('create', 'zugangsdaten');
        
        // Korrespondenten und Verträge für Dropdowns
        $korrespondenten = Korrespondent::all();
        $vertraege = Vertrag::all(['status' => 'laufend']);
        
        // Vorgefüllte Werte
        $preselected = [
            'korrespondent_id' => $_GET['korrespondent_id'] ?? null,
            'vertrag_id' => $_GET['vertrag_id'] ?? null
        ];
        
        return $this->view('pages/zugangsdaten/create', [
            'page_title' => 'Neue Zugangsdaten',
            'zugangsdaten' => null,
            'korrespondenten' => $korrespondenten,
            'vertraege' => $vertraege,
            'preselected' => $preselected
        ]);
    }
    
    /**
     * Neue Zugangsdaten speichern
     */
    public function store(): void
    {
        $this->authorize('create', 'zugangsdaten');
        
        try {
            $data = $this->validate([
                'korrespondent_id' => 'integer',
                'vertrag_id' => 'integer',
                'webseite' => ['required', 'max:255'],
                'benutzername' => ['required', 'max:255'],
                'passwort' => ['required', 'min:1'],
                'totp_qr_uri' => '',
                'scriptsnippet' => ''
            ]);
            
            // Recovery Codes verarbeiten
            $recoveryCodes = [];
            if (!empty($_POST['recovery_codes_text'])) {
                $lines = explode("\n", $_POST['recovery_codes_text']);
                foreach ($lines as $line) {
                    $code = trim($line);
                    if (!empty($code)) {
                        $recoveryCodes[] = $code;
                    }
                }
            }
            
            if (!empty($recoveryCodes)) {
                $data['recovery_codes'] = $recoveryCodes;
            }
            
            $zugangsdaten = Zugangsdaten::create($data);
            
            // Für htmx-Requests
            if (!empty($_SERVER['HTTP_HX_REQUEST'])) {
                $this->json([
                    'success' => true,
                    'message' => 'Zugangsdaten erfolgreich erstellt',
                    'redirect' => "/zugangsdaten/{$zugangsdaten->id}"
                ]);
                return;
            }
            
            $this->redirectWithMessage(
                "/zugangsdaten/{$zugangsdaten->id}",
                'Zugangsdaten erfolgreich erstellt',
                'success'
            );
            
        } catch (\Exception $e) {
            if (!empty($_SERVER['HTTP_HX_REQUEST'])) {
                $this->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 400);
                return;
            }
            
            $this->redirectWithMessage('/zugangsdaten/create', $e->getMessage(), 'error');
        }
    }
    
    /**
     * Zugangsdaten bearbeiten - Formular
     */
    public function edit(): string
    {
        $id = (int) ($_GET['id'] ?? 0);
        $zugangsdaten = Zugangsdaten::find($id);
        
        if (!$zugangsdaten) {
            http_response_code(404);
            return $this->view('errors/404');
        }
        
        $this->authorize('update', 'zugangsdaten', ['id' => $id]);
        
        // Korrespondenten und Verträge für Dropdowns
        $korrespondenten = Korrespondent::all();
        $vertraege = Vertrag::all(['status' => 'laufend']);
        
        return $this->view('pages/zugangsdaten/edit', [
            'page_title' => 'Zugangsdaten bearbeiten',
            'zugangsdaten' => $zugangsdaten,
            'korrespondenten' => $korrespondenten,
            'vertraege' => $vertraege
        ]);
    }
    
    /**
     * Zugangsdaten aktualisieren
     */
    public function update(): void
    {
        $id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
        $zugangsdaten = Zugangsdaten::find($id);
        
        if (!$zugangsdaten) {
            http_response_code(404);
            if (!empty($_SERVER['HTTP_HX_REQUEST'])) {
                $this->json(['success' => false, 'message' => 'Zugangsdaten nicht gefunden'], 404);
                return;
            }
            $this->redirect('/zugangsdaten');
            return;
        }
        
        $this->authorize('update', 'zugangsdaten', ['id' => $id]);
        
        try {
            $data = $this->validate([
                'korrespondent_id' => 'integer',
                'vertrag_id' => 'integer',
                'webseite' => ['required', 'max:255'],
                'benutzername' => ['required', 'max:255'],
                'totp_qr_uri' => '',
                'scriptsnippet' => ''
            ]);
            
            // Passwort nur aktualisieren wenn angegeben
            if (!empty($_POST['passwort'])) {
                $data['passwort'] = $_POST['passwort'];
            }
            
            // Recovery Codes verarbeiten
            if (isset($_POST['recovery_codes_text'])) {
                $recoveryCodes = [];
                if (!empty($_POST['recovery_codes_text'])) {
                    $lines = explode("\n", $_POST['recovery_codes_text']);
                    foreach ($lines as $line) {
                        $code = trim($line);
                        if (!empty($code)) {
                            $recoveryCodes[] = $code;
                        }
                    }
                }
                $data['recovery_codes'] = $recoveryCodes;
            }
            
            $zugangsdaten->update($data);
            
            if (!empty($_SERVER['HTTP_HX_REQUEST'])) {
                $this->json([
                    'success' => true,
                    'message' => 'Zugangsdaten erfolgreich aktualisiert',
                    'redirect' => "/zugangsdaten/{$zugangsdaten->id}"
                ]);
                return;
            }
            
            $this->redirectWithMessage(
                "/zugangsdaten/{$zugangsdaten->id}",
                'Zugangsdaten erfolgreich aktualisiert',
                'success'
            );
            
        } catch (\Exception $e) {
            if (!empty($_SERVER['HTTP_HX_REQUEST'])) {
                $this->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 400);
                return;
            }
            
            $this->redirectWithMessage("/zugangsdaten/{$id}/edit", $e->getMessage(), 'error');
        }
    }
    
    /**
     * Zugangsdaten löschen
     */
    public function delete(): void
    {
        $id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
        $zugangsdaten = Zugangsdaten::find($id);
        
        if (!$zugangsdaten) {
            http_response_code(404);
            if (!empty($_SERVER['HTTP_HX_REQUEST'])) {
                $this->json(['success' => false, 'message' => 'Zugangsdaten nicht gefunden'], 404);
                return;
            }
            $this->redirect('/zugangsdaten');
            return;
        }
        
        $this->authorize('delete', 'zugangsdaten', ['id' => $id]);
        
        try {
            $webseite = $zugangsdaten->webseite;
            $zugangsdaten->delete();
            
            if (!empty($_SERVER['HTTP_HX_REQUEST'])) {
                $this->json([
                    'success' => true,
                    'message' => "Zugangsdaten für '$webseite' erfolgreich gelöscht",
                    'redirect' => '/zugangsdaten'
                ]);
                return;
            }
            
            $this->redirectWithMessage(
                '/zugangsdaten',
                "Zugangsdaten für '$webseite' erfolgreich gelöscht",
                'success'
            );
            
        } catch (\Exception $e) {
            if (!empty($_SERVER['HTTP_HX_REQUEST'])) {
                $this->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 400);
                return;
            }
            
            $this->redirectWithMessage("/zugangsdaten/{$id}", $e->getMessage(), 'error');
        }
    }
    
    /**
     * Passwort entschlüsseln und anzeigen
     */
    public function revealPassword(): void
    {
        $id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
        $zugangsdaten = Zugangsdaten::find($id);
        
        if (!$zugangsdaten) {
            $this->json(['success' => false, 'message' => 'Zugangsdaten nicht gefunden'], 404);
            return;
        }
        
        try {
            $password = $zugangsdaten->revealPassword();
            $totpUri = $zugangsdaten->revealTotpUri();
            $recoveryCodes = $zugangsdaten->revealRecoveryCodes();
            $scriptSnippet = $zugangsdaten->revealScriptSnippet();
            
            $this->json([
                'success' => true,
                'password' => $password,
                'totp_uri' => $totpUri,
                'recovery_codes' => $recoveryCodes,
                'script_snippet' => $scriptSnippet,
                'password_strength' => $zugangsdaten->getPasswordStrength()
            ]);
            
        } catch (\Exception $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 403);
        }
    }
    
    /**
     * Passwort in Zwischenablage kopieren
     */
    public function copyPassword(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $zugangsdaten = Zugangsdaten::find($id);
        
        if (!$zugangsdaten) {
            $this->json(['success' => false, 'message' => 'Zugangsdaten nicht gefunden'], 404);
            return;
        }
        
        try {
            $password = $zugangsdaten->revealPassword();
            
            $this->json([
                'success' => true,
                'password' => $password,
                'message' => 'Passwort in Zwischenablage kopiert'
            ]);
            
        } catch (\Exception $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 403);
        }
    }
    
    /**
     * TOTP QR-Code generieren
     */
    public function generateTotp(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $zugangsdaten = Zugangsdaten::find($id);
        
        if (!$zugangsdaten) {
            $this->json(['success' => false, 'message' => 'Zugangsdaten nicht gefunden'], 404);
            return;
        }
        
        $this->authorize('update', 'zugangsdaten', ['id' => $id]);
        
        try {
            $serviceName = $zugangsdaten->webseite;
            $accountName = $zugangsdaten->benutzername;
            
            $secret = TOTP::generateSecret();
            $qrUri = TOTP::getQRCodeUrl($serviceName, $accountName, $secret);
            
            // Temporär speichern (wird erst bei Update persistent)
            $_SESSION['temp_totp_secret'] = $secret;
            
            $this->json([
                'success' => true,
                'secret' => $secret,
                'qr_uri' => $qrUri,
                'manual_entry_key' => chunk_split($secret, 4, ' ')
            ]);
            
        } catch (\Exception $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Recovery Codes generieren
     */
    public function generateRecoveryCodes(): void
    {
        $this->authorize('update', 'zugangsdaten');
        
        try {
            $codes = [];
            for ($i = 0; $i < 10; $i++) {
                $codes[] = strtoupper(bin2hex(random_bytes(4))) . '-' . strtoupper(bin2hex(random_bytes(4)));
            }
            
            $this->json([
                'success' => true,
                'recovery_codes' => $codes
            ]);
            
        } catch (\Exception $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Passwort-Stärke bewerten
     */
    public function checkPasswordStrength(): void
    {
        $password = $_POST['password'] ?? '';
        
        if (empty($password)) {
            $this->json(['strength' => ['score' => 0, 'label' => 'Kein Passwort']]);
            return;
        }
        
        $score = 0;
        $feedback = [];
        
        // Länge prüfen
        if (strlen($password) >= 12) $score += 3;
        elseif (strlen($password) >= 8) $score += 2;
        elseif (strlen($password) >= 6) $score += 1;
        else $feedback[] = 'Zu kurz (min. 8 Zeichen)';
        
        // Zeichen-Arten prüfen
        if (preg_match('/[a-z]/', $password)) $score += 1;
        else $feedback[] = 'Kleinbuchstaben fehlen';
        
        if (preg_match('/[A-Z]/', $password)) $score += 1;
        else $feedback[] = 'Großbuchstaben fehlen';
        
        if (preg_match('/[0-9]/', $password)) $score += 1;
        else $feedback[] = 'Ziffern fehlen';
        
        if (preg_match('/[^a-zA-Z0-9]/', $password)) $score += 1;
        else $feedback[] = 'Sonderzeichen fehlen';
        
        // Häufige Muster prüfen
        if (preg_match('/(.)\1{2,}/', $password)) {
            $score -= 1;
            $feedback[] = 'Wiederholende Zeichen vermeiden';
        }
        
        if (preg_match('/123|abc|qwe|password|admin/i', $password)) {
            $score -= 2;
            $feedback[] = 'Häufige Muster vermeiden';
        }
        
        // Bewertung
        $label = match (true) {
            $score >= 7 => 'Sehr stark',
            $score >= 5 => 'Stark', 
            $score >= 3 => 'Mittel',
            $score >= 1 => 'Schwach',
            default => 'Sehr schwach'
        };
        
        $this->json([
            'strength' => [
                'score' => max(0, $score),
                'max_score' => 8,
                'percentage' => round((max(0, $score) / 8) * 100),
                'label' => $label,
                'feedback' => $feedback
            ]
        ]);
    }
    
    /**
     * Export Zugangsdaten als verschlüsseltes JSON
     */
    public function export(): void
    {
        $this->authorize('export', 'zugangsdaten');
        
        try {
            $zugangsdaten = Zugangsdaten::all();
            $exportData = [];
            
            foreach ($zugangsdaten as $item) {
                if (rbac_check('export', 'zugangsdaten', ['id' => $item->id])) {
                    $exportData[] = $item->toExportArray();
                }
            }
            
            $filename = 'zugangsdaten_export_' . date('Y-m-d_H-i-s') . '.json';
            
            header('Content-Type: application/json');
            header("Content-Disposition: attachment; filename=\"$filename\"");
            
            echo json_encode([
                'exported_at' => date('Y-m-d H:i:s'),
                'count' => count($exportData),
                'data' => $exportData
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}