<?php

namespace App\Http\Controllers;

use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PackageController extends Controller
{
    public function create()
    {
        return Inertia::render('Package/Create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file'
        ]);

        $file = $request->file('file');
        $token = Str::random(40);
        $filename = $token . '.zip';
        $file->storeAs('zips', $filename);

        $package = Package::create([
            'token' => $token,
            'filename' => $filename,
            'expires_at' => now()->addDays(7),
        ]);

        return Inertia::render('Package/Success', [
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
