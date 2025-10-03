<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Home Controller für Startseite und öffentliche Bereiche
 * 
 * @package App\Controllers
 * @author GenSpark AI Developer
 */
class HomeController extends BaseController
{
    /**
     * Startseite anzeigen
     * 
     * Wenn der Benutzer angemeldet ist, redirect zum Dashboard.
     * Ansonsten zeige Landingpage oder Login-Form.
     */
    public function index(): string
    {
        // Wenn Benutzer bereits angemeldet, redirect zum Dashboard
        if (auth()) {
            $this->redirect('/dashboard');
        }
        
        // Prüfe ob .env existiert - falls nicht, redirect zum Installer
        if (!file_exists(app_path('../.env'))) {
            $this->redirect('/installer/');
        }
        
        return $this->view('pages/home/index');
    }
    
    /**
     * Über uns / Info Seite
     */
    public function about(): string
    {
        return $this->view('pages/home/about');
    }
    
    /**
     * Kontakt Seite
     */
    public function contact(): string
    {
        return $this->view('pages/home/contact');
    }
    
    /**
     * Datenschutz
     */
    public function privacy(): string
    {
        return $this->view('pages/home/privacy');
    }
    
    /**
     * Impressum
     */
    public function imprint(): string
    {
        return $this->view('pages/home/imprint');
    }
    
    /**
     * API Status für Health Checks
     */
    public function healthCheck(): void
    {
        $status = [
            'status' => 'ok',
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => config('app.version', '1.0.0'),
        ];
        
        // Database Connection Check
        try {
            $db = db();
            $db->query('SELECT 1')->fetchColumn();
            $status['database'] = 'connected';
        } catch (\Exception $e) {
            $status['database'] = 'error';
            $status['status'] = 'error';
        }
        
        $this->json($status, $status['status'] === 'ok' ? 200 : 500);
    }
}