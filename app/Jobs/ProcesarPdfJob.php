<?php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use ZipArchive;

use GuzzleHttp\Client;
use setasign\Fpdf\Fpdf;
use setasign\Fpdi\Fpdi;  // <--- ESTA LÍNEA ES CLAVE


class ProcesarPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $pdfPath;
    protected $jobId;

    public function __construct($pdfPath, $jobId)
    {
        $this->pdfPath = $pdfPath;
        $this->jobId = $jobId;
    }

    public function handle()
    {
         // \Log::info("Procesando ahora si desde aui job con ID: {$this->jobId}");
        try {
            $tempFolder = storage_path('app/temp');
            $pagesPath = $tempFolder . DIRECTORY_SEPARATOR . 'pages_' . uniqid();
            if (!file_exists($pagesPath)) mkdir($pagesPath, 0777, true);

            $pdf = new Fpdi();
            $pageCount = $pdf->setSourceFile($this->pdfPath);

            Cache::put("progreso_pdf_{$this->jobId}", [
                'estado' => 'procesando',
                'total_paginas' => $pageCount,
                'paginas_procesadas' => 0,
                'zip_path' => null,
                'error' => null,
            ], 3600);

             $zip = new ZipArchive;
            $zipName = 'paginas_separadas_' . $this->jobId . '.zip';
            $zipPath = $tempFolder . DIRECTORY_SEPARATOR . $zipName;

            if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
                Cache::put("progreso_pdf_{$this->jobId}.error", 'No se pudo crear el ZIP');
                return;
            }

            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $pdfNew = new Fpdi();
                $pdfNew->setSourceFile($this->pdfPath);
                $pdfNew->AddPage();
                $tplId = $pdfNew->importPage($pageNo);
                $pdfNew->useTemplate($tplId);

                $pagePdfName = "pagina_{$pageNo}.pdf";
                $pagePdfPath = $pagesPath . DIRECTORY_SEPARATOR . $pagePdfName;
                $pdfNew->Output('F', $pagePdfPath);

                // Convertir página a imagen PNG para OCR y mejorar imagen (puedes adaptar esto si quieres)
                $imagePath = $pagesPath . DIRECTORY_SEPARATOR . "pagina_{$pageNo}.png";
                $imagick = new \Imagick();
                $imagick->setResolution(300, 300);
                $imagick->readImage($pagePdfPath);
                $imagick->setImageColorspace(\Imagick::COLORSPACE_GRAY);
                $imagick->contrastImage(true);
                $imagick->sharpenImage(1, 0.5);
                $imagick->setImageFormat('png');
                $imagick->writeImage($imagePath);
                $imagick->clear();
                $imagick->destroy();

                // Preparar base64 para OpenAI
                $imgData = base64_encode(file_get_contents($imagePath));
                $apiKey = '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';

                $payload = [
                    "model" => "gpt-4o",
                    "messages" => [
                        [
                            "role" => "user",
                            "content" => [
                                [
                                    "type" => "text",
                                    "text" => "Extrae TODO el texto visible de esta imagen. No expliques nada, solo devuelve el texto tal como aparece."
                                ],
                                [
                                    "type" => "image_url",
                                    "image_url" => [
                                        "url" => "data:image/png;base64,{$imgData}"
                                    ]
                                ]
                            ]
                        ]
                    ],
                    "max_tokens" => 3000,
                ];

                $client = new Client();
                $response = $client->post('https://api.openai.com/v1/chat/completions', [
                    'headers' => [
                        'Authorization' => "Bearer {$apiKey}",
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $payload,
                ]);

                $responseBody = json_decode($response->getBody(), true);
                $extractedText = '';
                if (isset($responseBody['choices'][0]['message']['content'])) {
                    $extractedText = $responseBody['choices'][0]['message']['content'];
                }

                $txtPath = $pagesPath . DIRECTORY_SEPARATOR . "pagina_{$pageNo}.txt";
                file_put_contents($txtPath, $extractedText);

                $zip->addFile($pagePdfPath, $pagePdfName);
                $zip->addFile($txtPath, "pagina_{$pageNo}.txt");

                unlink($imagePath);

                // Actualizar progreso
                Cache::put("progreso_pdf_{$this->jobId}", [
                    'estado' => 'procesando',
                    'total_paginas' => $pageCount,
                    'paginas_procesadas' => $pageNo,
                    'zip_path' => $zipPath,
                    'error' => null,
                ], 3600);
            }

            $zip->close();

            // Marcar como terminado
            Cache::put("progreso_pdf_{$this->jobId}", [
                'estado' => 'finalizado',
                'total_paginas' => $pageCount,
                'paginas_procesadas' => $pageCount,
                'zip_path' => $zipPath,
                'error' => null,
            ], 3600);

            // Opcional: borrar PDF original si no se necesita
            // unlink($this->pdfPath);

        } catch (\Exception $e) {
            Cache::put("progreso_pdf_{$this->jobId}", [
                'estado' => 'error',
                'total_paginas' => 0,
                'paginas_procesadas' => 0,
                'zip_path' => null,
                'error' => $e->getMessage(),
            ], 3600);
        }
    }
}
