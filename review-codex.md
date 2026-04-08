# Critical Review (Codex)

## Scope

- Gepruefte Dateien:
  - `spec/herold-spec.prompt.md`
  - `spec/non-functional-requirements.md`
- Fokus: Konsistenz, Umsetzbarkeit, Security-Aspekte.

## Findings und Fix-Vorschlaege

### 1) Queue-Verarbeitung weiterhin unvollstaendig spezifiziert (High)

**Evidence**
- `spec/herold-spec.prompt.md:526`
- `spec/herold-spec.prompt.md:761`

**Problem**
- Die Spec sagt, `schedule:run` arbeite die Queue ab. Das ist so nicht ausreichend: Der Scheduler startet nur geplante Tasks; ohne geplanten Worker (`queue:work --stop-when-empty`) bleiben Jobs liegen.

**Fix-Vorschlag**
- Queue-Abschnitte auf folgendes Modell umstellen:
  - Cron triggert jede Minute `php artisan schedule:run`.
  - Scheduler startet explizit einen kurzlebigen Worker.

**Suggested text snippet**
```php
Schedule::command('queue:work database --stop-when-empty --tries=3 --max-time=50')
    ->everyMinute()
    ->withoutOverlapping();
```

**Zusatz**
- Verifikation auf End-to-End pruefen: Job von `RECORDED`/`TRANSCRIBING` bis `SENT` laeuft ohne manuellen Worker.

---

### 2) Auto-Migration in `AppServiceProvider::boot()` ist riskant (High)

**Evidence**
- `spec/herold-spec.prompt.md:537`

**Problem**
- Migrationen im Request-Lebenszyklus (`boot()`) bergen Risiken (Race Conditions, lange Requests, teilweise migrierte Zustande, ungeplante Downtime).

**Fix-Vorschlag**
- `Artisan::call('migrate')` aus `boot()` entfernen.
- Stattdessen kontrollierter Deploy-Schritt:
  - bevorzugt: externes Deployment-Skript,
  - alternativ (wenn kein Shell-Zugang): stark abgesicherter One-Time-Migrationsendpoint (signierter Token, TTL, One-Time-Nonce, Throttle, Audit-Log, optional IP-Allowlist).

**Suggested text snippet**
```md
Migrationen werden nicht im HTTP-Request-Pfad ausgefuehrt.
Deployments fuehren Migrationen in einem dedizierten, kontrollierten Schritt aus.
Bei Shared Hosting ohne Shell wird ein einmaliger, signierter Deploy-Endpoint mit TTL,
Throttle und Audit-Logging verwendet.
```

---

### 3) Recovery-Flow ohne Token-Expiry (TTL) (Medium)

**Evidence**
- `spec/herold-spec.prompt.md:551`
- `spec/herold-spec.prompt.md:563`

**Problem**
- Recovery wurde gehaertet, aber ohne Ablaufzeit ist ein kompromittierter Recovery-Token potenziell unbefristet nutzbar.

**Fix-Vorschlag**
- TTL in Recovery-Mechanismus aufnehmen (z. B. 10 Minuten).
- Dateiformat um `expires_at` erweitern, bei Ablauf hard-fail.
- Nach Erfolg Datei serverseitig loeschen (bereits vorhanden) und Event loggen.

**Suggested text snippet**
```md
Die Datei `.herold-recovery` enthaelt `token` und `expires_at` (Unix-Timestamp).
Recovery ist nur gueltig, wenn `now <= expires_at` (max. 10 Minuten).
Abgelaufene oder ungueltige Tokens liefern 403 und werden geloggt.
```

---

### 4) Widerspruch: config-driven types vs. `MessageType` Enum (Medium)

**Evidence**
- `spec/herold-spec.prompt.md:89`
- `spec/non-functional-requirements.md:154`

**Problem**
- NFR fordert neue Typen ohne Codeaenderung; ein harter Enum widerspricht diesem Ziel.

**Fix-Vorschlag**
- Entweder:
  1. `MessageType` Enum entfernen (voll konfigurationsgetrieben), oder
  2. klar dokumentieren, dass Enum nur Core-Typen repraesentiert und dynamic types nicht blockiert.

**Empfehlung**
- Option 1 (Enum entfernen), damit NFR-14a-01 strikt eingehalten wird.

---

### 5) Veralteter Dateipfad in Projektstruktur (Low)

**Evidence**
- `spec/herold-spec.prompt.md:54`

**Problem**
- Es steht `spec/SPEC.md`, die aktuelle Spec-Datei ist jedoch `spec/herold-spec.prompt.md`.

**Fix-Vorschlag**
- Pfad in der Struktur korrigieren.

---

### 6) NFR zu Log-Redaction technisch missverstaendlich formuliert (Low)

**Evidence**
- `spec/non-functional-requirements.md:254`

**Problem**
- Formulierung "Laravel built-in hidden configuration redacts ..." ist missverstaendlich; `hidden` betrifft Model-Serialisierung, nicht allgemeine Logs.

**Fix-Vorschlag**
- Als konkrete Logging-Anforderung formulieren:
  - Secrets duerfen nie geloggt werden,
  - dedizierter Redaction-Mechanismus/Monolog-Processor fuer bekannte Schluessel,
  - keine Transcript-Inhalte im Log.

**Suggested text snippet**
```md
Application logs must pass through a redaction layer that masks known secret keys
(`APP_KEY`, `HEROLD_API_KEY`, `GITHUB_TOKEN`, `OPENAI_API_KEY`, Bearer tokens).
Transcript content must never be logged.
```

---

## Kurzfazit

- Viele wichtige Security-Verbesserungen sind bereits umgesetzt (Login-Haertung, `/types`-Filter, Recovery-Throttle, Cron-Korrekturen).
- Vor Implementierungsstart sollten vor allem die beiden High-Punkte (Queue-Scheduler-Realitaet, Migration aus `boot()` entfernen) verbindlich bereinigt werden.
