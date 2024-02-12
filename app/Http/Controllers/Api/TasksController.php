<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Controller;
use App\Models\Tasks;
use App\Models\User;
use Dotenv\Validator;
use Illuminate\Http\Request;
use phpDocumentor\Reflection\Types\Nullable;

class TasksController extends Controller
{
    public function index(Request $request)
    {
        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
        //    if ($auth->role != "admin")
//            return response()->json(['error' => 'Unauthorized'], 401);

        $filter = $request->filter;
        if ($auth->role == "admin") {
            if ($filter == "all") {
                $tasks = Tasks::with(['taskCustomer'])
                    ->orderBy('end_date', 'desc')
                    ->get();
            } else if ($filter == "completed") {
                $tasks = Tasks::with(['taskCustomer'])
                    ->where('isCompleted', true)
                    ->orderBy('end_date', 'desc')
                    ->get();
            } else if ($filter == "unread") {
                $tasks = Tasks::with(['taskCustomer'])
                    ->where('isRead', false)
                    ->orderBy('end_date', 'desc')
                    ->get();
            } else if ($filter == "important") {
                $tasks = Tasks::with(['taskCustomer'])
                    ->where('isImportant', true)
                    ->orderBy('end_date', 'desc')
                    ->get();
            } else {
                $tasks = Tasks::with(['taskCustomer'])
                    ->where('type', $filter)
                    ->orderBy('end_date', 'desc')
                    ->get();
            }
        } else {
            if ($filter == "all") {
                $tasks = Tasks::with(['taskCustomer'])
                    ->where('creator_id', $auth->id)
                    ->orderBy('end_date', 'desc')
                    ->get();
            } else if ($filter == "completed") {
                $tasks = Tasks::with(['taskCustomer'])
                    ->where('creator_id', $auth->id)
                    ->where('isCompleted', true)
                    ->orderBy('end_date', 'desc')
                    ->get();
            } else if ($filter == "unread") {
                $tasks = Tasks::with(['taskCustomer'])
                    ->where('creator_id', $auth->id)
                    ->where('isRead', false)
                    ->orderBy('end_date', 'desc')
                    ->get();
            } else if ($filter == "important") {
                $tasks = Tasks::with(['taskCustomer'])
                    ->where('creator_id', $auth->id)
                    ->where('isImportant', true)
                    ->orderBy('end_date', 'desc')
                    ->get();
            } else {
                $tasks = Tasks::with(['taskCustomer'])
                    ->where('creator_id', $auth->id)
                    ->where('type', $filter)
                    ->orderBy('end_date', 'desc')
                    ->get();
            }
        }
        return $tasks->toJson(JSON_PRETTY_PRINT);
    }

    public function customer_tasks(Request $request)
    {
        //        if ($auth->role != "admin")
//            return response()->json(['error' => 'Unauthorized'], 401);

        $filter = $request->filter;
        if ($filter == "all") {
            $tasks = Tasks::with(['taskCustomer'])
                ->where('customer_id', $request->user_id)
                ->orderBy('end_date', 'desc')
                ->get();
        } else if ($filter == "completed") {
            $tasks = Tasks::with(['taskCustomer'])
                ->where('customer_id', $request->user_id)
                ->where('isCompleted', true)
                ->orderBy('end_date', 'desc')
                ->get();
        } else if ($filter == "unread") {
            $tasks = Tasks::with(['taskCustomer'])
                ->where('customer_id', $request->user_id)
                ->where('isRead', false)
                ->orderBy('end_date', 'desc')
                ->get();
        } else if ($filter == "important") {
            $tasks = Tasks::with(['taskCustomer'])
                ->where('customer_id', $request->user_id)
                ->where('isImportant', true)
                ->orderBy('end_date', 'desc')
                ->get();
        } else {
            $tasks = Tasks::with(['taskCustomer'])
                ->where('customer_id', $request->user_id)
                ->where('type', $filter)
                ->orderBy('end_date', 'desc')
                ->get();
        }
        return $tasks->toJson(JSON_PRETTY_PRINT);
    }

    public function unread_count()
    {
        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }

        $tasks = Tasks::where('isRead', false)
            ->where('customer_id', $auth->id)
            ->get();

        return response()->json([
            'count' => count($tasks)
        ]);
    }
    public function store(Request $request)
    {
        return Tasks::create($request->all());
    }

    public function show($id)
    {
    }

    public function update(Request $request, $id)
    {
        $task = Tasks::find($id);
        $task->update($request->all());
    }

    public function destroy($id)
    {
        $task = Tasks::find($id);
        $task->delete();
    }
}
