<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\LogSysteme;
use App\Services\SystemLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogSystemeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $logs = LogSysteme::query()
            ->with('user:id,name,email,role_id')
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->string('action')->toString()))
            ->when($request->filled('module'), fn ($query) => $query->where('module', $request->string('module')->toString()))
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->when($request->filled('subject_type'), fn ($query) => $query->where('subject_type', $request->string('subject_type')->toString()))
            ->when($request->filled('subject_id'), fn ($query) => $query->where('subject_id', $request->integer('subject_id')))
            ->when($request->filled('date_debut'), fn ($query) => $query->whereDate('created_at', '>=', $request->date('date_debut')))
            ->when($request->filled('date_fin'), fn ($query) => $query->whereDate('created_at', '<=', $request->date('date_fin')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search): void {
                    $query->where('action', 'ilike', "%{$search}%")
                        ->orWhere('module', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%");
                });
            })
            ->latest('created_at')
            ->paginate(20);

        return response()->json(['data' => $logs]);
    }

    public function store(Request $request, SystemLogger $logger): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', 'string', 'max:255'],
            'module' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'before' => ['nullable', 'array'],
            'after' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
        ]);

        $log = $logger->record(
            action: $data['action'],
            module: $data['module'] ?? null,
            description: $data['description'] ?? null,
            before: $data['before'] ?? null,
            after: $data['after'] ?? null,
            metadata: $data['metadata'] ?? null,
            request: $request,
        );

        return response()->json([
            'message' => 'Log systeme cree.',
            'data' => $log?->load('user:id,name,email,role_id'),
        ], 201);
    }

    public function show(LogSysteme $logSysteme): JsonResponse
    {
        return response()->json([
            'data' => $logSysteme->load('user:id,name,email,role_id'),
        ]);
    }
}
