<?php

namespace App\Http\Controllers;

use App\Enums\NoteStatus;
use App\Http\Requests\ProcessNoteRequest;
use App\Http\Requests\StoreVoiceNoteRequest;
use App\Jobs\TranscribeAudioJob;
use App\Models\VoiceNote;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class VoiceNoteController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Notes/Index', [
            'notes' => VoiceNote::query()->latest()->paginate(20),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Recording/Create', ['types' => config('herold.types')]);
    }

    public function store(StoreVoiceNoteRequest $request): RedirectResponse
    {
        $audioPath = $request->file('audio')->store('audio', 'local');

        $note = VoiceNote::query()->create([
            'type' => $request->string('type')->toString(),
            'status' => NoteStatus::RECORDED,
            'audio_path' => $audioPath,
            'metadata' => $request->input('metadata', []),
        ]);

        return redirect()->route('notes.show', $note);
    }

    public function show(VoiceNote $note): Response
    {
        return Inertia::render('Notes/Show', ['note' => $note]);
    }

    public function update(ProcessNoteRequest $request, VoiceNote $note): RedirectResponse
    {
        $note->update($request->validated());

        return back();
    }

    public function destroy(VoiceNote $note): RedirectResponse
    {
        $note->delete();

        return redirect()->route('notes.index');
    }

    public function process(VoiceNote $note): RedirectResponse
    {
        $note->update(['status' => NoteStatus::TRANSCRIBING]);
        TranscribeAudioJob::dispatch($note->id);

        return back();
    }
}
