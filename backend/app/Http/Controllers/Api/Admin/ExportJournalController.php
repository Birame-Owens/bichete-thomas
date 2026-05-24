<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportJournalController extends Controller
{
    private const COLOR_ROSE    = 'FFe91e63';
    private const COLOR_ROSE_BG = 'FFfff2f7';
    private const COLOR_AMBER   = 'FFf59e0b';
    private const COLOR_GREEN   = 'FF10b981';
    private const COLOR_GRAY_BG = 'FFF9FAFB';
    private const COLOR_WHITE   = 'FFFFFFFF';
    private const COLOR_DARK    = 'FF111018';
    private const COLOR_HEADER  = 'FF1f2937';

    public function __invoke(Request $request): StreamedResponse
    {
        $request->validate([
            'annee' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $annee  = $request->integer('annee', (int) now()->year);
        $start    = CarbonImmutable::create($annee, 1, 1)->startOfDay();
        $yearEnd  = CarbonImmutable::create($annee, 12, 31)->endOfDay();
        // Pour l'année en cours, on s'arrête à aujourd'hui — les jours futurs
        // n'ont pas de données et feraient stagner le solde cumulatif.
        $end      = $yearEnd->greaterThan(now()) ? CarbonImmutable::today()->endOfDay() : $yearEnd;

        $journal = $this->buildJournal($start, $end);
        $recap   = $this->buildRecapMensuel($journal);
        $depenses = $this->getDetailDepenses($start, $end);

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setTitle("Journal {$annee}")
            ->setSubject("Journal financier {$annee}")
            ->setDescription("Export pour declaration au Tresor - Annee {$annee}");

        $this->buildSheetJournal($spreadsheet, $journal, $annee);
        $this->buildSheetRecapMensuel($spreadsheet, $recap, $annee);
        $this->buildSheetDepenses($spreadsheet, $depenses, $annee);

        $spreadsheet->setActiveSheetIndex(1);

        $filename = "journal-financier-{$annee}.xlsx";

        return new StreamedResponse(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
            'Pragma'              => 'no-cache',
            'Expires'             => '0',
        ]);
    }

    /**
     * Construit le journal journalier : entrées, sorties, solde net, solde cumulatif.
     *
     * @return array<int, array{date: string, label: string, entrees: float, sorties: float, net: float, cumul: float}>
     */
    private function buildJournal(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $entreesByDay  = $this->entreesByDay($start, $end);
        $sortiesByDay  = $this->sortiesByDay($start, $end);

        $journal = [];
        $cumul   = 0.0;
        $cursor  = $start;

        while ($cursor->lessThanOrEqualTo($end)) {
            $key     = $cursor->toDateString();
            $entrees = $entreesByDay[$key] ?? 0.0;
            $sorties = $sortiesByDay[$key] ?? 0.0;
            $net     = $entrees - $sorties;
            $cumul  += $net;

            $journal[] = [
                'date'    => $key,
                'label'   => $cursor->translatedFormat('d/m/Y'),
                'entrees' => $entrees,
                'sorties' => $sorties,
                'net'     => $net,
                'cumul'   => $cumul,
            ];

            $cursor = $cursor->addDay();
        }

        return $journal;
    }

    /**
     * Agrège le journal journalier par mois.
     *
     * @param  array<int, array{date: string, label: string, entrees: float, sorties: float, net: float, cumul: float}> $journal
     * @return array<int, array{mois: string, label: string, entrees: float, sorties: float, net: float}>
     */
    private function buildRecapMensuel(array $journal): array
    {
        $mois = [];

        foreach ($journal as $row) {
            $key = substr($row['date'], 0, 7); // YYYY-MM
            if (! isset($mois[$key])) {
                $date        = CarbonImmutable::parse($row['date']);
                $mois[$key]  = [
                    'mois'    => $key,
                    'label'   => ucfirst($date->translatedFormat('F Y')),
                    'entrees' => 0.0,
                    'sorties' => 0.0,
                    'net'     => 0.0,
                ];
            }
            $mois[$key]['entrees'] += $row['entrees'];
            $mois[$key]['sorties'] += $row['sorties'];
            $mois[$key]['net']     += $row['net'];
        }

        return array_values($mois);
    }

    /**
     * @return array<int, array{date: string, categorie: string, titre: string, montant: float, mode: string}>
     */
    private function getDetailDepenses(CarbonImmutable $start, CarbonImmutable $end): array
    {
        if (! Schema::hasTable('depenses')) {
            return [];
        }

        return DB::table('depenses')
            ->leftJoin('categories_depenses', 'depenses.categorie_depense_id', '=', 'categories_depenses.id')
            ->select(
                'depenses.date_depense',
                DB::raw("COALESCE(categories_depenses.nom, 'Sans categorie') as categorie"),
                'depenses.titre',
                'depenses.montant',
                DB::raw("COALESCE(depenses.mode_paiement, '') as mode_paiement"),
                DB::raw("COALESCE(depenses.description, '') as description"),
            )
            ->whereBetween('depenses.date_depense', [$start->toDateString(), $end->toDateString()])
            ->orderBy('depenses.date_depense')
            ->get()
            ->map(fn (object $row): array => [
                'date'      => CarbonImmutable::parse($row->date_depense)->format('d/m/Y'),
                'categorie' => (string) $row->categorie,
                'titre'     => (string) $row->titre,
                'montant'   => (float) $row->montant,
                'mode'      => $this->modeLabel((string) $row->mode_paiement),
                'description' => (string) $row->description,
            ])
            ->all();
    }

    /** @return array<string, float> */
    private function entreesByDay(CarbonImmutable $start, CarbonImmutable $end): array
    {
        if (! Schema::hasTable('paiements')) {
            return [];
        }

        $rows = DB::table('paiements')
            ->select(DB::raw('DATE(date_paiement) as jour'), DB::raw('SUM(montant) as total'))
            ->whereBetween('date_paiement', [$start, $end])
            ->where('statut', 'valide')
            ->whereIn('type', ['acompte', 'solde', 'complet', 'ajustement'])
            ->groupByRaw('DATE(date_paiement)')
            ->get();

        $remboursements = DB::table('paiements')
            ->select(DB::raw('DATE(date_paiement) as jour'), DB::raw('SUM(montant) as total'))
            ->whereBetween('date_paiement', [$start, $end])
            ->where('statut', 'valide')
            ->where('type', 'remboursement')
            ->groupByRaw('DATE(date_paiement)')
            ->get()
            ->keyBy('jour');

        return $rows->mapWithKeys(function (object $row) use ($remboursements): array {
            $remb = isset($remboursements[$row->jour]) ? (float) $remboursements[$row->jour]->total : 0.0;
            return [$row->jour => max(0.0, (float) $row->total - $remb)];
        })->all();
    }

    /** @return array<string, float> */
    private function sortiesByDay(CarbonImmutable $start, CarbonImmutable $end): array
    {
        if (! Schema::hasTable('depenses')) {
            return [];
        }

        return DB::table('depenses')
            ->select(DB::raw('DATE(date_depense) as jour'), DB::raw('SUM(montant) as total'))
            ->whereBetween('date_depense', [$start->toDateString(), $end->toDateString()])
            ->groupByRaw('DATE(date_depense)')
            ->get()
            ->mapWithKeys(fn (object $row): array => [$row->jour => (float) $row->total])
            ->all();
    }

    /**
     * Feuille 1 : journal journalier complet.
     *
     * @param array<int, array{date: string, label: string, entrees: float, sorties: float, net: float, cumul: float}> $journal
     */
    private function buildSheetJournal(Spreadsheet $spreadsheet, array $journal, int $annee): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Journal journalier');

        // Titre
        $sheet->setCellValue('A1', "JOURNAL FINANCIER {$annee}");
        $sheet->mergeCells('A1:E1');
        $this->styleTitre($sheet, 'A1:E1');

        // En-têtes
        $headers = ['Date', 'Entrees (FCFA)', 'Sorties (FCFA)', 'Solde net du jour', 'Solde cumulatif'];
        foreach ($headers as $i => $header) {
            $col = chr(65 + $i);
            $sheet->setCellValue("{$col}2", $header);
        }
        $this->styleEnTete($sheet, 'A2:E2');

        // Données
        $row = 3;
        foreach ($journal as $entry) {
            $sheet->setCellValue("A{$row}", $entry['label']);
            $sheet->setCellValue("B{$row}", $entry['entrees']);
            $sheet->setCellValue("C{$row}", $entry['sorties']);
            $sheet->setCellValue("D{$row}", $entry['net']);
            $sheet->setCellValue("E{$row}", $entry['cumul']);

            $this->styleLigneJournal($sheet, $row, $entry['net']);
            $row++;
        }

        // Totaux
        $totalRow = $row;
        $lastData = $row - 1;
        $sheet->setCellValue("A{$totalRow}", 'TOTAL');
        $sheet->setCellValue("B{$totalRow}", "=SUM(B3:B{$lastData})");
        $sheet->setCellValue("C{$totalRow}", "=SUM(C3:C{$lastData})");
        $sheet->setCellValue("D{$totalRow}", "=SUM(D3:D{$lastData})");
        $sheet->setCellValue("E{$totalRow}", $journal[count($journal) - 1]['cumul'] ?? 0);
        $this->styleTotaux($sheet, "A{$totalRow}:E{$totalRow}");

        // Format monétaire
        $moneyFormat = '#,##0 "FCFA"';
        $sheet->getStyle("B3:E{$totalRow}")->getNumberFormat()->setFormatCode($moneyFormat);

        // Largeurs
        $sheet->getColumnDimension('A')->setWidth(14);
        $sheet->getColumnDimension('B')->setWidth(22);
        $sheet->getColumnDimension('C')->setWidth(22);
        $sheet->getColumnDimension('D')->setWidth(22);
        $sheet->getColumnDimension('E')->setWidth(22);
        $sheet->freezePane('A3');
    }

    /**
     * Feuille 2 : récapitulatif mensuel.
     *
     * @param array<int, array{mois: string, label: string, entrees: float, sorties: float, net: float}> $recap
     */
    private function buildSheetRecapMensuel(Spreadsheet $spreadsheet, array $recap, int $annee): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Recapitulatif mensuel');

        $sheet->setCellValue('A1', "RECAPITULATIF MENSUEL {$annee} - DECLARATION TRESOR");
        $sheet->mergeCells('A1:D1');
        $this->styleTitre($sheet, 'A1:D1');

        $headers = ['Mois', 'Revenus (FCFA)', 'Depenses (FCFA)', 'Benefice net (FCFA)'];
        foreach ($headers as $i => $header) {
            $col = chr(65 + $i);
            $sheet->setCellValue("{$col}2", $header);
        }
        $this->styleEnTete($sheet, 'A2:D2');

        $row = 3;
        foreach ($recap as $m) {
            $sheet->setCellValue("A{$row}", $m['label']);
            $sheet->setCellValue("B{$row}", $m['entrees']);
            $sheet->setCellValue("C{$row}", $m['sorties']);
            $sheet->setCellValue("D{$row}", $m['net']);
            $this->styleLigneRecap($sheet, $row, $m['net']);
            $row++;
        }

        // Totaux
        $totalRow = $row;
        $lastData = $row - 1;
        $sheet->setCellValue("A{$totalRow}", 'TOTAL ANNUEL');
        $sheet->setCellValue("B{$totalRow}", "=SUM(B3:B{$lastData})");
        $sheet->setCellValue("C{$totalRow}", "=SUM(C3:C{$lastData})");
        $sheet->setCellValue("D{$totalRow}", "=SUM(D3:D{$lastData})");
        $this->styleTotaux($sheet, "A{$totalRow}:D{$totalRow}");

        $moneyFormat = '#,##0 "FCFA"';
        $sheet->getStyle("B3:D{$totalRow}")->getNumberFormat()->setFormatCode($moneyFormat);

        $sheet->getColumnDimension('A')->setWidth(22);
        $sheet->getColumnDimension('B')->setWidth(22);
        $sheet->getColumnDimension('C')->setWidth(22);
        $sheet->getColumnDimension('D')->setWidth(24);
        $sheet->freezePane('A3');
    }

    /**
     * Feuille 3 : détail des dépenses.
     *
     * @param array<int, array{date: string, categorie: string, titre: string, montant: float, mode: string, description: string}> $depenses
     */
    private function buildSheetDepenses(Spreadsheet $spreadsheet, array $depenses, int $annee): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Detail depenses');

        $sheet->setCellValue('A1', "DETAIL DES DEPENSES {$annee}");
        $sheet->mergeCells('A1:F1');
        $this->styleTitre($sheet, 'A1:F1');

        $headers = ['Date', 'Categorie', 'Libelle', 'Montant (FCFA)', 'Mode paiement', 'Description'];
        foreach ($headers as $i => $header) {
            $col = chr(65 + $i);
            $sheet->setCellValue("{$col}2", $header);
        }
        $this->styleEnTete($sheet, 'A2:F2');

        $row = 3;
        foreach ($depenses as $dep) {
            $sheet->setCellValue("A{$row}", $dep['date']);
            $sheet->setCellValue("B{$row}", $dep['categorie']);
            $sheet->setCellValue("C{$row}", $dep['titre']);
            $sheet->setCellValue("D{$row}", $dep['montant']);
            $sheet->setCellValue("E{$row}", $dep['mode']);
            $sheet->setCellValue("F{$row}", $dep['description']);

            $bg = $row % 2 === 0 ? self::COLOR_GRAY_BG : self::COLOR_WHITE;
            $sheet->getStyle("A{$row}:F{$row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB($bg);
            $sheet->getStyle("A{$row}:F{$row}")->getFont()->setSize(10);
            $row++;
        }

        if ($row > 3) {
            $totalRow = $row;
            $lastData = $row - 1;
            $sheet->setCellValue("C{$totalRow}", 'TOTAL DEPENSES');
            $sheet->setCellValue("D{$totalRow}", "=SUM(D3:D{$lastData})");
            $this->styleTotaux($sheet, "A{$totalRow}:F{$totalRow}");
            $sheet->getStyle("D3:D{$totalRow}")->getNumberFormat()->setFormatCode('#,##0 "FCFA"');
        }

        $sheet->getColumnDimension('A')->setWidth(14);
        $sheet->getColumnDimension('B')->setWidth(22);
        $sheet->getColumnDimension('C')->setWidth(28);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getColumnDimension('E')->setWidth(18);
        $sheet->getColumnDimension('F')->setWidth(30);
        $sheet->freezePane('A3');
    }

    private function styleTitre(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $range): void
    {
        $style = $sheet->getStyle($range);
        $style->getFont()->setBold(true)->setSize(14)->getColor()->setARGB(self::COLOR_WHITE);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COLOR_ROSE);
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(30);
    }

    private function styleEnTete(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $range): void
    {
        $style = $sheet->getStyle($range);
        $style->getFont()->setBold(true)->setSize(10)->getColor()->setARGB(self::COLOR_WHITE);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COLOR_HEADER);
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $style->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFD1D5DB');
        $sheet->getRowDimension(2)->setRowHeight(22);
    }

    private function styleLigneJournal(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $row, float $net): void
    {
        $bg = $row % 2 === 0 ? self::COLOR_GRAY_BG : self::COLOR_WHITE;
        $sheet->getStyle("A{$row}:E{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($bg);
        $sheet->getStyle("A{$row}:E{$row}")->getFont()->setSize(10);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);

        $netColor = $net >= 0 ? self::COLOR_GREEN : 'FFEF4444';
        $sheet->getStyle("D{$row}")->getFont()->getColor()->setARGB($netColor);
        $sheet->getStyle("E{$row}")->getFont()->setBold(true);
    }

    private function styleLigneRecap(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $row, float $net): void
    {
        $bg = $row % 2 === 0 ? self::COLOR_GRAY_BG : self::COLOR_WHITE;
        $sheet->getStyle("A{$row}:D{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($bg);
        $sheet->getStyle("A{$row}:D{$row}")->getFont()->setSize(10);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);

        $netColor = $net >= 0 ? self::COLOR_GREEN : 'FFEF4444';
        $sheet->getStyle("D{$row}")->getFont()->getColor()->setARGB($netColor);
        $sheet->getStyle("D{$row}")->getFont()->setBold(true);
        $sheet->getStyle("B{$row}")->getFont()->getColor()->setARGB(self::COLOR_ROSE);
        $sheet->getStyle("C{$row}")->getFont()->getColor()->setARGB(self::COLOR_AMBER);
    }

    private function styleTotaux(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $range): void
    {
        $style = $sheet->getStyle($range);
        $style->getFont()->setBold(true)->setSize(11)->getColor()->setARGB(self::COLOR_WHITE);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COLOR_DARK);
        $style->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setARGB(self::COLOR_ROSE);
    }

    private function modeLabel(string $mode): string
    {
        return [
            'especes'       => 'Especes',
            'wave'          => 'Wave',
            'orange_money'  => 'Orange Money',
            'carte_bancaire'=> 'Carte bancaire',
            'virement'      => 'Virement',
            'autre'         => 'Autre',
        ][$mode] ?? ucfirst(str_replace('_', ' ', $mode));
    }
}
