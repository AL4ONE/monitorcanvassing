<?php

namespace App\Http\Controllers;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class TemplateController extends Controller
{
    /**
     * Generate and download Excel template
     */
    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set headers
        $headers = [
            'A1' => 'Nama Merchant',
            'B1' => 'Kategori',
            'C1' => 'Jenis Usaha',
            'D1' => 'Channel',
            'E1' => 'Link IG Merchant',
            'F1' => 'Status',
            'G1' => 'Catatan',
            'H1' => 'SS Chat (fu0)',
            'I1' => 'Tanggal',
            'J1' => 'fu1',
            'K1' => 'fu2',
            'L1' => 'fu3',
            'M1' => 'fu4',
            'N1' => 'fu5',
            'O1' => 'fu6',
            'P1' => 'fu7',
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        // Style headers
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4F46E5'], // Indigo
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ];

        $sheet->getStyle('A1:P1')->applyFromArray($headerStyle);

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(20);
        $sheet->getColumnDimension('D')->setWidth(12);
        $sheet->getColumnDimension('E')->setWidth(30);
        $sheet->getColumnDimension('F')->setWidth(15);
        $sheet->getColumnDimension('G')->setWidth(30);
        $sheet->getColumnDimension('H')->setWidth(15);
        $sheet->getColumnDimension('I')->setWidth(12);

        foreach (range('J', 'P') as $col) {
            $sheet->getColumnDimension($col)->setWidth(15);
        }

        // Add sample data
        $sampleData = [
            [
                'kedai.cekni',
                'Menengah',
                'Coffee Shop',
                'IG',
                'https://instagram.com/kedai.cekni',
                'Ongoing',
                'Tertarik dengan promo',
                '[Insert Image]',
                date('Y-m-d'),
                '[Insert Image]',
                '[Insert Image]',
                '',
                '',
                '',
                '',
                '',
            ],
        ];

        $row = 2;
        foreach ($sampleData as $data) {
            $col = 'A';
            foreach ($data as $value) {
                $sheet->setCellValue($col . $row, $value);
                $col++;
            }
            $row++;
        }

        // Add instructions sheet
        $instructionSheet = $spreadsheet->createSheet();
        $instructionSheet->setTitle('Instruksi');

        $instructions = [
            ['INSTRUKSI PENGGUNAAN TEMPLATE IMPORT'],
            [''],
            ['1. Format Kolom:'],
            ['   - Kolom A: Nama Merchant (Instagram Username) - WAJIB'],
            ['   - Kolom B: Kategori (Menengah/Kecil)'],
            ['   - Kolom C: Jenis Usaha (Coffee Shop/Restoran)'],
            ['   - Kolom D: Channel (IG/TikTok/Facebook)'],
            ['   - Kolom E: Link Instagram'],
            ['   - Kolom F: Status (Ongoing/Rejected/Converted)'],
            ['   - Kolom G: Catatan'],
            ['   - Kolom H: Screenshot Canvassing (fu0)'],
            ['   - Kolom I: Tanggal'],
            ['   - Kolom J-P: Screenshot FU 1-7'],
            [''],
            ['2. Cara Insert Foto:'],
            ['   - Klik kanan cell → Insert → Picture'],
            ['   - Pilih foto dari komputer'],
            ['   - Resize foto agar pas di cell'],
            [''],
            ['3. Upload file Excel yang sudah diisi'],
        ];

        $instructionRow = 1;
        foreach ($instructions as $instruction) {
            $instructionSheet->setCellValue('A' . $instructionRow, $instruction[0]);
            $instructionRow++;
        }

        $instructionSheet->getColumnDimension('A')->setWidth(80);
        $instructionSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $spreadsheet->setActiveSheetIndex(0);

        // Generate file
        $writer = new Xlsx($spreadsheet);

        $filename = 'template_import_canvassing_' . date('Ymd') . '.xlsx';
        $tempFile = storage_path('app/temp/' . $filename);

        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $writer->save($tempFile);

        return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
    }
}
