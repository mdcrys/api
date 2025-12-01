<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Events\AfterSheet;

class GenericExport implements FromCollection, WithEvents, WithStyles, WithCustomStartCell
{
    protected $empresa;
    protected $usuario;
    protected $data;
    protected $tipo;

    // PROPIEDADES PARA EL SEGUIMIENTO DE LA COMBINACIÓN DE CELDAS
    protected $previousProyecto = null;
    protected $previousSubseccion = null;
    protected $previousSubseccion2 = null;
    protected $mergeStartRow = 5; // Los datos comienzan en la fila 5

    // 🆕 MAPAS DE TRADUCCIÓN DE ID A TEXTO
    protected $mapaCondicionesAcceso = [
        1 => 'Público',
        2 => 'Confidencial',
    ];

    protected $mapaOrigenDocumentacion = [
        1 => 'Digital',
        2 => 'Físico',
        3 => 'Físico y/o digital',
    ];

    public function __construct($empresa, $usuario, $data, $tipo)
    {
        $this->empresa = $data['empresa'];
        $this->usuario = $data['usuario'];
        $this->data = $data;
        $this->tipo = $tipo;
    }

    // ----------------------------------------------------
    // 1. OBTENCIÓN DE DATOS Y LÓGICA DE OCULTACIÓN (MERGE PREP)
    // ----------------------------------------------------
    public function collection()
    {
        $filas = [];
        // Referencia a los mapas para usarlos dentro del bucle
        $mapaAcceso = $this->mapaCondicionesAcceso;
        $mapaOrigen = $this->mapaOrigenDocumentacion;

        foreach ($this->data['data'] as $item) {
            
            $proyecto_nombre = $item->proyecto_nombre;
            $subseccion_nombre = $item->subseccion_nombre;
            $subseccion2_nombre = $item->subseccion2_nombre;

            // --- Lógica de ocultación para A, B, C (Se mantiene igual) ---
            
            // --- COLUMNA A: PROYECTO ---
            if ($proyecto_nombre === $this->previousProyecto) {
                $proyecto_nombre_output = '';
            } else {
                $proyecto_nombre_output = $proyecto_nombre;
                $this->previousProyecto = $proyecto_nombre;
                $this->previousSubseccion = null;
                $this->previousSubseccion2 = null;
            }

            // --- COLUMNA B: SUBSECCIÓN ---
            if ($subseccion_nombre === $this->previousSubseccion && $proyecto_nombre_output === '') {
                $subseccion_nombre_output = '';
            } else {
                $subseccion_nombre_output = $subseccion_nombre;
                $this->previousSubseccion = $subseccion_nombre;
                $this->previousSubseccion2 = null;
            }

            // --- COLUMNA C: SUBSECCIÓN 2 ---
            if ($subseccion2_nombre === $this->previousSubseccion2 && $subseccion_nombre_output === '') {
                $subseccion2_nombre_output = '';
            } else {
                $subseccion2_nombre_output = $subseccion2_nombre;
                $this->previousSubseccion2 = $subseccion2_nombre;
            }


            $filas[] = [
                'proyecto_nombre'     => $proyecto_nombre_output,      // A
                'subseccion_nombre'   => $subseccion_nombre_output,    // B
                'subseccion2_nombre'  => $subseccion2_nombre_output,   // C
                'serie_nombre'        => $item->serie_nombre,          // D
                'subserie_nombre'     => $item->subserie_nombre,      // E
                'descripcion_serie'   => $item->descripcion_serie,    // F
                
                // ⚠️ Aplicación del mapeo de ID a TEXTO para G y H
                'origen_documentacion'=> $mapaOrigen[$item->origen_documentacion] ?? 'N/A', // G
                'condiciones_acceso'  => $mapaAcceso[$item->condiciones_acceso] ?? 'N/A',   // H
            ];
        }

        return collect($filas);
    }

    // ----------------------------------------------------
    // 2. CELDA DE INICIO
    // ----------------------------------------------------
    public function startCell(): string
    {
        return 'A5';
    }

    // ----------------------------------------------------
    // 3. ESTILOS BASE (Mantenidos para compatibilidad)
    // ----------------------------------------------------
    public function styles(Worksheet $sheet)
    {
        return [
            // TITULO (Talla 14)
            'A1' => ['font' => ['bold' => true, 'size' => 14]],
            // NOMBRE EMPRESA (Talla 11)
            'A2' => ['font' => ['bold' => true, 'size' => 11]],
        ];
    }

    // ----------------------------------------------------
    // 4. EVENTOS (ENCABEZADOS, ESTILOS DE ANCHO/WRAP, TAMAÑO DE CONTENIDO Y COMBINACIÓN)
    // ----------------------------------------------------
    
public function registerEvents(): array
{
    return [
        AfterSheet::class => function (AfterSheet $event) {

            $sheet = $event->sheet;
            $data = $this->data['data'];
            $totalRows = count($data);
            $endRow = $this->mergeStartRow + $totalRows - 1; 
            
            // 💡 Definición de anchos finales
            $fixedWidth = 20;               // Ancho para la columna E
            $jerarquiaWidth = 15;           // Ancho específico para A, B, C, G, H
            $serieDocumentalWidth = 30;     // Ancho específico para D
            $descripcionSerieWidth = 60;    // Ancho específico para F

            /* ============================
                FILA 1 & 2: TÍTULOS (Se mantienen igual)
            ============================ */
            $sheet->mergeCells('A1:H1');
            $sheet->setCellValue('A1', 'CUADRO GENERAL DE CLASIFICACIÓN DOCUMENTAL ');
            $sheet->getStyle('A1')->getAlignment()->setHorizontal('center');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            $sheet->getRowDimension(1)->setRowHeight(28);

            $sheet->mergeCells('A2:H2');
            $sheet->setCellValue('A2', $this->empresa->nombre_empresa);
            $sheet->getStyle('A2')->getAlignment()->setHorizontal('center');
            $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);
            $sheet->getRowDimension(2)->setRowHeight(23);

            /* ============================
                FILA 4 — ENCABEZADOS (Se mantienen igual)
            ============================ */
            $sheet->setCellValue('A4', 'SECCIÓN DOCUMENTAL');
            $sheet->setCellValue('B4', 'SUBSECCIÓN DOCUMENTAL');
            $sheet->setCellValue('C4', 'SUBSECCIÓN DOCUMENTAL 2');
            $sheet->setCellValue('D4', 'SERIE DOCUMENTAL');
            $sheet->setCellValue('E4', 'SUBSERIE DOCUMENTAL');
            $sheet->setCellValue('F4', 'DESCRIPCIÓN DE SERIE DOCUMENTAL');
            $sheet->setCellValue('G4', 'ORIGEN DE LA DOCUMENTACIÓN');
            $sheet->setCellValue('H4', 'CONDICIONES DE ACCESO');

            $headerCells = ['A4','B4','C4','D4','E4','F4','G4','H4'];
            foreach ($headerCells as $cell) {
                $sheet->getStyle($cell)->getAlignment()->setHorizontal('center');
                $sheet->getStyle($cell)->getAlignment()->setVertical('center');
                $sheet->getStyle($cell)->getFont()->setBold(true)->setSize(10);
                $sheet->getStyle($cell)->getAlignment()->setWrapText(true);
            }

            $sheet->getRowDimension(4)->setRowHeight(35);

            // 🚀 Lógica para Ancho de columnas FINALIZADA
            foreach (range('A','H') as $col) {
                if (in_array($col, ['A', 'B', 'C', 'G', 'H'])) { // 🚨 G y H ahora a 15
                    $sheet->getColumnDimension($col)->setWidth($jerarquiaWidth);
                } elseif ($col === 'D') {
                    $sheet->getColumnDimension($col)->setWidth($serieDocumentalWidth);
                } elseif ($col === 'F') {
                    $sheet->getColumnDimension($col)->setWidth($descripcionSerieWidth);
                } else { // Columna E (SUBSERIE DOCUMENTAL) se queda en 20
                    $sheet->getColumnDimension($col)->setWidth($fixedWidth);
                }
            }

            /* ============================
                LÓGICA DE COMBINACIÓN Y ESTILOS DE CONTENIDO
            ============================ */
            if ($totalRows > 0) {
                
                // --- Lógica de Combinación de Celdas (se mantiene igual) ---
                $startA = $this->mergeStartRow; $startB = $this->mergeStartRow; $startC = $this->mergeStartRow;
                $currentA = $data[0]->proyecto_nombre; $currentB = $data[0]->subseccion_nombre; $currentC = $data[0]->subseccion2_nombre;

                for ($i = 1; $i < $totalRows; $i++) {
                    $item = $data[$i]; $nextRow = $this->mergeStartRow + $i;
                    if ($item->proyecto_nombre !== $currentA) { if ($nextRow - 1 > $startA) { $sheet->mergeCells("A{$startA}:A" . ($nextRow - 1)); } $startA = $nextRow; $currentA = $item->proyecto_nombre; }
                    if ($item->subseccion_nombre !== $currentB || $item->proyecto_nombre !== $currentA) { if ($nextRow - 1 > $startB) { $sheet->mergeCells("B{$startB}:B" . ($nextRow - 1)); } $startB = $nextRow; $currentB = $item->subseccion_nombre; }
                    if ($item->subseccion2_nombre !== $currentC || $item->subseccion_nombre !== $currentB) { if ($nextRow - 1 > $startC) { $sheet->mergeCells("C{$startC}:C" . ($nextRow - 1)); } $startC = $nextRow; $currentC = $item->subseccion2_nombre; }
                }

                if ($endRow > $startA) { $sheet->mergeCells("A{$startA}:A{$endRow}"); }
                if ($endRow > $startB) { $sheet->mergeCells("B{$startB}:B{$endRow}"); }
                if ($endRow > $startC) { $sheet->mergeCells("C{$startC}:C{$endRow}"); }
                
                // --- ESTILOS DE CONTENIDO (Filas 5 en adelante) ---
                $dataRange = 'A' . $this->mergeStartRow . ':H' . $endRow;
                $GH_Range = 'G' . $this->mergeStartRow . ':H' . $endRow;

                $sheet->getStyle($dataRange)->getFont()->setSize(10); 
                $sheet->getStyle($dataRange)->getAlignment()->setVertical('center'); // Centrado Vertical
                $sheet->getStyle($dataRange)->getAlignment()->setWrapText(true); 
                
                // 🚨 NUEVO: Centrado Horizontal para columnas G y H
                $sheet->getStyle($GH_Range)->getAlignment()->setHorizontal('center'); 
            }
        }
    ];
}


}