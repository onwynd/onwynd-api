<?php

namespace App\Http\Controllers\API\V1\AI;

use App\Http\Controllers\API\BaseController;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use ZipArchive;

class DocumentAnalysisController extends BaseController
{
    /**
     * Accept a document or image upload and return extracted text content.
     *
     * Supported types:
     *  - Images (JPG, PNG, WEBP, GIF): vision API describes the content
     *  - TXT / CSV / MD: raw text read directly
     *  - DOCX: XML extracted via ZipArchive (no extra package needed)
     *  - PDF: basic BT/ET text extraction; falls back to a note if extraction fails
     */
    public function extract(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:20480', // 20 MB max
        ]);

        $file = $request->file('file');
        $mime = $file->getMimeType() ?? 'application/octet-stream';
        $name = $file->getClientOriginalName();
        $ext = strtolower($file->getClientOriginalExtension());

        // ── Images: use vision API ──────────────────────────────────────────
        $imageTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'];
        if (in_array($mime, $imageTypes, true) || in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            $base64 = base64_encode(file_get_contents($file->getRealPath()));
            $dataUrl = "data:{$mime};base64,{$base64}";
            $extracted = $this->describeImageWithVision($dataUrl, $name);

            return $this->sendResponse([
                'file_name' => $name,
                'file_type' => 'image',
                'extracted_text' => $extracted,
            ], 'Image described');
        }

        // ── Plain text / CSV / Markdown ─────────────────────────────────────
        if (in_array($ext, ['txt', 'csv', 'md', 'log'], true) || str_starts_with($mime, 'text/')) {
            $raw = file_get_contents($file->getRealPath());
            $text = mb_convert_encoding($raw !== false ? $raw : '', 'UTF-8', 'auto');
            $text = mb_substr($text, 0, 15000); // cap at 15 k chars

            return $this->sendResponse([
                'file_name' => $name,
                'file_type' => 'text',
                'extracted_text' => $text,
            ], 'Text extracted');
        }

        // ── DOCX ────────────────────────────────────────────────────────────
        if (
            in_array($ext, ['docx', 'doc'], true) ||
            $mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ||
            $mime === 'application/msword'
        ) {
            $text = $this->extractDocx($file->getRealPath());

            return $this->sendResponse([
                'file_name' => $name,
                'file_type' => 'document',
                'extracted_text' => $text ?: '[Could not extract text from this Word document.]',
            ], 'Document text extracted');
        }

        // ── PDF ─────────────────────────────────────────────────────────────
        if ($ext === 'pdf' || $mime === 'application/pdf') {
            $text = $this->extractPdf($file->getRealPath());

            if (! $text || strlen(trim($text)) < 20) {
                $extracted = '[This PDF appears to be image-based or its text could not be extracted automatically. Please describe the key points you\'d like help with from this document.]';
            } else {
                $extracted = mb_substr($text, 0, 15000);
            }

            return $this->sendResponse([
                'file_name' => $name,
                'file_type' => 'pdf',
                'extracted_text' => $extracted,
            ], 'PDF text extracted');
        }

        // ── Unsupported ─────────────────────────────────────────────────────
        return $this->sendError('Unsupported file type', ['mime' => $mime, 'ext' => $ext], 422);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ────────────────────────────────────────────────────────────────────────

    /**
     * Extract plain text from a DOCX file using ZipArchive.
     * No extra packages required — PHP ships with ext-zip.
     */
    private function extractDocx(string $path): string
    {
        if (! class_exists(ZipArchive::class)) {
            return '';
        }

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return '';
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            return '';
        }

        // Paragraph breaks before stripping tags
        $xml = str_replace(['</w:p>', '</w:tr>'], ["\n", "\n"], $xml);
        $text = strip_tags($xml);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return mb_substr(trim($text), 0, 15000);
    }

    /**
     * Basic PDF text extraction by scanning BT…ET blocks.
     * Works for most machine-generated PDFs.
     * Scanned / image-based PDFs will produce little or no text.
     */
    private function extractPdf(string $path): string
    {
        $content = file_get_contents($path);
        if ($content === false) {
            return '';
        }

        $text = '';

        // Extract text streams in BT…ET blocks
        preg_match_all('/BT(.+?)ET/s', $content, $matches);
        foreach ($matches[1] as $block) {
            // Regular string literals: (text) Tj / TJ
            preg_match_all('/\(([^)\\\\]*(?:\\\\.[^)\\\\]*)*)\)\s*T[jJ]/s', $block, $strings);
            foreach ($strings[1] as $str) {
                // Decode basic PDF escape sequences
                $str = str_replace(['\\n', '\\r', '\\t', '\\(', '\\)', '\\\\'], ["\n", "\r", "\t", '(', ')', '\\'], $str);
                $text .= $str.' ';
            }

            // Hex strings: <hex> Tj / TJ
            preg_match_all('/<([0-9A-Fa-f]+)>\s*T[jJ]/', $block, $hexStrings);
            foreach ($hexStrings[1] as $hex) {
                if (strlen($hex) % 2 === 0) {
                    $decoded = @hex2bin($hex);
                    if ($decoded !== false) {
                        $text .= $decoded.' ';
                    }
                }
            }
        }

        // Remove non-printable characters and collapse whitespace
        $text = preg_replace('/[^\x09\x0A\x0D\x20-\x7E\xA0-\xFF]/', ' ', $text) ?? $text;
        $text = preg_replace('/\s{3,}/', ' ', $text) ?? $text;

        return mb_substr(trim($text), 0, 15000);
    }

    /**
     * Send an image to the OpenAI / Groq vision model and get a detailed description.
     */
    private function describeImageWithVision(string $dataUrl, string $fileName): string
    {
        $driver = config('services.ai.default', 'openai');

        if ($driver === 'openai') {
            $apiKey = config('services.openai.api_key');
            $model = 'gpt-4o'; // Vision requires GPT-4o or GPT-4-vision
            $base = 'https://api.openai.com/v1';
        } elseif ($driver === 'groq') {
            // Groq supports meta-llama/llama-4-scout-17b-16e-instruct which has vision
            $apiKey = config('services.groq.api_key');
            $model = config('services.groq.vision_model', 'meta-llama/llama-4-scout-17b-16e-instruct');
            $base = 'https://api.groq.com/openai/v1';
        } else {
            return "[Image: {$fileName} — Vision analysis is not available with the current AI driver. Please describe the content you need help with.]";
        }

        try {
            $client = new Client([
                'headers' => [
                    'Authorization' => 'Bearer '.$apiKey,
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 60,
            ]);

            $response = $client->post($base.'/chat/completions', [
                'json' => [
                    'model' => $model,
                    'max_tokens' => 2000,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => 'Please provide a thorough, detailed analysis of this document or image. Extract ALL readable text exactly as written. Describe any charts, graphs, numbers, dates, names, and key information. If this is a medical report, school report card, tenancy agreement, or any official document, describe all sections and their content in detail. Be comprehensive so someone can understand the full content without seeing the image.',
                                ],
                                [
                                    'type' => 'image_url',
                                    'image_url' => ['url' => $dataUrl, 'detail' => 'high'],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return $data['choices'][0]['message']['content']
                ?? "[Could not generate description for: {$fileName}]";
        } catch (\Throwable $e) {
            return "[Image: {$fileName} — Vision processing failed. Please describe the content you need help with.]";
        }
    }
}
