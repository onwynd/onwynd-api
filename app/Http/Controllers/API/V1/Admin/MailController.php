<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Mail\MailProviderException;
use App\Services\Mail\MailProviderFactory;
use App\Services\Mail\MailProviderInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MailController extends Controller
{
    private MailProviderInterface $provider;

    public function __construct()
    {
        // Never reference a specific provider here
        // Always use the factory
        $this->provider = MailProviderFactory::make();
    }

    /**
     * GET /api/v1/admin/mail/inbox?folder=INBOX&page=1
     */
    public function inbox(Request $request): JsonResponse
    {
        try {
            $folder = $request->get('folder', 'INBOX');
            $page = (int) $request->get('page', 1);
            $perPage = (int) $request->get('per_page', 20);

            $result = $this->provider->listMessages($folder, $page, $perPage);

            return response()->json($result);
        } catch (MailProviderException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'provider' => $e->provider,
                'suggestion' => $e->suggestion,
            ], 503);
        } catch (\Exception $e) {
            Log::error('Mail inbox error: '.$e->getMessage());

            return response()->json(['error' => 'Failed to fetch messages.'], 500);
        }
    }

    /**
     * GET /api/v1/admin/mail/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $message = $this->provider->getMessage($id);
            // Auto-mark as read when shown
            $this->provider->markRead($id, true);

            return response()->json($message);
        } catch (MailProviderException $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch message details.'], 500);
        }
    }

    /**
     * POST /api/v1/admin/mail/send
     */
    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'to' => 'required|email',
            'subject' => 'required|string',
            'body' => 'required|string',
            'reply_to_id' => 'nullable|string',
        ]);

        try {
            $result = $this->provider->sendMessage($data);

            return response()->json($result);
        } catch (MailProviderException $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to send message.'], 500);
        }
    }

    /**
     * PATCH /api/v1/admin/mail/{id}/read
     */
    public function markRead(string $id, Request $request): JsonResponse
    {
        $request->validate(['read' => 'required|boolean']);

        try {
            $success = $this->provider->markRead($id, $request->read);

            return response()->json(['success' => $success]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update message status.'], 500);
        }
    }

    /**
     * DELETE /api/v1/admin/mail/{id}
     */
    public function trash(string $id): JsonResponse
    {
        try {
            $success = $this->provider->trashMessage($id);

            return response()->json(['success' => $success]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to move message to trash.'], 500);
        }
    }

    /**
     * GET /api/v1/admin/mail/unread-count
     */
    public function unreadCount(): JsonResponse
    {
        try {
            $count = $this->provider->getUnreadCount();

            return response()->json(['unread_count' => $count]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch unread count.'], 500);
        }
    }

    /**
     * GET /api/v1/admin/mail/folders
     */
    public function folders(): JsonResponse
    {
        try {
            $folders = $this->provider->listFolders();

            return response()->json($folders);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch folders.'], 500);
        }
    }

    /**
     * POST /api/v1/admin/mail/test-connection
     */
    public function testConnection(): JsonResponse
    {
        try {
            $result = $this->provider->testConnection();

            return response()->json(array_merge($result, [
                'provider' => Setting::getValue('mail_provider', 'zoho_imap'),
            ]));
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
