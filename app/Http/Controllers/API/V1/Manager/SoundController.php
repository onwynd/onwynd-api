<?php

namespace App\Http\Controllers\API\V1\Manager;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SoundController extends Controller
{
    public function index(Request $request)
    {
        $files = [];
        $category = $request->query('category', 'general');
        $dir = 'public/sounds/'.$category;

        if (! Storage::exists($dir)) {
            Storage::makeDirectory($dir);
        }

        foreach (Storage::files($dir) as $path) {
            $files[] = [
                'name' => basename($path),
                'size' => Storage::size($path),
                'url' => asset('storage/sounds/'.$category.'/'.basename($path)),
                'path' => $path,
                'category' => $category,
            ];
        }

        // Get all available categories
        $categories = ['general'];
        if (Storage::exists('public/sounds')) {
            $categories = array_merge($categories, array_map('basename', Storage::directories('public/sounds')));
        }

        return response()->json([
            'data' => $files,
            'categories' => array_unique($categories),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:mp3,wav,ogg,m4a,aac,flac|max:20480',
            'category' => 'nullable|string|in:general,notifications,background,music,effects,ui,system,ambient,other',
        ]);

        $file = $request->file('file');
        $category = $request->input('category', 'general');
        $dir = 'public/sounds/'.$category;

        if (! Storage::exists($dir)) {
            Storage::makeDirectory($dir);
        }

        $filename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $ext = $file->getClientOriginalExtension();
        $unique = $filename.'-'.uniqid().'.'.$ext;
        $file->storeAs($dir, $unique);

        return response()->json(['data' => [
            'name' => $unique,
            'size' => Storage::size("$dir/$unique"),
            'url' => asset('storage/sounds/'.$category.'/'.$unique),
            'category' => $category,
        ]], 201);
    }

    public function destroy(string $filename, Request $request)
    {
        $category = $request->query('category', 'general');
        $path = 'public/sounds/'.$category.'/'.$filename;

        if (! Storage::exists($path)) {
            return response()->json(['message' => 'Not found'], 404);
        }

        Storage::delete($path);

        return response()->json(['message' => 'Deleted']);
    }
}
