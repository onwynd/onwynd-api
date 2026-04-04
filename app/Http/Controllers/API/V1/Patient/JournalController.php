<?php

namespace App\Http\Controllers\API\V1\Patient;

use App\Http\Controllers\API\BaseController;
use App\Models\JournalEntry;
use App\Services\OnwyndScoreService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class JournalController extends BaseController
{
    protected $scoreService;

    public function __construct(OnwyndScoreService $scoreService)
    {
        $this->scoreService = $scoreService;
    }

    public function index(Request $request)
    {
        $query = JournalEntry::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc');

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('mood')) {
            // Simplified mood filter (this would need more logic based on emoji mapping)
            // For now, assuming exact match or partial implementation
            $query->where('mood_emoji', $request->mood);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return $this->sendResponse($query->paginate(20), 'Journal entries retrieved successfully.');
    }

    public function types()
    {
        return $this->sendResponse([
            'text' => 'Text Entry',
            'voice' => 'Voice Note',
        ], 'Journal types retrieved.');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:text,voice,journal,free_form,gratitude,reflection,therapy_notes',
            'title' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'audio_file' => 'nullable|required_if:type,voice|file|mimes:audio/mpeg,mpga,mp3,wav,m4a|max:10240', // 10MB
            'duration_seconds' => 'nullable|integer',
            'mood_emoji' => 'nullable|string',
            'mood' => 'nullable|string',
            'stress_level' => 'nullable|integer|min:1|max:10',
            'emotions' => 'nullable|array',
            'tags' => 'nullable|array',
            'is_private' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $data = $request->except(['audio_file', 'mood']);
        $data['user_id'] = $request->user()->id;

        // Map 'mood' field to 'mood_emoji' if provided and mood_emoji is not already set
        if ($request->filled('mood') && empty($data['mood_emoji'])) {
            $data['mood_emoji'] = $request->input('mood');
        }

        // Handle File Upload
        if ($request->hasFile('audio_file')) {
            $userId = $request->user()->id;
            $path = $request->file('audio_file')->store("journal-audio/{$userId}", 'public');
            $data['audio_url'] = Storage::url($path);

            // In a real app, we would trigger an async job for Speech-to-Text here
            // For now, we'll mark analysis as pending
            $data['ai_analysis'] = ['transcription_status' => 'pending'];
        }

        $journal = JournalEntry::create($data);

        // Update Onwynd Score (Journaling is a positive activity)
        $this->scoreService->updateScore($request->user());

        return $this->sendResponse($journal, 'Journal entry created successfully.');
    }

    public function show($id)
    {
        $journal = JournalEntry::where('user_id', auth()->id())->find($id);

        if (! $journal) {
            return $this->sendError('Journal entry not found.');
        }

        return $this->sendResponse($journal, 'Journal entry retrieved successfully.');
    }

    public function update(Request $request, $id)
    {
        $journal = JournalEntry::where('user_id', auth()->id())->find($id);

        if (! $journal) {
            return $this->sendError('Journal entry not found.');
        }

        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'mood_emoji' => 'nullable|string',
            'stress_level' => 'nullable|integer|min:1|max:10',
            'emotions' => 'nullable|array',
            'tags' => 'nullable|array',
            'is_private' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $journal->update($request->all());

        return $this->sendResponse($journal, 'Journal entry updated successfully.');
    }

    public function destroy($id)
    {
        $journal = JournalEntry::where('user_id', auth()->id())->find($id);

        if (! $journal) {
            return $this->sendError('Journal entry not found.');
        }

        $journal->delete();

        return $this->sendResponse([], 'Journal entry deleted successfully.');
    }

    public function search(Request $request)
    {
        $query = JournalEntry::where('user_id', $request->user()->id);

        // Accept both 'q' (short) and 'query' (full name from frontend)
        $searchTerm = $request->input('q') ?? $request->input('query');
        if (filled($searchTerm)) {
            $term = $searchTerm;
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', "%{$term}%")
                    ->orWhere('content', 'like', "%{$term}%");
            });
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('tags')) {
            $tags = is_array($request->tags) ? $request->tags : explode(',', $request->tags);
            foreach ($tags as $tag) {
                $query->whereJsonContains('tags', trim($tag));
            }
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return $this->sendResponse(
            $query->orderBy('created_at', 'desc')->paginate(20),
            'Search results retrieved.'
        );
    }

    public function tags(Request $request)
    {
        $tags = JournalEntry::where('user_id', $request->user()->id)
            ->whereNotNull('tags')
            ->pluck('tags')
            ->flatten()
            ->filter()
            ->unique()
            ->values();

        return $this->sendResponse($tags, 'Journal tags retrieved.');
    }

    public function export(Request $request)
    {
        $format = $request->input('format', 'json');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');

        $query = JournalEntry::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc');

        if ($fromDate) {
            $query->whereDate('created_at', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        $entries = $query->get();

        switch ($format) {
            case 'pdf':
                return $this->exportAsPdf($entries, $request->user());
            case 'txt':
                return $this->exportAsTxt($entries, $request->user());
            default:
                return $this->exportAsJson($entries);
        }
    }

    private function exportAsJson($entries)
    {
        $data = $entries->map(function ($entry) {
            return [
                'id' => $entry->id,
                'type' => $entry->type,
                'title' => $entry->title,
                'content' => $entry->content,
                'mood_emoji' => $entry->mood_emoji,
                'stress_level' => $entry->stress_level,
                'emotions' => $entry->emotions,
                'tags' => $entry->tags,
                'is_private' => $entry->is_private,
                'created_at' => $entry->created_at,
                'updated_at' => $entry->updated_at,
            ];
        });

        return $this->sendResponse([
            'total' => $entries->count(),
            'exported_at' => now()->toISOString(),
            'entries' => $data,
        ], 'Journal exported successfully.');
    }

    private function exportAsTxt($entries, $user)
    {
        $txt = "JOURNAL ENTRIES EXPORT\n";
        $txt .= "User: {$user->name} ({$user->email})\n";
        $txt .= 'Exported: '.now()->format('Y-m-d H:i:s')."\n";
        $txt .= "Total Entries: {$entries->count()}\n";
        $txt .= str_repeat('=', 50)."\n\n";

        foreach ($entries as $entry) {
            $txt .= "Entry #{$entry->id}\n";
            $txt .= 'Date: '.$entry->created_at->format('Y-m-d H:i:s')."\n";
            $txt .= 'Type: '.ucfirst($entry->type)."\n";
            if ($entry->title) {
                $txt .= "Title: {$entry->title}\n";
            }
            if ($entry->mood_emoji) {
                $txt .= "Mood: {$entry->mood_emoji}\n";
            }
            if ($entry->stress_level) {
                $txt .= "Stress Level: {$entry->stress_level}/10\n";
            }
            if ($entry->emotions && is_array($entry->emotions)) {
                $txt .= 'Emotions: '.implode(', ', $entry->emotions)."\n";
            }
            if ($entry->tags && is_array($entry->tags)) {
                $txt .= 'Tags: '.implode(', ', $entry->tags)."\n";
            }
            if ($entry->content) {
                $txt .= "\nContent:\n".$entry->content."\n";
            }
            $txt .= "\n".str_repeat('-', 30)."\n\n";
        }

        return response($txt, 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => 'attachment; filename="journal-entries-'.now()->format('Y-m-d').'.txt"',
        ]);
    }

    private function exportAsPdf($entries, $user)
    {
        // For now, we'll generate a simple text-based PDF-like format
        // In a production environment, you might want to use a proper PDF library like DomPDF or TCPDF

        $html = "<!DOCTYPE html>\n<html>\n<head>\n";
        $html .= "<meta charset=\"UTF-8\">\n";
        $html .= "<title>Journal Entries Export</title>\n";
        $html .= "<style>\n";
        $html .= "body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; }\n";
        $html .= "h1 { color: #333; border-bottom: 2px solid #333; padding-bottom: 10px; }\n";
        $html .= "h2 { color: #666; margin-top: 30px; }\n";
        $html .= ".entry { margin-bottom: 30px; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }\n";
        $html .= ".entry-header { background-color: #f5f5f5; padding: 10px; margin: -20px -20px 15px -20px; border-radius: 5px 5px 0 0; }\n";
        $html .= ".meta { color: #666; font-size: 0.9em; }\n";
        $html .= ".content { margin-top: 15px; white-space: pre-wrap; }\n";
        $html .= "</style>\n</head>\n<body>\n";

        $html .= "<h1>Journal Entries Export</h1>\n";
        $html .= "<p><strong>User:</strong> {$user->name} ({$user->email})</p>\n";
        $html .= '<p><strong>Exported:</strong> '.now()->format('Y-m-d H:i:s')."</p>\n";
        $html .= "<p><strong>Total Entries:</strong> {$entries->count()}</p>\n";
        $html .= "<hr style=\"margin: 30px 0;\">\n";

        foreach ($entries as $entry) {
            $html .= "<div class=\"entry\">\n";
            $html .= "<div class=\"entry-header\">\n";
            $html .= "<h2>Entry #{$entry->id}</h2>\n";
            $html .= "<div class=\"meta\">\n";
            $html .= '<strong>Date:</strong> '.$entry->created_at->format('Y-m-d H:i:s')."<br>\n";
            $html .= '<strong>Type:</strong> '.ucfirst($entry->type)."<br>\n";
            if ($entry->title) {
                $html .= '<strong>Title:</strong> '.htmlspecialchars($entry->title)."<br>\n";
            }
            if ($entry->mood_emoji) {
                $html .= "<strong>Mood:</strong> {$entry->mood_emoji}<br>\n";
            }
            if ($entry->stress_level) {
                $html .= "<strong>Stress Level:</strong> {$entry->stress_level}/10<br>\n";
            }
            if ($entry->emotions && is_array($entry->emotions)) {
                $html .= '<strong>Emotions:</strong> '.htmlspecialchars(implode(', ', $entry->emotions))."<br>\n";
            }
            if ($entry->tags && is_array($entry->tags)) {
                $html .= '<strong>Tags:</strong> '.htmlspecialchars(implode(', ', $entry->tags))."<br>\n";
            }
            $html .= "</div>\n</div>\n";

            if ($entry->content) {
                $html .= '<div class="content">'.nl2br(htmlspecialchars($entry->content))."</div>\n";
            }
            $html .= "</div>\n";
        }

        $html .= "</body>\n</html>";

        return response($html, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="journal-entries-'.now()->format('Y-m-d').'.pdf"',
        ]);
    }

    public function stats(Request $request)
    {
        $user = $request->user();

        $totalEntries = JournalEntry::where('user_id', $user->id)->count();
        $entriesThisMonth = JournalEntry::where('user_id', $user->id)
            ->whereMonth('created_at', now()->month)
            ->count();

        // Average mood improvement: average of (mood_after - mood_before) for entries that have both
        $avgMoodImprovement = JournalEntry::where('user_id', $user->id)
            ->whereNotNull('mood_before')
            ->whereNotNull('mood_after')
            ->selectRaw('AVG(mood_after - mood_before) as avg_improvement')
            ->value('avg_improvement') ?? 0;

        // Favorite tags: flatten all tag arrays and get the most used ones
        $allTags = JournalEntry::where('user_id', $user->id)
            ->whereNotNull('tags')
            ->pluck('tags')
            ->flatten()
            ->filter()
            ->countBy()
            ->sortDesc()
            ->take(5)
            ->keys()
            ->values()
            ->toArray();

        // Longest entry by word count
        $longestWords = JournalEntry::where('user_id', $user->id)
            ->whereNotNull('content')
            ->get(['content'])
            ->max(fn ($e) => str_word_count($e->content ?? '')) ?? 0;

        return $this->sendResponse([
            'total_entries' => $totalEntries,
            'entries_this_month' => $entriesThisMonth,
            'current_streak' => $user->streak_count ?? 0,
            'favorite_tags' => $allTags,
            'average_mood_improvement' => round((float) $avgMoodImprovement, 1),
            'longest_entry_words' => (int) $longestWords,
        ], 'Journal statistics retrieved.');
    }
}
