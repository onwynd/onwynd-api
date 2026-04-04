<?php

namespace App\Http\Controllers\API\V1\Document;

use App\Http\Controllers\Controller;
use App\Models\SecureDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SecureDocumentController extends Controller
{
    /**
     * Display a listing of shared documents.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $documents = SecureDocument::where('owner_id', $user->id)
            ->orWhere('shared_with_id', $user->id)
            ->latest()
            ->paginate(20);

        return response()->json(['status' => 'success', 'data' => $documents]);
    }

    /**
     * Store a newly created document in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'file' => 'required|file|max:10240', // 10MB
            'shared_with_id' => 'nullable|exists:users,id',
            'is_encrypted' => 'boolean',
        ]);

        $file = $request->file('file');
        $userId = Auth::id();
        $path = $file->store("secure-documents/{$userId}", 'private'); // segregated per user

        $document = SecureDocument::create([
            'uuid' => (string) Str::uuid(),
            'owner_id' => Auth::id(),
            'shared_with_id' => $validated['shared_with_id'] ?? null,
            'title' => $validated['title'],
            'file_path' => $path,
            'file_type' => $file->getClientOriginalExtension(),
            'file_size' => $file->getSize(),
            'is_encrypted' => $validated['is_encrypted'] ?? true,
            'metadata' => ['original_name' => $file->getClientOriginalName()],
        ]);

        return response()->json(['status' => 'success', 'data' => $document], 201);
    }

    /**
     * Display the specified document.
     */
    public function show($id)
    {
        $document = SecureDocument::findOrFail($id);
        $user = Auth::user();

        if ($user->id !== $document->owner_id && $user->id !== $document->shared_with_id) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        // Generate temporary URL if using S3, or return file
        // For now, return metadata
        return response()->json(['status' => 'success', 'data' => $document]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $document = SecureDocument::findOrFail($id);
        if (Auth::user()->id !== $document->owner_id) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        Storage::disk('private')->delete($document->file_path);
        $document->delete();

        return response()->json(['status' => 'success', 'message' => 'Document deleted']);
    }
}
