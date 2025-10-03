<?php

/**
 * Web Routes für Vertragsverwaltung
 * 
 * @package Routes
 * @author GenSpark AI Developer
 */

use App\Core\Router;

/** @var Router $router */

// Public Routes (ohne Auth)
$router->get('/', 'HomeController@index');

// Installer Routes
$router->group(['prefix' => 'installer'], function($router) {
    $router->get('/', 'InstallerController@welcome');
    $router->get('/requirements', 'InstallerController@requirements');
    $router->get('/database', 'InstallerController@database');
    $router->post('/database', 'InstallerController@testDatabase');
    $router->get('/config', 'InstallerController@config');
    $router->post('/config', 'InstallerController@saveConfig');
    $router->get('/admin', 'InstallerController@admin');
    $router->post('/admin', 'InstallerController@createAdmin');
    $router->get('/finish', 'InstallerController@finish');
});

// Auth Routes (öffentlich)
$router->get('/login', 'AuthController@showLogin');
$router->post('/login', 'AuthController@login');
$router->get('/register', 'AuthController@showRegister');
$router->post('/register', 'AuthController@register');
$router->get('/forgot-password', 'AuthController@showForgotPassword');
$router->post('/forgot-password', 'AuthController@forgotPassword');
$router->get('/reset-password/{token}', 'AuthController@showResetPassword');
$router->post('/reset-password', 'AuthController@resetPassword');

// Geschützte Routes (Auth erforderlich)
$router->group(['middleware' => ['csrf', 'auth']], function($router) {
    // Auth
    $router->post('/logout', 'AuthController@logout');
    
    // Dashboard
    $router->get('/dashboard', 'DashboardController@index');
    
    // Korrespondenten
    $router->group(['prefix' => 'korrespondenten'], function($router) {
        $router->get('/', 'KorrespondentenController@index');
        $router->get('/create', 'KorrespondentenController@create');
        $router->post('/', 'KorrespondentenController@store')->middleware(['csrf']);
        $router->get('/{id}', 'KorrespondentenController@show');
        $router->get('/{id}/edit', 'KorrespondentenController@edit');
        $router->put('/{id}', 'KorrespondentenController@update')->middleware(['csrf']);
        $router->delete('/{id}', 'KorrespondentenController@delete')->middleware(['csrf']);
    });
    
    // Verträge
    $router->group(['prefix' => 'vertraege'], function($router) {
        $router->get('/', 'VertraegeController@index');
        $router->get('/create', 'VertraegeController@create');
        $router->post('/', 'VertraegeController@store')->middleware(['csrf']);
        $router->get('/{id}', 'VertraegeController@show');
        $router->get('/{id}/edit', 'VertraegeController@edit');
        $router->put('/{id}', 'VertraegeController@update')->middleware(['csrf']);
        $router->delete('/{id}', 'VertraegeController@delete')->middleware(['csrf']);
        $router->get('/{id}/dokumente', 'VertraegeController@documents');
    });
    
    // Zugangsdaten
    $router->group(['prefix' => 'zugangsdaten'], function($router) {
        $router->get('/', 'ZugangsdatenController@index');
        $router->get('/create', 'ZugangsdatenController@create');
        $router->post('/', 'ZugangsdatenController@store')->middleware(['csrf']);
        $router->get('/{id}', 'ZugangsdatenController@show');
        $router->get('/{id}/edit', 'ZugangsdatenController@edit');
        $router->put('/{id}', 'ZugangsdatenController@update')->middleware(['csrf']);
        $router->delete('/{id}', 'ZugangsdatenController@delete')->middleware(['csrf']);
        $router->get('/{id}/reveal', 'ZugangsdatenController@revealPassword');
    });
    
    // Dokumente
    $router->group(['prefix' => 'dokumente'], function($router) {
        $router->get('/', 'DokumenteController@index');
        $router->post('/upload', 'DokumenteController@upload')->middleware(['csrf']);
        $router->get('/{id}', 'DokumenteController@show');
        $router->get('/{id}/download', 'DokumenteController@download');
        $router->delete('/{id}', 'DokumenteController@delete')->middleware(['csrf']);
    });
    
    // Liquiditätsplanung
    $router->get('/liquiditaet', 'LiquiditaetController@index');
    $router->get('/liquiditaet/data', 'LiquiditaetController@getData');
    $router->get('/liquiditaet/export', 'LiquiditaetController@exportCsv');
    
    // Freigaben
    $router->group(['prefix' => 'freigaben'], function($router) {
        $router->get('/', 'FreigabenController@index');
        $router->post('/', 'FreigabenController@store')->middleware(['csrf']);
        $router->delete('/{id}', 'FreigabenController@delete')->middleware(['csrf']);
    });
    
    // Notfall-Modus
    $router->get('/notfall', 'NotfallController@index');
    $router->post('/notfall/generate', 'NotfallController@generatePackage')->middleware(['csrf']);
    
    // 2FA Management
    $router->group(['prefix' => '2fa'], function($router) {
        $router->get('/setup', 'TwoFactorController@setup');
        $router->post('/enable', 'TwoFactorController@enable')->middleware(['csrf']);
        $router->post('/disable', 'TwoFactorController@disable')->middleware(['csrf']);
        $router->get('/recovery-codes', 'TwoFactorController@recoveryCodes');
    });
});

// Admin-spezifische Routes
$router->group(['middleware' => ['csrf', 'auth', 'rbac'], 'prefix' => 'admin'], function($router) {
    // Benutzerverwaltung
    $router->group(['prefix' => 'benutzer'], function($router) {
        $router->get('/', 'Admin\BenutzerController@index');
        $router->get('/create', 'Admin\BenutzerController@create');
        $router->post('/', 'Admin\BenutzerController@store')->middleware(['csrf']);
        $router->get('/{id}', 'Admin\BenutzerController@show');
        $router->get('/{id}/edit', 'Admin\BenutzerController@edit');
        $router->put('/{id}', 'Admin\BenutzerController@update')->middleware(['csrf']);
        $router->delete('/{id}', 'Admin\BenutzerController@delete')->middleware(['csrf']);
    });
    
    // Rollenverwaltung
    $router->group(['prefix' => 'rollen'], function($router) {
        $router->get('/', 'Admin\RollenController@index');
        $router->get('/create', 'Admin\RollenController@create');
        $router->post('/', 'Admin\RollenController@store')->middleware(['csrf']);
        $router->get('/{id}', 'Admin\RollenController@show');
        $router->get('/{id}/edit', 'Admin\RollenController@edit');
        $router->put('/{id}', 'Admin\RollenController@update')->middleware(['csrf']);
        $router->delete('/{id}', 'Admin\RollenController@delete')->middleware(['csrf']);
    });
    
    // Audit Log
    $router->get('/audit', 'Admin\AuditController@index');
    $router->get('/audit/{id}', 'Admin\AuditController@show');
    
    // System-Einstellungen
    $router->get('/settings', 'Admin\SettingsController@index');
    $router->post('/settings', 'Admin\SettingsController@update')->middleware(['csrf']);
});

// API Routes (für htmx)
$router->group(['prefix' => 'api', 'middleware' => ['csrf', 'auth']], function($router) {
    // Autocomplete/Search
    $router->get('/korrespondenten/search', 'Api\KorrespondentenController@search');
    $router->get('/vertraege/search', 'Api\VertraegeController@search');
    
    // Statistics für Dashboard
    $router->get('/stats/dashboard', 'Api\StatsController@dashboard');
    
    // Liquiditäts-Daten
    $router->get('/liquiditaet/timeline', 'Api\LiquiditaetController@timeline');
});