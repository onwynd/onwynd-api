<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SoundController extends Controller
{
    public function index(Request $request)
    {
        $baseDir = 'public/sounds';
        $directories = Storage::exists($baseDir)
            ? array_map('basename', Storage::directories($baseDir))
            : [];
        $rootFiles = Storage::exists($baseDir) ? Storage::files($baseDir) : [];

        $categories = $directories;
        if (! empty($rootFiles) && ! in_array('general', $categories, true)) {
            $categories[] = 'general';
        }
        if (empty($categories)) {
            $categories = ['general'];
        }
        sort($categories);

        // Pick requested category; fall back to first available one
        $category = $request->query('category');
        if (! $category || ! in_array($category, $categories, true)) {
            $category = $categories[0];
        }

        $dir = $category === 'general' ? $baseDir : $baseDir.'/'.$category;
        if (! Storage::exists($dir)) {
            Storage::makeDirectory($dir);
        }

        $files = [];
        foreach (Storage::files($dir) as $path) {
            $resolvedCategory = basename(dirname($path)) === 'sounds'
                ? 'general'
                : basename(dirname($path));
            $files[] = [
                'name'      => basename($path),
                'size'      => Storage::size($path),
                'url'       => $resolvedCategory === 'general'
                    ? asset('storage/sounds/'.basename($path))
                    : asset('storage/sounds/'.$resolvedCategory.'/'.basename($path)),
                'path'      => $path,
                'timestamp' => Storage::lastModified($path),
                'category'  => $resolvedCategory,
            ];
        }

        return response()->json([
            'data'       => $files,
            'categories' => $categories,
            'category'   => $category,
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
        $dir = $category === 'general' ? 'public/sounds' : 'public/sounds/'.$category;

        if (! Storage::exists($dir)) {
            Storage::makeDirectory($dir);
        }

        $filename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $ext = $file->getClientOriginalExtension();
        $unique = $filename.'-'.uniqid().'.'.$ext;
        $file->storeAs($dir, $unique);

        return response()->json([
            'data' => [
                'name' => $unique,
                'size' => Storage::size("$dir/$unique"),
                'url' => $category === 'general'
                    ? asset('storage/sounds/'.$unique)
                    : asset('storage/sounds/'.$category.'/'.$unique),
                'category' => $category,
            ],
            'message' => 'Uploaded',
        ], 201);
    }

    public function destroy(string $filename, Request $request)
    {
        $category = $request->query('category', 'general');
        $path = $category === 'general'
            ? 'public/sounds/'.$filename
            : 'public/sounds/'.$category.'/'.$filename;

        if (! Storage::exists($path)) {
            return response()->json(['message' => 'Not found'], 404);
        }

        Storage::delete($path);

        return response()->json(['message' => 'Deleted']);
    }
}
