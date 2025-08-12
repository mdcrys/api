<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use ZipArchive;
use setasign\Fpdi\Fpdi;
use Illuminate\Support\Facades\Storage;
use GuzzleHttp\Client;

class ProcesarOcrJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $pdfPath;
    protected $indexacion;

    public function __construct($pdfPath, $indexacion)
    {
        $this->pdfPath = $pdfPath;
        $this->indexacion = $indexacion;
    }

    public function handle()
    {
        $apiKey = env('OPENAI_API_KEY');
        $fullPath = $this->pdfPath;

        $pdf = new Fpdi();
        $pageCount = $pdf->setSourceFile($fullPath);

        // Carpeta para guardar páginas y ZIP dentro de storage/app/public/temp
        $pagesPath = storage_path('app/public/temp/pages_' . uniqid());
        if (!file_exists($pagesPath)) {
            mkdir($pagesPath, 0777, true);
        }

        $zipName = 'paginas_separadas_' . uniqid() . '.zip';
        $zipPath = $pagesPath . DIRECTORY_SEPARATOR . $zipName;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
            // Manejar error si quieres
            return;
        }

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $pdfNew = new Fpdi();
            $pdfNew->setSourceFile($fullPath);
            $pdfNew->AddPage();
            $tplId = $pdfNew->importPage($pageNo);
            $pdfNew->useTemplate($tplId);

            $pagePdfName = "pagina_{$pageNo}.pdf";
            $pagePdfPath = $pagesPath . DIRECTORY_SEPARATOR . $pagePdfName;
            $pdfNew->Output('F', $pagePdfPath);

            // Convertir a PNG con Imagick
            $imagePath = $pagesPath . DIRECTORY_SEPARATOR . "pagina_{$pageNo}.png";
            $imagick = new \Imagick();
            $imagick->setResolution(300, 300);
            $imagick->readImage($pagePdfPath);
            $imagick->setImageFormat('png');
            $imagick->writeImage($imagePath);
            $imagick->clear();
            $imagick->destroy();

            // OCR con OpenAI
            $imgData = base64_encode(file_get_contents($imagePath));
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
            $extractedText = $responseBody['choices'][0]['message']['content'] ?? '';

            $txtPath = $pagesPath . DIRECTORY_SEPARATOR . "pagina_{$pageNo}.txt";
            file_put_contents($txtPath, $extractedText);

            $zip->addFile($pagePdfPath, $pagePdfName);
            $zip->addFile($txtPath, "pagina_{$pageNo}.txt");

            // Borra PNG temporal
            unlink($imagePath);
        }

        $zip->close();

        // Mover ZIP a storage/app/public/zip para que sea accesible via URL
        $publicZipDir = storage_path('app/public/zip');
        if (!file_exists($publicZipDir)) {
            mkdir($publicZipDir, 0777, true);
        }

        rename($zipPath, $publicZipDir . DIRECTORY_SEPARATOR . $zipName);

        // Actualizar registro en la BD con ruta y estado
        $this->indexacion->zip_path = 'zip/' . $zipName;
        $this->indexacion->status = 'completado';
        $this->indexacion->save();
    }
}
