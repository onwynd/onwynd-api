<?php

namespace App\Http\Controllers\API\V1\Tech;

use App\Http\Controllers\API\BaseController;
use App\Models\Deployment;
use Illuminate\Http\Request;

class DeploymentController extends BaseController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Deployment::with('deployer:id,first_name,last_name,email');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('environment')) {
            $query->where('environment', $request->environment);
        }

        $deployments = $query->orderBy('created_at', 'desc')->paginate(15);

        // Transform if necessary or rely on frontend to handle structure
        // But for consistency with frontend mock expectations, let's ensure structure matches

        $deployments->getCollection()->transform(function ($deployment) {
            return [
                'id' => $deployment->id,
                'version' => $deployment->version,
                'environment' => $deployment->environment,
                'status' => $deployment->status,
                'deployed_by' => $deployment->deployer ? $deployment->deployer->first_name.' '.$deployment->deployer->last_name : 'System',
                'deployed_at' => $deployment->created_at->toIso8601String(),
                'duration' => $deployment->duration,
            ];
        });

        return $this->sendResponse($deployments, 'Deployments retrieved successfully.');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'version' => 'required|string',
            'environment' => 'required|string',
        ]);

        $deployment = Deployment::create([
            'version' => $request->version,
            'environment' => $request->environment,
            'status' => 'pending', // Simulated start
            'deployed_by' => $request->user()->id ?? null,
            'duration' => '0s',
        ]);

        // In a real app, this would trigger a job.
        // For now, we simulate success after a "delay" by just returning it.

        return $this->sendResponse($deployment, 'Deployment triggered successfully.');
    }
}
