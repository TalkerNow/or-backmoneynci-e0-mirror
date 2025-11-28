<?php

namespace App\Http\Controllers\Api;

use App\Models\Files;
use Illuminate\Http\Request;
use phpDocumentor\Reflection\Types\Nullable;

class FilesController extends Controller
{
    public function index(Request $request)
    {
        $files = Files::with(['fileCreator'])
            ->Where('user_id', $request->user_id)
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
    }

    public function update(Request $request, $id)
    {
        $file = Files::find($id);
        $file->update($request->all());
    }

    public function destroy($id)
    {
        $file = Files::find($id);
        $file->delete();
    }

public function uploadFiles(Request $request)
{
    $image_urls = [];

    $user_id = $request->input('user_id');
    $dossier = (int) $request->input('dossier', 0); // 👈 récupère le dossier (0 = non trié)

    // $request->allFiles() récupère tous les fichiers envoyés (photoUpload0, photoUpload1, etc.)
    foreach ($request->allFiles() as $file) {
        $size = $file->getSize();
        if ($size > 5000000) {
            return response()->json([
                'success' => false,
                'message' => 'Fichier trop volumineux (> 5 Mo)',
            ]);
        }

        $ext = $file->getClientOriginalExtension();
        $filename = $file->getClientOriginalName();
        $file_path = public_path() . "/img/" . $filename;

        if (file_exists($file_path)) {
            unlink($file_path);
        }

        $file->move(public_path() . '/img', $filename);

        $image_url = url('/') . "/img/" . $filename;

        if (!file_exists($file_path) || !is_readable($file_path)) {
            return response()->json(['success' => false]);
        } else {
            $fileModel = new Files();
            $fileModel->user_id = $user_id;
            $fileModel->filename = $filename;
            $fileModel->url = $image_url;
            $fileModel->dossier = $dossier; // 👈 ICI : on l’associe bien au dossier

            $fileModel->save();

            $image_urls[] = $fileModel;
        }
    }

    return response()->json([
        'success' => true,
        'files' => $image_urls,
    ]);
}

    public function downloadFile(Request $request){
        $file_id = $request->file_id;
        $file = Files::find($file_id);
        $file_url = public_path('img/'.$file->filename);
        return response()->download($file_url);
    }

    public function sendToN8n(Request $request)
    {
        $url = 'https://n8n.srv796541.hstgr.cloud/webhook/f012dfc7-8b2c-479f-af1f-20dcd44cda02';

        // Check if any file is present
        if (count($request->allFiles()) === 0) {
            return response()->json(['error' => 'No file provided'], 400);
        }

        // Get the first file
        $files = $request->allFiles();
        $file = reset($files);

        try {
            $response = \Illuminate\Support\Facades\Http::attach(
                'file', file_get_contents($file->getPathname()), $file->getClientOriginalName()
            )->post($url);

            $n8nData = $response->json();
            
            // Generate Report if data is valid
            $reportUrls = [];
            if ($response->successful() && !empty($n8nData)) {
                // Handle array response (n8n often returns an array of items)
                $dataToProcess = is_array($n8nData) && isset($n8nData[0]) ? $n8nData[0] : $n8nData;
                
                // Check if the text field contains the JSON string
                if (isset($dataToProcess['text'])) {
                     // Clean up markdown code blocks if present
                    $jsonString = str_replace(['```json', '```'], '', $dataToProcess['text']);
                    $parsedData = json_decode($jsonString, true);
                    if ($parsedData) {
                        $reportUrls = $this->generateReportNative($parsedData);
                    }
                } else {
                     // Assume data is directly in the object
                    $reportUrls = $this->generateReportNative($dataToProcess);
                }
            }

            return response()->json([
                'success' => $response->successful(),
                'n8n_status' => $response->status(),
                'n8n_body' => $n8nData,
                'report_urls' => $reportUrls
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function generateReportNative($data)
    {
        $templatePath = storage_path('app/templates/consultation_retraite.docx');
        
        if (!file_exists($templatePath)) {
            return ['error' => 'Template file not found at ' . $templatePath];
        }

        // Generate unique filename
        $filename = 'Rapport_Retraite_' . ($data['CLIENT_NOM'] ?? 'Client') . '_' . time() . '.docx';
        $outputPath = public_path('reports/' . $filename);

        // Ensure directory exists
        if (!file_exists(public_path('reports'))) {
            mkdir(public_path('reports'), 0755, true);
        }

        // Copy template to output
        if (!copy($templatePath, $outputPath)) {
            return ['error' => 'Failed to copy template'];
        }

        // Use ZipArchive to edit the document.xml inside the .docx
        $zip = new \ZipArchive;
        if ($zip->open($outputPath) === TRUE) {
            // Read the document content
            $xml = $zip->getFromName('word/document.xml');
            
            // Perform replacements
            // Note: In Word XML, variables might be split by formatting tags. 
            // This simple replacement assumes the placeholders are clean in the XML.
            foreach ($data as $key => $value) {
                if (is_string($value) || is_numeric($value)) {
                    $xml = str_replace('{{' . $key . '}}', htmlspecialchars($value), $xml);
                }
            }

            // Write back to the zip
            $zip->addFromString('word/document.xml', $xml);
            $zip->close();

            return [
                'docx' => url('reports/' . $filename),
                'pdf' => null // PDF generation requires external libraries (dompdf/wkhtmltopdf) which are not installed.
            ];
        } else {
            return ['error' => 'Failed to open DOCX file'];
        }
    }
}
