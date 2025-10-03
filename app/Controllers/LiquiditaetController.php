<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Vertrag;
use App\Models\Korrespondent;

/**
 * Liquiditätsplanung Controller für Zeitstrahl und Finanzübersicht
 * 
 * @package App\Controllers
 * @author GenSpark AI Developer
 */
class LiquiditaetController extends BaseController
{
    /**
     * Liquiditätsplanung Hauptseite
     */
    public function index(): string
    {
        $this->authorize('read', 'liquiditaet');
        
        // Zeitraum aus Request oder Standard (nächste 12 Monate)
        $startDate = $_GET['start'] ? new \DateTime($_GET['start']) : new \DateTime('first day of this month');
        $endDate = $_GET['end'] ? new \DateTime($_GET['end']) : (clone $startDate)->add(new \DateInterval('P12M'));
        
        // Ansichtsmodus (monat, quartal, jahr)
        $viewMode = $_GET['view'] ?? 'monat';
        $validViewModes = ['monat', 'quartal', 'jahr'];
        if (!in_array($viewMode, $validViewModes)) {
            $viewMode = 'monat';
        }
        
        // Filter
        $filters = [
            'korrespondent_id' => $_GET['korrespondent_id'] ?? '',
            'status' => $_GET['status'] ?? 'laufend',
            'zahlungsweise' => $_GET['zahlungsweise'] ?? ''
        ];
        
        // Statistiken berechnen
        $stats = $this->calculateStats($startDate, $endDate, $filters);
        
        // Korrespondenten für Filter
        $korrespondenten = Korrespondent::all(['limit' => 100]);
        
        return $this->view('pages/liquiditaet/index', [
            'page_title' => 'Liquiditätsplanung',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'view_mode' => $viewMode,
            'filters' => $filters,
            'stats' => $stats,
            'korrespondenten' => $korrespondenten,
            'status_options' => $this->getStatusOptions(),
            'zahlungsweise_options' => $this->getZahlungsweiseOptions()
        ]);
    }
    
    /**
     * Liquiditäts-Daten für Timeline abrufen
     */
    public function getData(): void
    {
        $this->authorize('read', 'liquiditaet');
        
        try {
            $startDate = new \DateTime($_GET['start'] ?? 'first day of this month');
            $endDate = new \DateTime($_GET['end'] ?? '+12 months');
            $viewMode = $_GET['view'] ?? 'monat';
            
            $filters = [
                'korrespondent_id' => $_GET['korrespondent_id'] ?? '',
                'status' => $_GET['status'] ?? 'laufend',
                'zahlungsweise' => $_GET['zahlungsweise'] ?? ''
            ];
            
            // Verträge laden mit Filtern
            $vertraege = Vertrag::all($filters);
            
            // Zahlungsereignisse generieren
            $allEvents = [];
            foreach ($vertraege as $vertrag) {
                $events = $vertrag->generatePaymentEvents($startDate, $endDate);
                foreach ($events as $event) {
                    $event['korrespondent_name'] = $vertrag->getKorrespondent()?->name ?? 'Unbekannt';
                    $event['vertrag_id'] = $vertrag->id;
                    $event['zahlungsweise'] = $vertrag->zahlungsweise;
                    $event['status'] = $vertrag->status;
                    $allEvents[] = $event;
                }
            }
            
            // Nach Datum sortieren
            usort($allEvents, fn($a, $b) => $a['date'] <=> $b['date']);
            
            // Nach View-Modus aggregieren
            $aggregatedData = $this->aggregateByViewMode($allEvents, $viewMode);
            
            $this->json([
                'success' => true,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'view_mode' => $viewMode,
                'events' => $allEvents,
                'aggregated' => $aggregatedData,
                'stats' => $this->calculateEventsStats($allEvents)
            ]);
            
        } catch (\Exception $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * CSV-Export der Liquiditätsdaten
     */
    public function exportCsv(): void
    {
        $this->authorize('export', 'liquiditaet');
        
        try {
            $startDate = new \DateTime($_GET['start'] ?? 'first day of this month');
            $endDate = new \DateTime($_GET['end'] ?? '+12 months');
            
            $filters = [
                'korrespondent_id' => $_GET['korrespondent_id'] ?? '',
                'status' => $_GET['status'] ?? 'laufend',
                'zahlungsweise' => $_GET['zahlungsweise'] ?? ''
            ];
            
            // Verträge laden und Events generieren
            $vertraege = Vertrag::all($filters);
            $allEvents = [];
            
            foreach ($vertraege as $vertrag) {
                $events = $vertrag->generatePaymentEvents($startDate, $endDate);
                foreach ($events as $event) {
                    $korrespondent = $vertrag->getKorrespondent();
                    $allEvents[] = [
                        'datum' => $event['date']->format('d.m.Y'),
                        'korrespondent' => $korrespondent ? $korrespondent->name : 'Unbekannt',
                        'vertrag_id' => $vertrag->id,
                        'beschreibung' => $event['description'],
                        'betrag_cent' => $event['amount_cents'],
                        'betrag_euro' => number_format($event['amount_cents'] / 100, 2, ',', '.'),
                        'zahlungsweise' => $vertrag->zahlungsweise,
                        'zahlungszyklus' => $vertrag->zahlungszyklus,
                        'status' => $vertrag->status
                    ];
                }
            }
            
            // Nach Datum sortieren
            usort($allEvents, fn($a, $b) => strtotime($a['datum']) - strtotime($b['datum']));
            
            $filename = 'liquiditaetsplanung_' . 
                       $startDate->format('Y-m-d') . '_bis_' . 
                       $endDate->format('Y-m-d') . '.csv';
            
            header('Content-Type: text/csv; charset=utf-8');
            header("Content-Disposition: attachment; filename=\"$filename\"");
            
            $output = fopen('php://output', 'w');
            
            // UTF-8 BOM für Excel
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Header
            fputcsv($output, [
                'Datum',
                'Korrespondent', 
                'Vertrag-ID',
                'Beschreibung',
                'Betrag (€)',
                'Zahlungsweise',
                'Zahlungszyklus',
                'Status'
            ], ';');
            
            // Daten
            foreach ($allEvents as $event) {
                fputcsv($output, [
                    $event['datum'],
                    $event['korrespondent'],
                    $event['vertrag_id'],
                    $event['beschreibung'],
                    $event['betrag_euro'] . ' €',
                    $event['zahlungsweise'],
                    $event['zahlungszyklus'],
                    $event['status']
                ], ';');
            }
            
            // Summen am Ende
            $totalCents = array_sum(array_column($allEvents, 'betrag_cent'));
            $totalEuro = $totalCents / 100;
            
            fputcsv($output, [], ';'); // Leerzeile
            fputcsv($output, [
                'SUMME',
                count($allEvents) . ' Zahlungen',
                '',
                'Gesamtbetrag im Zeitraum',
                number_format($totalEuro, 2, ',', '.') . ' €',
                '',
                '',
                ''
            ], ';');
            
            fclose($output);
            
        } catch (\Exception $e) {
            http_response_code(500);
            echo 'Fehler beim CSV-Export: ' . $e->getMessage();
        }
    }
    
    /**
     * Liquiditäts-Timeline als HTML-Fragment
     */
    public function timeline(): string
    {
        $this->authorize('read', 'liquiditaet');
        
        try {
            $startDate = new \DateTime($_GET['start'] ?? 'first day of this month');
            $endDate = new \DateTime($_GET['end'] ?? '+12 months');
            $viewMode = $_GET['view'] ?? 'monat';
            
            $filters = [
                'korrespondent_id' => $_GET['korrespondent_id'] ?? '',
                'status' => $_GET['status'] ?? 'laufend',
                'zahlungsweise' => $_GET['zahlungsweise'] ?? ''
            ];
            
            // Daten laden
            $vertraege = Vertrag::all($filters);
            $allEvents = [];
            
            foreach ($vertraege as $vertrag) {
                $events = $vertrag->generatePaymentEvents($startDate, $endDate);
                foreach ($events as $event) {
                    $event['korrespondent_name'] = $vertrag->getKorrespondent()?->name ?? 'Unbekannt';
                    $event['vertrag_id'] = $vertrag->id;
                    $allEvents[] = $event;
                }
            }
            
            // Aggregieren
            $aggregatedData = $this->aggregateByViewMode($allEvents, $viewMode);
            
            return $this->view('partials/liquiditaet/timeline', [
                'events' => $allEvents,
                'aggregated' => $aggregatedData,
                'view_mode' => $viewMode,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
            
        } catch (\Exception $e) {
            return '<div class="alert alert-error">Fehler beim Laden der Timeline: ' . e($e->getMessage()) . '</div>';
        }
    }
    
    /**
     * Chart-Daten für JavaScript-Charts
     */
    public function chartData(): void
    {
        $this->authorize('read', 'liquiditaet');
        
        try {
            $startDate = new \DateTime($_GET['start'] ?? 'first day of this month');
            $endDate = new \DateTime($_GET['end'] ?? '+12 months');
            $viewMode = $_GET['view'] ?? 'monat';
            
            $filters = [
                'korrespondent_id' => $_GET['korrespondent_id'] ?? '',
                'status' => $_GET['status'] ?? 'laufend'
            ];
            
            // Events generieren
            $vertraege = Vertrag::all($filters);
            $allEvents = [];
            
            foreach ($vertraege as $vertrag) {
                $events = $vertrag->generatePaymentEvents($startDate, $endDate);
                $allEvents = array_merge($allEvents, $events);
            }
            
            // Für Charts aggregieren
            $chartData = $this->prepareChartData($allEvents, $viewMode, $startDate, $endDate);
            
            $this->json($chartData);
            
        } catch (\Exception $e) {
            $this->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Kumulierte Kosten berechnen
     */
    public function cumulativeData(): void
    {
        $this->authorize('read', 'liquiditaet');
        
        try {
            $startDate = new \DateTime($_GET['start'] ?? 'first day of this month');
            $endDate = new \DateTime($_GET['end'] ?? '+12 months');
            
            // Alle Verträge laden
            $vertraege = Vertrag::all(['status' => 'laufend']);
            $allEvents = [];
            
            foreach ($vertraege as $vertrag) {
                $events = $vertrag->generatePaymentEvents($startDate, $endDate);
                $allEvents = array_merge($allEvents, $events);
            }
            
            // Nach Datum sortieren
            usort($allEvents, fn($a, $b) => $a['date'] <=> $b['date']);
            
            // Kumulative Summen berechnen
            $cumulative = 0;
            $cumulativeData = [];
            $monthlyTotals = [];
            
            foreach ($allEvents as $event) {
                $cumulative += $event['amount_cents'];
                $month = $event['date']->format('Y-m');
                
                $cumulativeData[] = [
                    'date' => $event['date']->format('Y-m-d'),
                    'amount_cents' => $event['amount_cents'],
                    'amount_euro' => $event['amount_cents'] / 100,
                    'cumulative_cents' => $cumulative,
                    'cumulative_euro' => $cumulative / 100
                ];
                
                // Monatssummen
                if (!isset($monthlyTotals[$month])) {
                    $monthlyTotals[$month] = 0;
                }
                $monthlyTotals[$month] += $event['amount_cents'];
            }
            
            $this->json([
                'cumulative_data' => $cumulativeData,
                'monthly_totals' => $monthlyTotals,
                'total_amount_cents' => $cumulative,
                'total_amount_euro' => $cumulative / 100,
                'event_count' => count($allEvents)
            ]);
            
        } catch (\Exception $e) {
            $this->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Liquiditäts-Prognose basierend auf historischen Daten
     */
    public function forecast(): void
    {
        $this->authorize('read', 'liquiditaet');
        
        try {
            $months = (int) ($_GET['months'] ?? 12);
            $startDate = new \DateTime('first day of this month');
            $endDate = (clone $startDate)->add(new \DateInterval("P{$months}M"));
            
            // Aktuelle Verträge
            $activeContracts = Vertrag::all(['status' => 'laufend']);
            
            $forecast = [];
            $currentDate = clone $startDate;
            
            while ($currentDate <= $endDate) {
                $monthStart = (clone $currentDate)->modify('first day of this month');
                $monthEnd = (clone $currentDate)->modify('last day of this month');
                
                $monthTotal = 0;
                $contractCount = 0;
                
                foreach ($activeContracts as $vertrag) {
                    // Prüfen ob Vertrag in diesem Monat noch läuft
                    if ($vertrag->ende && $vertrag->ende < $monthStart) {
                        continue; // Vertrag bereits beendet
                    }
                    
                    if ($vertrag->beginn > $monthEnd) {
                        continue; // Vertrag noch nicht gestartet
                    }
                    
                    $events = $vertrag->generatePaymentEvents($monthStart, $monthEnd);
                    foreach ($events as $event) {
                        $monthTotal += $event['amount_cents'];
                    }
                    
                    $contractCount++;
                }
                
                $forecast[] = [
                    'month' => $currentDate->format('Y-m'),
                    'month_name' => $currentDate->format('M Y'),
                    'total_cents' => $monthTotal,
                    'total_euro' => $monthTotal / 100,
                    'contract_count' => $contractCount
                ];
                
                $currentDate->add(new \DateInterval('P1M'));
            }
            
            // Trends berechnen
            $averageMonthly = 0;
            if (count($forecast) > 0) {
                $averageMonthly = array_sum(array_column($forecast, 'total_cents')) / count($forecast);
            }
            
            $this->json([
                'forecast' => $forecast,
                'summary' => [
                    'months' => $months,
                    'average_monthly_cents' => $averageMonthly,
                    'average_monthly_euro' => $averageMonthly / 100,
                    'total_forecast_cents' => array_sum(array_column($forecast, 'total_cents')),
                    'total_forecast_euro' => array_sum(array_column($forecast, 'total_cents')) / 100,
                    'active_contracts' => count($activeContracts)
                ]
            ]);
            
        } catch (\Exception $e) {
            $this->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Events nach View-Modus aggregieren
     */
    private function aggregateByViewMode(array $events, string $viewMode): array
    {
        $aggregated = [];
        
        foreach ($events as $event) {
            $key = match ($viewMode) {
                'quartal' => $event['date']->format('Y') . '-Q' . ceil($event['date']->format('n') / 3),
                'jahr' => $event['date']->format('Y'),
                default => $event['date']->format('Y-m') // monat
            };
            
            if (!isset($aggregated[$key])) {
                $aggregated[$key] = [
                    'period' => $key,
                    'display_name' => $this->formatPeriodName($key, $viewMode),
                    'total_cents' => 0,
                    'total_euro' => 0,
                    'event_count' => 0,
                    'events' => []
                ];
            }
            
            $aggregated[$key]['total_cents'] += $event['amount_cents'];
            $aggregated[$key]['total_euro'] = $aggregated[$key]['total_cents'] / 100;
            $aggregated[$key]['event_count']++;
            $aggregated[$key]['events'][] = $event;
        }
        
        return array_values($aggregated);
    }
    
    /**
     * Chart-Daten vorbereiten
     */
    private function prepareChartData(array $events, string $viewMode, \DateTime $startDate, \DateTime $endDate): array
    {
        $aggregated = $this->aggregateByViewMode($events, $viewMode);
        
        return [
            'labels' => array_column($aggregated, 'display_name'),
            'datasets' => [
                [
                    'label' => 'Zahlungen (€)',
                    'data' => array_column($aggregated, 'total_euro'),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'borderWidth' => 2,
                    'fill' => true
                ]
            ],
            'options' => [
                'responsive' => true,
                'plugins' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Liquiditätsplanung (' . ucfirst($viewMode) . ')'
                    ]
                ],
                'scales' => [
                    'y' => [
                        'beginAtZero' => true,
                        'ticks' => [
                            'callback' => 'function(value) { return value.toLocaleString("de-DE", {style: "currency", currency: "EUR"}); }'
                        ]
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Periode formatieren
     */
    private function formatPeriodName(string $period, string $viewMode): string
    {
        return match ($viewMode) {
            'quartal' => str_replace('-Q', ' Q', $period),
            'jahr' => $period,
            default => (new \DateTime($period . '-01'))->format('M Y') // monat
        };
    }
    
    /**
     * Statistiken berechnen
     */
    private function calculateStats(\DateTime $startDate, \DateTime $endDate, array $filters): array
    {
        $vertraege = Vertrag::all($filters);
        
        $totalContracts = count($vertraege);
        $totalEventsInPeriod = 0;
        $totalAmountCents = 0;
        
        foreach ($vertraege as $vertrag) {
            $events = $vertrag->generatePaymentEvents($startDate, $endDate);
            $totalEventsInPeriod += count($events);
            
            foreach ($events as $event) {
                $totalAmountCents += $event['amount_cents'];
            }
        }
        
        $monthsDiff = $startDate->diff($endDate)->m + ($startDate->diff($endDate)->y * 12);
        $monthsDiff = max(1, $monthsDiff);
        
        return [
            'total_contracts' => $totalContracts,
            'total_events' => $totalEventsInPeriod,
            'total_amount_cents' => $totalAmountCents,
            'total_amount_euro' => $totalAmountCents / 100,
            'average_monthly_cents' => $totalAmountCents / $monthsDiff,
            'average_monthly_euro' => ($totalAmountCents / $monthsDiff) / 100,
            'period_months' => $monthsDiff
        ];
    }
    
    /**
     * Event-Statistiken berechnen
     */
    private function calculateEventsStats(array $events): array
    {
        if (empty($events)) {
            return [
                'count' => 0,
                'total_cents' => 0,
                'average_cents' => 0,
                'min_cents' => 0,
                'max_cents' => 0
            ];
        }
        
        $amounts = array_column($events, 'amount_cents');
        
        return [
            'count' => count($events),
            'total_cents' => array_sum($amounts),
            'total_euro' => array_sum($amounts) / 100,
            'average_cents' => array_sum($amounts) / count($amounts),
            'average_euro' => (array_sum($amounts) / count($amounts)) / 100,
            'min_cents' => min($amounts),
            'min_euro' => min($amounts) / 100,
            'max_cents' => max($amounts),
            'max_euro' => max($amounts) / 100
        ];
    }
    
    /**
     * Status-Optionen
     */
    private function getStatusOptions(): array
    {
        return [
            '' => 'Alle Status',
            'laufend' => 'Laufend',
            'gekündigt' => 'Gekündigt',
            'beendet' => 'Beendet'
        ];
    }
    
    /**
     * Zahlungsweise-Optionen
     */
    private function getZahlungsweiseOptions(): array
    {
        return [
            '' => 'Alle Zahlungsweisen',
            'sepa' => 'SEPA-Lastschrift',
            'kreditkarte' => 'Kreditkarte',
            'ueberweisung' => 'Überweisung',
            'paypal' => 'PayPal',
            'bar' => 'Bar',
            'sonstiges' => 'Sonstiges'
        ];
    }
}