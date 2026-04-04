<?php

namespace App\Http\Controllers\API\V1\Patient;

use App\Http\Controllers\API\BaseController;
use App\Services\Admin\AdminNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class HelpController extends BaseController
{
    /**
     * Get Help Center FAQs.
     */
    public function faqs(Request $request)
    {
        $category = $request->input('category', 'all');

        $faqs = [
            [
                'id' => 1,
                'category' => 'account',
                'question' => 'How do I reset my password?',
                'answer' => 'Go to settings -> security -> change password.',
            ],
            [
                'id' => 2,
                'category' => 'features',
                'question' => 'How does the AI chat work?',
                'answer' => 'The AI chat uses advanced natural language processing...',
            ],
        ];

        $categories = ['account', 'features', 'billing', 'technical'];

        return $this->sendResponse(['faqs' => $faqs, 'categories' => $categories], 'FAQs retrieved.');
    }

    /**
     * Search Help Articles.
     */
    public function search(Request $request)
    {
        $query = $request->input('q');

        // Mock search results
        $results = [
            ['id' => 1, 'title' => 'Deleting your account', 'snippet' => 'To delete your account...'],
            ['id' => 5, 'title' => 'Exporting data', 'snippet' => 'You can export your data...'],
        ];

        return $this->sendResponse($results, 'Help articles retrieved.');
    }

    /**
     * Contact Support.
     */
    public function contact(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'subject' => 'required|string',
            'message' => 'required|string',
            'category' => 'required|string',
            'attachments' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        return $this->sendResponse(['ticket_id' => 'TICKET-'.rand(1000, 9999)], 'Support ticket created.');
    }

    /**
     * Send Feedback.
     */
    public function feedback(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|string|in:bug,feature,general', // bug, feature, general
            'message' => 'required|string',
            'rating' => 'nullable|integer|min:1|max:5',
            'name' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        // Store feedback in database
        $feedback = \App\Models\Feedback::create([
            'user_id' => Auth::check() ? Auth::id() : null,
            'type' => $request->input('type'),
            'message' => $request->input('message'),
            'rating' => $request->input('rating'),
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'status' => 'pending',
        ]);

        AdminNotificationService::newFeedback(
            $feedback->type,
            $feedback->rating ?? 0,
            substr($feedback->message, 0, 120)
        );

        return $this->sendResponse([
            'feedback_id' => $feedback->id,
            'message' => 'Thank you for your feedback! We\'ve received your message and will review it soon.',
        ], 'Feedback submitted successfully.');
    }

    /**
     * Report Bug.
     */
    public function reportBug(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string',
            'description' => 'required|string',
            'steps_to_reproduce' => 'required|string',
            'device_info' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        return $this->sendResponse(['bug_id' => 'BUG-'.rand(1000, 9999)], 'Bug reported successfully.');
    }

    /**
     * Get App Version Info.
     */
    public function version()
    {
        return $this->sendResponse([
            'current_version' => '1.0.0',
            'minimum_required_version' => '1.0.0',
            'latest_version' => '1.0.1',
            'update_required' => false,
            'update_url' => 'https://play.google.com/store/apps/details?id=com.onwynd.app',
            'release_notes' => 'Bug fixes and performance improvements.',
        ], 'App version info retrieved.');
    }
}
