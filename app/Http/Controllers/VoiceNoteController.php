<?php

namespace App\Http\Controllers;

use App\Enums\NoteStatus;
use App\Http\Requests\StoreVoiceNoteRequest;
use App\Models\VoiceNote;
use App\Services\AIService;
use App\Services\GitHubService;
use App\Services\IssueContentSanitizer;
use App\Services\MessageTypeRegistry;
use App\Services\PreprocessingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class VoiceNoteController extends Controller
{
    public function __construct(
        private readonly MessageTypeRegistry $typeRegistry,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $query = VoiceNote::query()->latest();

        if ($request->filled('type')) {
            $query->ofType($request->input('type'));
        }

        if ($request->filled('status')) {
            $status = NoteStatus::tryFrom($request->input('status'));
            if ($status) {
                $query->ofStatus($status);
            }
        }

        return Inertia::render('Notes/Index', [
            'notes' => $query->paginate(15)->withQueryString(),
            'types' => $this->typeRegistry->all(),
            'filters' => $request->only(['type', 'status']),
        ]);
    }

    public function create(): InertiaResponse
    {
        return Inertia::render('Recording/Create', [
            'types' => $this->typeRegistry->all(),
        ]);
    }

    public function store(StoreVoiceNoteRequest $request)
    {
        $validated = $request->validated();

        $note = new VoiceNote([
            'type' => $validated['type'],
            'status' => NoteStatus::RECORDED,
            'metadata' => $validated['metadata'] ?? null,
        ]);

        $note->save();

        if ($request->hasFile('audio')) {
            $ext = match ($request->file('audio')->getMimeType()) {
                'audio/ogg' => 'ogg',
                'audio/mp4' => 'm4a',
                default => 'webm',
            };

            $path = $request->file('audio')->storeAs(
                'audio',
                "{$note->id}.{$ext}",
                'local'
            );
            $note->update(['audio_path' => $path]);
        }

        return redirect()->route('notes.show', $note);
    }

    public function show(VoiceNote $note): InertiaResponse
    {
        return Inertia::render('Notes/Show', [
            'note' => $note,
            'types' => $this->typeRegistry->all(),
        ]);
    }

    public function audio(VoiceNote $note)
    {
        if (! $note->audio_path || ! Storage::disk('local')->exists($note->audio_path)) {
            abort(404);
        }

        return response()->file(
            Storage::disk('local')->path($note->audio_path),
            ['Content-Type' => Storage::disk('local')->mimeType($note->audio_path) ?: 'audio/webm']
        );
    }

    public function update(Request $request, VoiceNote $note)
    {
        $validated = $request->validate([
            'transcript' => 'nullable|string',
            'processed_title' => 'nullable|string|max:255',
            'processed_body' => 'nullable|string',
            'metadata' => 'nullable|array',
            'metadata.entry_date' => 'nullable|date_format:Y-m-d',
        ]);

        $note->update($validated);

        return redirect()->route('notes.show', $note);
    }

    public function destroy(VoiceNote $note)
    {
        if ($note->audio_path) {
            Storage::disk('local')->delete($note->audio_path);
        }

        $note->delete();

        return redirect()->route('notes.index');
    }

    public function process(VoiceNote $note, AIService $aiService, PreprocessingService $preprocessingService)
    {
        try {
            $note->update(['error_message' => null]);

            if ($note->audio_path && ! $note->transcript) {
                $fullPath = Storage::disk('local')->path($note->audio_path);
                $transcript = $aiService->transcribe($fullPath);
                $note->update(['transcript' => $transcript]);
            }

            $preprocessingService->process($note);
        } catch (\Throwable $e) {
            Log::error('Voice note processing failed.', [
                'note_id' => $note->id,
                'error' => $e->getMessage(),
            ]);

            $note->update([
                'status' => NoteStatus::ERROR,
                'error_message' => $e->getMessage(),
            ]);
        }

        return redirect()->route('notes.show', $note);
    }

    public function send(
        VoiceNote $note,
        IssueContentSanitizer $sanitizer,
        GitHubService $gitHubService,
    ) {
        if ($note->status !== NoteStatus::PROCESSED) {
            return redirect()->route('notes.show', $note)
                ->withErrors(['status' => 'Note must be processed before sending.']);
        }

        try {
            $body = $sanitizer->sanitizeAndWrap($note);

            $typeConfig = config("herold.types.{$note->type}");
            $labels = [];
            if (isset($typeConfig['github_label'])) {
                $labels[] = $typeConfig['github_label'];
            }

            $result = $gitHubService->createIssue(
                $note->processed_title ?? 'Untitled Voice Note',
                $body,
                $labels,
            );

            $note->update([
                'status' => NoteStatus::SENT,
                'github_issue_number' => $result['number'],
                'github_issue_url' => $result['html_url'],
            ]);
        } catch (\Throwable $e) {
            Log::error('Voice note send to GitHub failed.', [
                'note_id' => $note->id,
                'error' => $e->getMessage(),
            ]);

            $note->update([
                'status' => NoteStatus::ERROR,
                'error_message' => $e->getMessage(),
            ]);
        }

        return redirect()->route('notes.show', $note);
    }
}
