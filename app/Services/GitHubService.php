<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GitHubService
{
    private string $token;

    private string $owner;

    private string $repo;

    public function __construct()
    {
        $config = config('herold.github');

        $this->token = $config['token']
            ?? throw new RuntimeException('GitHub token is not configured.');
        $this->owner = $config['owner']
            ?? throw new RuntimeException('GitHub owner is not configured.');
        $this->repo = $config['repo']
            ?? throw new RuntimeException('GitHub repo is not configured.');
    }

    public function createIssue(string $title, string $body, array $labels): array
    {
        $response = Http::withToken($this->token)
            ->accept('application/vnd.github+json')
            ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
            ->timeout(30)
            ->post("https://api.github.com/repos/{$this->owner}/{$this->repo}/issues", [
                'title' => $title,
                'body' => $body,
                'labels' => $labels,
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                "GitHub issue creation failed: {$response->status()} — {$response->body()}"
            );
        }

        return [
            'number' => $response->json('number'),
            'html_url' => $response->json('html_url'),
        ];
    }
}
