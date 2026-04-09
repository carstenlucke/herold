<?php

return [

    'api_key' => env('HEROLD_API_KEY'),

    'github' => [
        'token' => env('GITHUB_TOKEN'),
        'owner' => env('GITHUB_OWNER'),
        'repo' => env('GITHUB_REPO'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
    ],

    'types' => [
        'general' => [
            'label' => 'General',
            'icon' => 'mdi-message-text',
            'github_label' => 'type:general',
            'extra_fields' => [],
            'preprocessing_prompt' => 'You are a task structuring assistant. Given a voice note transcript, '
                . 'create a clear, actionable task. Respond with valid JSON containing two keys: '
                . '"title" (a concise task title, max 80 chars) and "body" (a detailed task description '
                . 'formatted in Markdown with sections as appropriate). Focus on clarity and actionability.',
        ],

        'youtube' => [
            'label' => 'YouTube Transcription',
            'icon' => 'mdi-youtube',
            'github_label' => 'type:youtube',
            'extra_fields' => [
                [
                    'name' => 'youtube_url',
                    'type' => 'url',
                    'required' => true,
                    'label' => 'YouTube URL',
                ],
            ],
            'preprocessing_prompt' => 'You are a transcription task assistant. Given a voice note transcript '
                . 'and a YouTube URL, create a transcription task. Respond with valid JSON containing '
                . 'two keys: "title" (a concise task title referencing the video, max 80 chars) and '
                . '"body" (Markdown formatted with the YouTube URL, any context from the voice note, '
                . 'and clear instructions for the transcription task).',
        ],

        'diary' => [
            'label' => 'Diary',
            'icon' => 'mdi-book-open-variant',
            'github_label' => 'type:diary',
            'extra_fields' => [],
            'preprocessing_prompt' => 'You are a diary formatting assistant. Given a voice note transcript of a '
                . 'personal diary entry, format it into a clean, readable diary entry. Respond with '
                . 'valid JSON containing two keys: "title" (a concise title capturing the essence of '
                . 'the entry, max 80 chars) and "body" (the diary entry formatted in Markdown, '
                . 'preserving the personal tone while improving structure and readability).',
        ],
    ],

];
