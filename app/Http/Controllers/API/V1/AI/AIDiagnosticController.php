<?php

namespace App\Http\Controllers\API\V1\AI;

use App\Http\Controllers\Controller;
use App\Http\Requests\AI\AIDiagnosticRequest;
use App\Repositories\Contracts\AIRepositoryInterface;
use App\Services\AI\AIDiagnosticService;
use Illuminate\Http\Request;

class AIDiagnosticController extends Controller
{
    protected $diagnosticService;

    protected $aiRepository;

    public function __construct(
        AIDiagnosticService $diagnosticService,
        AIRepositoryInterface $aiRepository
    ) {
        $this->diagnosticService = $diagnosticService;
        $this->aiRepository = $aiRepository;
    }

    /**
     * Start a new diagnostic session
     */
    public function start(Request $request)
    {
        $diagnostic = $this->diagnosticService->startDiagnostic($request->user());

        return response()->json($diagnostic);
    }

    /**
     * Send a message to the AI
     */
    public function message(AIDiagnosticRequest $request, $id)
    {
        $diagnostic = $this->aiRepository->getDiagnosticWithHistory($id, $request->user()->id);

        if (! $diagnostic) {
            return response()->json(['error' => 'Session not found'], 404);
        }

        if ($diagnostic->status === 'completed') {
            return response()->json(['error' => 'Session completed'], 400);
        }

        $updatedDiagnostic = $this->diagnosticService->processUserResponse($diagnostic, $request->input('message'));

        return response()->json($updatedDiagnostic);
    }

    /**
     * Get session history
     */
    public function show(Request $request, $id)
    {
        $diagnostic = $this->aiRepository->getDiagnosticWithHistory($id, $request->user()->id);

        if (! $diagnostic) {
            return response()->json(['error' => 'Session not found'], 404);
        }

        return response()->json($diagnostic);
    }
}
