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
        $url = 'https://n8n.srv796541.hstgr.cloud/webhook-test/f012dfc7-8b2c-479f-af1f-20dcd44cda02';

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

            return response()->json([
                'success' => $response->successful(),
                'n8n_status' => $response->status(),
                'n8n_body' => $response->json(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
