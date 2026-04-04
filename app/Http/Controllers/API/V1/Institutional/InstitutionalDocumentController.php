<?php

namespace App\Http\Controllers\API\V1\Institutional;

use App\Http\Controllers\API\BaseController;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InstitutionalDocumentController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Document::where('owner_id', Auth::id());

        if ($request->has('search')) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }

        $documents = $query->latest()->paginate(10);

        $data = $documents->getCollection()->transform(function ($doc) {
            return [
                'id' => $doc->id,
                'name' => $doc->name,
                'type' => $doc->file_type,
                'size' => $this->formatSize($doc->file_size),
                'date' => $doc->created_at->format('Y-m-d'),
                'url' => Storage::url($doc->file_path),
            ];
        });

        return $this->sendResponse($documents->setCollection($data), 'Documents retrieved successfully.');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240', // 10MB limit
        ]);

        $file = $request->file('file');
        $userId = Auth::id();
        $path = $file->store("institutional/documents/{$userId}", 'public');

        $document = Document::create([
            'uuid' => Str::uuid(),
            'name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_type' => $file->getClientOriginalExtension(),
            'file_size' => $file->getSize(),
            'owner_id' => Auth::id(),
        ]);

        $responseData = [
            'id' => $document->id,
            'name' => $document->name,
            'type' => $document->file_type,
            'size' => $this->formatSize($document->file_size),
            'date' => $document->created_at->format('Y-m-d'),
            'url' => Storage::url($document->file_path),
        ];

        return $this->sendResponse($responseData, 'Document uploaded successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $document = Document::where('owner_id', Auth::id())->find($id);

        if (! $document) {
            return $this->sendError('Document not found.');
        }

        if (Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        return $this->sendResponse([], 'Document deleted successfully.');
    }

    private function formatSize($bytes)
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2).' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' KB';
        } elseif ($bytes > 1) {
            return $bytes.' bytes';
        } elseif ($bytes == 1) {
            return $bytes.' byte';
        } else {
            return '0 bytes';
        }
    }
}
