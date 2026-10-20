<?php

namespace App\Modules\Daymark\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReportSpreadsheet
{
    private function workbook(string $title): Spreadsheet
    {
        $book = new Spreadsheet;
        $book->getProperties()->setCreator('Daymark')->setTitle($title);
        $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Reports');
        $sheet->getDefaultRowDimension()->setRowHeight(20);
        $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);

        return $book;
    }

    private function text(Worksheet $sheet, int $column, int $row, mixed $value): void
    {
        // Explicit string cells preserve dates and never execute user-authored formulas.
        $sheet->setCellValueExplicit([$column, $row], (string) ($value ?? ''), DataType::TYPE_STRING);
    }

    private function band(Worksheet $sheet, int $row, int $columns, string $label, string $color = '1C5948'): void
    {
        $sheet->mergeCells([1, $row, $columns, $row]);
        $this->text($sheet, 1, $row, $label);
        $sheet->getStyle([1, $row, $columns, $row])->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 12],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $color]],
            'alignment' => ['vertical' => 'center', 'wrapText' => true],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(30);
    }

    public function monthly(Collection $reports): Spreadsheet
    {
        $book = $this->workbook('Daymark monthly reports');
        $sheet = $book->getActiveSheet();
        $headers = ['Date', 'Employee', 'Company', 'Department', 'Status', 'Hours', 'Completed tasks', 'In progress', 'Next plan', 'Hurdles / blockers', 'Feedback'];
        foreach ($headers as $i => $header) {
            $this->text($sheet, $i + 1, 1, $header);
            $sheet->getColumnDimensionByColumn($i + 1)->setWidth([20, 25, 27, 24, 23, 10, 50, 50, 50, 45, 40][$i]);
        }
        $sheet->getStyle('A1:K1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1C5948']],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);
        foreach ($reports->values() as $i => $report) {
            $row = $i + 2;
            $values = [
                Carbon::parse($report->date)->format('d M Y'),
                $report->user->name,
                $report->user->company?->name,
                $report->user->department,
                str($report->status)->replace('_', ' ')->title(),
                $report->hours,
                $report->sectionText('completed'),
                $report->sectionText('progress'),
                $report->sectionText('plan'),
                $report->sectionText('blockers'),
                $report->feedback,
            ];
            foreach ($values as $j => $value) {
                $this->text($sheet, $j + 1, $row, $value);
            }
            $sheet->setCellValueExplicit([6, $row], $report->hours, DataType::TYPE_NUMERIC);
            $sheet->getRowDimension($row)->setRowHeight(-1);
        }
        $last = max(1, $reports->count() + 1);
        $sheet->getStyle('A2:K'.max(2, $last))->getAlignment()->setWrapText(true)->setVertical('top');
        $sheet->getStyle('A:A')->getNumberFormat()->setFormatCode('@');
        $sheet->setAutoFilter('A1:K'.$last);
        $sheet->freezePane('C2');

        return $book;
    }

    public function daily(Collection $companies, string $date): Spreadsheet
    {
        $book = $this->workbook('Daymark department report');
        $sheet = $book->getActiveSheet();
        foreach ([27, 100] as $i => $width) {
            $sheet->getColumnDimensionByColumn($i + 1)->setWidth($width);
        }
        $this->band($sheet, 1, 2, 'DAYMARK | DAILY TEAM REPORT');
        $this->text($sheet, 1, 2, 'Report date');
        $this->text($sheet, 2, 2, Carbon::parse($date)->format('l, d F Y'));
        $sheet->freezePane('A4');
        $row = 4;
        foreach ($companies as $company) {
            $this->band($sheet, $row++, 2, $company['name']);
            foreach ($company['departments'] as $department) {
                $this->band($sheet, $row++, 2, $department['name'].' | '.$department['reports']->count().' report(s)', '567461');
                foreach ($department['reports'] as $report) {
                    $this->band($sheet, $row++, 2, $report->user->name.' | '.$report->user->designation, '738579');
                    $fields = [
                        'Status' => str($report->status)->replace('_', ' ')->title(),
                        'Hours worked' => number_format($report->hours, 2),
                        'Completed tasks' => $report->sectionText('completed'),
                        'In progress' => $report->sectionText('progress'),
                        'Next plan' => $report->sectionText('plan'),
                        'Hurdles / blockers' => $report->sectionText('blockers'),
                    ];
                    if ($report->feedback) {
                        $fields['Reviewer feedback'] = $report->feedback;
                    }
                    foreach ($fields as $label => $value) {
                        // Multiple rows keep long reports readable without Excel's row-height limit.
                        $chunks = explode("\n", wordwrap(str_replace(["\r\n", "\r"], "\n", (string) $value), 90, "\n", true));
                        foreach (array_chunk($chunks, 12) as $index => $lines) {
                            $this->text($sheet, 1, $row, $index === 0 ? $label : $label.' (continued)');
                            $this->text($sheet, 2, $row, implode("\n", $lines));
                            $sheet->getStyle('A'.$row)->getFont()->setBold(true);
                            $sheet->getRowDimension($row)->setRowHeight(max(24, count($lines) * 16 + 10));
                            $row++;
                        }
                    }
                    $row++;
                }
            }
            $row++;
        }
        $sheet->getStyle('A1:B'.$row)->getAlignment()->setWrapText(true)->setVertical('top');
        $sheet->getPageSetup()->setOrientation('portrait');
        $sheet->getPageSetup()->setPrintArea('A1:B'.$row);

        return $book;
    }
}
