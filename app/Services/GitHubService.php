<?php

namespace App\Services;

class GitHubService
{
    /** @return array{number:int,url:string} */
    public function createIssue(string $title, string $body, array $labels): array
    {
        return ['number' => random_int(1000, 9999), 'url' => 'https://github.com/example/repo/issues/1'];
    }

    public function listIssues(array $labels = [], string $state = 'open'): array
    {
        return [];
    }

    public function updateLabels(int $issueNumber, array $addLabels, array $removeLabels): void
    {
    }
}
