<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Memory;
use App\Services\MemoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemoryController extends Controller
{
    public function __construct(private readonly MemoryService $memoryService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->memoryService->search(
            $request->query('scope'),
            $request->query('category'),
            $request->query('query')
        ));
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'scope' => ['required', 'string'],
            'category' => ['required', 'string'],
            'content' => ['required', 'string'],
            'source' => ['required', 'string'],
        ]);

        return response()->json($this->memoryService->store($payload['scope'], $payload['category'], $payload['content'], $payload['source']), 201);
    }

    public function destroy(Memory $memory): JsonResponse
    {
        $this->memoryService->destroy($memory);

        return response()->noContent();
    }
}
