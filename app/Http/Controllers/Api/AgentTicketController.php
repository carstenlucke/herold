<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GitHubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentTicketController extends Controller
{
    public function __construct(private readonly GitHubService $gitHubService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $state = $request->query('status', 'open');

        return response()->json($this->gitHubService->listIssues(state: $state));
    }

    public function updateStatus(Request $request, int $number): JsonResponse
    {
        $status = $request->validate(['status' => ['required', 'string']])['status'];
        $this->gitHubService->updateLabels($number, ["status:{$status}"], []);

        return response()->json(['ok' => true]);
    }
}
