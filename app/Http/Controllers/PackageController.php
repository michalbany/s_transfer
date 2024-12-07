<?php

namespace App\Http\Controllers;

use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PackageController extends Controller
{
    public function create(Request $request)
    {
        return Inertia::render('Package/Create');
    }

    public function initUpload(Request $request)
    {
        // Vygenerujeme token pro celý upload.
        $token = Str::random(40);
        Storage::makeDirectory('chunks');
        Storage::makeDirectory('zips');
        // Můžete token a seznam souborů, které čekáte, uložit do session nebo DB, ale pro zjednodušení to necháme takto.
        return response()->json(['token' => $token]);
    }

    public function uploadChunk(Request $request)
    {
        $request->validate([
            'file' => 'required|file',
            'token' => 'required|string',
            'filename' => 'required|string',
            'chunk_index' => 'required|integer',
            'total_chunks' => 'required|integer',
        ]);

        $token = $request->input('token');
        $filename = $request->input('filename');
        $chunkIndex = (int)$request->input('chunk_index');
        $totalChunks = (int)$request->input('total_chunks');

        // Uložíme chunk do dočasné složky
        $path = "chunks/$token/$filename";
        Storage::makeDirectory($path);

        $file = $request->file('file');
        $file->storeAs($path, "chunk_$chunkIndex");

        return response()->json(['status' => 'chunk_received']);
    }

    public function finalizeUpload(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'files' => 'required|array'
        ]);
    
        $token = $request->input('token');
        $files = $request->input('files');
        $tempPath = Storage::path("chunks/$token");
        $finalZipName = $token . '.zip';
        $finalZipPath = Storage::path("zips/$finalZipName");
    
        $zip = new \ZipArchive();
        if ($zip->open($finalZipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== TRUE) {
            return response()->json(['error' => 'Cannot create ZIP file'], 500);
        }
    
        foreach ($files as $f) {
            $type = $f['type'];
            $relativePath = ltrim($f['relativePath'], '/'); // odstraníme počáteční '/'
    
            if ($type === 'directory') {
                // Přidáme prázdnou složku
                if (!$zip->addEmptyDir($relativePath)) {
                    // \Log::error("Failed to add directory: $relativePath to ZIP");
                }
            } else {
                // Soubor
                $totalChunks = $f['total_chunks'];
                $content = '';
    
                for ($i = 0; $i < $totalChunks; $i++) {
                    $chunkPath = "$tempPath/$relativePath/chunk_$i";
                    if (!file_exists($chunkPath)) {
                        // \Log::error("Chunk file does not exist: $chunkPath");
                        continue;
                    }
                    $chunkContent = file_get_contents($chunkPath);
                    if ($chunkContent === false) {
                        // \Log::error("Failed to read chunk file: $chunkPath");
                        continue;
                    }
                    $content .= $chunkContent;
                }
    
                // Přidáme soubor do ZIPu s jeho relativní cestou
                if (!$zip->addFromString($relativePath, $content)) {
                    // \Log::error("Failed to add file: $relativePath to ZIP");
                }
            }
        }
    
        $zip->close();
        Storage::deleteDirectory("chunks/$token");
    
        // Vytvoříme Package záznam
        $package = Package::create([
            'token' => $token,
            'filename' => $finalZipName,
            'expires_at' => now()->addDays(7),
        ]);
    
        return response()->json([
            'link' => route('packages.show', $token),
        ]);
    }
    

    public function show($token)
    {
        $package = Package::where('token', $token)->firstOrFail();

        if ($package->expires_at < now()) {
            abort(404, 'File expired');
        }

        return Inertia::render('Package/Show', [
            'link' => route('packages.download', $token),
        ]);
    }

    public function download($token)
    {
        $package = Package::where('token', $token)->firstOrFail();

        if ($package->expires_at < now()) {
            abort(404, 'File expired');
        }

        $path = Storage::path('zips/' . $package->filename);
        return response()->download($path, $package->filename);
    }
}
