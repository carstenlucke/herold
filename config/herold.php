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
            'preprocessing_prompt' => 'You receive a voice note transcript. Your job is minimal cleanup, NOT restructuring. '
                . 'Respond with valid JSON: {"title": "...", "body": "..."}. '
                . 'Title: a concise summary of the transcript content (max 80 chars, same language as transcript). '
                . 'Body: the transcript text with ONLY obvious speech-to-text errors corrected '
                . '(misheard words, broken word boundaries, missing punctuation). '
                . 'Do NOT add content, do NOT restructure, do NOT create task steps or instructions. '
                . 'The body must stay faithful to what was actually said.',
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
            'preprocessing_prompt' => 'You receive a voice note transcript and a YouTube URL. Your job is minimal cleanup, NOT restructuring. '
                . 'Respond with valid JSON: {"title": "...", "body": "..."}. '
                . 'Title: a concise summary (max 80 chars, same language as transcript). '
                . 'If you can identify the video name or creator from the transcript, include it in the title. '
                . 'Body: the transcript text with ONLY obvious speech-to-text errors corrected. '
                . 'Do NOT add content, do NOT restructure, do NOT create task steps.',
        ],

        'diary' => [
            'label' => 'Diary',
            'icon' => 'mdi-book-open-variant',
            'github_label' => 'type:diary',
            'extra_fields' => [
                [
                    'name' => 'entry_date',
                    'type' => 'date',
                    'required' => false,
                    'label' => 'Entry Date',
                ],
            ],
            'preprocessing_prompt' => 'You receive a voice note transcript of a diary entry. Your job is minimal cleanup, NOT restructuring. '
                . 'Respond with valid JSON: {"title": "...", "body": "...", "entry_date": "..." or null}. '
                . 'Title: a concise summary of the entry (max 80 chars, same language as transcript). '
                . 'Body: the transcript text with ONLY obvious speech-to-text errors corrected. '
                . 'Do NOT restructure the text or change the personal tone. '
                . 'entry_date: if the speaker explicitly mentions a date for this entry '
                . '(e.g. "Eintrag fuer den fuenften April"), extract it as ISO 8601 (YYYY-MM-DD). '
                . 'Use the current date context provided to resolve relative references. '
                . 'If no date is mentioned, return null.',
        ],
    ],

];
