<?php

return [

    'api_key' => env('HEROLD_API_KEY'),

    'admin_email' => env('HEROLD_ADMIN_EMAIL', 'herold@flitzpeople.com'),

    'github' => [
        'token' => env('HEROLD_GITHUB_TOKEN'),
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
                .'Respond with valid JSON: {"title": "...", "body": "..."}. '
                .'Title: a concise summary of the transcript content (max 80 chars, same language as transcript). '
                .'Body: the transcript text with ONLY obvious speech-to-text errors corrected '
                .'(misheard words, broken word boundaries, missing punctuation). '
                .'Do NOT add content, do NOT restructure, do NOT create task steps or instructions. '
                .'The body must stay faithful to what was actually said.',
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
                .'Respond with valid JSON: {"title": "...", "body": "..."}. '
                .'Title: a concise summary (max 80 chars, same language as transcript). '
                .'If you can identify the video name or creator from the transcript, include it in the title. '
                .'Body: the transcript text with ONLY obvious speech-to-text errors corrected. '
                .'Do NOT add content, do NOT restructure, do NOT create task steps.',
        ],

        'diary' => [
            'label' => 'Diary',
            'icon' => 'mdi-book-open-variant',
            'github_label' => 'type:diary',
            'needs_current_date_context' => true,
            'extra_fields' => [
                [
                    'name' => 'entry_date',
                    'type' => 'date',
                    'required' => false,
                    'label' => 'Entry Date',
                ],
            ],
            'preprocessing_prompt' => 'You receive a voice note transcript of a diary entry. Your job is minimal cleanup, NOT restructuring. '
                .'Respond with valid JSON: {"title": "...", "body": "...", "entry_date": "..." or null}. '
                .'Title: a concise summary of the entry (max 80 chars, same language as transcript). '
                .'Body: the transcript text with ONLY obvious speech-to-text errors corrected. '
                .'Do NOT restructure the text or change the personal tone. '
                .'entry_date: if the speaker explicitly mentions a date for this entry '
                .'(e.g. "Eintrag fuer den fuenften April"), extract it as ISO 8601 (YYYY-MM-DD). '
                .'Resolve relative date references (e.g. "gestern", "vorgestern", "morgen", '
                .'"naechsten Freitag", "diese Woche Montag", "letzte Woche Montag") '
                .'using the current date context provided. When ambiguous, prefer the nearest future date. '
                .'If no date is mentioned, return null.',
        ],

        'obsidian' => [
            'label' => 'Obsidian',
            'icon' => 'mdi-note-text',
            'github_label' => 'type:obsidian',
            'extra_fields' => [
                [
                    'name' => 'vault',
                    'type' => 'text',
                    'required' => false,
                    'label' => 'Vault Name',
                ],
            ],
            'preprocessing_prompt' => 'You receive a voice note transcript destined for an Obsidian vault. Your job is minimal cleanup, NOT restructuring. '
                .'Respond with valid JSON: {"title": "...", "body": "...", "vault": "..." or null}. '
                .'Title: a concise summary of the content (max 80 chars, same language as transcript). '
                .'Body: the transcript text with ONLY obvious speech-to-text errors corrected. '
                .'Do NOT restructure the text or add content. '
                .'vault: if the speaker explicitly mentions a target vault name '
                .'(e.g. "das soll in meinen Work-Vault" or "put this in my research vault"), extract the vault name. '
                .'If no vault is mentioned, return null.',
        ],

        'todo' => [
            'label' => 'To-Do',
            'icon' => 'mdi-checkbox-marked-outline',
            'github_label' => 'type:todo',
            'needs_current_date_context' => true,
            'extra_fields' => [
                [
                    'name' => 'deadline',
                    'type' => 'date',
                    'required' => false,
                    'label' => 'Deadline',
                ],
            ],
            'preprocessing_prompt' => 'You receive a voice note transcript capturing a task or to-do item. Your job is minimal cleanup, NOT restructuring. '
                .'Respond with valid JSON: {"title": "...", "body": "...", "deadline": "..." or null}. '
                .'Title: a concise description of the task (max 80 chars, same language as transcript). '
                .'Body: the transcript text with ONLY obvious speech-to-text errors corrected. '
                .'Do NOT restructure the text or create step-by-step instructions. '
                .'deadline: if the speaker explicitly mentions a deadline or due date '
                .'(e.g. "bis naechsten Freitag", "deadline is April 15th"), extract it as ISO 8601 (YYYY-MM-DD). '
                .'Resolve relative date references (e.g. "gestern", "vorgestern", "morgen", '
                .'"naechsten Freitag", "diese Woche Montag", "letzte Woche Montag") '
                .'using the current date context provided. When ambiguous, prefer the nearest future date. '
                .'If no deadline is mentioned, return null.',
        ],
    ],

];
