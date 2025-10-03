<?php
/**
 * Installation Step 4: Admin User Setup
 */

$errors = [];
$success = [];

// Admin-Benutzer erstellen
if ($_POST && isset($_POST['create_admin'])) {
    $name = trim($_POST['admin_name'] ?? '');
    $email = trim($_POST['admin_email'] ?? '');
    $password = $_POST['admin_password'] ?? '';
    $passwordConfirm = $_POST['admin_password_confirm'] ?? '';
    
    // Validierung
    if (empty($name)) $errors[] = 'Name ist erforderlich';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Gültige E-Mail-Adresse ist erforderlich';
    if (strlen($password) < 8) $errors[] = 'Passwort muss mindestens 8 Zeichen lang sein';
    if ($password !== $passwordConfirm) $errors[] = 'Passwörter stimmen nicht überein';
    
    // Passwort-Stärke prüfen
    if (!empty($password)) {
        $strength = 0;
        if (preg_match('/[a-z]/', $password)) $strength++;
        if (preg_match('/[A-Z]/', $password)) $strength++;
        if (preg_match('/[0-9]/', $password)) $strength++;
        if (preg_match('/[^A-Za-z0-9]/', $password)) $strength++;
        
        if ($strength < 3) {
            $errors[] = 'Passwort ist zu schwach. Verwenden Sie Groß- und Kleinbuchstaben, Zahlen und Sonderzeichen.';
        }
    }
    
    if (empty($errors)) {
        $_SESSION['admin_user'] = [
            'name' => $name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_ARGON2ID)
        ];
        
        $success[] = 'Administrator-Konto konfiguriert!';
    }
}

// Redirect-Verarbeitung wurde in den Hauptwizard verschoben

// Standardwerte
$name = $_POST['admin_name'] ?? $_SESSION['admin_user']['name'] ?? '';
$email = $_POST['admin_email'] ?? $_SESSION['admin_user']['email'] ?? '';

?>

<h2>Administrator-Konto erstellen</h2>

<p>Erstellen Sie das erste Administrator-Konto für die Vertragsverwaltung.</p>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <strong>Fehler:</strong>
        <ul style="margin-top: 10px; padding-left: 20px;">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success">
        <?php foreach ($success as $msg): ?>
            <div><?= htmlspecialchars($msg) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="alert alert-warning">
    <strong>Wichtig:</strong> Dieses Konto erhält vollständige Administratorrechte. 
    Verwenden Sie ein starkes Passwort und bewahren Sie die Zugangsdaten sicher auf.
</div>

<form method="post">
    <div class="form-group">
        <label for="admin_name">Vollständiger Name:</label>
        <input type="text" id="admin_name" name="admin_name" value="<?= htmlspecialchars($name) ?>" 
               placeholder="Max Mustermann" required>
    </div>
    
    <div class="form-group">
        <label for="admin_email">E-Mail-Adresse:</label>
        <input type="email" id="admin_email" name="admin_email" value="<?= htmlspecialchars($email) ?>" 
               placeholder="admin@example.com" required>
        <small style="color: #6b7280;">Diese E-Mail wird für Anmeldung und Benachrichtigungen verwendet</small>
    </div>
    
    <div class="form-group">
        <label for="admin_password">Passwort:</label>
        <input type="password" id="admin_password" name="admin_password" 
               placeholder="Sicheres Passwort eingeben" required minlength="8">
        <small style="color: #6b7280;">Mindestens 8 Zeichen mit Groß-/Kleinbuchstaben, Zahlen und Sonderzeichen</small>
    </div>
    
    <div class="form-group">
        <label for="admin_password_confirm">Passwort bestätigen:</label>
        <input type="password" id="admin_password_confirm" name="admin_password_confirm" 
               placeholder="Passwort wiederholen" required>
    </div>
    
    <div style="margin-bottom: 20px;">
        <button type="submit" name="create_admin" class="btn">
            👤 Administrator-Konto erstellen
        </button>
        
        <?php if (!empty($success)): ?>
            <button type="submit" name="continue" class="btn" style="margin-left: 10px;">
                ✓ Weiter
            </button>
        <?php endif; ?>
    </div>
</form>

<h3>Sicherheitshinweise</h3>
<div style="background: #f9fafb; padding: 15px; border-radius: 6px;">
    <ul style="margin: 0; padding-left: 20px;">
        <li><strong>Starkes Passwort:</strong> Mindestens 12 Zeichen empfohlen</li>
        <li><strong>Zwei-Faktor-Authentifizierung:</strong> Kann nach der Installation aktiviert werden</li>
        <li><strong>E-Mail-Sicherheit:</strong> Verwenden Sie eine sichere E-Mail-Adresse</li>
        <li><strong>Regelmäßige Updates:</strong> Ändern Sie das Passwort regelmäßig</li>
    </ul>
</div>

<div class="navigation">
    <a href="?step=database" class="btn btn-secondary">← Zurück</a>
    <?php if (isset($_SESSION['admin_user'])): ?>
        <a href="?step=settings" class="btn">Weiter →</a>
    <?php endif; ?>
</div>

<script>
// Passwort-Stärke-Anzeige
document.getElementById('admin_password').addEventListener('input', function() {
    const password = this.value;
    let strength = 0;
    let feedback = [];
    
    if (password.length >= 8) strength++; else feedback.push('mindestens 8 Zeichen');
    if (/[a-z]/.test(password)) strength++; else feedback.push('Kleinbuchstaben');
    if (/[A-Z]/.test(password)) strength++; else feedback.push('Großbuchstaben');
    if (/[0-9]/.test(password)) strength++; else feedback.push('Zahlen');
    if (/[^A-Za-z0-9]/.test(password)) strength++; else feedback.push('Sonderzeichen');
    
    let strengthText = '';
    let strengthColor = '';
    
    if (strength < 2) {
        strengthText = 'Sehr schwach';
        strengthColor = '#dc2626';
    } else if (strength < 3) {
        strengthText = 'Schwach';
        strengthColor = '#f59e0b';
    } else if (strength < 4) {
        strengthText = 'Mittel';
        strengthColor = '#10b981';
    } else {
        strengthText = 'Stark';
        strengthColor = '#059669';
    }
    
    // Feedback anzeigen
    let feedbackElement = document.getElementById('password-feedback');
    if (!feedbackElement) {
        feedbackElement = document.createElement('div');
        feedbackElement.id = 'password-feedback';
        feedbackElement.style.marginTop = '5px';
        feedbackElement.style.fontSize = '14px';
        this.parentNode.appendChild(feedbackElement);
    }
    
    feedbackElement.innerHTML = `<span style="color: ${strengthColor}; font-weight: bold;">${strengthText}</span>`;
    if (feedback.length > 0 && strength < 4) {
        feedbackElement.innerHTML += `<br><small>Fehlt: ${feedback.join(', ')}</small>`;
    }
});
</script>