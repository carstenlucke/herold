# Herold – Implementierungsplan

## Kontext

Herold ist ein Voice-basierter Task-Dispatcher fuer lokale KI-Agenten. Ein einzelner Nutzer nimmt Sprachnachrichten auf, die App transkribiert und verarbeitet diese per OpenAI API und erstellt typisierte GitHub Issues in einem privaten Repo. Lokale Agenten (Claude Code, OpenCode) lesen diese Tickets via `gh` CLI + Cron und arbeiten sie ab.

**Designprinzipien:**
- **Mobile First / Responsive** -- primaerer Nutzungskontext ist das Smartphone (Sprachaufnahme unterwegs), Desktop als Zweitbildschirm
- Vuetify 4 Breakpoints (xs/sm/md/lg/xl) konsequent nutzen
- Touch-optimierte UI: grosse Tap-Targets, Swipe-Gesten wo sinnvoll
- Bottom Navigation auf Mobile, Side Navigation auf Desktop

**Entschiedener Stack:**
- Laravel 13 (PHP 8.5) als Monolith
- Inertia.js 3 + Vue 3.5 (Composition API, TypeScript 6) + Vuetify 4
- Auth: API-Key + TOTP (Browser/Mensch), Laravel Sanctum Tokens (Agenten)
- Laravel AI SDK (`laravel/ai`) fuer OpenAI Whisper + Chat API
- GitHub Issues API (Fine-grained PAT)
- Keine PWA, kein Service Worker
- SQLite als lokale DB
- Docker Compose fuer lokale Entwicklung und Betrieb
- Vite 8 (Rolldown-Bundler) fuer Frontend-Build
- Node.js 24 LTS nur fuer Build-Prozess (kein Runtime-Bestandteil)

**Versionen (Stand April 2026):**

| Komponente | Version | Hinweise |
|-----------|---------|----------|
| Laravel | 13.4.0 | First-party AI SDK |
| PHP | 8.5.4 | Support bis 2027, Security bis 2029 |
| Composer | 2.9.5 | |
| Inertia.js (Laravel) | 3.0.3 | |
| Inertia.js (Vue 3) | 3.0.3 | |
| Vue.js | 3.5.32 | |
| TypeScript | 6.0.2 | |
| Vuetify | 4.0.5 | MD3, CSS Cascade Layers |
| Vite | 8.0.7 | Rolldown-Bundler (Rust) |
| Node.js LTS | 24.14.1 | Nur fuer Build |
| npm | 11.12.1 | |
| laragear/two-factor | 4.0.0 | TOTP, Octane-kompatibel |
| Laravel Sanctum | (mit Laravel 13) | Agent-Token-Auth |
| laravel/ai | 0.4.5 | OpenAI, Anthropic, Gemini |
| Docker Engine | 29.4.0 | |
| Docker Compose | 5.1.1 | |
| PHP Docker Image | 8.5-apache | Apache + PHP in einem Container |
| SQLite | 3.51.3 | 3.52.0 zurueckgezogen |

---

## Projektstruktur

```
herold/
  spec/SPEC.md
  docker-compose.yml              # Orchestrierung aller Services
  Dockerfile                      # PHP 8.5 + Apache (wie Prod)
  .env.example                    # Vorlage fuer Umgebungsvariablen
  app/
    Http/
      Controllers/
        AuthController.php          # Login, TOTP-Verifizierung
        DashboardController.php     # Home/Uebersicht
        VoiceNoteController.php     # CRUD, Upload, Processing
        TicketController.php        # GitHub Issue Erstellung & Status
        Api/
          MemoryController.php      # Agent Memory CRUD (Sanctum-Auth)
          AgentTicketController.php  # Agent Ticket-Zugriff (Sanctum-Auth)
        SettingsController.php      # Einstellungen + Token-Verwaltung
      Middleware/
        VerifyApiKey.php            # API-Key Check (vor TOTP)
      Requests/
        StoreVoiceNoteRequest.php   # Validierung Audio-Upload
        ProcessNoteRequest.php      # Validierung Typ + Metadaten
    Models/
      VoiceNote.php                 # Eloquent Model
      Memory.php                    # Agent-Gedaechtnis Model
      User.php                      # Single-User + Sanctum HasApiTokens
    Services/
      AIService.php                  # Laravel AI SDK (Whisper + Chat)
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
      MemoryScope.php               # global, project:{name}, ticket:{number}
      MemoryCategory.php            # decision, learning, preference, context
  config/
    herold.php                      # App-Konfiguration (Typen, Prompts, etc.)
  database/
    migrations/
      create_voice_notes_table.php
      create_memories_table.php
      create_personal_access_tokens_table.php  # Sanctum
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
    web.php                         # Browser-Routen (Inertia, Session-Auth)
    api.php                         # Agent-API-Routen (Sanctum Token-Auth)
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

### Migration: `memories`

```php
Schema::create('memories', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->string('scope');                 // global, project:{name}, ticket:{number}
    $table->string('category');              // decision, learning, preference, context
    $table->text('content');                 // Das eigentliche Wissen
    $table->string('source');                // claude-code, opencode, user, herold
    $table->timestamps();

    $table->index(['scope', 'category']);
    $table->fullText('content');             // Volltextsuche
});
```

### Migration: `personal_access_tokens` (Sanctum)

Wird automatisch durch `php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"` erstellt.

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
   → AIService::transcribe(audioPath)
   → Speichert Transkript, Status: TRANSCRIBED
   → Dispatched PreprocessTranscriptJob

4. PreprocessTranscriptJob
   → Laedt Typ-Config + Preprocessing-Prompt
   → AIService::chat(prompt, transcript, metadata)
   → Speichert Title + Body, Status: PROCESSED

5. Nutzer prueft/editiert → Klickt "Ticket erstellen"
   → POST /notes/{id}/send
   → CreateGitHubIssueJob dispatched
   → Status: SENDING

6. CreateGitHubIssueJob
   → GitHubService::createIssue(title, body, labels)
   → Speichert Issue-Nummer + URL, Status: SENT
```

**Queue-Verarbeitung:**
- **Lokal:** Queue-Worker als eigener Docker-Service (dauerhaft laufend)
- **Produktion:** Cron ruft jede Minute `schedule:run` auf, Laravel arbeitet Jobs ab

**Polling fuer Status-Updates:** Inertia `router.reload()` alle 2s waehrend Processing laeuft, oder alternativ Laravel Echo + Pusher/Reverb fuer Echtzeit (kann spaeter ergaenzt werden).

---

## Authentifizierung

Zwei getrennte Auth-Mechanismen fuer Mensch und Agenten:

| Wer | Methode | Guard | Routen |
|-----|---------|-------|--------|
| **Mensch (Browser)** | API-Key + TOTP → Session | `auth` (Session) | `web.php` |
| **Agent (CLI/curl)** | Sanctum Bearer Token | `auth:sanctum` | `api.php` |

### Browser-Auth (Mensch)

**Flow:**
1. Nutzer oeffnet App → Login-Seite
2. Gibt API-Key ein (gespeichert in `.env` als `HEROLD_API_KEY`)
3. Server validiert Key → zeigt TOTP-Feld
4. Nutzer gibt TOTP-Code ein (Authenticator-App)
5. Server validiert TOTP → erstellt Laravel-Session
6. Alle weiteren Requests sind Session-basiert

**Implementierung:**
- **API-Key**: Einfacher String-Vergleich gegen `config('herold.api_key')`
- **TOTP**: Package `laragear/two-factor` 4.0 (Octane-kompatibel)
- **Setup**: Beim ersten Start TOTP-Secret generieren, QR-Code anzeigen
- **Session**: Standard Laravel Session-Auth
- **Middleware**: `VerifyApiKey` auf Login-Route, danach Standard `auth` Middleware

### Agent-Auth (Sanctum Tokens)

**Flow:**
1. Nutzer erstellt Token in Settings-Seite (Name + Scopes)
2. Token wird einmalig angezeigt (danach nur noch Hash in DB)
3. Agent nutzt Token in jedem Request: `Authorization: Bearer herold_...`
4. Sanctum validiert Token + prueft Scopes

**Token-Scopes:**
- `memory:read` – Memories lesen/suchen
- `memory:write` – Memories erstellen/loeschen
- `tickets:read` – Tickets auflisten
- `tickets:status` – Ticket-Status aendern

**Token-Verwaltung (Settings-Seite):**
- Token erstellen (Name + Scopes waehlen)
- Aktive Tokens auflisten (Name, letzter Zugriff, Scopes)
- Token widerrufen

**Agent-Nutzung:**
```bash
# Memories lesen
curl -H "Authorization: Bearer herold_abc123..." \
     http://localhost:8080/api/memories?scope=global&category=learning

# Memory speichern
curl -X POST -H "Authorization: Bearer herold_abc123..." \
     -H "Content-Type: application/json" \
     -d '{"scope":"global","category":"learning","content":"...","source":"claude-code"}' \
     http://localhost:8080/api/memories

# Memory loeschen
curl -X DELETE -H "Authorization: Bearer herold_abc123..." \
     http://localhost:8080/api/memories/{id}

# Tickets lesen
curl -H "Authorization: Bearer herold_abc123..." \
     http://localhost:8080/api/tickets?status=open

# Ticket-Status aendern
curl -X PATCH -H "Authorization: Bearer herold_abc123..." \
     -H "Content-Type: application/json" \
     -d '{"status":"in_progress"}' \
     http://localhost:8080/api/tickets/42/status
```

---

## Routen

### web.php (Browser, Session-Auth)

```php
// Auth
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login/key', [AuthController::class, 'verifyKey']);
Route::post('/login/totp', [AuthController::class, 'verifyTotp']);
Route::post('/logout', [AuthController::class, 'logout']);

// Geschuetzt (Session-Auth)
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

    Route::get('/tickets', [TicketController::class, 'index']);
    Route::patch('/tickets/{number}', [TicketController::class, 'updateStatus']);

    Route::get('/settings', [SettingsController::class, 'index']);
    Route::post('/settings/tokens', [SettingsController::class, 'createToken']);
    Route::delete('/settings/tokens/{token}', [SettingsController::class, 'revokeToken']);

    Route::get('/types', fn () => response()->json(config('herold.types')));
});
```

### api.php (Agenten, Sanctum Token-Auth)

```php
Route::middleware('auth:sanctum')->group(function () {

    // Memory API
    Route::get('/memories', [Api\MemoryController::class, 'index'])       // Suchen/Auflisten
        ->middleware('ability:memory:read');
    Route::post('/memories', [Api\MemoryController::class, 'store'])      // Erstellen
        ->middleware('ability:memory:write');
    Route::delete('/memories/{memory}', [Api\MemoryController::class, 'destroy']) // Loeschen
        ->middleware('ability:memory:write');

    // Ticket API (Proxy zu GitHub Issues)
    Route::get('/tickets', [Api\AgentTicketController::class, 'index'])   // Auflisten
        ->middleware('ability:tickets:read');
    Route::patch('/tickets/{number}/status', [Api\AgentTicketController::class, 'updateStatus'])
        ->middleware('ability:tickets:status');
});
```

---

## Services

### AIService (via laravel/ai)

```php
class AIService
{
    public function transcribe(string $audioPath): string
    // → Laravel AI SDK → OpenAI Whisper, gibt Text zurueck

    public function chat(string $systemPrompt, string $userMessage, float $temperature = 0.3): array
    // → Laravel AI SDK → OpenAI Chat Completion, gibt {title, body} zurueck
    // Provider spaeter austauschbar (Anthropic, Gemini, etc.)
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
    // 3. AIService::chat() aufrufen
    // 4. Ergebnis parsen (Title + Body)
    // 5. VoiceNote aktualisieren
}
```

### MemoryService

```php
class MemoryService
{
    public function search(?string $scope, ?string $category, ?string $query): Collection
    // → Volltextsuche + Filter nach Scope/Category

    public function store(string $scope, string $category, string $content, string $source): Memory
    // → Neuen Memory-Eintrag erstellen

    public function destroy(Memory $memory): void
    // → Memory loeschen
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
- Zahlen: Offene Notizen, gesendete Tickets, Agent-Memories
- Quick-Action: "Neue Aufnahme"
- Letzte 5 Notizen

### Settings/Index.vue
- Dark/Light Theme Toggle
- **Agent-Tokens Sektion:**
  - Token erstellen: Name + Scopes (Checkboxen) → Token wird einmalig angezeigt
  - Aktive Tokens: Name, Scopes, letzter Zugriff, erstellt am
  - Token widerrufen (mit Bestaetigung)
- GitHub-Repo Info (Anzeige)
- TOTP-Status

---

## Deployment

### Umgebungen

DEV und PROD sind absichtlich identisch aufgebaut (siehe [ADR-002](../adr/002-dev-prod-parity.md)).

| Aspekt | DEV (Docker) | PROD (Shared Hosting) |
|--------|-------------|----------------------|
| PHP | 8.5 (php:8.5-apache) | 8.5 (nativ) |
| Webserver | Apache (im Container) | Apache (Hosting) |
| Queue | Cron → `schedule:run` | Cron → `schedule:run` |
| SQLite | Docker Volume | Datei auf Server |
| `.htaccess` | Identisch | Identisch |

### Produktion (Shared Hosting)

- PHP laeuft nativ auf dem Server (kein Docker)
- Deployment via FTP-Upload
- Kein Shell-Zugang
- Cron-Job verfuegbar
- HTTPS via Hosting-Provider (noetig fuer MediaRecorder API)

**Queue in Produktion:**
Cron-Job ruft jede Minute `php artisan schedule:run` auf. Laravel Scheduler arbeitet
die Queue ab (database driver). Kein dauerhaft laufender Worker-Prozess noetig.

```
# Cron-Eintrag auf dem Server
* * * * * cd /path/to/herold && php artisan schedule:run >> /dev/null 2>&1
```

**Deployment-Workflow:**
1. Lokal `npm run build` (kompiliert Vue/TS → `public/build/`)
2. FTP-Upload aller Dateien (inkl. `public/build/`, `vendor/`, Migrations)
3. Einmalig: Migration via Web-Route oder Deployment-Script

**Produktions-.env:**
```bash
APP_ENV=production
APP_DEBUG=false
QUEUE_CONNECTION=database    # Jobs in SQLite, Cron arbeitet ab
```

### Auth-Recovery (bei Verlust von API-Key oder TOTP)

Da kein Shell-Zugang in Produktion vorhanden ist, nutzen wir einen datei-basierten
Recovery-Mechanismus via FTP:

1. Nutzer laedt Datei `.herold-recovery` per FTP in das Projekt-Root hoch
2. Nutzer besucht `/recovery` im Browser
3. App prueft ob `.herold-recovery` existiert → zeigt Reset-Formular
4. Neuer API-Key wird generiert und angezeigt
5. Neues TOTP-Secret wird generiert, QR-Code angezeigt
6. Nutzer scannt QR-Code mit Authenticator-App
7. Nutzer loescht `.herold-recovery` per FTP wieder

Ohne die Datei im Dateisystem ist `/recovery` nicht erreichbar (404).

---

## Docker Setup (lokale Entwicklung)

Kein lokales PHP/Composer/Node noetig. Alles laeuft in Docker.
Setup ist bewusst identisch zu Produktion (Apache + Cron, kein nginx, kein dauerhafter Worker).

### docker-compose.yml Services

```yaml
services:
  app:                            # PHP 8.5 + Apache (wie Prod)
    build: .
    ports:
      - "8080:80"
    volumes:
      - .:/var/www/html           # Code-Mount fuer Live-Entwicklung
      - sqlite_data:/var/www/html/database  # SQLite Persistenz
    env_file: .env

  cron:                           # Cron → schedule:run (wie Prod)
    build: .
    command: crond -f -l 2
    volumes:
      - .:/var/www/html
      - sqlite_data:/var/www/html/database
    env_file: .env

  node:                           # Vite Dev Server (nur Entwicklung)
    image: node:24-alpine
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

### Dockerfile

```dockerfile
FROM php:8.5-apache

# Apache mod_rewrite (fuer Laravel .htaccess)
RUN a2enmod rewrite

# PHP Extensions
RUN apt-get update && apt-get install -y libsqlite3-dev cron \
    && docker-php-ext-install pdo_sqlite pcntl \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2.9 /usr/bin/composer /usr/bin/composer

# Apache DocumentRoot auf public/ setzen
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf

# Crontab fuer Laravel Scheduler
RUN echo "* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1" \
    > /etc/cron.d/herold-scheduler \
    && chmod 0644 /etc/cron.d/herold-scheduler

WORKDIR /var/www/html
COPY . .
RUN composer install --no-dev --optimize-autoloader
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

# Queue-Jobs werden durch Cron-Service verarbeitet (jede Minute)
# Logs: docker compose logs -f cron

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
1. Laravel 13 Projekt erstellen (via `composer create-project` im Container)
2. Dockerfile (php:8.5-apache) + docker-compose.yml + Cron-Setup erstellen
3. Inertia.js 3 + Vue 3.5 + TypeScript 6 einrichten
4. Vuetify 4 installieren und konfigurieren
5. SQLite konfigurieren (Volume fuer Persistenz)
6. Basis-Layout (App-Shell, Navigation)
7. `docker compose up` → App laeuft

### Phase 2: Datenmodell + Auth
8. Migration `voice_notes` + `memories` erstellen
9. VoiceNote Model + NoteStatus Enum
10. Memory Model + MemoryScope/MemoryCategory Enums
11. User Model mit Sanctum HasApiTokens
12. Browser-Auth: API-Key Middleware + TOTP Setup
13. Agent-Auth: Sanctum installieren + konfigurieren
14. Login-Seite (Vue)
15. Settings-Seite: Token-Verwaltung (erstellen, auflisten, widerrufen)

### Phase 3: Audio-Aufnahme
16. `useAudioRecorder` Composable (MediaRecorder API)
17. AudioRecorder Vue-Komponente (UI + Waveform)
18. TypeSelector Komponente
19. Recording/Create.vue Seite
20. VoiceNoteController::store (Audio-Upload)

### Phase 4: Verarbeitung
21. AIService via laravel/ai (Whisper + Chat)
22. config/herold.php mit Typ-Definitionen + Prompts
23. PreprocessingService
24. TranscribeAudioJob + PreprocessTranscriptJob
25. Queue: Cron-basiert via Laravel Scheduler (identisch in DEV und PROD)
26. Notes/Show.vue mit Status-Polling

### Phase 5: Ticket-Erstellung
27. GitHubService
28. CreateGitHubIssueJob
29. Ticket-Body Templates (mit herold:meta Kommentar)
30. TicketController (erstellen + Status aendern)

### Phase 6: Agent-API (Memory + Tickets)
31. MemoryService + MemoryController (CRUD + Suche)
32. AgentTicketController (Tickets lesen, Status aendern)
33. Sanctum Ability-Middleware auf API-Routen
34. Testen mit curl-Aufrufen

### Phase 7: UI vervollstaendigen
35. Notes/Index.vue (History)
36. Dashboard.vue (inkl. Memory-Statistiken)
37. Dark/Light Theme

### Phase 8: Recovery + Deployment
38. Recovery-Route + `.herold-recovery`-Datei-Pruefung
39. Artisan-Command `herold:reset-auth` (fuer lokale Entwicklung)
40. Produktions-Deployment dokumentieren (FTP-Workflow)

### Phase 9: Polish
41. Error-Handling (fehlgeschlagene API-Calls, Retry-Logik in Jobs)
42. Rate Limiting + Security Headers
43. .env.example mit allen Variablen dokumentieren
44. Spec aktualisieren

---

## Verifikation

- **Docker**: `docker compose up -d` → alle Services laufen, App erreichbar unter localhost:8080
- **Audio-Aufnahme**: Im Browser aufnehmen, pruefen ob Datei in storage landet
- **Transkription**: Audio hochladen, Queue-Worker-Logs pruefen (`docker compose logs -f queue`), Transkript pruefen
- **Vorverarbeitung**: Transkript verarbeiten lassen, strukturiertes Ergebnis pruefen
- **Ticket**: Issue in GitHub pruefen (Labels, Body-Format, Meta-Kommentar)
- **Status-Lifecycle**: Labels manuell via `gh` aendern, pruefen ob App Status korrekt anzeigt
- **Browser-Auth**: Ohne Key → abgewiesen; mit Key ohne TOTP → abgewiesen; mit beidem → Zugang
- **Agent-Auth**: Ohne Token → 401; mit Token ohne Scope → 403; mit korrektem Scope → Zugang
- **Memory-API**: curl-Aufrufe zum Erstellen, Suchen und Loeschen von Memories
- **Token-Verwaltung**: Token erstellen in Settings, widerrufen, pruefen ob Agent abgewiesen wird
- **Typ-Erweiterung**: Neuen Typ in config/herold.php eintragen, pruefen ob UI + Processing funktioniert
- **Persistenz**: `docker compose down && docker compose up -d` → SQLite-Daten + Memories bleiben erhalten
- **Recovery**: `.herold-recovery` per FTP hochladen → /recovery erreichbar → Auth zuruecksetzen → Datei loeschen → /recovery wieder 404
- **Cron-Queue**: `php artisan schedule:run` verarbeitet ausstehende Jobs korrekt
