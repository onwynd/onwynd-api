<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAIChat;
use App\Services\AI\AdminAIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminAIChatController extends Controller
{
    protected AdminAIService $aiService;

    public function __construct(AdminAIService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Send a message to the AI and get a response
     * Supports file uploads for document analysis
     */
    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:4000',
            'conversation_id' => 'nullable|string|uuid',
            'file' => 'nullable|file|max:10240', // 10MB max
            'file_type' => 'nullable|string|in:document,image,audio',
            'context' => 'nullable|string|max:8000',
        ]);

        $admin = Auth::user();
        $message = $request->input('message');
        $conversationId = $request->input('conversation_id');
        $file = $request->file('file');
        $fileType = $request->input('file_type', 'document');
        $context = $request->input('context');

        try {
            // Handle file upload if present
            $fileContent = null;
            $fileMetadata = null;

            if ($file) {
                $fileContent = $this->processUploadedFile($file, $fileType);
                $fileMetadata = [
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'type' => $file->getMimeType(),
                    'file_type' => $fileType,
                ];
            }

            $result = $this->aiService->chat($admin, $message, $conversationId, $fileContent, $fileMetadata, $context);

            return response()->json([
                'success' => true,
                'data' => [
                    'reply' => $result['reply'],
                    'conversation_id' => $result['conversation_id'],
                    'formatted_reply' => $this->formatAIResponse($result['reply']),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process AI chat request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all conversation threads for the admin
     */
    public function getConversations(): JsonResponse
    {
        $admin = Auth::user();

        try {
            $conversations = $this->aiService->getConversations($admin);

            return response()->json([
                'success' => true,
                'data' => $conversations,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve conversations',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all messages in a specific conversation
     */
    public function getConversation(Request $request, string $conversationId): JsonResponse
    {
        validator(
            ['conversation_id' => $conversationId],
            ['conversation_id' => 'required|string|uuid']
        )->validate();

        $admin = Auth::user();

        try {
            $messages = $this->aiService->getConversation($admin, $conversationId);

            return response()->json([
                'success' => true,
                'data' => $messages,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve conversation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a conversation and all its messages
     */
    public function deleteConversation(string $conversationId): JsonResponse
    {
        $admin = Auth::user();

        try {
            // Verify the conversation belongs to the admin
            $conversationExists = AdminAIChat::where('user_id', $admin->id)
                ->where('conversation_id', $conversationId)
                ->exists();

            if (! $conversationExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversation not found or access denied',
                ], 404);
            }

            // Delete all messages in the conversation
            AdminAIChat::where('user_id', $admin->id)
                ->where('conversation_id', $conversationId)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Conversation deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete conversation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear all conversations for the admin
     */
    public function clearAllConversations(): JsonResponse
    {
        $admin = Auth::user();

        try {
            AdminAIChat::where('user_id', $admin->id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'All conversations cleared successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear conversations',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process uploaded files and extract content for AI analysis
     */
    private function processUploadedFile($file, string $fileType): array
    {
        $content = [];

        switch ($fileType) {
            case 'document':
                $content = $this->extractDocumentContent($file);
                break;
            case 'image':
                $content = $this->processImageFile($file);
                break;
            case 'audio':
                $content = $this->processAudioFile($file);
                break;
        }

        return $content;
    }

    /**
     * Extract text content from document files
     */
    private function extractDocumentContent($file): array
    {
        $path = $file->store('temp-documents', 'local');
        $fullPath = storage_path('app/'.$path);

        $mimeType = $file->getMimeType();
        $content = '';

        try {
            if (str_contains($mimeType, 'pdf')) {
                // For PDF files, we'll extract text
                $content = $this->extractPdfText($fullPath);
            } elseif (str_contains($mimeType, 'text') || str_contains($mimeType, 'csv')) {
                // For text files
                $content = file_get_contents($fullPath);
            } elseif (str_contains($mimeType, 'word') || str_contains($mimeType, 'document')) {
                // For Word documents, extract what we can
                $content = $this->extractWordText($fullPath);
            } else {
                $content = 'Document uploaded: '.$file->getClientOriginalName().' ('.$file->getSize().' bytes)';
            }
        } catch (\Exception $e) {
            $content = 'Document uploaded but content extraction failed: '.$e->getMessage();
        }

        // Clean up temp file
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }

        return [
            'type' => 'document',
            'content' => $content,
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $mimeType,
        ];
    }

    /**
     * Process image files (OCR if needed)
     */
    private function processImageFile($file): array
    {
        $path = $file->store('temp-images', 'local');
        $fullPath = storage_path('app/'.$path);

        $content = 'Image uploaded: '.$file->getClientOriginalName().' ('.$file->getSize().' bytes)';

        // In a production environment, you might want to integrate with OCR services
        // For now, we'll just acknowledge the image upload

        // Clean up temp file
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }

        return [
            'type' => 'image',
            'content' => $content,
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
        ];
    }

    /**
     * Process audio files (transcription if needed)
     */
    private function processAudioFile($file): array
    {
        $path = $file->store('temp-audio', 'local');
        $fullPath = storage_path('app/'.$path);

        $content = 'Audio file uploaded: '.$file->getClientOriginalName().' ('.$file->getSize().' bytes)';

        // In a production environment, you might want to integrate with transcription services
        // For now, we'll just acknowledge the audio upload

        // Clean up temp file after a delay to allow processing
        // In production, you'd want to process this asynchronously

        return [
            'type' => 'audio',
            'content' => $content,
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
        ];
    }

    /**
     * Extract text from PDF files
     */
    private function extractPdfText(string $path): string
    {
        // Simple PDF text extraction using shell command
        // This requires pdftotext to be installed on the server
        $output = '';
        if (function_exists('shell_exec')) {
            try {
                $escapedPath = escapeshellarg($path);
                $output = shell_exec("pdftotext {$escapedPath} - 2>/dev/null");
                if ($output === null) {
                    $output = 'PDF content extraction requires pdftotext utility';
                }
            } catch (\Exception $e) {
                $output = 'PDF extraction failed: '.$e->getMessage();
            }
        } else {
            $output = 'PDF text extraction not available (shell_exec disabled)';
        }

        return $output ?: 'PDF content could not be extracted';
    }

    /**
     * Extract text from Word documents
     */
    private function extractWordText(string $path): string
    {
        // For Word documents, we'll use a simple approach
        // In production, you'd want to use a proper library like PhpOffice/PhpWord

        $content = file_get_contents($path);

        // Simple text extraction - this won't preserve formatting but will get the text
        $text = strip_tags($content);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);

        if (empty($text)) {
            return 'Word document content could not be extracted. Document may be encrypted or in an unsupported format.';
        }

        return substr($text, 0, 10000).(strlen($text) > 10000 ? '... (truncated)' : '');
    }

    /**
     * Format AI response for better display
     */
    private function formatAIResponse(string $response): array
    {
        // Convert markdown to HTML for better rendering
        $html = $this->markdownToHtml($response);

        // Extract key sections
        $sections = $this->extractSections($response);

        return [
            'html' => $html,
            'markdown' => $response,
            'sections' => $sections,
            'has_bold' => strpos($response, '**') !== false,
            'has_headers' => preg_match('/^##\s+/m', $response) === 1,
            'has_lists' => preg_match('/^[-*+]\s+/m', $response) === 1,
        ];
    }

    /**
     * Convert markdown to HTML
     */
    private function markdownToHtml(string $markdown): string
    {
        // Simple markdown to HTML conversion
        $html = $markdown;

        // Convert headers
        $html = preg_replace('/^##\s+(.+)$/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^###\s+(.+)$/m', '<h4>$1</h4>', $html);

        // Convert bold text
        $html = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $html);

        // Convert italic text
        $html = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $html);

        // Convert bullet lists
        $html = preg_replace('/^[-*+]\s+(.+)$/m', '<li>$1</li>', $html);
        $html = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $html);

        // Convert numbered lists
        $html = preg_replace('/^\d+\.\s+(.+)$/m', '<li>$1</li>', $html);

        // Convert line breaks
        $html = nl2br($html);

        return $html;
    }

    /**
     * Extract key sections from AI response
     */
    private function extractSections(string $response): array
    {
        $sections = [];

        // Extract sections with headers
        if (preg_match_all('/^##\s+(.+)$\s*([^#]+)/m', $response, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $sections[] = [
                    'title' => trim($match[1]),
                    'content' => trim($match[2]),
                ];
            }
        }

        // Extract next steps
        if (preg_match('/\*\*Next Steps:\*\*(.+?)(?=\*\*|$)/s', $response, $match)) {
            $sections['next_steps'] = trim($match[1]);
        }

        // Extract key metrics
        if (preg_match_all('/\*\*([^*]+)\*\*:\s*([^\n]+)/', $response, $matches, PREG_SET_ORDER)) {
            $sections['metrics'] = [];
            foreach ($matches as $match) {
                $sections['metrics'][] = [
                    'label' => trim($match[1]),
                    'value' => trim($match[2]),
                ];
            }
        }

        return $sections;
    }
}


