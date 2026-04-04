<?php

namespace App\Http\Controllers\API\V1\Sales;

use App\Http\Controllers\API\BaseController;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TaskController extends BaseController
{
    private function table(): \Illuminate\Database\Query\Builder
    {
        return DB::table('sales_tasks');
    }

    public function index(Request $request)
    {
        if (! $this->tableExists()) {
            return $this->sendResponse([], 'Sales tasks retrieved.');
        }

        $query = $this->table()->where('user_id', Auth::id());

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $tasks = $query->orderBy('due_date')->get();

        return $this->sendResponse($tasks, 'Sales tasks retrieved.');
    }

    public function store(Request $request)
    {
        if (! $this->tableExists()) {
            return $this->sendResponse([], 'Task created (table pending migration).');
        }

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'priority' => 'nullable|string|in:low,medium,high',
            'lead_id' => 'nullable|integer',
            'deal_id' => 'nullable|integer',
        ]);

        $id = $this->table()->insertGetId(array_merge($data, [
            'user_id' => Auth::id(),
            'status' => 'pending',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]));

        $task = $this->table()->where('id', $id)->first();

        return $this->sendResponse($task, 'Task created.');
    }

    public function update(Request $request, $id)
    {
        if (! $this->tableExists()) {
            return $this->sendResponse([], 'Task updated (table pending migration).');
        }

        $this->table()->where('id', $id)->where('user_id', Auth::id())->update(
            array_merge($request->only(['title', 'description', 'due_date', 'priority', 'status']), [
                'updated_at' => Carbon::now(),
            ])
        );

        $task = $this->table()->where('id', $id)->first();

        return $this->sendResponse($task, 'Task updated.');
    }

    public function destroy($id)
    {
        if (! $this->tableExists()) {
            return $this->sendResponse([], 'Task deleted (table pending migration).');
        }

        $this->table()->where('id', $id)->where('user_id', Auth::id())->delete();

        return $this->sendResponse([], 'Task deleted.');
    }

    private function tableExists(): bool
    {
        return \Illuminate\Support\Facades\Schema::hasTable('sales_tasks');
    }
}
