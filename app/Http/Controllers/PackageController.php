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

    public function clear()
    {
        Package::query()->delete();

        Storage::deleteDirectory('chunks');
        Storage::deleteDirectory('zips');
    }

    public function index()
    {
        return Package::all();
    }

    public function initUpload(Request $request)
    {
        // Vygenerujeme token pro celý upload.
        $token = Str::random(40);
        Storage::deleteDirectory("chunks");
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

        ini_set('memory_limit', '1G'); // #temp
        // Validace požadavku
        $request->validate([
            'token' => 'required|string',
            'files' => 'required|array'
        ]);
    
        $token = $request->input('token');
        $files = $request->input('files');
        $tempPath = Storage::path("chunks/$token");
        $finalZipName = $token . '.zip';
        $finalZipPath = Storage::path("zips/$finalZipName");
    
        // Zajištění existence a oprávnění složky pro ZIP
        $zipDir = dirname($finalZipPath);
        if (!file_exists($zipDir)) {
            if (!mkdir($zipDir, 0755, true)) {
                return response()->json(['error' => 'Failed to create ZIP directory'], 500);
            }
        }
    
        // Otevření ZIP archivu
        $zip = new \ZipArchive();
        if ($zip->open($finalZipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== TRUE) {
            return response()->json(['error' => 'Cannot create ZIP file'], 500);
        }
    
        // Pole pro sledování sestavených souborů k odstranění po zavření ZIP
        $assembledFiles = [];
    
        foreach ($files as $f) {
            $type = $f['type'];
            $relativePath = ltrim($f['relativePath'], '/'); // Odstranění počáteční '/'
    
            if ($type === 'directory') {
                // Přidání prázdné složky do ZIP
                if (!$zip->addEmptyDir($relativePath)) {
                    $zip->close();
                    return response()->json(['error' => "Failed to add directory: $relativePath to ZIP"], 500);
                }
            } elseif ($type === 'file') {
                $totalChunks = $f['total_chunks'];
                $assembledPath = Storage::path("chunks/{$token}/assembled_$relativePath");
    
                // Zajištění existence adresáře pro sestavený soubor
                $assembledDir = dirname($assembledPath);
                if (!file_exists($assembledDir)) {
                    if (!mkdir($assembledDir, 0755, true)) {
                        $zip->close();
                        return response()->json(['error' => "Failed to create directory for assembled file: $relativePath"], 500);
                    }
                }
    
                // Otevření dočasného souboru pro sestavení
                $assembledFile = fopen($assembledPath, 'wb');
                if (!$assembledFile) {
                    $zip->close();
                    return response()->json(['error' => "Failed to create assembled file: $relativePath"], 500);
                }
    
                // Postupné přidávání chunks do sestaveného souboru
                for ($i = 0; $i < $totalChunks; $i++) {
                    $chunkPath = "$tempPath/$relativePath/chunk_$i";
                    if (!file_exists($chunkPath)) {
                        fclose($assembledFile);
                        $zip->close();
                        return response()->json(['error' => "Missing chunk: $relativePath chunk $i"], 500);
                    }
    
                    // Čtení chunku
                    $chunkContent = file_get_contents($chunkPath);
                    if ($chunkContent === false) {
                        fclose($assembledFile);
                        $zip->close();
                        return response()->json(['error' => "Failed to read chunk: $relativePath chunk $i"], 500);
                    }
    
                    // Zápis chunku do sestaveného souboru
                    $bytesWritten = fwrite($assembledFile, $chunkContent);
                    if ($bytesWritten === false) {
                        fclose($assembledFile);
                        $zip->close();
                        return response()->json(['error' => "Failed to write chunk: $relativePath chunk $i"], 500);
                    }
                }
    
                fclose($assembledFile);
    
                // Přidání sestaveného souboru do ZIPu
                if (!$zip->addFile($assembledPath, $relativePath)) {
                    $zip->close();
                    return response()->json(['error' => "Failed to add file: $relativePath to ZIP"], 500);
                }
    
                // Přidání cesty sestaveného souboru do pole pro pozdější odstranění
                $assembledFiles[] = $assembledPath;
            } else {
                // Neznámý typ souboru, můžete se rozhodnout vrátit chybu nebo pokračovat
                return response()->json(['error' => "Unknown file type: $type for relativePath: $relativePath"], 400);
            }
        }
    
        // Pokus o zavření ZIP archivu
        if (!$zip->close()) {
            return response()->json(['error' => 'Failed to close ZIP archive'], 500);
        }
    
        // Odstranění sestavených souborů až po úspěšném zavření ZIP archivu
        foreach ($assembledFiles as $file) {
            unlink($file);
        }
    
        // Odstranění dočasných chunks
        Storage::deleteDirectory("chunks/$token");
    
        // Vytvoření záznamu o balíčku
        Package::create([
            'token' => $token,
            'filename' => $finalZipName,
            'expires_at' => now()->addDays(7),
        ]);

        \Log::info('Upload Peak Memory ' . memory_get_peak_usage());
    
        // Návrat odpovědi s odkazem
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
