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
        $task = Files::find($id);
        $task->update($request->all());
    }

    public function destroy($id)
    {
        $task = Files::find($id);
        $task->delete();
    }

    public function uploadFiles(Request $request){
        $image_urls = [];
        $user_id = $request->user_id;
        foreach($request->files as $file){
            $size = $file->getSize();
            if ($size > 5000000) return false;
            $ext = $file->getClientOriginalExtension();
            $filename = $file->getClientOriginalName();
            $file_path = public_path(). "/img/".$filename;

            if (file_exists($file_path)) {
                unlink($file_path);
            }

            $file->move(public_path() . '/img', $filename);

            //---- import RTC file -------
            //reading payment_history.csv file

//            $image_url = url('/')."/public/img/".$filename;
            $image_url = url('/')."/img/".$filename;
            if (!file_exists($file_path) || !is_readable($file_path))
                return response()->json(['data'=>['success' => false]]);
            else {
                $file = new Files();
                $file->user_id = $user_id;
                $file->filename = $filename;
                $file->url = $image_url;

                $file->save();

                array_push($image_urls, $file);
            }
        }
        return response()->json([
            'success' => true,
            'files' => $image_urls
        ]);
    }
}
