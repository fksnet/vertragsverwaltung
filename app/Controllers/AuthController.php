<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\TOTP;
use App\Core\AuditLog;
use App\Models\User;

/**
 * Authentifizierungs-Controller für Login, Registrierung, 2FA etc.
 * 
 * @package App\Controllers
 * @author GenSpark AI Developer
 */
class AuthController extends BaseController
{
    /**
     * Login-Formular anzeigen
     */
    public function showLogin(): string
    {
        // Bereits angemeldet? Redirect zum Dashboard
        if (auth()) {
            $this->redirect('/dashboard');
        }
        
        return $this->view('pages/auth/login');
    }
    
    /**
     * Login verarbeiten
     */
    public function login(): void
    {
        try {
            $data = $this->validate([
                'email' => ['required', 'email'],
                'password' => ['required', 'min:6']
            ]);
            
            $user = User::findByEmail($data['email']);
            
            if (!$user || !password_verify($data['password'], $user['passwort_hash'])) {
                // Audit Log für fehlgeschlagene Logins
                AuditLog::log(
                    'login_failed',
                    'user',
                    null,
                    ['email' => $data['email'], 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']
                );
                
                throw new \Exception('Ungültige E-Mail oder Passwort');
            }
            
            if (!$user['aktiv']) {
                throw new \Exception('Ihr Account wurde deaktiviert');
            }
            
            // 2FA prüfen falls aktiviert
            if (!empty($user['2fa_secret_enc'])) {
                if (empty($_POST['totp_code'])) {
                    // TOTP-Code anfordern
                    $_SESSION['login_user_id'] = $user['id'];
                    $this->redirectWithMessage('/login?2fa=1', 'Bitte geben Sie Ihren 2FA-Code ein', 'info');
                }
                
                $totpCode = $_POST['totp_code'];
                $secret = decrypt($user['2fa_secret_enc']);
                
                if (!TOTP::verify($secret, $totpCode)) {
                    AuditLog::log('2fa_failed', 'user', $user['id']);
                    throw new \Exception('Ungültiger 2FA-Code');
                }
            }
            
            // Login erfolgreich
            Auth::login($user);
            
            AuditLog::log('login_success', 'user', $user['id'], [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            
            // Redirect zum ursprünglichen Ziel oder Dashboard
            $redirectTo = $_SESSION['intended_url'] ?? '/dashboard';
            unset($_SESSION['intended_url'], $_SESSION['login_user_id']);
            
            $this->redirect($redirectTo);
            
        } catch (\Exception $e) {
            $this->redirectWithMessage('/login', $e->getMessage(), 'error');
        }
    }
    
    /**
     * Registrierungs-Formular anzeigen
     */
    public function showRegister(): string
    {
        // Nur wenn Registrierung erlaubt
        if (!config('auth.allow_registration', false)) {
            http_response_code(404);
            return $this->view('errors/404');
        }
        
        return $this->view('pages/auth/register');
    }
    
    /**
     * Registrierung verarbeiten
     */
    public function register(): void
    {
        if (!config('auth.allow_registration', false)) {
            http_response_code(403);
            $this->json(['error' => 'Registrierung nicht erlaubt'], 403);
        }
        
        try {
            $data = $this->validate([
                'name' => ['required', 'min:2', 'max:100'],
                'email' => ['required', 'email'],
                'password' => ['required', 'min:8'],
                'password_confirmation' => ['required']
            ]);
            
            // Passwort-Bestätigung prüfen
            if ($data['password'] !== $data['password_confirmation']) {
                throw new \Exception('Passwörter stimmen nicht überein');
            }
            
            // E-Mail bereits vergeben?
            if (User::findByEmail($data['email'])) {
                throw new \Exception('E-Mail-Adresse bereits vergeben');
            }
            
            // Passwort-Stärke prüfen
            if (!$this->isStrongPassword($data['password'])) {
                throw new \Exception('Passwort ist nicht sicher genug. Verwenden Sie mindestens 8 Zeichen mit Groß- und Kleinbuchstaben, Zahlen und Sonderzeichen.');
            }
            
            // Standard-Rolle für neue Benutzer
            $defaultRoleId = db()->query(
                "SELECT id FROM rollen WHERE name = 'benutzer' LIMIT 1"
            )->fetchColumn();
            
            if (!$defaultRoleId) {
                throw new \Exception('Standard-Benutzerrolle nicht gefunden');
            }
            
            // Benutzer erstellen
            $userId = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'passwort_hash' => password_hash($data['password'], PASSWORD_ARGON2ID),
                'rolle_id' => $defaultRoleId,
                'aktiv' => true,
                'angelegt_am' => date('Y-m-d H:i:s')
            ]);
            
            AuditLog::log('user_registered', 'user', $userId, ['email' => $data['email']]);
            
            $this->redirectWithMessage('/login', 'Registrierung erfolgreich! Sie können sich jetzt anmelden.', 'success');
            
        } catch (\Exception $e) {
            $this->redirectWithMessage('/register', $e->getMessage(), 'error');
        }
    }
    
    /**
     * Passwort vergessen - Formular
     */
    public function showForgotPassword(): string
    {
        return $this->view('pages/auth/forgot-password');
    }
    
    /**
     * Passwort-Reset-E-Mail senden
     */
    public function forgotPassword(): void
    {
        try {
            $data = $this->validate([
                'email' => ['required', 'email']
            ]);
            
            $user = User::findByEmail($data['email']);
            
            if ($user) {
                // Reset-Token generieren und speichern
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', time() + 3600); // 1 Stunde gültig
                
                db()->query(
                    "INSERT INTO password_resets (email, token, created_at, expires_at) 
                     VALUES (?, ?, NOW(), ?) 
                     ON DUPLICATE KEY UPDATE token = ?, created_at = NOW(), expires_at = ?",
                    [$data['email'], $token, $expires, $token, $expires]
                );
                
                // E-Mail senden (TODO: Implementierung)
                // Mail::send('emails.password-reset', ['token' => $token], $data['email']);
                
                AuditLog::log('password_reset_requested', 'user', $user['id']);
            }
            
            // Immer Success-Nachricht anzeigen (Security)
            $this->redirectWithMessage(
                '/forgot-password', 
                'Falls diese E-Mail-Adresse registriert ist, wurde ein Reset-Link gesendet.', 
                'success'
            );
            
        } catch (\Exception $e) {
            $this->redirectWithMessage('/forgot-password', $e->getMessage(), 'error');
        }
    }
    
    /**
     * Passwort-Reset-Formular anzeigen
     */
    public function showResetPassword(): string
    {
        $token = $_GET['token'] ?? '';
        
        if (empty($token)) {
            $this->redirect('/forgot-password');
        }
        
        // Token validieren
        $reset = db()->query(
            "SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW() LIMIT 1",
            [$token]
        )->fetch();
        
        if (!$reset) {
            $this->redirectWithMessage('/forgot-password', 'Ungültiger oder abgelaufener Reset-Link', 'error');
        }
        
        return $this->view('pages/auth/reset-password', ['token' => $token]);
    }
    
    /**
     * Neues Passwort setzen
     */
    public function resetPassword(): void
    {
        try {
            $data = $this->validate([
                'token' => 'required',
                'password' => ['required', 'min:8'],
                'password_confirmation' => 'required'
            ]);
            
            if ($data['password'] !== $data['password_confirmation']) {
                throw new \Exception('Passwörter stimmen nicht überein');
            }
            
            if (!$this->isStrongPassword($data['password'])) {
                throw new \Exception('Passwort ist nicht sicher genug');
            }
            
            // Token validieren
            $reset = db()->query(
                "SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW() LIMIT 1",
                [$data['token']]
            )->fetch();
            
            if (!$reset) {
                throw new \Exception('Ungültiger oder abgelaufener Reset-Link');
            }
            
            // Passwort aktualisieren
            $user = User::findByEmail($reset['email']);
            if (!$user) {
                throw new \Exception('Benutzer nicht gefunden');
            }
            
            User::update($user['id'], [
                'passwort_hash' => password_hash($data['password'], PASSWORD_ARGON2ID)
            ]);
            
            // Token löschen
            db()->query("DELETE FROM password_resets WHERE token = ?", [$data['token']]);
            
            AuditLog::log('password_reset_completed', 'user', $user['id']);
            
            $this->redirectWithMessage('/login', 'Passwort erfolgreich zurückgesetzt', 'success');
            
        } catch (\Exception $e) {
            $this->redirectWithMessage('/reset-password?token=' . ($_POST['token'] ?? ''), $e->getMessage(), 'error');
        }
    }
    
    /**
     * Logout
     */
    public function logout(): void
    {
        $user = auth();
        if ($user) {
            AuditLog::log('logout', 'user', $user['id']);
        }
        
        Auth::logout();
        
        $this->redirect('/');
    }
    
    /**
     * Passwort-Stärke prüfen
     */
    private function isStrongPassword(string $password): bool
    {
        return strlen($password) >= 8 &&
               preg_match('/[a-z]/', $password) &&
               preg_match('/[A-Z]/', $password) &&
               preg_match('/[0-9]/', $password) &&
               preg_match('/[^a-zA-Z0-9]/', $password);
    }
}