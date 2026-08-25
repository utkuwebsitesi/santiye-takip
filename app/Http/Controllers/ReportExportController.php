<?php

namespace App\Http\Controllers;

use App\Models\FuelEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReportExportController extends Controller
{
    public function cashExcel(Request $request): Response
    {
        abort_unless($request->user()?->hasPermission('transactions.view'), 403);
        $rows = $this->cashQuery($request)->get()->map(fn (Transaction $item): array => [
            $item->occurred_on->format('d.m.Y'),
            $item->type === 'income' ? 'Gelir' : 'Gider',
            $item->category,
            $item->description,
            $item->creator?->name ?? '—',
            number_format((float) $item->amount, 2, ',', '.').' ₺',
            $item->trashed() ? 'Silinmiş' : 'Aktif',
        ])->all();

        return $this->excelResponse(
            'kasa-hareket-raporu.xlsx',
            'Kasa Hareket Raporu',
            ['Tarih', 'Tür', 'Kategori', 'Açıklama', 'Personel', 'Tutar', 'Durum'],
            $rows,
            [6]
        );
    }

    public function fuelExcel(Request $request): Response
    {
        abort_unless($request->user()?->hasPermission('fuel.view'), 403);
        $rows = $this->fuelQuery($request)->get()->map(fn (FuelEntry $item): array => [
            $item->fuel_date->format('d.m.Y'),
            $item->vehicle?->display_name ?? '—',
            $item->tanker?->name ?? 'Eski kayıt',
            number_format((float) $item->liters, 3, ',', '.').' L',
            number_format((float) $item->unit_price, 4, ',', '.').' ₺/L',
            number_format((float) $item->total_amount, 2, ',', '.').' ₺',
            $item->creator?->name ?? '—',
            $item->trashed() ? 'Silinmiş' : 'Aktif',
        ])->all();

        return $this->excelResponse(
            'yakit-raporu.xlsx',
            'Yakıt Raporu',
            ['Tarih', 'Araç / Makine', 'Tanker', 'Litre', 'Birim Fiyat', 'Tutar', 'Personel', 'Durum'],
            $rows,
            [4, 5, 6]
        );
    }

    public function cashPdf(Request $request): Response
    {
        abort_unless($request->user()?->hasPermission('transactions.view'), 403);
        $items = $this->cashQuery($request)->get();
        $rows = $items->map(fn (Transaction $item): array => [
            $item->occurred_on->format('d.m.Y'),
            $item->type === 'income' ? 'Gelir' : 'Gider',
            $item->category,
            $item->description,
            $item->creator?->name ?? '—',
            number_format((float) $item->amount, 2, ',', '.').' TL',
            $item->trashed() ? 'Silinmis' : 'Aktif',
        ])->all();

        return $this->pdfResponse(
            'Kasa Hareket Raporu',
            ['Tarih', 'Tur', 'Kategori', 'Aciklama', 'Personel', 'Tutar', 'Durum'],
            $rows,
            'kasa-hareket-raporu.pdf',
            [
                ['label' => 'Toplam Gelir', 'value' => $this->money($items->where('type', 'income')->sum('amount')), 'tone' => 'green'],
                ['label' => 'Toplam Gider', 'value' => $this->money($items->where('type', 'expense')->sum('amount')), 'tone' => 'red'],
                ['label' => 'Net Degisim', 'value' => $this->money($items->where('type', 'income')->sum('amount') - $items->where('type', 'expense')->sum('amount')), 'tone' => 'navy'],
                ['label' => 'Kayit Sayisi', 'value' => number_format($items->count(), 0, ',', '.'), 'tone' => 'amber'],
            ],
            $this->reportFilters($request, 'cash')
        );
    }

    public function fuelPdf(Request $request): Response
    {
        abort_unless($request->user()?->hasPermission('fuel.view'), 403);
        $items = $this->fuelQuery($request)->get();
        $rows = $items->map(fn (FuelEntry $item): array => [
            $item->fuel_date->format('d.m.Y'),
            $item->vehicle?->display_name ?? '—',
            $item->tanker?->name ?? 'Eski kayıt',
            number_format((float) $item->liters, 3, ',', '.').' L',
            number_format((float) $item->unit_price, 4, ',', '.').' TL/L',
            number_format((float) $item->total_amount, 2, ',', '.').' TL',
            $item->trashed() ? 'Silinmis' : 'Aktif',
        ])->all();

        return $this->pdfResponse(
            'Yakıt Raporu',
            ['Tarih', 'Arac / Makine', 'Tanker', 'Litre', 'Birim Fiyat', 'Tutar', 'Durum'],
            $rows,
            'yakit-raporu.pdf',
            [
                ['label' => 'Toplam Litre', 'value' => number_format($items->sum('liters'), 3, ',', '.').' L', 'tone' => 'amber'],
                ['label' => 'Toplam Tutar', 'value' => $this->money($items->sum('total_amount')), 'tone' => 'navy'],
                ['label' => 'Ortalama Fiyat', 'value' => $this->money($items->sum('liters') > 0 ? $items->sum('total_amount') / $items->sum('liters') : 0).' / L', 'tone' => 'green'],
                ['label' => 'Kayit Sayisi', 'value' => number_format($items->count(), 0, ',', '.'), 'tone' => 'purple'],
            ],
            $this->reportFilters($request, 'fuel')
        );
    }

    /** @return Builder<Transaction> */
    private function cashQuery(Request $request): Builder
    {
        return Transaction::with('creator')
            ->where('affects_cash', true)
            ->when($request->user()?->hasPermission('transactions.manage') && $request->boolean('with_deleted'), fn (Builder $query) => $query->withTrashed())
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('occurred_on', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('occurred_on', '<=', $request->date('to')))
            ->when($request->filled('category'), fn (Builder $query) => $query->where('category', $request->string('category')))
            ->when($request->filled('created_by'), fn (Builder $query) => $query->where('created_by', $request->integer('created_by')))
            ->latest('occurred_on')->latest('id');
    }

    /** @return Builder<FuelEntry> */
    private function fuelQuery(Request $request): Builder
    {
        return FuelEntry::with(['vehicle', 'tanker', 'creator'])
            ->when($request->user()?->hasPermission('fuel.manage') && $request->boolean('with_deleted'), fn (Builder $query) => $query->withTrashed())
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('fuel_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('fuel_date', '<=', $request->date('to')))
            ->when($request->filled('vehicle_id'), fn (Builder $query) => $query->where('vehicle_id', $request->integer('vehicle_id')))
            ->latest('fuel_date')->latest('id');
    }

    /** @param array<int, array<int, string>> $rows */
    private function excelResponse(string $filename, string $title, array $headers, array $rows, array $numericColumns = []): Response
    {
        $temporaryFile = tempnam(sys_get_temp_dir(), 'santiye-report-');
        if ($temporaryFile === false) {
            abort(503, 'Rapor dosyası için geçici alan oluşturulamadı.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($temporaryFile, \ZipArchive::OVERWRITE) !== true) {
            @unlink($temporaryFile);
            abort(503, 'Excel raporu oluşturulamadı.');
        }
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Rapor" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFEAF1FB"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="5"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0"/><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="right"/></xf><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="right"/></xf></cellXfs></styleSheet>');

        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        $sheet .= $this->xlsxRow(1, [$title], 1);
        $sheet .= $this->xlsxRow(2, $headers, 2, $numericColumns);
        $rowNumber = 3;
        foreach ($rows as $row) {
            $sheet .= $this->xlsxRow($rowNumber++, $row, 0, $numericColumns);
        }
        $sheet .= '</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();

        return response()->download($temporaryFile, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache',
        ])->deleteFileAfterSend(true);
    }

    /** @param array<int, string> $values */
    private function xlsxRow(int $rowNumber, array $values, int $style = 0, array $numericColumns = []): string
    {
        $xml = '<row r="'.$rowNumber.'">';
        foreach ($values as $index => $value) {
            $cell = $this->xlsxColumn($index + 1).$rowNumber;
            $cellStyle = in_array($index + 1, $numericColumns, true) ? ($style === 2 ? 4 : 3) : $style;
            $xml .= '<c r="'.$cell.'" t="inlineStr"'.($cellStyle > 0 ? ' s="'.$cellStyle.'"' : '').'><is><t xml:space="preserve">'.$this->xml((string) $value).'</t></is></c>';
        }
        return $xml.'</row>';
    }

    private function xlsxColumn(int $number): string
    {
        $column = '';
        while ($number > 0) {
            $remainder = ($number - 1) % 26;
            $column = chr(65 + $remainder).$column;
            $number = intdiv($number - 1, 26);
        }

        return $column;
    }

    /**
     * Render a compact accounting-style PDF without an external PDF package.
     * The report uses fixed-width columns, summary cards, repeating headers and
     * page footers so long descriptions never turn into a screenshot-like line.
     *
     * @param array<int, array<int, string>> $rows
     * @param array<int, array{label: string, value: string, tone: string}> $summary
     * @param array<int, string> $filters
     */
    private function pdfResponse(string $title, array $headers, array $rows, string $filename, array $summary = [], array $filters = []): Response
    {
        $isFuel = in_array('Litre', $headers, true);
        $widths = $isFuel
            ? [55, 155, 120, 90, 105, 115, 138]
            : [55, 52, 80, 330, 75, 105, 81];
        $rowModels = $this->pdfRowModels($rows, $widths);
        $pages = $this->pdfPageRows($rowModels, 360, 430);

        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
        ];
        $pageRefs = [];
        $nextObject = 5;
        foreach ($pages as $pageNumber => $pageRows) {
            $pageObject = $nextObject++;
            $contentObject = $nextObject++;
            $pageRefs[] = $pageObject.' 0 R';
            $stream = $this->pdfReportPage($title, $headers, $widths, $pageRows, $summary, $filters, $pageNumber + 1, count($pages));
            $objects[$pageObject] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.$contentObject.' 0 R >>';
            $objects[$contentObject] = "<< /Length ".strlen($stream)." >>\nstream\n".$stream."\nendstream";
        }
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $pageRefs).'] /Count '.count($pageRefs).' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $number => $object) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        for ($number = 1; $number <= count($objects); $number++) {
            $pdf .= sprintf('%010d 00000 n %s', $offsets[$number] ?? 0, "\n");
        }
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /** @param array<int, array<int, string>> $rows @param array<int, int> $widths */
    private function pdfRowModels(array $rows, array $widths): array
    {
        $models = [];
        foreach ($rows as $row) {
            $cells = [];
            $maxLines = 1;
            foreach ($row as $index => $value) {
                $lines = $this->pdfWrap($this->ascii((string) $value), $widths[$index] ?? 80, 8.2, 5);
                $cells[] = ['lines' => $lines];
                $maxLines = max($maxLines, count($lines));
            }
            $models[] = [
                'cells' => $cells,
                'height' => max(26, min(70, 9 + ($maxLines * 10))),
            ];
        }

        return $models;
    }

    /** @param array<int, array{cells: array<int, array{lines: array<int, string>}>, height: int}> $rows */
    private function pdfPageRows(array $rows, int $firstLimit, int $nextLimit): array
    {
        $pages = [];
        $current = [];
        $height = 0;
        $limit = $firstLimit;
        foreach ($rows as $row) {
            if ($current !== [] && $height + $row['height'] > $limit) {
                $pages[] = $current;
                $current = [];
                $height = 0;
                $limit = $nextLimit;
            }
            $current[] = $row;
            $height += $row['height'];
        }
        if ($current !== [] || $pages === []) {
            $pages[] = $current;
        }

        return $pages;
    }

    /** @param array<int, int> $widths @param array<int, array{cells: array<int, array{lines: array<int, string>}>, height: int}> $rows */
    private function pdfReportPage(string $title, array $headers, array $widths, array $rows, array $summary, array $filters, int $pageNumber, int $pageCount): string
    {
        $navy = [0.04, 0.12, 0.24];
        $amber = [0.98, 0.67, 0.16];
        $muted = [0.37, 0.44, 0.53];
        $border = [0.84, 0.87, 0.91];
        $stream = $this->pdfRect(0, 535, 842, 60, $navy);
        $stream .= $this->pdfRect(32, 548, 30, 30, $amber);
        $stream .= $this->pdfText('ST', 39, 558, 12, 'F2', $navy);
        $stream .= $this->pdfText('Santiye Takip', 74, 566, 16, 'F2', [1, 1, 1]);
        $stream .= $this->pdfText('Kasa ve Yakit Yonetimi', 74, 548, 8.5, 'F1', [0.78, 0.84, 0.92]);
        $stream .= $this->pdfText($title, 810, 566, 13.5, 'F2', [1, 1, 1], 'right');
        $stream .= $this->pdfText('Olusturma: '.now()->format('d.m.Y H:i'), 810, 548, 8.5, 'F1', [0.78, 0.84, 0.92], 'right');

        if ($pageNumber === 1) {
            $cardTop = 516;
            $cardHeight = 44;
            $gap = 8;
            $cardWidth = (778 - ($gap * 3)) / 4;
            $tones = [
                'green' => [0.10, 0.52, 0.34],
                'red' => [0.78, 0.22, 0.22],
                'navy' => $navy,
                'amber' => [0.86, 0.51, 0.05],
                'purple' => [0.42, 0.29, 0.66],
            ];
            foreach ($summary as $index => $card) {
                $x = 32 + (($cardWidth + $gap) * $index);
                $tone = $tones[$card['tone']] ?? $navy;
                $stream .= $this->pdfRect($x, $cardTop - $cardHeight, $cardWidth, $cardHeight, [0.97, 0.98, 0.99]);
                $stream .= $this->pdfBorder($x, $cardTop - $cardHeight, $cardWidth, $cardHeight, $border);
                $stream .= $this->pdfLine($x, $cardTop - $cardHeight, $x + $cardWidth, $cardTop - $cardHeight, $tone, 2.5);
                $stream .= $this->pdfText($card['label'], $x + 10, $cardTop - 15, 7.5, 'F2', $muted);
                $stream .= $this->pdfText($card['value'], $x + 10, $cardTop - 33, 12, 'F2', $tone);
            }
            $filterText = implode(' | ', $filters ?: ['Tum kayitlar']);
            $stream .= $this->pdfText('Filtreler', 32, 443, 8.5, 'F2', $navy);
            $stream .= $this->pdfText($filterText, 80, 443, 8.5, 'F1', $muted);
            $tableTop = 421;
        } else {
            $stream .= $this->pdfText('Rapor devam ediyor - '.$title, 32, 510, 9, 'F2', $navy);
            $tableTop = 490;
        }

        $stream .= $this->pdfTable($tableTop, $headers, $widths, $rows, $border, $navy, $muted);
        $stream .= $this->pdfLine(32, 28, 810, 28, $border, 0.6);
        $stream .= $this->pdfText('Santiye Takip - Muhasebe raporu', 32, 16, 7.5, 'F1', $muted);
        $stream .= $this->pdfText('Sayfa '.$pageNumber.' / '.$pageCount, 810, 16, 7.5, 'F1', $muted, 'right');

        return $stream;
    }

    /** @param array<int, string> $headers @param array<int, int> $widths @param array<int, array{cells: array<int, array{lines: array<int, string>}>, height: int}> $rows */
    private function pdfTable(float $top, array $headers, array $widths, array $rows, array $border, array $navy, array $muted): string
    {
        $stream = '';
        $x = 32.0;
        $tableWidth = array_sum($widths);
        $headerHeight = 24.0;
        $stream .= $this->pdfRect($x, $top - $headerHeight, $tableWidth, $headerHeight, $navy);
        $columnX = $x;
        foreach ($headers as $index => $header) {
            $stream .= $this->pdfText($header, $columnX + 5, $top - 16, 7.4, 'F2', [1, 1, 1]);
            $columnX += $widths[$index];
            if ($index < count($headers) - 1) {
                $stream .= $this->pdfLine($columnX, $top - $headerHeight, $columnX, $top, [0.20, 0.28, 0.40], 0.35);
            }
        }
        $cursor = $top - $headerHeight;
        if ($rows === []) {
            $stream .= $this->pdfRect($x, $cursor - 42, $tableWidth, 42, [0.99, 0.99, 1]);
            $stream .= $this->pdfText('Kayit bulunamadi.', $x + 10, $cursor - 25, 8.5, 'F1', $muted);
            return $stream.$this->pdfBorder($x, $cursor - 42, $tableWidth, 42, $border);
        }
        foreach ($rows as $rowIndex => $row) {
            $height = $row['height'];
            $fill = $rowIndex % 2 === 0 ? [1, 1, 1] : [0.97, 0.98, 0.99];
            $stream .= $this->pdfRect($x, $cursor - $height, $tableWidth, $height, $fill);
            $columnX = $x;
            foreach ($row['cells'] as $index => $cell) {
                $header = $headers[$index] ?? '';
                $align = in_array($header, ['Tutar', 'Birim Fiyat', 'Litre', 'Kayit Sayisi'], true) ? 'right' : 'left';
                foreach ($cell['lines'] as $lineIndex => $line) {
                    $baseline = $cursor - 14 - ($lineIndex * 10);
                    $stream .= $this->pdfText($line, $align === 'right' ? $columnX + $widths[$index] - 5 : $columnX + 5, $baseline, 8.2, $lineIndex === 0 ? 'F1' : 'F1', $navy, $align);
                }
                $columnX += $widths[$index];
                if ($index < count($headers) - 1) {
                    $stream .= $this->pdfLine($columnX, $cursor - $height, $columnX, $cursor, $border, 0.35);
                }
            }
            $stream .= $this->pdfLine($x, $cursor - $height, $x + $tableWidth, $cursor - $height, $border, 0.45);
            $cursor -= $height;
        }

        return $stream.$this->pdfBorder($x, $cursor, $tableWidth, $top - $cursor, $border);
    }

    /** @return array<int, string> */
    private function pdfWrap(string $value, int $width, float $fontSize, int $maxLines): array
    {
        $value = trim($value);
        if ($value === '') {
            return ['-'];
        }
        $available = max(12.0, $width - 10.0);
        $lines = [];
        $line = '';
        foreach (preg_split('/\s+/', $value) ?: [$value] as $word) {
            if ($line === '') {
                $line = $word;
                continue;
            }
            $candidate = $line.' '.$word;
            if ($this->pdfTextWidth($candidate, $fontSize) <= $available) {
                $line = $candidate;
            } else {
                $lines[] = $line;
                $line = $word;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }
        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $last = $lines[$maxLines - 1];
            while ($last !== '' && $this->pdfTextWidth($last.'...', $fontSize) > $available) {
                $last = rtrim(substr($last, 0, -1));
            }
            $lines[$maxLines - 1] = rtrim($last).'...';
        }

        return $lines;
    }

    private function pdfRect(float $x, float $y, float $width, float $height, array $color): string
    {
        return sprintf("%.3f %.3f %.3f rg\n%.2f %.2f %.2f %.2f re\nf\n", $color[0], $color[1], $color[2], $x, $y, $width, $height);
    }

    private function pdfBorder(float $x, float $y, float $width, float $height, array $color): string
    {
        return sprintf("%.3f %.3f %.3f RG\n0.55 w\n%.2f %.2f %.2f %.2f re\nS\n", $color[0], $color[1], $color[2], $x, $y, $width, $height);
    }

    private function pdfLine(float $x1, float $y1, float $x2, float $y2, array $color, float $width): string
    {
        return sprintf("%.3f %.3f %.3f RG\n%.2f w\n%.2f %.2f m %.2f %.2f l\nS\n", $color[0], $color[1], $color[2], $width, $x1, $y1, $x2, $y2);
    }

    private function pdfText(string $text, float $x, float $y, float $size, string $font, array $color, string $align = 'left'): string
    {
        $text = $this->ascii($text);
        $width = $this->pdfTextWidth($text, $size);
        if ($align === 'right') {
            $x -= $width;
        } elseif ($align === 'center') {
            $x -= $width / 2;
        }

        return sprintf("%.3f %.3f %.3f rg\nBT\n/%s %.2f Tf\n1 0 0 1 %.2f %.2f Tm\n(%s) Tj\nET\n", $color[0], $color[1], $color[2], $font, $size, $x, $y, $this->pdfEscape($text));
    }

    private function pdfTextWidth(string $text, float $size): float
    {
        $width = 0.0;
        foreach (str_split($text) as $character) {
            $width += str_contains('ilI.,:;| ', $character) ? 0.25 : (str_contains('MW@#', $character) ? 0.82 : 0.52);
        }

        return $width * $size;
    }

    private function money(float|int $amount): string
    {
        return number_format((float) $amount, 2, ',', '.').' TL';
    }

    /** @return array<int, string> */
    private function reportFilters(Request $request, string $type): array
    {
        $filters = [];
        if ($request->filled('from') || $request->filled('to')) {
            $from = $request->date('from')?->format('d.m.Y') ?? 'Baslangic yok';
            $to = $request->date('to')?->format('d.m.Y') ?? 'Bitis yok';
            $filters[] = 'Donem: '.$from.' - '.$to;
        }
        if ($type === 'cash' && $request->filled('category')) {
            $filters[] = 'Kategori: '.$request->string('category')->toString();
        }
        if ($type === 'cash' && $request->filled('created_by')) {
            $filters[] = 'Personel: '.(User::find($request->integer('created_by'))?->name ?? 'Secilen personel');
        }
        if ($type === 'fuel' && $request->filled('vehicle_id')) {
            $filters[] = 'Arac/Makine: '.(Vehicle::find($request->integer('vehicle_id'))?->display_name ?? 'Secilen arac/makine');
        }
        if ($request->boolean('with_deleted')) {
            $filters[] = 'Silinenler dahil';
        }

        return $filters;
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function ascii(string $value): string
    {
        $value = strtr($value, [
            'ı' => 'i', 'İ' => 'I', 'ğ' => 'g', 'Ğ' => 'G',
            'ü' => 'u', 'Ü' => 'U', 'ş' => 's', 'Ş' => 'S',
            'ö' => 'o', 'Ö' => 'O', 'ç' => 'c', 'Ç' => 'C',
            '₺' => 'TL', '—' => '-', '–' => '-',
        ]);
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return $converted === false ? preg_replace('/[^\x20-\x7E]/', '', $value) : $converted;
    }

    private function pdfEscape(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }
}
