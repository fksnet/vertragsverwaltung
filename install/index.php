<?php
/**
 * Vertragsverwaltung - Installations-Wizard
 * 
 * Einfacher Installations-Wizard nach dem Vorbild von WordPress.
 * Führt durch alle notwendigen Schritte der Installation.
 * 
 * @package Vertragsverwaltung
 * @version 1.0.0
 */

session_start();

// Debug-Modus aktivieren (kann später entfernt werden)
$debug = isset($_GET['debug']) || isset($_POST['debug']);
if ($debug) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Basis-Pfade
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('DATABASE_PATH', ROOT_PATH . '/database');

// Aktueller Schritt
$step = $_GET['step'] ?? $_POST['step'] ?? 'welcome';

// Bereits installiert?
if ($step !== 'complete' && file_exists(CONFIG_PATH . '/app.php')) {
    $config = include CONFIG_PATH . '/app.php';
    if (!empty($config['installed'])) {
        header('Location: /');
        exit;
    }
}

// Session-Debug-Info
if ($debug) {
    echo '<div style="background: #f0f0f0; padding: 10px; margin: 10px; border: 1px solid #ccc;">';
    echo '<strong>Debug Info:</strong><br>';
    echo 'Current Step: ' . htmlspecialchars($step) . '<br>';
    echo 'Session ID: ' . session_id() . '<br>';
    echo 'Session Data: <pre>' . htmlspecialchars(print_r($_SESSION, true)) . '</pre>';
    echo 'POST Data: <pre>' . htmlspecialchars(print_r($_POST, true)) . '</pre>';
    echo 'GET Data: <pre>' . htmlspecialchars(print_r($_GET, true)) . '</pre>';
    echo '</div>';
}

// Fehler-Array
$errors = [];
$success = [];

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vertragsverwaltung - Installation</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 600px;
            width: 100%;
            overflow: hidden;
        }
        
        .header {
            background: #2563eb;
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2em;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
        }
        
        .content {
            padding: 40px;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }
        
        .step-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #e5e7eb;
            margin: 0 5px;
            transition: background 0.3s;
        }
        
        .step-dot.active {
            background: #2563eb;
        }
        
        .step-dot.completed {
            background: #10b981;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #374151;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e5e7eb;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #2563eb;
        }
        
        .btn {
            background: #2563eb;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            background: #1d4ed8;
        }
        
        .btn-secondary {
            background: #6b7280;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
        }
        
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }
        
        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        
        .alert-warning {
            background: #fffbeb;
            color: #92400e;
            border: 1px solid #fed7aa;
        }
        
        .requirements-grid {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .req-item {
            padding: 10px;
            background: #f9fafb;
            border-radius: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .status-ok {
            color: #10b981;
            font-weight: bold;
        }
        
        .status-error {
            color: #dc2626;
            font-weight: bold;
        }
        
        .navigation {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
        
        .progress-bar {
            width: 100%;
            height: 6px;
            background: #e5e7eb;
            border-radius: 3px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: #2563eb;
            transition: width 0.3s;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Vertragsverwaltung</h1>
            <p>Installations-Assistent</p>
        </div>
        
        <div class="content">
            <?php
            // Fortschrittsberechnung
            $steps = ['welcome', 'requirements', 'database', 'admin', 'settings', 'complete'];
            $currentStepIndex = array_search($step, $steps);
            $progress = ($currentStepIndex / (count($steps) - 1)) * 100;
            ?>
            
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?= $progress ?>%"></div>
            </div>
            
            <div class="step-indicator">
                <?php foreach ($steps as $i => $s): ?>
                    <div class="step-dot <?= $i < $currentStepIndex ? 'completed' : ($i === $currentStepIndex ? 'active' : '') ?>"></div>
                <?php endforeach; ?>
            </div>
            
            <?php
            // Schritt-Validierung
            $validSteps = ['welcome', 'requirements', 'database', 'admin', 'settings', 'complete'];
            if (!in_array($step, $validSteps)) {
                $step = 'welcome';
            }
            
            // Schritt-spezifischer Content
            switch ($step) {
                case 'welcome':
                    include __DIR__ . '/steps/welcome.php';
                    break;
                case 'requirements':
                    include __DIR__ . '/steps/requirements.php';
                    break;
                case 'database':
                    include __DIR__ . '/steps/database.php';
                    break;
                case 'admin':
                    include __DIR__ . '/steps/admin.php';
                    break;
                case 'settings':
                    include __DIR__ . '/steps/settings.php';
                    break;
                case 'complete':
                    include __DIR__ . '/steps/complete.php';
                    break;
                default:
                    include __DIR__ . '/steps/welcome.php';
            }
            ?>
        </div>
    </div>
</body>
</html>