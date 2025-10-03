<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Vertrag;
use App\Models\Korrespondent;
use App\Models\Zugangsdaten;
use App\Models\Dokument;

/**
 * Dashboard Controller mit KPIs und Übersichtskarten
 * 
 * @package App\Controllers
 * @author GenSpark AI Developer
 */
class DashboardController extends BaseController
{
    /**
     * Dashboard-Hauptseite anzeigen
     */
    public function index(): string
    {
        // RBAC-Check
        $this->authorize('read', 'dashboard');
        
        // KPIs berechnen
        $stats = $this->getStats();
        
        // Anstehende Kündigungsfristen
        $upcomingDeadlines = Vertrag::getUpcomingDeadlines(30);
        
        // Letzte Aktivitäten (vereinfacht - könnte später erweitert werden)
        $recentActivities = $this->getRecentActivities();
        
        // Liquiditäts-Übersicht für die nächsten Monate
        $liquidityOverview = $this->getLiquidityOverview();
        
        return $this->view('pages/dashboard/index', [
            'page_title' => 'Dashboard',
            'stats' => $stats,
            'upcoming_deadlines' => $upcomingDeadlines,
            'recent_activities' => $recentActivities,
            'liquidity_overview' => $liquidityOverview,
            'current_user' => auth()
        ]);
    }
    
    /**
     * Dashboard-Statistiken berechnen
     */
    private function getStats(): array
    {
        $user = auth();
        
        // Basis-Statistiken
        $stats = [
            'total_vertraege' => 0,
            'active_vertraege' => 0,
            'gekuendigte_vertraege' => 0,
            'total_korrespondenten' => 0,
            'monthly_costs_cents' => 0,
            'total_dokumente' => 0,
            'total_zugangsdaten' => 0,
            'deadlines_30_days' => 0,
            'deadlines_7_days' => 0
        ];
        
        try {
            // Verträge-Statistiken
            $vertraegeStats = db()->query(
                "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'laufend' THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = 'gekündigt' THEN 1 ELSE 0 END) as gekuendigt,
                    SUM(CASE 
                        WHEN status = 'laufend' THEN 
                            CASE 
                                WHEN kosten_zeitraum = 'monat' THEN kosten_cent
                                WHEN kosten_zeitraum = 'quartal' THEN kosten_cent / 3
                                WHEN kosten_zeitraum = 'jahr' THEN kosten_cent / 12
                                ELSE 0
                            END
                        ELSE 0
                    END) as monthly_costs
                FROM vertraege"
            )->fetch();
            
            if ($vertraegeStats) {
                $stats['total_vertraege'] = (int) $vertraegeStats['total'];
                $stats['active_vertraege'] = (int) $vertraegeStats['active'];
                $stats['gekuendigte_vertraege'] = (int) $vertraegeStats['gekuendigt'];
                $stats['monthly_costs_cents'] = (int) $vertraegeStats['monthly_costs'];
            }
            
            // Korrespondenten
            $stats['total_korrespondenten'] = (int) db()->query("SELECT COUNT(*) FROM korrespondenten")->fetchColumn();
            
            // Dokumente
            $stats['total_dokumente'] = (int) db()->query("SELECT COUNT(*) FROM dokumente")->fetchColumn();
            
            // Zugangsdaten
            $stats['total_zugangsdaten'] = (int) db()->query("SELECT COUNT(*) FROM zugangsdaten")->fetchColumn();
            
            // Kündigungsfristen
            $deadlines = db()->query(
                "SELECT 
                    SUM(CASE 
                        WHEN (v.ende IS NOT NULL AND DATEDIFF(DATE_SUB(v.ende, INTERVAL v.kuendigungsfrist_tage DAY), CURDATE()) BETWEEN 0 AND 30)
                        OR (v.ende IS NULL AND v.laufzeit_monate IS NOT NULL AND DATEDIFF(DATE_ADD(v.beginn, INTERVAL v.laufzeit_monate MONTH - INTERVAL v.kuendigungsfrist_tage DAY), CURDATE()) BETWEEN 0 AND 30)
                        THEN 1 ELSE 0 
                    END) as deadlines_30,
                    SUM(CASE 
                        WHEN (v.ende IS NOT NULL AND DATEDIFF(DATE_SUB(v.ende, INTERVAL v.kuendigungsfrist_tage DAY), CURDATE()) BETWEEN 0 AND 7)
                        OR (v.ende IS NULL AND v.laufzeit_monate IS NOT NULL AND DATEDIFF(DATE_ADD(v.beginn, INTERVAL v.laufzeit_monate MONTH - INTERVAL v.kuendigungsfrist_tage DAY), CURDATE()) BETWEEN 0 AND 7)
                        THEN 1 ELSE 0 
                    END) as deadlines_7
                FROM vertraege v 
                WHERE v.status = 'laufend'"
            )->fetch();
            
            if ($deadlines) {
                $stats['deadlines_30_days'] = (int) $deadlines['deadlines_30'];
                $stats['deadlines_7_days'] = (int) $deadlines['deadlines_7'];
            }
            
        } catch (\Exception $e) {
            // Fehler loggen aber Dashboard weiterhin anzeigen
            error_log("Dashboard stats error: " . $e->getMessage());
        }
        
        // Berechnete Werte hinzufügen
        $stats['monthly_costs_euro'] = $stats['monthly_costs_cents'] / 100;
        $stats['yearly_costs_euro'] = $stats['monthly_costs_euro'] * 12;
        
        return $stats;
    }
    
    /**
     * Letzte Aktivitäten ermitteln
     */
    private function getRecentActivities(): array
    {
        $activities = [];
        
        try {
            // Neue Verträge (letzte 7 Tage)
            $recentContracts = db()->query(
                "SELECT v.*, k.name as korrespondent_name
                 FROM vertraege v 
                 LEFT JOIN korrespondenten k ON v.korrespondent_id = k.id
                 WHERE v.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                 ORDER BY v.created_at DESC LIMIT 5"
            )->fetchAll();
            
            foreach ($recentContracts as $contract) {
                if (rbac_check('read', 'vertrag', ['id' => $contract['id']])) {
                    $activities[] = [
                        'type' => 'vertrag_created',
                        'title' => 'Neuer Vertrag: ' . $contract['korrespondent_name'],
                        'description' => 'Vertrag erstellt',
                        'date' => $contract['created_at'],
                        'url' => "/vertraege/{$contract['id']}",
                        'icon' => 'fas fa-file-contract text-green-500'
                    ];
                }
            }
            
            // Neue Dokumente (letzte 7 Tage)
            $recentDocuments = db()->query(
                "SELECT d.*, k.name as korrespondent_name
                 FROM dokumente d
                 LEFT JOIN korrespondenten k ON d.korrespondent_id = k.id
                 WHERE d.hochgeladen_am >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                 ORDER BY d.hochgeladen_am DESC LIMIT 5"
            )->fetchAll();
            
            foreach ($recentDocuments as $doc) {
                if (rbac_check('read', 'dokument', ['id' => $doc['id']])) {
                    $activities[] = [
                        'type' => 'dokument_uploaded',
                        'title' => 'Dokument hochgeladen: ' . $doc['dateiname_original'],
                        'description' => ($doc['korrespondent_name'] ? 'für ' . $doc['korrespondent_name'] : 'Allgemein'),
                        'date' => $doc['hochgeladen_am'],
                        'url' => "/dokumente/{$doc['id']}",
                        'icon' => 'fas fa-file-upload text-blue-500'
                    ];
                }
            }
            
            // Nach Datum sortieren
            usort($activities, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));
            
            // Nur die neuesten 10 behalten
            $activities = array_slice($activities, 0, 10);
            
        } catch (\Exception $e) {
            error_log("Dashboard activities error: " . $e->getMessage());
        }
        
        return $activities;
    }
    
    /**
     * Liquiditäts-Übersicht für die nächsten Monate
     */
    private function getLiquidityOverview(): array
    {
        $overview = [];
        $startDate = new \DateTime('first day of this month');
        
        try {
            for ($i = 0; $i < 6; $i++) {
                $currentMonth = clone $startDate;
                $currentMonth->add(new \DateInterval('P' . $i . 'M'));
                
                $monthStart = clone $currentMonth;
                $monthEnd = clone $currentMonth;
                $monthEnd->modify('last day of this month')->setTime(23, 59, 59);
                
                // Zahlungen für den Monat berechnen
                $totalCents = 0;
                
                $vertraege = Vertrag::all(['status' => 'laufend']);
                foreach ($vertraege as $vertrag) {
                    $events = $vertrag->generatePaymentEvents($monthStart, $monthEnd);
                    foreach ($events as $event) {
                        $totalCents += $event['amount_cents'];
                    }
                }
                
                $overview[] = [
                    'month' => $currentMonth->format('Y-m'),
                    'month_name' => $currentMonth->format('M Y'),
                    'total_cents' => $totalCents,
                    'total_euro' => $totalCents / 100
                ];
            }
            
        } catch (\Exception $e) {
            error_log("Dashboard liquidity error: " . $e->getMessage());
        }
        
        return $overview;
    }
    
    /**
     * Dashboard-Widgets für htmx
     */
    public function widgetStats(): string
    {
        $stats = $this->getStats();
        
        return $this->view('partials/dashboard/stats-cards', [
            'stats' => $stats
        ]);
    }
    
    /**
     * Kündigungsfristen-Widget
     */
    public function widgetDeadlines(): string
    {
        $deadlines = Vertrag::getUpcomingDeadlines(30);
        
        return $this->view('partials/dashboard/deadlines-widget', [
            'deadlines' => $deadlines
        ]);
    }
    
    /**
     * Aktivitäten-Widget
     */
    public function widgetActivities(): string
    {
        $activities = $this->getRecentActivities();
        
        return $this->view('partials/dashboard/activities-widget', [
            'activities' => $activities
        ]);
    }
    
    /**
     * Liquiditäts-Chart-Daten für htmx/JavaScript
     */
    public function chartLiquidity(): void
    {
        $overview = $this->getLiquidityOverview();
        
        $chartData = [
            'labels' => array_column($overview, 'month_name'),
            'datasets' => [
                [
                    'label' => 'Monatliche Kosten',
                    'data' => array_column($overview, 'total_euro'),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'borderWidth' => 2,
                    'fill' => true
                ]
            ]
        ];
        
        $this->json($chartData);
    }
    
    /**
     * Quick Actions für Dashboard
     */
    public function quickActions(): string
    {
        $user = auth();
        
        $actions = [
            [
                'title' => 'Neuer Vertrag',
                'description' => 'Vertrag hinzufügen',
                'url' => '/vertraege/create',
                'icon' => 'fas fa-plus-circle',
                'color' => 'btn-primary',
                'permission' => rbac_check('create', 'vertrag')
            ],
            [
                'title' => 'Neuer Korrespondent',
                'description' => 'Korrespondent hinzufügen',
                'url' => '/korrespondenten/create',
                'icon' => 'fas fa-building',
                'color' => 'btn-secondary',
                'permission' => rbac_check('create', 'korrespondent')
            ],
            [
                'title' => 'Dokument hochladen',
                'description' => 'PDF oder Dokument hinzufügen',
                'url' => '/dokumente',
                'icon' => 'fas fa-upload',
                'color' => 'btn-accent',
                'permission' => rbac_check('create', 'dokument')
            ],
            [
                'title' => 'Zugangsdaten',
                'description' => 'Login-Daten verwalten',
                'url' => '/zugangsdaten',
                'icon' => 'fas fa-key',
                'color' => 'btn-info',
                'permission' => rbac_check('read', 'zugangsdaten')
            ]
        ];
        
        // Nur erlaubte Aktionen anzeigen
        $allowedActions = array_filter($actions, fn($action) => $action['permission']);
        
        return $this->view('partials/dashboard/quick-actions', [
            'actions' => $allowedActions
        ]);
    }
    
    /**
     * Dashboard-Suche
     */
    public function search(): void
    {
        $query = $_GET['q'] ?? '';
        
        if (strlen($query) < 2) {
            $this->json(['results' => []]);
            return;
        }
        
        $results = [];
        
        try {
            // Korrespondenten suchen
            $korrespondenten = Korrespondent::search($query, 5);
            foreach ($korrespondenten as $item) {
                $results[] = [
                    'type' => 'korrespondent',
                    'title' => $item['name'],
                    'subtitle' => $item['adresse'],
                    'url' => "/korrespondenten/{$item['id']}",
                    'icon' => 'fas fa-building'
                ];
            }
            
            // Verträge suchen (vereinfacht)
            $vertraege = db()->query(
                "SELECT v.id, k.name as korrespondent_name, v.status, v.beginn
                 FROM vertraege v
                 LEFT JOIN korrespondenten k ON v.korrespondent_id = k.id
                 WHERE k.name LIKE ? OR v.notizen LIKE ?
                 LIMIT 5",
                ['%' . $query . '%', '%' . $query . '%']
            )->fetchAll();
            
            foreach ($vertraege as $vertrag) {
                if (rbac_check('read', 'vertrag', ['id' => $vertrag['id']])) {
                    $results[] = [
                        'type' => 'vertrag',
                        'title' => 'Vertrag: ' . $vertrag['korrespondent_name'],
                        'subtitle' => 'Status: ' . $vertrag['status'] . ' | Seit: ' . $vertrag['beginn'],
                        'url' => "/vertraege/{$vertrag['id']}",
                        'icon' => 'fas fa-file-contract'
                    ];
                }
            }
            
            // Dokumente suchen
            $dokumente = db()->query(
                "SELECT d.id, d.dateiname_original, k.name as korrespondent_name
                 FROM dokumente d
                 LEFT JOIN korrespondenten k ON d.korrespondent_id = k.id
                 WHERE d.dateiname_original LIKE ? OR k.name LIKE ?
                 LIMIT 5",
                ['%' . $query . '%', '%' . $query . '%']
            )->fetchAll();
            
            foreach ($dokumente as $dokument) {
                if (rbac_check('read', 'dokument', ['id' => $dokument['id']])) {
                    $results[] = [
                        'type' => 'dokument',
                        'title' => $dokument['dateiname_original'],
                        'subtitle' => $dokument['korrespondent_name'] ?: 'Allgemein',
                        'url' => "/dokumente/{$dokument['id']}",
                        'icon' => 'fas fa-file-pdf'
                    ];
                }
            }
            
        } catch (\Exception $e) {
            error_log("Dashboard search error: " . $e->getMessage());
        }
        
        $this->json([
            'query' => $query,
            'results' => array_slice($results, 0, 15) // Max 15 Ergebnisse
        ]);
    }
}