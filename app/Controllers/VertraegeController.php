<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Vertrag;
use App\Models\Korrespondent;
use App\Models\Zugangsdaten;
use App\Models\Dokument;

/**
 * Verträge Controller für CRUD-Operationen und Vertragsmanagement
 * 
 * @package App\Controllers
 * @author GenSpark AI Developer
 */
class VertraegeController extends BaseController
{
    /**
     * Verträge-Liste anzeigen
     */
    public function index(): string
    {
        $this->authorize('read', 'vertrag');
        
        // Filter aus Request
        $filters = [
            'search' => $_GET['q'] ?? '',
            'status' => $_GET['status'] ?? '',
            'korrespondent_id' => $_GET['korrespondent_id'] ?? '',
            'kuendigungsfrist_days' => $_GET['kuendigungsfrist_days'] ?? '',
            'page' => (int) ($_GET['page'] ?? 1),
            'limit' => 20
        ];
        
        // Verträge laden
        $vertraege = Vertrag::all($filters);
        $paginatedData = $this->paginate($vertraege, $filters['limit']);
        
        // Korrespondenten für Filter-Dropdown
        $korrespondenten = Korrespondent::all(['limit' => 100]);
        
        // Status-Optionen
        $statusOptions = [
            '' => 'Alle Status',
            Vertrag::STATUS_LAUFEND => 'Laufend',
            Vertrag::STATUS_GEKUENDIGT => 'Gekündigt', 
            Vertrag::STATUS_BEENDET => 'Beendet',
            Vertrag::STATUS_STORNIERT => 'Storniert'
        ];
        
        // Für htmx-Requests nur die Tabelle zurückgeben
        if (!empty($_SERVER['HTTP_HX_REQUEST'])) {
            return $this->view('partials/vertraege/table', [
                'vertraege' => $paginatedData['data'],
                'pagination' => $paginatedData['pagination']
            ]);
        }
        
        return $this->view('pages/vertraege/index', [
            'page_title' => 'Verträge',
            'vertraege' => $paginatedData['data'],
            'pagination' => $paginatedData['pagination'],
            'filters' => $filters,
            'korrespondenten' => $korrespondenten,
            'status_options' => $statusOptions,
            'can_create' => rbac_check('create', 'vertrag')
        ]);
    }
    
    /**
     * Vertrag-Details anzeigen
     */
    public function show(): string
    {
        $id = (int) ($_GET['id'] ?? 0);
        $vertrag = Vertrag::find($id);
        
        if (!$vertrag) {
            http_response_code(404);
            return $this->view('errors/404');
        }
        
        // Korrespondent laden
        $korrespondent = $vertrag->getKorrespondent();
        
        // Zugangsdaten für diesen Vertrag
        $zugangsdaten = Zugangsdaten::findByVertrag($vertrag->id);
        
        // Dokumente für diesen Vertrag
        $dokumente = Dokument::findByVertrag($vertrag->id);
        
        // Kündigungsfrist berechnen
        $kuendigungsfrist = $vertrag->getKuendigungsfrist();
        $daysUntilDeadline = $vertrag->getDaysUntilDeadline();
        
        // Zahlungsereignisse für die nächsten 12 Monate
        $paymentEvents = [];
        if ($vertrag->status === Vertrag::STATUS_LAUFEND) {
            $startDate = new \DateTime();
            $endDate = new \DateTime('+12 months');
            $paymentEvents = $vertrag->generatePaymentEvents($startDate, $endDate);
        }
        
        return $this->view('pages/vertraege/show', [
            'page_title' => 'Vertrag: ' . ($korrespondent ? $korrespondent->name : 'Unbekannt'),
            'vertrag' => $vertrag,
            'korrespondent' => $korrespondent,
            'zugangsdaten' => $zugangsdaten,
            'dokumente' => $dokumente,
            'kuendigungsfrist' => $kuendigungsfrist,
            'days_until_deadline' => $daysUntilDeadline,
            'payment_events' => $paymentEvents,
            'can_edit' => rbac_check('update', 'vertrag', ['id' => $id]),
            'can_delete' => rbac_check('delete', 'vertrag', ['id' => $id])
        ]);
    }
    
    /**
     * Neuen Vertrag erstellen - Formular anzeigen
     */
    public function create(): string
    {
        $this->authorize('create', 'vertrag');
        
        // Korrespondenten für Dropdown
        $korrespondenten = Korrespondent::all();
        
        // Vorgefüllter Korrespondent (falls von Korrespondent-Seite verlinkt)
        $selectedKorrespondent = null;
        if (!empty($_GET['korrespondent_id'])) {
            $selectedKorrespondent = Korrespondent::find((int) $_GET['korrespondent_id']);
        }
        
        return $this->view('pages/vertraege/create', [
            'page_title' => 'Neuer Vertrag',
            'vertrag' => null,
            'korrespondenten' => $korrespondenten,
            'selected_korrespondent' => $selectedKorrespondent,
            'status_options' => $this->getStatusOptions(),
            'kosten_zeitraum_options' => $this->getKostenZeitraumOptions(),
            'zahlungszyklus_options' => $this->getZahlungszyklusOptions(),
            'zahlungsweise_options' => $this->getZahlungsweiseOptions(),
            'verlaengerung_options' => $this->getVerlaengerungOptions()
        ]);
    }
    
    /**
     * Neuen Vertrag speichern
     */
    public function store(): void
    {
        $this->authorize('create', 'vertrag');
        
        try {
            $data = $this->validate([
                'korrespondent_id' => ['required', 'integer'],
                'beginn' => 'required',
                'ende' => '',
                'status' => 'required',
                'laufzeit_monate' => 'integer',
                'kosten_euro' => ['required', 'numeric'],
                'kosten_zeitraum' => 'required',
                'zahlungszyklus' => 'required',
                'zahlungsweise' => 'required',
                'verlängerungsmodus' => 'required',
                'kuendigungsfrist_tage' => ['required', 'integer'],
                'notizen' => '',
                'kuendigungsablauf' => ''
            ]);
            
            // Euro in Cent umwandeln
            $data['kosten_cent'] = (int) round($data['kosten_euro'] * 100);
            unset($data['kosten_euro']);
            
            $vertrag = Vertrag::create($data);
            
            // Für htmx-Requests
            if (!empty($_SERVER['HTTP_HX_REQUEST'])) {
                $this->json([
                    'success' => true,
                    'message' => 'Vertrag erfolgreich erstellt',
                    'redirect' => "/vertraege/{$vertrag->id}"
                ]);
                return;
            }
            
            $this->redirectWithMessage(
                "/vertraege/{$vertrag->id}",
                'Vertrag erfolgreich erstellt',
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
            
            $this->redirectWithMessage('/vertraege/create', $e->getMessage(), 'error');
        }
    }
    
    /**
     * Vertrag bearbeiten - Formular anzeigen
     */
    public function edit(): string
    {
        $id = (int) ($_GET['id'] ?? 0);
        $vertrag = Vertrag::find($id);
        
        if (!$vertrag) {
            http_response_code(404);
            return $this->view('errors/404');
        }
        
        $this->authorize('update', 'vertrag', ['id' => $id]);
        
        // Korrespondenten für Dropdown
        $korrespondenten = Korrespondent::all();
        
        return $this->view('pages/vertraege/edit', [
            'page_title' => 'Vertrag bearbeiten',
            'vertrag' => $vertrag,
            'korrespondenten' => $korrespondenten,
            'status_options' => $this->getStatusOptions(),
            'kosten_zeitraum_options' => $this->getKostenZeitraumOptions(),
            'zahlungszyklus_options' => $this->getZahlungszyklusOptions(),
            'zahlungsweise_options' => $this->getZahlungsweiseOptions(),
            'verlaengerung_options' => $this->getVerlaengerungOptions()
        ]);
    }
    
    /**
     * Vertrag aktualisieren
     */
    public function update(): void
    {
        $id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
        $vertrag = Vertrag::find($id);
        
        if (!$vertrag) {
            http_response_code(404);
            if (!empty($_SERVER['HTTP_HX_REQUEST'])) {
                $this->json(['success' => false, 'message' => 'Vertrag nicht gefunden'], 404);
                return;
            }
            $this->redirect('/vertraege');
            return;
        }
        
        $this->authorize('update', 'vertrag', ['id' => $id]);
        
        try {
            $data = $this->validate([
                'korrespondent_id' => ['required', 'integer'],
                'beginn' => 'required',
                'ende' => '',
                'status' => 'required',
                'laufzeit_monate' => 'integer',
                'kosten_euro' => ['required', 'numeric'],
                'kosten_zeitraum' => 'required',
                'zahlungszyklus' => 'required',
                'zahlungsweise' => 'required',
                'verlängerungsmodus' => 'required',
                'kuendigungsfrist_tage' => ['required', 'integer'],
                'notizen' => '',
                'kuendigungsablauf' => ''
            ]);
            
            // Euro in Cent umwandeln
            $data['kosten_cent'] = (int) round($data['kosten_euro'] * 100);
            unset($data['kosten_euro']);
            
            $vertrag->update($data);
            
            if (!empty($_SERVER['HTTP_HX_REQUEST'])) {
                $this->json([
                    'success' => true,
                    'message' => 'Vertrag erfolgreich aktualisiert',
                    'redirect' => "/vertraege/{$vertrag->id}"
                ]);
                return;
            }
            
            $this->redirectWithMessage(
                "/vertraege/{$vertrag->id}",
                'Vertrag erfolgreich aktualisiert',
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
            
            $this->redirectWithMessage("/vertraege/{$id}/edit", $e->getMessage(), 'error');
        }
    }
    
    /**
     * Vertrag löschen
     */
    public function delete(): void
    {
        $id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
        $vertrag = Vertrag::find($id);
        
        if (!$vertrag) {
            http_response_code(404);
            if (!empty($_SERVER['HTTP_HX_REQUEST'])) {
                $this->json(['success' => false, 'message' => 'Vertrag nicht gefunden'], 404);
                return;
            }
            $this->redirect('/vertraege');
            return;
        }
        
        $this->authorize('delete', 'vertrag', ['id' => $id]);
        
        try {
            $korrespondentName = $vertrag->getKorrespondent()?->name ?? 'Unbekannt';
            $vertrag->delete();
            
            if (!empty($_SERVER['HTTP_HX_REQUEST'])) {
                $this->json([
                    'success' => true,
                    'message' => "Vertrag mit '$korrespondentName' erfolgreich gelöscht",
                    'redirect' => '/vertraege'
                ]);
                return;
            }
            
            $this->redirectWithMessage(
                '/vertraege',
                "Vertrag mit '$korrespondentName' erfolgreich gelöscht",
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
            
            $this->redirectWithMessage("/vertraege/{$id}", $e->getMessage(), 'error');
        }
    }
    
    /**
     * Vertrags-Dokumente anzeigen
     */
    public function documents(): string
    {
        $id = (int) ($_GET['id'] ?? 0);
        $vertrag = Vertrag::find($id);
        
        if (!$vertrag) {
            http_response_code(404);
            return $this->view('errors/404');
        }
        
        $dokumente = Dokument::findByVertrag($vertrag->id);
        
        return $this->view('partials/vertraege/documents-list', [
            'vertrag' => $vertrag,
            'dokumente' => $dokumente
        ]);
    }
    
    /**
     * Vertrag kündigen
     */
    public function kuendigen(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $vertrag = Vertrag::find($id);
        
        if (!$vertrag) {
            $this->json(['success' => false, 'message' => 'Vertrag nicht gefunden'], 404);
            return;
        }
        
        $this->authorize('update', 'vertrag', ['id' => $id]);
        
        try {
            $kuendigungsdatum = $_POST['kuendigungsdatum'] ?? null;
            $grund = $_POST['grund'] ?? '';
            
            if (!$kuendigungsdatum) {
                throw new \Exception('Kündigungsdatum ist erforderlich');
            }
            
            $vertrag->update([
                'korrespondent_id' => $vertrag->korrespondent_id,
                'beginn' => $vertrag->beginn->format('Y-m-d'),
                'ende' => $kuendigungsdatum,
                'status' => Vertrag::STATUS_GEKUENDIGT,
                'laufzeit_monate' => $vertrag->laufzeit_monate,
                'kosten_cent' => $vertrag->kosten_cent,
                'kosten_zeitraum' => $vertrag->kosten_zeitraum,
                'zahlungszyklus' => $vertrag->zahlungszyklus,
                'zahlungsweise' => $vertrag->zahlungsweise,
                'verlängerungsmodus' => $vertrag->verlängerungsmodus,
                'kuendigungsfrist_tage' => $vertrag->kuendigungsfrist_tage,
                'notizen' => $vertrag->notizen . "\n\n[Kündigung am " . date('Y-m-d') . "]\nGrund: " . $grund,
                'kuendigungsablauf' => $vertrag->kuendigungsablauf
            ]);
            
            $this->json([
                'success' => true,
                'message' => 'Vertrag erfolgreich gekündigt',
                'redirect' => "/vertraege/{$vertrag->id}"
            ]);
            
        } catch (\Exception $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Vertrag reaktivieren
     */
    public function reaktivieren(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $vertrag = Vertrag::find($id);
        
        if (!$vertrag) {
            $this->json(['success' => false, 'message' => 'Vertrag nicht gefunden'], 404);
            return;
        }
        
        $this->authorize('update', 'vertrag', ['id' => $id]);
        
        try {
            if ($vertrag->status === Vertrag::STATUS_LAUFEND) {
                throw new \Exception('Vertrag ist bereits aktiv');
            }
            
            $vertrag->update([
                'korrespondent_id' => $vertrag->korrespondent_id,
                'beginn' => $vertrag->beginn->format('Y-m-d'),
                'ende' => null, // Ende-Datum entfernen
                'status' => Vertrag::STATUS_LAUFEND,
                'laufzeit_monate' => $vertrag->laufzeit_monate,
                'kosten_cent' => $vertrag->kosten_cent,
                'kosten_zeitraum' => $vertrag->kosten_zeitraum,
                'zahlungszyklus' => $vertrag->zahlungszyklus,
                'zahlungsweise' => $vertrag->zahlungsweise,
                'verlängerungsmodus' => $vertrag->verlängerungsmodus,
                'kuendigungsfrist_tage' => $vertrag->kuendigungsfrist_tage,
                'notizen' => $vertrag->notizen . "\n\n[Reaktiviert am " . date('Y-m-d') . "]",
                'kuendigungsablauf' => $vertrag->kuendigungsablauf
            ]);
            
            $this->json([
                'success' => true,
                'message' => 'Vertrag erfolgreich reaktiviert'
            ]);
            
        } catch (\Exception $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Export Verträge als CSV
     */
    public function export(): void
    {
        $this->authorize('export', 'vertrag');
        
        try {
            $filters = [
                'status' => $_GET['status'] ?? '',
                'korrespondent_id' => $_GET['korrespondent_id'] ?? ''
            ];
            
            $vertraege = Vertrag::all($filters);
            
            $filename = 'vertraege_' . date('Y-m-d_H-i-s') . '.csv';
            
            header('Content-Type: text/csv; charset=utf-8');
            header("Content-Disposition: attachment; filename=\"$filename\"");
            
            $output = fopen('php://output', 'w');
            
            // UTF-8 BOM für Excel
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Header
            fputcsv($output, [
                'ID',
                'Korrespondent',
                'Beginn',
                'Ende',
                'Status',
                'Laufzeit (Monate)',
                'Kosten',
                'Kosten-Zeitraum',
                'Zahlungszyklus',
                'Zahlungsweise',
                'Verlängerungsmodus',
                'Kündigungsfrist (Tage)',
                'Monatliche Kosten',
                'Nächste Kündigungsfrist',
                'Tage bis Frist',
                'Notizen'
            ], ';');
            
            // Daten
            foreach ($vertraege as $vertrag) {
                $korrespondent = $vertrag->getKorrespondent();
                $kuendigungsfrist = $vertrag->getKuendigungsfrist();
                
                fputcsv($output, [
                    $vertrag->id,
                    $korrespondent ? $korrespondent->name : 'Unbekannt',
                    $vertrag->beginn->format('d.m.Y'),
                    $vertrag->ende ? $vertrag->ende->format('d.m.Y') : '',
                    $vertrag->status,
                    $vertrag->laufzeit_monate,
                    number_format($vertrag->kosten_cent / 100, 2, ',', '.') . ' €',
                    $vertrag->kosten_zeitraum,
                    $vertrag->zahlungszyklus,
                    $vertrag->zahlungsweise,
                    $vertrag->verlängerungsmodus,
                    $vertrag->kuendigungsfrist_tage,
                    number_format($vertrag->getMonthlyCoststCents() / 100, 2, ',', '.') . ' €',
                    $kuendigungsfrist ? $kuendigungsfrist->format('d.m.Y') : '',
                    $vertrag->getDaysUntilDeadline() ?? '',
                    str_replace(["\r", "\n"], ' ', $vertrag->notizen ?? '')
                ], ';');
            }
            
            fclose($output);
            
        } catch (\Exception $e) {
            http_response_code(500);
            echo 'Fehler beim Erstellen des Exports: ' . $e->getMessage();
        }
    }
    
    /**
     * Status-Optionen
     */
    private function getStatusOptions(): array
    {
        return [
            Vertrag::STATUS_LAUFEND => 'Laufend',
            Vertrag::STATUS_GEKUENDIGT => 'Gekündigt',
            Vertrag::STATUS_BEENDET => 'Beendet',
            Vertrag::STATUS_STORNIERT => 'Storniert'
        ];
    }
    
    /**
     * Kosten-Zeitraum-Optionen
     */
    private function getKostenZeitraumOptions(): array
    {
        return [
            Vertrag::KOSTEN_ZEITRAUM_MONAT => 'Monat',
            Vertrag::KOSTEN_ZEITRAUM_QUARTAL => 'Quartal',
            Vertrag::KOSTEN_ZEITRAUM_JAHR => 'Jahr'
        ];
    }
    
    /**
     * Zahlungszyklus-Optionen
     */
    private function getZahlungszyklusOptions(): array
    {
        return [
            Vertrag::ZAHLUNGSZYKLUS_MONAT => 'Monatlich',
            Vertrag::ZAHLUNGSZYKLUS_QUARTAL => 'Vierteljährlich',
            Vertrag::ZAHLUNGSZYKLUS_JAHR => 'Jährlich'
        ];
    }
    
    /**
     * Zahlungsweise-Optionen
     */
    private function getZahlungsweiseOptions(): array
    {
        return [
            Vertrag::ZAHLUNGSWEISE_SEPA => 'SEPA-Lastschrift',
            Vertrag::ZAHLUNGSWEISE_KREDITKARTE => 'Kreditkarte',
            Vertrag::ZAHLUNGSWEISE_UEBERWEISUNG => 'Überweisung',
            Vertrag::ZAHLUNGSWEISE_PAYPAL => 'PayPal',
            Vertrag::ZAHLUNGSWEISE_BAR => 'Bar',
            Vertrag::ZAHLUNGSWEISE_SONSTIGES => 'Sonstiges'
        ];
    }
    
    /**
     * Verlängerungs-Optionen
     */
    private function getVerlaengerungOptions(): array
    {
        return [
            Vertrag::VERLAENGERUNG_AUTOMATISCH => 'Automatisch',
            Vertrag::VERLAENGERUNG_MANUELL => 'Manuell',
            Vertrag::VERLAENGERUNG_KUENDIGUNGSFRIST => 'Mit Kündigungsfrist'
        ];
    }
}