<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class TablaPlazosExport implements FromCollection, WithCustomStartCell, WithStyles
{
    protected $empresa;
    protected $indexaciones;

    public function __construct($empresa, $indexaciones)
    {
        $this->empresa = $empresa;
        $this->indexaciones = $indexaciones;
    }

    public function startCell(): string
    {
        // Los datos de la colección comienzan en la fila 5
        return 'A5';
    }

    public function styles(Worksheet $sheet)
    {
        // Fila 1: Título principal
        $sheet->setCellValue('A1', 'TABLA DE PLAZOS'); 
        $sheet->mergeCells('A1:P1'); 
        $sheet->getStyle('A1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 14,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFE0FFE0'],
            ],
            'borders' => [
                'outline' => [
                    'borderStyle' => Border::BORDER_MEDIUM,
                ],
            ],
        ]);
        
        // Fila 2: Fondo
        $sheet->setCellValue('A2', 'FONDO: CUERPO DE BOMBEROS DE NARAHUAL');
        $sheet->mergeCells('A2:P2'); 
        $sheet->getStyle('A2')->applyFromArray([
            'font' => [
                'bold' => true,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // --- Fila 3: Encabezados Superiores Fusionados ---
        
        $sheet->mergeCells('F3:I3');
        $sheet->setCellValue('F3', 'PLAZOS DE CONSERVACIÓN DOCUMENTAL EN AÑOS');
        
        $sheet->mergeCells('L3:M3');
        $sheet->setCellValue('L3', 'DISPOSICIÓN FINAL');

        $sheet->mergeCells('N3:P3');
        $sheet->setCellValue('N3', 'TÉCNICA DE SELECCIÓN');

        $sheet->getStyle('F3:P3')->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFFCE5CD'],
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
        ]);

        // --- Fila 4: Encabezados de Columna y Estilos Generales ---

        $sheet->getStyle('A4:P4')->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFFCE5CD'],
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
        ]);
        
        $sheet->getRowDimension(4)->setRowHeight(40);

        // A4: Insertar títulos que no tienen fusión superior
        $sheet->setCellValue('A4', 'SECCIÓN DOCUMENTAL');
        $sheet->setCellValue('B4', 'SUBSECCIÓN DOCUMENTAL');
        $sheet->setCellValue('C4', 'SUBSECCIÓN DOCUMENTAL 2');
        $sheet->setCellValue('D4', 'SERIE DOCUMENTAL');
        $sheet->setCellValue('E4', 'SUBSERIE DOCUMENTAL');
        
        // J4: BASE LEGAL (fusiona J4:K4)
        $sheet->mergeCells('J4:K4'); 
        $sheet->setCellValue('J4', 'BASE LEGAL'); 
        
        // F4: Títulos individuales bajo "PLAZOS DE CONSERVACIÓN"
        $sheet->setCellValue('F4', 'GESTIÓN');
        $sheet->setCellValue('G4', 'CENTRAL');
        $sheet->setCellValue('H4', 'INTERMEDIO');
        $sheet->setCellValue('I4', 'HISTÓRICO');
        
        // L4: Títulos individuales bajo "DISPOSICIÓN FINAL"
        $sheet->setCellValue('L4', 'ELIMINACIÓN');
        $sheet->setCellValue('M4', 'CONSERVACIÓN COMPLETA');

        // N4: Títulos individuales bajo "TÉCNICA DE SELECCIÓN"
        $sheet->setCellValue('N4', 'PARCIAL'); 
        $sheet->setCellValue('O4', 'CONSERVACIÓN'); // Asumo que son estos
        $sheet->setCellValue('P4', 'COMPLETA'); // Asumo que son estos


        foreach (range('A', 'P') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return [];
    }
    
    public function collection()
    {
        return $this->indexaciones->map(function ($item) {
            return [
                // Columna A: SECCIÓN DOCUMENTAL (Usando subseccion_nombre)
                $item->subseccion_nombre,
                
                // Columna B: SUBSECCIÓN DOCUMENTAL (Usando subseccion2_nombre)
                $item->subseccion2_nombre, 
                
                // Columna C: SUBSECCIÓN DOCUMENTAL 2 (Usando serie_nombre)
                $item->serie_nombre, 
                
                // Columna D: SERIE DOCUMENTAL (Usando subserie_nombre)
                $item->subserie_nombre, 
                
                // Columna E: SUBSERIE DOCUMENTAL (Usando descripcion_serie, ya es un texto)
                $item->descripcion_serie, 
                
                // Columna F a I: Plazos de Conservación (usando los campos directos)
                $item->plazo_gestion, 
                $item->plazo_central, 
                $item->plazo_intermedio, 
                $item->plazo_historico, 
                
                // Columna J & K: BASE LEGAL (usando el campo directo)
                $item->base_legal, 
                
                // Columna L a P: Disposición Final y Técnica (usando la lógica de 'X')
                ($item->disposicion_final === 'ELIMINACIÓN' ? 'X' : null), 
                ($item->disposicion_final === 'CONSERVACIÓN COMPLETA' ? 'X' : null),
                ($item->tecnica_seleccion === 'PARCIAL' ? 'X' : null),
                ($item->tecnica_seleccion === 'CONSERVACIÓN' ? 'X' : null),
                ($item->tecnica_seleccion === 'COMPLETA' ? 'X' : null),
            ];
        });
    }
}