<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Files;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class FilesController extends Controller
{
    public function index(Request $request)
    {
        $files = Files::with(['fileCreator'])
            ->where('user_id', $request->user_id)
            ->orderBy('created_at', 'desc')
            ->get();

        return $files->toJson(JSON_PRETTY_PRINT);
    }

    public function store(Request $request)
    {
        return Files::create($request->all());
    }

    public function show($id)
    {
        //
    }

    public function update(Request $request, $id)
    {
        $file = Files::find($id);

        if (!$file) {
            return response()->json(['error' => 'File not found'], 404);
        }

        $file->update($request->all());

        return response()->json(['success' => true]);
    }

    public function destroy($id)
    {
        $file = Files::find($id);

        if (!$file) {
            return response()->json(['error' => 'File not found'], 404);
        }

        $file->delete();

        return response()->json(['success' => true]);
    }

    public function uploadFiles(Request $request)
    {
        $image_urls = [];

        $user_id = $request->input('user_id');
        $dossier = (int) $request->input('dossier', 0); // 0 = non trié

        foreach ($request->allFiles() as $file) {
            $size = $file->getSize();
            if ($size > 5000000) {
                return response()->json([
                    'success' => false,
                    'message' => 'Fichier trop volumineux (> 5 Mo)',
                ], 413);
            }

            $ext = $file->getClientOriginalExtension();
            $filename = $file->getClientOriginalName();
            $file_path = public_path('img/' . $filename);

            if (file_exists($file_path)) {
                unlink($file_path);
            }

            $file->move(public_path('img'), $filename);
            $image_url = url('img/' . $filename);

            if (!file_exists($file_path) || !is_readable($file_path)) {
                return response()->json(['success' => false], 500);
            }

            $fileModel = new Files();
            $fileModel->user_id = $user_id;
            $fileModel->filename = $filename;
            $fileModel->url = $image_url;
            $fileModel->dossier = $dossier;
            $fileModel->save();

            $image_urls[] = $fileModel;
        }

        return response()->json([
            'success' => true,
            'files'   => $image_urls,
        ]);
    }

    public function downloadFile(Request $request)
    {
        $file_id = $request->file_id;
        $file = Files::find($file_id);

        if (!$file) {
            return response()->json(['error' => 'File not found'], 404);
        }

        $file_path = public_path('img/' . $file->filename);

        if (!file_exists($file_path)) {
            return response()->json(['error' => 'File not found on disk'], 404);
        }

        return response()->download($file_path);
    }

    /**
     * ANCIEN FLUX : envoi du fichier à n8n + génération directe
     * ⚠️ Long / sujet au timeout sur OVH → à NE PLUS utiliser côté front.
     */
    public function sendToN8n(Request $request)
    {
        $url = 'https://n8n.srv796541.hstgr.cloud/webhook/f012dfc7-8b2c-479f-af1f-20dcd44cda02';

        if (count($request->allFiles()) === 0) {
            return response()->json(['error' => 'No file provided'], 400);
        }

        $files = $request->allFiles();
        $file = reset($files);

        try {
            $response = Http::attach(
                'file',
                file_get_contents($file->getPathname()),
                $file->getClientOriginalName()
            )->post($url);

            $n8nData = $response->json();

            $reportUrls = [];
            if ($response->successful() && !empty($n8nData)) {
                $dataToProcess = is_array($n8nData) && isset($n8nData[0])
                    ? $n8nData[0]
                    : $n8nData;

                if (isset($dataToProcess['text'])) {
                    if (preg_match('/\{.*\}/s', $dataToProcess['text'], $matches)) {
                        $jsonString = $matches[0];
                        $parsedData = json_decode($jsonString, true);
                        if ($parsedData) {
                            $reportUrls = $this->generateReportNative($parsedData);
                        }
                    } else {
                        $jsonString = str_replace(
                            ['```json', '```'],
                            '',
                            $dataToProcess['text']
                        );
                        $parsedData = json_decode($jsonString, true);
                        if ($parsedData) {
                            $reportUrls = $this->generateReportNative($parsedData);
                        }
                    }
                } else {
                    $reportUrls = $this->generateReportNative($dataToProcess);
                }
            }

            return response()->json([
                'success'             => $response->successful(),
                'n8n_status'          => $response->status(),
                'n8n_body'            => $n8nData,
                'report_urls'         => $reportUrls,
                'debug_json_error'    => json_last_error_msg(),
                'debug_data_to_process' => isset($dataToProcess) ? $dataToProcess : 'Not Set',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * NOUVEAU FLUX : le front envoie directement le JSON à partir de n8n
     * + éventuellement client_id.
     *
     * POST /api/generate-report
     * Body JSON = toutes les clés (RAPPORT_DATE, CLIENT_NOM, SC1_..., etc.)
     * + optionnellement "client_id".
     */
    public function generateReportFromJson(Request $request)
    {
        // On récupère toutes les données du rapport
        $payload = $request->all();

        // Optionnel : extraire l'id client si tu l'envoies
        $clientId = $request->input('client_id');
        unset($payload['client_id']); // on le retire pour ne pas polluer les placeholders

        if (empty($payload) || !is_array($payload)) {
            return response()->json([
                'success' => false,
                'message' => 'No report data provided',
            ], 422);
        }

        $reportUrls = $this->generateReportNative($payload);

        return response()->json([
            'success'     => !isset($reportUrls['error']),
            'report_urls' => $reportUrls,
            'client_id'   => $clientId,
        ]);
    }

    /**
     * Remplit la template Word avec les données fournies.
     */
    private function generateReportNative(array $data)
    {
        // 1. Template DOCX
        $templatePath = storage_path('app/templates/consultation_retraite.docx');

        if (!file_exists($templatePath)) {
            return ['error' => 'Template file not found at ' . $templatePath];
        }

        // 2. Nom du fichier de sortie
        $filename = 'Rapport_Retraite_' . ($data['CLIENT_NOM'] ?? 'Client') . '_' . time() . '.docx';

        // 3. Dossier de sortie DANS public (comme /img)
        $outputDir = public_path('reports');

        if (!is_dir($outputDir)) {
            // Ce code ne sera PAS exécuté si tu crées le dossier à la main
            if (!mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
                return ['error' => 'Failed to create reports directory: ' . $outputDir];
            }
        }

        $outputPath = $outputDir . DIRECTORY_SEPARATOR . $filename;

        // 4. Copier la template vers le fichier de sortie
        if (!copy($templatePath, $outputPath)) {
            return ['error' => 'Failed to copy template'];
        }

        // 5. Modifier le DOCX (Zip)
        $zip = new \ZipArchive;
        if ($zip->open($outputPath) === true) {
            $xml = $zip->getFromName('word/document.xml');

            if ($xml === false) {
                $zip->close();
                return ['error' => 'document.xml not found in template'];
            }

            foreach ($data as $key => $value) {
                if (is_string($value) || is_numeric($value)) {
                    $xml = str_replace('{{' . $key . '}}', htmlspecialchars((string) $value), $xml);
                }
            }

            $zip->addFromString('word/document.xml', $xml);
            $zip->close();

            // 6. URL publique : /reports/xxx.docx
            return [
                'docx' => url('reports/' . $filename),
                'pdf'  => null,
            ];
        }

        return ['error' => 'Failed to open DOCX file'];
    }

}
