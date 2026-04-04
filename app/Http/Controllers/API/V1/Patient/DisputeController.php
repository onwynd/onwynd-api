<?php

namespace App\Http\Controllers\API\V1\Patient;

use App\Http\Controllers\API\BaseController;
use App\Models\Dispute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DisputeController extends BaseController
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'session_uuid' => 'required|string|max:36',
            'issue_type' => 'required|in:no_show,technical_issue,quality_concern,other',
            'description' => 'required|string|min:10|max:2000',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $user = $request->user();
        $dispute = Dispute::create([
            'session_uuid' => $request->session_uuid,
            'user_id' => $user->id,
            'issue_type' => $request->issue_type,
            'description' => $request->description,
            'status' => 'open',
        ]);

        return $this->sendResponse($dispute, 'Dispute filed successfully. Our team will review it within 48 hours.');
    }
}
