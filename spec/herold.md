# Herold – Implementierungsplan

## Kontext

Herold ist ein Voice-basierter Task-Dispatcher fuer lokale KI-Agenten. Ein einzelner Nutzer nimmt Sprachnachrichten auf, die App transkribiert und verarbeitet diese per OpenAI API und erstellt typisierte GitHub Issues in einem privaten Repo. Lokale Agenten (Claude Code, OpenCode) lesen diese Tickets via `gh` CLI + Cron und arbeiten sie ab.

**Entschiedener Stack:**
- Laravel 11+ (PHP 8.3+) als Monolith
- Inertia.js + Vue 3 (Composition API, TypeScript) + Vuetify 3
- Auth: API-Key + TOTP (Single-User)
- OpenAI Whisper API (Transkription) + Chat API (Vorverarbeitung)
- GitHub Issues API (Fine-grained PAT)
- Keine PWA, kein Service Worker
- SQLite als lokale DB
- **Docker Compose fuer lokale Entwicklung und Betrieb**

**Spec-Dokument:** `/Users/carsten/Development/projects/herold/spec/SPEC.md`

---

## Projektstruktur

```
herold/
  spec/SPEC.md
  docker-compose.yml              # Orchestrierung aller Services
  Dockerfile                      # Multi-stage: PHP + Node Build
  .docker/
    nginx.conf                    # Nginx Konfiguration
  .env.example                    # Vorlage fuer Umgebungsvariablen
  app/
    Http/
      Controllers/
        AuthController.php          # Login, TOTP-Verifizierung
        DashboardController.php     # Home/Uebersicht
        VoiceNoteController.php     # CRUD, Upload, Processing
        TicketController.php        # GitHub Issue Erstellung & Status
      Middleware/
        VerifyApiKey.php            # API-Key Check (vor TOTP)
      Requests/
        StoreVoiceNoteRequest.php   # Validierung Audio-Upload
        ProcessNoteRequest.php      # Validierung Typ + Metadaten
    Models/
      VoiceNote.php                 # Eloquent Model
    Services/
      OpenAIService.php             # Whisper + Chat Completion
      GitHubService.php             # Issues erstellen, Labels verwalten
      MessageTypeRegistry.php       # Typ-Definitionen laden
      PreprocessingService.php      # Orchestriert Transkription + LLM
    Jobs/
      TranscribeAudioJob.php        # Queue: Audio → Text
      PreprocessTranscriptJob.php   # Queue: Text → Strukturiertes Ticket
      CreateGitHubIssueJob.php      # Queue: Ticket → GitHub Issue
    Enums/
      NoteStatus.php                # recorded, transcribing, transcribed, ...
      MessageType.php               # general, youtube, diary
  config/
    herold.php                      # App-Konfiguration (Typen, Prompts, etc.)
  database/
    migrations/
      create_voice_notes_table.php
  resources/
    js/
      app.ts                        # Inertia + Vue + Vuetify Setup
      Pages/
        Auth/Login.vue              # API-Key + TOTP Login
        Dashboard.vue               # Uebersicht
        Recording/Create.vue        # Aufnahme + Typ-Wahl
        Notes/Show.vue              # Vorschau, Bearbeitung, Absenden
        Notes/Index.vue             # History / Liste
        Settings/Index.vue          # Einstellungen
      Components/
        AudioRecorder.vue           # MediaRecorder, Timer, Waveform
        TypeSelector.vue            # Nachrichtentyp-Auswahl
        TranscriptEditor.vue        # Editierbares Transkript
        NoteStatusBadge.vue         # Status-Anzeige
        NoteCard.vue                # Karte fuer Listen
      Composables/
        useAudioRecorder.ts         # MediaRecorder API Logik
        useProcessing.ts            # Processing-Pipeline Status
      Types/
        index.ts                    # TypeScript Interfaces
    views/
      app.blade.php                 # Inertia Root-Template
  routes/
    web.php                         # Alle Routen (Inertia)
```

---

## Datenbank (SQLite)

### Migration: `voice_notes`

```php
Schema::create('voice_notes', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->string('type');                  // general, youtube, diary
    $table->string('status');                // NoteStatus enum
    $table->string('audio_path')->nullable(); // Storage-Pfad
    $table->text('transcript')->nullable();
    $table->string('processed_title')->nullable();
    $table->text('processed_body')->nullable();
    $table->json('metadata')->nullable();    // Typ-spezifisch (youtube_url, etc.)
    $table->integer('github_issue_number')->nullable();
    $table->string('github_issue_url')->nullable();
    $table->text('error_message')->nullable();
    $table->timestamps();
});
```

### Enum: NoteStatus

```php
enum NoteStatus: string {
    case RECORDED = 'recorded';
    case TRANSCRIBING = 'transcribing';
    case TRANSCRIBED = 'transcribed';
    case PROCESSING = 'processing';
    case PROCESSED = 'processed';
    case SENDING = 'sending';
    case SENT = 'sent';
    case ERROR = 'error';
}
```

---

## Nachrichtentyp-Registry

Konfigurationsbasiert in `config/herold.php`:

```php
'types' => [
    'general' => [
        'label' => 'Allgemein',
        'icon' => 'mdi-message-text',
        'github_label' => 'type:general',
        'extra_fields' => [],
        'preprocessing_prompt' => '...', // oder Referenz auf Prompt-Datei
    ],
    'youtube' => [
        'label' => 'YouTube-Transkription',
        'icon' => 'mdi-youtube',
        'github_label' => 'type:youtube',
        'extra_fields' => [
            ['name' => 'youtube_url', 'type' => 'url', 'required' => true, 'label' => 'YouTube URL'],
        ],
        'preprocessing_prompt' => '...',
    ],
    'diary' => [
        'label' => 'Tagebuch',
        'icon' => 'mdi-book-open-variant',
        'github_label' => 'type:diary',
        'extra_fields' => [],
        'preprocessing_prompt' => '...',
    ],
],
```

Neuer Typ = neuer Eintrag in der Config + ggf. Prompt-Datei. Kein neuer Code noetig.

---

## Verarbeitungs-Pipeline

**Queue-basiert** (Laravel Jobs), damit die UI nicht blockiert:

```
1. Nutzer nimmt Audio auf → POST /notes (Audio-Upload)
   → VoiceNote wird mit Status RECORDED gespeichert
   → Audio in storage/app/private/audio/{ulid}.webm

2. Nutzer startet Verarbeitung → POST /notes/{id}/process
   → TranscribeAudioJob dispatched
   → Status: TRANSCRIBING

3. TranscribeAudioJob
   → OpenAIService::transcribe(audioPath)
   → Speichert Transkript, Status: TRANSCRIBED
   → Dispatched PreprocessTranscriptJob

4. PreprocessTranscriptJob
   → Laedt Typ-Config + Preprocessing-Prompt
   → OpenAIService::chat(prompt, transcript, metadata)
   → Speichert Title + Body, Status: PROCESSED

5. Nutzer prueft/editiert → Klickt "Ticket erstellen"
   → POST /notes/{id}/send
   → CreateGitHubIssueJob dispatched
   → Status: SENDING

6. CreateGitHubIssueJob
   → GitHubService::createIssue(title, body, labels)
   → Speichert Issue-Nummer + URL, Status: SENT
```

**Polling fuer Status-Updates:** Inertia `router.reload()` alle 2s waehrend Processing laeuft, oder alternativ Laravel Echo + Pusher/Reverb fuer Echtzeit (kann spaeter ergaenzt werden).

---

## Authentifizierung

### Flow

1. Nutzer oeffnet App → Login-Seite
2. Gibt API-Key ein (gespeichert in `.env` als `HEROLD_API_KEY`)
3. Server validiert Key → zeigt TOTP-Feld
4. Nutzer gibt TOTP-Code ein (Authenticator-App)
5. Server validiert TOTP → erstellt Laravel-Session
6. Alle weiteren Requests sind Session-basiert (Standard-Laravel)

### Implementierung

- **API-Key**: Einfacher String-Vergleich gegen `config('herold.api_key')`
- **TOTP**: Package `pragmarx/google2fa-laravel` oder `laragear/two-factor`
- **Setup**: Beim ersten Start TOTP-Secret generieren, QR-Code anzeigen
- **Session**: Standard Laravel Session-Auth, kein Sanctum noetig (kein SPA-API, Inertia nutzt Cookies)
- **Middleware**: `VerifyApiKey` auf Login-Route, danach Standard `auth` Middleware

---

## Routen

```php
// Auth
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login/key', [AuthController::class, 'verifyKey']);
Route::post('/login/totp', [AuthController::class, 'verifyTotp']);
Route::post('/logout', [AuthController::class, 'logout']);

// Geschuetzt (auth middleware)
Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/notes', [VoiceNoteController::class, 'index'])->name('notes.index');
    Route::get('/notes/create', [VoiceNoteController::class, 'create'])->name('notes.create');
    Route::post('/notes', [VoiceNoteController::class, 'store'])->name('notes.store');
    Route::get('/notes/{note}', [VoiceNoteController::class, 'show'])->name('notes.show');
    Route::put('/notes/{note}', [VoiceNoteController::class, 'update'])->name('notes.update');
    Route::delete('/notes/{note}', [VoiceNoteController::class, 'destroy'])->name('notes.destroy');

    Route::post('/notes/{note}/process', [VoiceNoteController::class, 'process']);
    Route::post('/notes/{note}/send', [TicketController::class, 'send']);

    Route::get('/tickets', [TicketController::class, 'index']);          // GitHub Issues auflisten
    Route::patch('/tickets/{number}', [TicketController::class, 'updateStatus']); // Status-Label aendern

    Route::get('/settings', [SettingsController::class, 'index']);
    Route::get('/types', fn () => response()->json(config('herold.types'))); // Typ-Registry
});
```

---

## Services

### OpenAIService

```php
class OpenAIService
{
    public function transcribe(string $audioPath): string
    // → OpenAI Whisper API, gibt Text zurueck

    public function chat(string $systemPrompt, string $userMessage, float $temperature = 0.3): array
    // → OpenAI Chat Completion, gibt {title, body} zurueck
}
```

### GitHubService

```php
class GitHubService
{
    public function createIssue(string $title, string $body, array $labels): array
    // → GitHub API POST /repos/{owner}/{repo}/issues

    public function listIssues(array $labels = [], string $state = 'open'): array
    // → GitHub API GET /repos/{owner}/{repo}/issues

    public function updateLabels(int $issueNumber, array $addLabels, array $removeLabels): void
    // → GitHub API: Labels hinzufuegen/entfernen
}
```

### PreprocessingService

```php
class PreprocessingService
{
    public function process(VoiceNote $note): void
    // 1. Typ-Config laden
    // 2. Prompt zusammenbauen (System-Prompt + Transkript + Metadaten)
    // 3. OpenAIService::chat() aufrufen
    // 4. Ergebnis parsen (Title + Body)
    // 5. VoiceNote aktualisieren
}
```

---

## Vue-Seiten (Inertia)

### Recording/Create.vue
- TypeSelector (Chips: General | YouTube | Diary)
- Dynamische Extra-Felder je nach Typ (z.B. YouTube-URL)
- AudioRecorder-Komponente (Start/Stop/Pause, Timer, Waveform)
- "Speichern" → POST /notes mit Audio + Typ + Metadaten

### Notes/Show.vue
- Status-Badge (aktueller Verarbeitungsstand)
- Rohtranskript (editierbar)
- "Verarbeiten" Button → POST /notes/{id}/process
- Verarbeitetes Ergebnis: Titel (editierbar) + Body (editierbar)
- "Ticket erstellen" Button → POST /notes/{id}/send
- Link zum GitHub Issue (nach Erstellung)

### Notes/Index.vue
- Liste aller Notizen, sortiert nach Datum
- Filter: Typ + Status
- NoteCard-Komponenten mit Status, Typ-Icon, Titel, Datum

### Dashboard.vue
- Zahlen: Offene Notizen, gesendete Tickets
- Quick-Action: "Neue Aufnahme"
- Letzte 5 Notizen

---

## Docker Setup (Entwicklung & Betrieb)

Kein lokales PHP/Composer/Node noetig. Alles laeuft in Docker.

### docker-compose.yml Services

```yaml
services:
  app:                            # Laravel (PHP-FPM 8.3)
    build: .
    volumes:
      - .:/var/www/html           # Code-Mount fuer Live-Entwicklung
      - sqlite_data:/var/www/html/database  # SQLite Persistenz
    env_file: .env
    depends_on: [nginx]

  nginx:                          # Webserver
    image: nginx:alpine
    ports:
      - "8080:80"
    volumes:
      - .:/var/www/html
      - .docker/nginx.conf:/etc/nginx/conf.d/default.conf

  queue:                          # Laravel Queue Worker
    build: .
    command: php artisan queue:work --sleep=3 --tries=3
    volumes:
      - .:/var/www/html
      - sqlite_data:/var/www/html/database
    env_file: .env

  node:                           # Vite Dev Server (nur Entwicklung)
    image: node:20-alpine
    working_dir: /var/www/html
    command: npm run dev -- --host 0.0.0.0
    ports:
      - "5173:5173"
    volumes:
      - .:/var/www/html
      - node_modules:/var/www/html/node_modules

volumes:
  sqlite_data:
  node_modules:
```

### Dockerfile (Multi-stage)

```dockerfile
FROM php:8.3-fpm-alpine AS base

# PHP Extensions: pdo_sqlite, pcntl (fuer Queue Worker)
RUN apk add --no-cache sqlite-dev \
    && docker-php-ext-install pdo_sqlite pcntl

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .
RUN composer install --no-dev --optimize-autoloader

# --- Dev Target ---
FROM base AS dev
RUN composer install  # Mit dev-dependencies
```

### Entwicklungs-Workflow

```bash
# Erstmaliges Setup
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan migrate
docker compose exec app php artisan key:generate

# Taeglicher Start
docker compose up -d

# Erreichbar unter:
# App:  http://localhost:8080
# Vite: http://localhost:5173 (HMR)

# Laravel-Befehle ausfuehren
docker compose exec app php artisan <command>

# Queue-Worker laeuft automatisch als eigener Service
# Logs: docker compose logs -f queue

# Stoppen
docker compose down
```

### HTTPS fuer Mikrofon-Zugriff

MediaRecorder API erfordert HTTPS (oder localhost). Optionen:
- **localhost**: Funktioniert ohne HTTPS im Browser
- **Caddy als Reverse Proxy**: Fuer Zugriff von anderen Geraeten im Netz

Fuer lokale Entwicklung reicht `localhost:8080` – kein Caddy noetig.
Falls Zugriff vom Handy gewuenscht: Caddy-Service ergaenzen mit Self-Signed Cert.

---

## Implementierungsreihenfolge

### Phase 1: Projektsetup + Docker
1. Laravel 11 Projekt erstellen (via `composer create-project` im Container)
2. Dockerfile + docker-compose.yml + nginx.conf erstellen
3. Inertia.js + Vue 3 + TypeScript einrichten
4. Vuetify 3 installieren und konfigurieren
5. SQLite konfigurieren (Volume fuer Persistenz)
6. Basis-Layout (App-Shell, Navigation)
7. `docker compose up` → App laeuft

### Phase 2: Datenmodell + Auth
8. Migration `voice_notes` erstellen
9. VoiceNote Model + NoteStatus Enum
10. Auth: API-Key Middleware
11. Auth: TOTP Setup + Verifizierung
12. Login-Seite (Vue)

### Phase 3: Audio-Aufnahme
13. `useAudioRecorder` Composable (MediaRecorder API)
14. AudioRecorder Vue-Komponente (UI + Waveform)
15. TypeSelector Komponente
16. Recording/Create.vue Seite
17. VoiceNoteController::store (Audio-Upload)

### Phase 4: Verarbeitung
18. OpenAIService (Whisper + Chat)
19. config/herold.php mit Typ-Definitionen + Prompts
20. PreprocessingService
21. TranscribeAudioJob + PreprocessTranscriptJob
22. Queue-Worker laeuft als eigener Docker-Service (database driver)
23. Notes/Show.vue mit Status-Polling

### Phase 5: Ticket-Erstellung
24. GitHubService
25. CreateGitHubIssueJob
26. Ticket-Body Templates (mit herold:meta Kommentar)
27. TicketController (erstellen + Status aendern)

### Phase 6: UI vervollstaendigen
28. Notes/Index.vue (History)
29. Dashboard.vue
30. Settings-Seite
31. Dark/Light Theme

### Phase 7: Polish
32. Error-Handling (fehlgeschlagene API-Calls, Retry-Logik in Jobs)
33. Rate Limiting + Security Headers
34. .env.example mit allen Variablen dokumentieren
35. Spec aktualisieren

---

## Verifikation

- **Docker**: `docker compose up -d` → alle Services laufen, App erreichbar unter localhost:8080
- **Audio-Aufnahme**: Im Browser aufnehmen, pruefen ob Datei in storage landet
- **Transkription**: Audio hochladen, Queue-Worker-Logs pruefen (`docker compose logs -f queue`), Transkript pruefen
- **Vorverarbeitung**: Transkript verarbeiten lassen, strukturiertes Ergebnis pruefen
- **Ticket**: Issue in GitHub pruefen (Labels, Body-Format, Meta-Kommentar)
- **Status-Lifecycle**: Labels manuell via `gh` aendern, pruefen ob App Status korrekt anzeigt
- **Auth**: Ohne Key → abgewiesen; mit Key ohne TOTP → abgewiesen; mit beidem → Zugang
- **Typ-Erweiterung**: Neuen Typ in config/herold.php eintragen, pruefen ob UI + Processing funktioniert
- **Persistenz**: `docker compose down && docker compose up -d` → SQLite-Daten bleiben erhalten
