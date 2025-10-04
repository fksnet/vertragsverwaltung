<?php

/**
 * Web Routes für Vertragsverwaltung
 * 
 * @package Routes
 * @author GenSpark AI Developer
 */

// Simple routes array for compatibility with current Application class
return [
    // Public Routes (ohne Auth)
    '/' => 'HomeController@index',
    'home' => 'HomeController@index',
    'dashboard' => 'DashboardController@index',
    
    // Test Route
    'test' => 'TestController@index',
    
    // Auth Routes (öffentlich)
    'login' => 'AuthController@showLogin',
    'register' => 'AuthController@showRegister',
    
    // Admin Routes
    'admin' => 'AdminController@index',
    'admin/users' => 'Admin\BenutzerController@index',
    
    // Korrespondenten
    'korrespondenten' => 'KorrespondentenController@index',
    
    // Verträge  
    'vertraege' => 'VertraegeController@index',
    
    // Zugangsdaten
    'zugangsdaten' => 'ZugangsdatenController@index',
];

    // Weitere Routes können hier hinzugefügt werden
    // Diese vereinfachte Struktur ist kompatibel mit der aktuellen Application-Klasse