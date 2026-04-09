<?php

return [
    'api_key' => env('HEROLD_API_KEY'),
    'github' => [
        'owner' => env('HEROLD_GITHUB_OWNER'),
        'repo' => env('HEROLD_GITHUB_REPO'),
        'token' => env('HEROLD_GITHUB_TOKEN'),
    ],
    'ai' => [
        'provider' => 'openai',
        'api_key' => env('HEROLD_OPENAI_API_KEY'),
    ],
    'types' => [
        'general' => [
            'label' => 'General',
            'icon' => 'mdi-message-text',
            'github_label' => 'type:general',
            'extra_fields' => [],
            'preprocessing_prompt' => 'Summarize the transcript into an actionable issue.',
        ],
        'youtube' => [
            'label' => 'YouTube transcription',
            'icon' => 'mdi-youtube',
            'github_label' => 'type:youtube',
            'extra_fields' => [
                ['name' => 'youtube_url', 'type' => 'url', 'required' => true, 'label' => 'YouTube URL'],
            ],
            'preprocessing_prompt' => 'Extract key tasks and references from this YouTube-related note.',
        ],
        'diary' => [
            'label' => 'Diary',
            'icon' => 'mdi-book-open-variant',
            'github_label' => 'type:diary',
            'extra_fields' => [],
            'preprocessing_prompt' => 'Convert the diary entry into concise tasks and context.',
        ],
    ],
];
