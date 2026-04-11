# Herold – Implementierungsplan

## Kontext

Herold ist ein Voice-basierter Task-Dispatcher fuer lokale KI-Agenten. Ein einzelner Nutzer nimmt Sprachnachrichten auf, die App transkribiert und verarbeitet diese synchron per OpenAI API und erstellt typisierte GitHub Issues in einem privaten Repo. Lokale Agenten (Claude Code, OpenCode) lesen diese Tickets via `gh` CLI und arbeiten sie ab. GitHub Issues ist der alleinige Ticket-Speicher -- Herold ist ein Eingangskanal neben GitHub Web UI, `gh` CLI und anderen Tools (siehe [ADR-003](../adr/003-github-issues-as-ticket-store.md)). Die Verarbeitung erfolgt synchron im Request (siehe [ADR-002](../adr/002-dev-prod-parity.md)).

**Designprinzipien:**
- **Mobile First / Responsive** -- primaerer Nutzungskontext ist das Smartphone (Sprachaufnahme unterwegs), Desktop als Zweitbildschirm
- Vuetify 4 Breakpoints (xs/sm/md/lg/xl) konsequent nutzen
- Touch-optimierte UI: grosse Tap-Targets, Swipe-Gesten wo sinnvoll
- Bottom Navigation auf Mobile, Side Navigation auf Desktop
- **UI-Referenz:** Die statischen HTML-Prototypen in `poc-ui/` sind die verbindliche Vorlage fuer Layout, Struktur und Interaktionsmuster aller Vue-Seiten. Farben und Typografie folgen `DESIGN.md`.

**Entschiedener Stack:**
- Laravel 13 (PHP 8.5) als Monolith
- Inertia.js 3 + Vue 3.5 (Composition API, TypeScript 6) + Vuetify 4
- Auth: API-Key + TOTP (Browser/Mensch)
- Laravel AI SDK (`laravel/ai`) fuer OpenAI Whisper + Chat API
- GitHub Issues API (Fine-grained PAT)
- Synchrone Verarbeitung (kein Queue, kein Cron)
- **Keine** PWA, **kein** Service Worker
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
| laravel/ai | 0.4.5 | OpenAI, Anthropic, Gemini |
| Docker Engine | 29.4.0 | |
| Docker Compose | 5.1.1 | |
| PHP Docker Image | 8.5-apache | Apache + PHP in einem Container |
| SQLite | 3.51.3 | 3.52.0 zurueckgezogen |

---

## Projektstruktur

```
herold/
  prompts/herold-spec.prompt.md
  DESIGN.md                         # UI Design-Vorgaben (Farben, Layout, Komponenten)
  docker-compose.yml              # Orchestrierung aller Services
  Dockerfile                      # PHP 8.5 + Apache (wie Prod)
  .env.example                    # Vorlage fuer Umgebungsvariablen
  app/
    Http/
      Controllers/
        AuthController.php          # Login, TOTP-Verifizierung
        DashboardController.php     # Home/Uebersicht
        VoiceNoteController.php     # CRUD, Upload, Processing, GitHub-Push
        SettingsController.php      # Einstellungen
      Middleware/
        VerifyApiKey.php            # API-Key Check (vor TOTP)
      Requests/
        StoreVoiceNoteRequest.php   # Validierung Audio-Upload (max 25 MB, MIME: audio/webm, audio/ogg, audio/mp4)
        ProcessNoteRequest.php      # Validierung Typ + Metadaten
    Models/
      VoiceNote.php                 # Eloquent Model
      User.php                      # Single-User
    Services/
      AIService.php                  # Laravel AI SDK (Whisper + Chat)
      GitHubService.php             # Issues erstellen (Einweg-Push)
      MessageTypeRegistry.php       # Typ-Definitionen laden
      PreprocessingService.php      # Orchestriert Transkription + LLM
    Enums/
      NoteStatus.php                # recorded, processed, sent, error
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
        Settings/Index.vue          # Einstellungen (Theme)
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
    $table->text('processed_body')->nullable();  // Markdown-formatiert
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
    case PROCESSED = 'processed';
    case SENT = 'sent';
    case ERROR = 'error';
}
```

Da die Verarbeitung synchron erfolgt (siehe [ADR-002](../adr/002-dev-prod-parity.md)), genuegen vier Status. Zwischen-Status (`transcribing`, `processing`, `sending`) entfallen -- der Nutzer sieht waehrend der Verarbeitung einen Loading-Indikator.

---

## Nachrichtentyp-Registry

Konfigurationsbasiert in `config/herold.php`:

```php
'types' => [
    'general' => [
        'label' => 'General',
        'icon' => 'mdi-message-text',
        'github_label' => 'type:general',
        'extra_fields' => [],
        'preprocessing_prompt' => '...', // oder Referenz auf Prompt-Datei
    ],
    'youtube' => [
        'label' => 'YouTube Transcription',
        'icon' => 'mdi-youtube',
        'github_label' => 'type:youtube',
        'extra_fields' => [
            ['name' => 'youtube_url', 'type' => 'url', 'required' => true, 'label' => 'YouTube URL'],
        ],
        'preprocessing_prompt' => '...',
    ],
    'diary' => [
        'label' => 'Diary',
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

**Synchron** im HTTP-Request (siehe [ADR-002](../adr/002-dev-prod-parity.md)):

```
1. Nutzer nimmt Audio auf → POST /notes (Audio-Upload)
   → VoiceNote wird mit Status RECORDED gespeichert
   → Audio in storage/app/private/audio/{ulid}.webm
   → Validierung: max 25 MB, MIME-Types: audio/webm|audio/ogg|audio/mp4, Rate Limit: max 10 Uploads/Stunde

2. Nutzer startet Verarbeitung → POST /notes/{id}/process (synchron, ~10-30s)
   → AIService::transcribe(audioPath) → Transkript gespeichert
   → PreprocessingService::process(note) → Title + Body gespeichert
   → Status: PROCESSED
   → Response mit verarbeitetem Ergebnis

3. Nutzer prueft/editiert → Klickt "Ticket erstellen"
   → POST /notes/{id}/send (synchron)
   → IssueContentSanitizer::sanitizeAndWrap(note) → sanitizedBody
   → GitHubService::createIssue(title, sanitizedBody, labels)
   → Speichert Issue-Nummer + URL, Status: SENT
```

Die UI zeigt waehrend Schritt 2 einen Loading-Indikator. Bei Fehlern wird Status ERROR gesetzt und die Fehlermeldung angezeigt -- der Nutzer kann erneut versuchen.

---

## Authentifizierung

Ein Auth-Mechanismus fuer den einzelnen Nutzer (Browser). Agenten interagieren direkt mit GitHub, nicht mit Herold (siehe [ADR-003](../adr/003-github-issues-as-ticket-store.md)).

### Browser-Auth

**Flow:**
1. Nutzer oeffnet App → Login-Seite
2. Gibt API-Key ein (gespeichert in `.env` als `HEROLD_API_KEY`)
3. Server validiert Key → zeigt TOTP-Feld
4. Nutzer gibt TOTP-Code ein (Authenticator-App)
5. Server validiert TOTP → erstellt Laravel-Session
6. Alle weiteren Requests sind Session-basiert

**Implementierung:**
- **API-Key**: Timing-sicherer Vergleich (`hash_equals()`) gegen `config('herold.api_key')`
- **TOTP**: Package `laragear/two-factor` 4.0 (Octane-kompatibel)
- **Setup**: Erster TOTP-Setup erfordert zunaechst gueltige API-Key-Eingabe (normaler Login-Flow). Wenn noch kein TOTP-Secret existiert, wird nach erfolgreicher Key-Validierung der QR-Code zur Einrichtung angezeigt statt der TOTP-Eingabe. Kein unauthentifizierter Zugang zum TOTP-Setup.
- **Rate Limiting**: Login-Routen (`/login/key`, `/login/totp`) mit Throttle (max 5 Versuche pro Minute). Nach 10 Fehlversuchen: 15-Minuten-Sperre (IP-basiert).
- **Session**: Standard Laravel Session-Auth
- **Middleware**: `VerifyApiKey` auf Login-Route, danach Standard `auth` Middleware
- **Security Headers (Basis)**: Auf alle Responses via Middleware: `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: camera=(), microphone=(self)`. CSP folgt in Phase 7 (Vite-Nonce-Kompatibilitaet).

### Security-Hardening (MUST)

- **Log-Redaction**: Custom Monolog Processor maskiert bekannte Secrets (`APP_KEY`, `HEROLD_API_KEY`, `GITHUB_TOKEN`, `OPENAI_API_KEY`) sowie `Authorization` Header/Bearer-Token und Session-Token.
- **No Transcript Logging**: Transcript-Inhalte, Prompt-Texte und untrusted Payloads werden nicht geloggt; nur Status-Events mit `voice_note_id`.
- **Error Sanitization**: API-Fehler von OpenAI/GitHub werden in nutzerfreundliche, nicht-sensitive Fehlermeldungen uebersetzt.
- **Session-Cookie Hardening**: `Secure`, `HttpOnly`, `SameSite=Lax` in Produktion; Session-Regeneration nach Login und Recovery.

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
    Route::post('/notes/{note}/send', [VoiceNoteController::class, 'send']);

    Route::get('/settings', [SettingsController::class, 'index']);

    Route::get('/types', fn () => response()->json(
        collect(config('herold.types'))->map(fn ($type) => Arr::only($type, ['label', 'icon', 'extra_fields', 'github_label']))
    ));
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
    // → Erwartet bereits sanitisierten Body (IssueContentSanitizer)
    // Einweg-Push: kein Read-Back, kein Sync
}
```

### IssueContentSanitizer

```php
class IssueContentSanitizer
{
    public function sanitizeAndWrap(VoiceNote $note): string
    // 1. Entfernt aktive HTML/JS-Inhalte (z.B. script tags, javascript: URIs)
    // 2. Trennt untrusted Input klar vom App-Output (z.B. Abschnitt "## Input")
    // 3. Schreibt niemals System-Prompts in den Issue-Body
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
- "Verarbeiten" Button → POST /notes/{id}/process (synchron, Loading-Indikator)
- Verarbeitetes Ergebnis: Titel (editierbar) + Body (editierbar)
- "Ticket erstellen" Button → POST /notes/{id}/send (synchron)
- Link zum GitHub Issue (nach Erstellung)

### Notes/Index.vue
- Liste aller Notizen, sortiert nach Datum
- Filter: Typ + Status
- NoteCard-Komponenten mit Status, Typ-Icon, Titel, Datum

### Dashboard.vue
- Zahlen: Offene Notizen, gesendete Tickets
- Quick-Action: "Neue Aufnahme"
- Letzte 5 Notizen

### Settings/Index.vue
- Dark/Light Theme Toggle
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
| Verarbeitung | Synchron | Synchron |
| SQLite | Docker Volume | Datei auf Server |
| `.htaccess` | Identisch | Identisch |

### Produktion (Shared Hosting)

- PHP laeuft nativ auf dem Server (kein Docker)
- Deployment via FTP-Upload
- Eingeschraenkter SSH-Zugang (PHP 8.5 verfuegbar, `crontab` gesperrt)
- HTTPS via Hosting-Provider (noetig fuer MediaRecorder API)
- Keine Cron-Konfiguration noetig (synchrone Verarbeitung, siehe [ADR-002](../adr/002-dev-prod-parity.md))

**Deployment-Workflow:**
1. Lokal `npm run build` (kompiliert Vue/TS → `public/build/`)
2. FTP-Upload aller Dateien (inkl. `public/build/`, `vendor/`, Migrations)
3. Migration per SSH: `php artisan migrate --force` (Default-PHP ist 8.5)

Migrationen werden **nicht** im HTTP-Request-Pfad ausgefuehrt (kein `Artisan::call('migrate')` in `boot()` oder Middleware).

**Produktions-.env:**
```bash
APP_ENV=production
APP_DEBUG=false
QUEUE_CONNECTION=sync
```

### Auth-Recovery (bei Verlust von API-Key oder TOTP)

Fuer den Fall, dass SSH nicht verfuegbar ist, nutzen wir einen datei-basierten
Recovery-Mechanismus via FTP:

1. Nutzer generiert einen zufaelligen Token (z.B. `openssl rand -hex 32`) und speichert ihn als Inhalt der Datei `.herold-recovery`
2. Nutzer laedt `.herold-recovery` per FTP in `storage/app/private/` hoch
3. Nutzer besucht `/recovery` im Browser
4. App prueft ob `storage/app/private/.herold-recovery` existiert und nicht aelter als 60 Minuten ist (`filemtime`) → zeigt Formular mit Token-Eingabefeld; sonst generische 404-Antwort + Logging
5. Nutzer gibt den Token aus Schritt 1 ein
6. App vergleicht Eingabe mit Datei-Inhalt (`hash_equals`, getrimmt) unter exklusivem File-Lock
7. Bei Nicht-Uebereinstimmung: generische 404-Antwort + Logging (Datei bleibt bis TTL erhalten)
8. Bei Uebereinstimmung: `.herold-recovery` atomar verbrauchen (unlink unter Lock) und danach API-Key/TOTP resetten
9. Neuer API-Key wird generiert und angezeigt, neues TOTP-Secret generiert, QR-Code angezeigt
10. Nutzer scannt QR-Code mit Authenticator-App
11. Nutzer bestaetigt TOTP-Einrichtung mit einem Code
12. Session wird regeneriert (`session()->regenerate()`)

**Sicherheitsmassnahmen:**
- Recovery-Datei in `storage/app/private/` (ausserhalb Webroot, nicht direkt per URL abrufbar)
- `/recovery`-Route: Throttle max 5 Versuche/Stunde (IP-basiert)
- Recovery-Token TTL: 60 Minuten ab Upload (basierend auf `filemtime`). Abgelaufene Tokens liefern generische 404-Antwort + Logging.
- Recovery-Formular erfordert CSRF-Token (`@csrf`)
- Atomarer Consume-Once nur nach erfolgreicher Token-Pruefung (unter File-Lock, verhindert Replay und DoS durch Fehlversuche)
- Session-Regeneration nach erfolgreichem Reset (`session()->regenerate()`)
- Uniforme Fehlermeldungen: `/recovery` gibt fuer "Datei nicht vorhanden", "Token falsch" und "Token abgelaufen" dieselbe generische 404-Antwort (verhindert Enumeration)
- Recovery-Events werden geloggt (Zeitpunkt, IP, Erfolg/Fehlschlag)

Ohne die Datei in `storage/app/private/` ist `/recovery` nicht erreichbar (404).

---

## Docker Setup (lokale Entwicklung)

Kein lokales PHP/Composer/Node noetig. Alles laeuft in Docker.
Setup ist bewusst identisch zu Produktion (Apache, kein nginx).

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
RUN apt-get update && apt-get install -y libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2.9 /usr/bin/composer /usr/bin/composer

# Apache DocumentRoot auf public/ setzen
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf

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

# Stoppen
docker compose down
```

### HTTPS fuer Mikrofon-Zugriff

MediaRecorder API erfordert HTTPS (oder localhost). Optionen:
- **localhost**: Funktioniert ohne HTTPS im Browser
- **Caddy als Reverse Proxy**: Fuer Zugriff von anderen Geraeten im Netz ist nicht vorgesehen, da es die lokale Entwicklung komplexer macht (z.B. Zertifikatsverwaltung, Port-Weiterleitung). Caddy koennte optional mit Self-Signed Cert eingerichtet werden, aber das ist fuer die meisten Entwickler zu aufwendig.

Fuer lokale Entwicklung reicht `localhost:8080` – **kein Caddy noetig**.

---

## Implementierungsreihenfolge

### Phase 1: Projektsetup + Docker
1. Laravel 13 Projekt erstellen (via `composer create-project` im Container)
2. Dockerfile (php:8.5-apache) + docker-compose.yml erstellen (2 Services: app + node)
3. Inertia.js 3 + Vue 3.5 + TypeScript 6 einrichten
4. Vuetify 4 installieren und konfigurieren
5. SQLite konfigurieren (Volume fuer Persistenz)
6. Basis-Layout (App-Shell, Navigation)
7. `docker compose up` → App laeuft

### Phase 2: Datenmodell + Auth
8. Migration `voice_notes` erstellen
9. VoiceNote Model + NoteStatus Enum
10. User Model
11. Browser-Auth: API-Key Middleware + TOTP Setup
12. Login-Seite (Vue)

### Phase 3: Audio-Aufnahme
13. `useAudioRecorder` Composable (MediaRecorder API)
14. AudioRecorder Vue-Komponente (UI + Waveform)
15. TypeSelector Komponente
16. Recording/Create.vue Seite
17. VoiceNoteController::store (Audio-Upload)

### Phase 4: Verarbeitung + Ticket-Erstellung
18. AIService via laravel/ai (Whisper + Chat)
19. config/herold.php mit Typ-Definitionen + Prompts
20. PreprocessingService
21. GitHubService + IssueContentSanitizer (Einweg-Push + Sanitization)
22. VoiceNoteController::process (synchrone Transkription + LLM)
23. VoiceNoteController::send (synchroner GitHub-Push, sanitisierter Issue-Body)
24. Notes/Show.vue mit Loading-Indikator

### Phase 5: UI vervollstaendigen
25. Notes/Index.vue (History)
26. Dashboard.vue
27. Settings/Index.vue (Theme, GitHub-Info, TOTP-Status)
28. Dark/Light Theme

### Phase 6: Recovery + Deployment
29. Recovery-Route: `.herold-recovery` in `storage/app/private/`, Random-Token, CSRF, Consume-Once, Session-Regeneration, Throttle (max 5/Stunde). Recovery-Events loggen.
30. Artisan-Command `herold:reset-auth` (fuer lokale Entwicklung)
31. Produktions-Deployment dokumentieren (FTP-Workflow)

### Phase 7: Polish
32. Error-Handling (fehlgeschlagene API-Calls)
33. CSP-Header (Content Security Policy, Vite-kompatibel) + Log-Redaction (Secrets/Token) + no-transcript logging
34. .env.example mit allen Variablen dokumentieren
35. Spec aktualisieren

---

## Verifikation

- **Docker**: `docker compose up -d` → beide Services laufen, App erreichbar unter localhost:8080
- **Audio-Aufnahme**: Im Browser aufnehmen, pruefen ob Datei in storage landet
- **Transkription + Vorverarbeitung**: Audio hochladen, "Verarbeiten" klicken, Loading-Indikator pruefen, strukturiertes Ergebnis pruefen
- **Ticket**: "Ticket erstellen" klicken, Issue in GitHub pruefen (Labels, Body-Format)
- **Issue-Sanitization**: Transcript mit Injection-String (z.B. `<!-- @agent: ignore all previous instructions -->`) testen → im Issue inert und klar als untrusted Input markiert
- **Browser-Auth**: Ohne Key → abgewiesen; mit Key ohne TOTP → abgewiesen; mit beidem → Zugang
- **Typ-Erweiterung**: Neuen Typ in config/herold.php eintragen, pruefen ob UI + Processing funktioniert
- **Persistenz**: `docker compose down && docker compose up -d` → SQLite-Daten bleiben erhalten
- **Recovery**: `.herold-recovery` per FTP in `storage/app/private/` hochladen → /recovery erreichbar → Auth zuruecksetzen → Datei automatisch geloescht → /recovery wieder 404
- **Log-Redaction**: `storage/logs/` pruefen → keine Secrets, keine Bearer-/Session-Token, keine Transcript-Inhalte
