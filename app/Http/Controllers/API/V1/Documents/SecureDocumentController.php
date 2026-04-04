<?php

namespace App\Http\Controllers\API\V1\Documents;

use App\Http\Controllers\Controller;
use App\Models\SecureDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class SecureDocumentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index()
    {
        $user = Auth::user();
        $documents = SecureDocument::where('owner_id', $user->id)
            ->orWhere('shared_with_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $documents]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:10240', // 10MB max
            'title' => 'required|string|max:255',
            'document_type' => 'nullable|string',
            'shared_with_id' => 'nullable|exists:users,id',
            'therapy_session_id' => 'nullable|exists:therapy_sessions,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $file = $request->file('file');

        if ($request->therapy_session_id) {
            $path = $file->store('documents/sessions/'.$request->therapy_session_id, 'local');
        } else {
            $path = $file->store('documents/users/'.$user->id, 'local');
        }

        $document = SecureDocument::create([
            'owner_id' => $user->id,
            'shared_with_id' => $request->shared_with_id,
            'title' => $request->title,
            'file_path' => $path,
            'file_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'is_encrypted' => true,
            'metadata' => [
                'document_type' => $request->document_type ?? 'other',
                'original_name' => $file->getClientOriginalName(),
                'therapy_session_id' => $request->therapy_session_id,
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Document uploaded securely.',
            'data' => $document,
        ], 201);
    }

    public function show($uuid)
    {
        $document = SecureDocument::where('uuid', $uuid)->firstOrFail();

        if ($document->owner_id !== Auth::id()) {
            if ($document->shared_with_id !== Auth::id()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }
        }

        return Storage::disk(config('filesystems.default'))->download($document->file_path, $document->title);
    }

    public function destroy($uuid)
    {
        $document = SecureDocument::where('uuid', $uuid)->where('owner_id', Auth::id())->firstOrFail();

        Storage::disk(config('filesystems.default'))->delete($document->file_path);
        $document->delete();

        return response()->json(['success' => true, 'message' => 'Document deleted']);
    }
}
