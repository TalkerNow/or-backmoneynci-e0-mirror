<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Files;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
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
        $dossier = (int) $request->input('dossier', 0);

        foreach ($request->allFiles() as $file) {
            $size = $file->getSize();
            if ($size > 20000000) {
                return response()->json([
                    'success' => false,
                    'message' => 'Fichier trop volumineux (> 20 Mo)',
                ], 413);
            }

            $filename  = $file->getClientOriginalName();
            $mimeType  = $file->getMimeType();
            $content   = file_get_contents($file->getPathname());

            if ($content === false) {
                return response()->json(['success' => false, 'message' => 'Impossible de lire le fichier.'], 500);
            }

            // Stocker le contenu binaire directement en DB — rien sur le disque
            $fileModel = new Files();
            $fileModel->user_id      = $user_id;
            $fileModel->filename     = $filename;
            $fileModel->dossier      = $dossier;
            $fileModel->file_content = $content;
            $fileModel->mime_type    = $mimeType;
            $fileModel->file_size    = $size;
            $fileModel->save();

            // URL fetchable pointant vers le contenu stocké en DB.
            // Le front utilise ce champ pour : afficher le lien, fetch le HTML via /fetch-html, etc.
            $fileModel->url = url('/api/downloadFile?file_id=' . $fileModel->id);
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

        if (empty($file->file_content)) {
            return response()->json(['error' => 'File content not available in database'], 404);
        }

        $mimeType = $file->mime_type ?? 'application/octet-stream';

        return response($file->file_content, 200)
            ->header('Content-Type', $mimeType)
            ->header('Content-Disposition', 'attachment; filename="' . $file->filename . '"')
            ->header('Content-Length', strlen($file->file_content));
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
        // On récupère tout le body JSON
        $payload = $request->json()->all();

        // n8n envoie souvent un tableau [ {...} ]
        if (isset($payload[0]) && is_array($payload[0])) {
            $item = $payload[0];
        } else {
            $item = $payload;
        }

        $clientId = $item['client_id'] ?? null;

        // CAS 1 : réponse de n8n comme tu l’as collée : [ { "text": "```json\n{...}\n```" } ]
        if (isset($item['text']) && is_string($item['text'])) {
            $rawText = $item['text'];

            // On récupère juste le JSON entre { ... }
            if (preg_match('/\{.*\}/s', $rawText, $matches)) {
                $jsonString = $matches[0];
                $data = json_decode($jsonString, true);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun JSON trouvé dans text'
                ], 400);
            }

            if (!is_array($data)) {
                return response()->json([
                    'success' => false,
                    'message' => 'JSON invalide dans text'
                ], 400);
            }
        } else {
            // CAS 2 : si un jour tu envoies déjà un JSON propre
            $data = $item;
        }

        // DEBUG si tu veux voir ce qui arrive :
        // dd(array_keys($data));

        $reportUrls = $this->generateReportNative($data);

        return response()->json([
            'success'     => true,
            'report_urls' => $reportUrls,
            'client_id'   => $clientId,
            'debug_keys'  => array_slice(array_keys($data), 0, 10),
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

        // 3. Dossier de sortie dans public/reports
        $outputDir = public_path('reports');

        if (!is_dir($outputDir)) {
            if (!mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
                return ['error' => 'Failed to create reports directory: ' . $outputDir];
            }
        }

        $outputPath = $outputDir . DIRECTORY_SEPARATOR . $filename;

        // 4. Copier la template vers le fichier de sortie
        if (!copy($templatePath, $outputPath)) {
            return ['error' => 'Failed to copy template'];
        }

        // 5. Ouvrir le DOCX comme zip
        $zip = new \ZipArchive;
        if ($zip->open($outputPath) === true) {
            $xml = $zip->getFromName('word/document.xml');

            if ($xml === false) {
                $zip->close();
                return ['error' => 'document.xml not found in template'];
            }

            // 🧠 TRUC MAGIQUE : on matche TOUT ce qui va de {{ ... }} même si entre les deux il y a du XML
            // Exemple réel dans le XML :
            //   <w:t>{{CLIENT_</w:t></w:r><w:r><w:t>PRENOM}}</w:t>
            // => le regex /{{.*?}}/s va quand même matcher tout le bloc avec les balises

            $xml = preg_replace_callback('/\{\{.*?\}\}/s', function ($matches) use ($data) {
                $raw = $matches[0]; // ex: "{{CLIENT_</w:t></w:r><w:r><w:t>PRENOM}}"

                // 1) On vire toutes les balises XML à l'intérieur
                $textOnly = strip_tags($raw);          // => "{{CLIENT_PRENOM}}"

                // 2) On enlève tout sauf lettres/chiffres/underscore pour garder juste la clé
                //    "{{CLIENT_PRENOM}}" -> "CLIENT_PRENOM"
                $key = preg_replace('/[^\w]/', '', $textOnly);

                // 3) On cherche la valeur dans le JSON renvoyé par n8n
                $value = $data[$key] ?? '';

                // 4) On renvoie la valeur échappée pour XML
                return htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1);
            }, $xml);

            // 6. Écrire le XML modifié dans le DOCX
            $zip->addFromString('word/document.xml', $xml);
            $zip->close();

            // 7. Récupérer le contenu du fichier en base64 avant de le supprimer
            $fileContent = base64_encode(file_get_contents($outputPath));

            // Supprimer le fichier physique immédiatement pour ne pas saturer le disque
            @unlink($outputPath);

            return [
                'docx'           => url('reports/' . $filename),
                'docx_base64'    => $fileContent,
                'pdf'            => null,
            ];
        }

        return ['error' => 'Failed to open DOCX file'];
    }



}
