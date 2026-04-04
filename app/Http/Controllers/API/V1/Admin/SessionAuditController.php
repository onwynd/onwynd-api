<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\API\BaseController;
use App\Models\SessionAuditLog;
use App\Models\TherapySession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Handles AI session audit lifecycle.
 *
 * Internal webhook (called by audit_agent.py — verified by AUDIT_AGENT_SECRET):
 *   POST /api/v1/internal/session-audit/segment   — stream transcript segment
 *   POST /api/v1/internal/session-audit/complete  — session ended, full analysis
 *
 * Admin API (clinical/admin staff):
 *   GET  /api/v1/admin/sessions/{uuid}/audit      — get audit for a session
 *   POST /api/v1/admin/sessions/{uuid}/audit/review — mark reviewed + notes
 *   GET  /api/v1/admin/session-audits             — list flagged sessions
 */
class SessionAuditController extends BaseController
{
    // ── Internal webhook endpoints (called by Python agent) ──────────────────

    /**
     * Receive a real-time transcript segment from the audit agent.
     * Agent POSTs every ~5s while session is live.
     */
    public function segment(Request $request)
    {
        if (! $this->verifyAgentSecret($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'session_uuid' => 'required|string',
            'room_name'    => 'required|string',
            'speaker'      => 'required|in:therapist,patient,unknown',
            'text'         => 'required|string',
            'start_ms'     => 'required|integer',
            'end_ms'       => 'required|integer',
        ]);

        $log = SessionAuditLog::firstOrCreate(
            ['session_uuid' => $request->session_uuid],
            [
                'room_name'     => $request->room_name,
                'audit_status'  => 'processing',
                'agent_version' => $request->header('X-Agent-Version', 'unknown'),
            ]
        );

        // Append segment to JSON array
        $segments = $log->segments ?? [];
        $segments[] = [
            'speaker'  => $request->speaker,
            'text'     => $request->text,
            'start_ms' => $request->start_ms,
            'end_ms'   => $request->end_ms,
        ];
        $log->segments = $segments;

        // Rebuild plain transcript
        $log->transcript = collect($segments)
            ->map(fn($s) => '['.strtoupper($s['speaker']).'] '.$s['text'])
            ->implode("\n");

        $log->save();

        return response()->json(['ok' => true]);
    }

    /**
     * Receive completed session audit from the agent.
     * Called once when the LiveKit room closes.
     */
    public function complete(Request $request)
    {
        if (! $this->verifyAgentSecret($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'session_uuid'     => 'required|string',
            'room_name'        => 'required|string',
            'transcript'       => 'nullable|string',
            'segments'         => 'nullable|array',
            'duration_seconds' => 'nullable|integer',
            'error'            => 'nullable|string',
        ]);

        $log = SessionAuditLog::firstOrCreate(
            ['session_uuid' => $request->session_uuid],
            ['room_name' => $request->room_name]
        );

        if ($request->error) {
            $log->update([
                'audit_status'     => 'error',
                'error_message'    => $request->error,
                'duration_seconds' => $request->duration_seconds,
            ]);
            return response()->json(['ok' => true]);
        }

        $transcript = $request->transcript ?? $log->transcript ?? '';
        $segments   = $request->segments   ?? $log->segments   ?? [];

        if (empty($transcript)) {
            $log->update(['audit_status' => 'clean', 'duration_seconds' => $request->duration_seconds]);
            return response()->json(['ok' => true]);
        }

        // Run LLM violation analysis
        [$status, $riskScore, $violations] = $this->analyzeTranscript($transcript);

        $log->update([
            'transcript'       => $transcript,
            'segments'         => $segments,
            'audit_status'     => $status,
            'risk_score'       => $riskScore,
            'violations'       => $violations,
            'duration_seconds' => $request->duration_seconds,
            'agent_version'    => $request->header('X-Agent-Version', 'unknown'),
        ]);

        // If flagged, create a clinical notification
        if ($status === 'flagged') {
            $this->notifyClinicalTeam($log);
        }

        return response()->json(['ok' => true]);
    }

    // ── Admin/clinical read endpoints ─────────────────────────────────────────

    /** Get the audit log for a specific session. */
    public function show(string $uuid)
    {
        $audit = SessionAuditLog::where('session_uuid', $uuid)
            ->with('reviewer:id,first_name,last_name')
            ->first();

        if (! $audit) {
            return $this->sendError('No audit log found for this session.', [], 404);
        }

        return $this->sendResponse($audit, 'Audit log retrieved.');
    }

    /** Mark a flagged session as reviewed. */
    public function review(Request $request, string $uuid)
    {
        $audit = SessionAuditLog::where('session_uuid', $uuid)->first();
        if (! $audit) {
            return $this->sendError('Audit log not found.', [], 404);
        }

        $request->validate(['notes' => 'nullable|string|max:2000']);

        $audit->update([
            'reviewed'       => true,
            'reviewed_by'    => $request->user()->id,
            'reviewer_notes' => $request->notes,
            'reviewed_at'    => now(),
        ]);

        return $this->sendResponse($audit->fresh('reviewer'), 'Review saved.');
    }

    /** List sessions with audit logs — filterable by status. */
    public function index(Request $request)
    {
        $query = SessionAuditLog::with('session:id,uuid,scheduled_at,status')
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('audit_status', $request->status);
        }

        if ($request->boolean('unreviewed')) {
            $query->where('reviewed', false)->where('audit_status', 'flagged');
        }

        $results = $query->paginate(25);

        return $this->sendResponse($results, 'Audit logs retrieved.');
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function verifyAgentSecret(Request $request): bool
    {
        $secret = env('AUDIT_AGENT_SECRET');
        if (! $secret) return true; // Not configured — allow in dev
        return $request->header('X-Audit-Secret') === $secret;
    }

    /**
     * Send transcript to Groq LLM with a T&C violation detection prompt.
     * Returns [status, risk_score, violations].
     */
    private function analyzeTranscript(string $transcript): array
    {
        $prompt = <<<PROMPT
You are an AI compliance auditor for Onwynd, a mental health platform. Analyze the following therapy session transcript for violations of the therapist Terms & Conditions.

Violations to check:
1. SOLICITATION — therapist asks patient to contact or pay outside the platform
2. BOUNDARY_VIOLATION — inappropriate personal relationship, romantic/sexual language
3. SCOPE_VIOLATION — diagnosing or prescribing without qualification, practicing outside scope
4. CRISIS_MISHANDLING — patient expresses suicidal ideation, self-harm, or crisis; therapist fails to follow protocol
5. SESSION_ABANDONMENT — therapist ends or threatens to end session early without clinical reason
6. DISCRIMINATORY_LANGUAGE — racist, sexist, or otherwise discriminatory speech
7. CONFIDENTIALITY_BREACH — sharing patient information or discussing other patients

Respond ONLY with valid JSON in this exact format:
{
  "risk_score": 0.0,
  "violations": [
    {
      "type": "SOLICITATION",
      "severity": "high",
      "quote": "exact quote from transcript",
      "recommendation": "what action to take"
    }
  ]
}
risk_score: 0.0 (clean) to 1.0 (critical). Empty violations array if clean.

TRANSCRIPT:
{$transcript}
PROMPT;

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.env('GROQ_API_KEY'),
                'Content-Type'  => 'application/json',
            ])->post('https://api.groq.com/openai/v1/chat/completions', [
                'model'       => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
                'messages'    => [['role' => 'user', 'content' => $prompt]],
                'temperature' => 0.1,
                'max_tokens'  => 1500,
            ]);

            $content = $response->json('choices.0.message.content', '{}');

            // Strip markdown code fences if present
            $content = preg_replace('/^```json\s*|\s*```$/m', '', trim($content));
            $data = json_decode($content, true);

            if (! is_array($data)) throw new \RuntimeException('Invalid JSON from LLM');

            $riskScore  = min(1.0, max(0.0, (float) ($data['risk_score'] ?? 0)));
            $violations = $data['violations'] ?? [];
            $status     = empty($violations) ? 'clean' : 'flagged';

            return [$status, $riskScore, $violations];

        } catch (\Throwable $e) {
            Log::error('Session audit LLM analysis failed', [
                'error' => $e->getMessage(),
            ]);
            return ['error', null, []];
        }
    }

    private function notifyClinicalTeam(SessionAuditLog $log): void
    {
        try {
            // Fire a database notification to all clinical advisors and admins
            $clinicalUsers = \App\Models\User::whereHas('role', function ($q) {
                $q->whereIn('slug', ['clinical_advisor', 'admin', 'super_admin']);
            })->get();

            foreach ($clinicalUsers as $user) {
                $user->notifications()->create([
                    'type'       => 'distress_alert',
                    'title'      => 'Session flagged by AI audit',
                    'message'    => 'A therapy session has been flagged for potential T&C violations. Risk score: '.round($log->risk_score * 100).'%.',
                    'data'       => json_encode([
                        'session_uuid' => $log->session_uuid,
                        'risk_score'   => $log->risk_score,
                        'top_violation'=> $log->topViolation(),
                        'action_url'   => '/admin/sessions/'.$log->session_uuid.'/review',
                    ]),
                    'is_read'    => false,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to notify clinical team of flagged session', ['error' => $e->getMessage()]);
        }
    }
}
