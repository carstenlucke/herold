# Critical Review: Herold Spec (Codex)

Datum: 2026-04-09

## Kontext

Geprueft wurden:

- `spec/herold-spec.prompt.md`
- `spec/constraints.md`
- `spec/non-functional-requirements.md`

Prueffokus: Sicherheitsaspekte, innere Konsistenz, Widerspruchsfreiheit.

## Gesamturteil

**Needs revision first** - Die Spezifikation ist in der aktuellen Form nicht konsistent genug fuer eine sichere, eindeutige Implementierung.

## Priorisierte Findings

### F-01 (Critical): Architekturkonflikt Sync vs Async

- Beobachtung: In der Implementierungs-Spec ist die Pipeline synchron im Request definiert. In den NFRs ist asynchrone Queue-Verarbeitung mit Cron-Pfad vorgeschrieben.
- Evidenz:
  - `spec/herold-spec.prompt.md:19`
  - `spec/herold-spec.prompt.md:189`
  - `spec/herold-spec.prompt.md:197`
  - `spec/non-functional-requirements.md:65`
  - `spec/non-functional-requirements.md:70`
  - `spec/non-functional-requirements.md:73`
- Risiko:
  - Uneinheitliche Implementierungen
  - Unerreichbare Fit Criteria (UI-Reaktionszeit, Polling)
  - Sicherheits-/Betriebskontrollen fuer Jobs koennen fehlen
- Empfehlung:
  - Genau **eine** Zielarchitektur verbindlich festlegen (sync oder async) und alle Specs darauf normieren.

### F-02 (Critical): Retry-/Statusmodell widerspricht sich

- Beobachtung: Keine Zwischenstatus in der Spec, aber NFR verlangt asynchrone Statusanzeige und automatische Retries.
- Evidenz:
  - `spec/herold-spec.prompt.md:147`
  - `spec/herold-spec.prompt.md:209`
  - `spec/non-functional-requirements.md:73`
  - `spec/non-functional-requirements.md:86`
- Risiko:
  - Fehlerbehandlung wird inkonsistent
  - Monitoring und UX-Feedback unklar
- Empfehlung:
  - Status-Lifecycle und Retry-Strategie explizit definieren (z. B. queued/transcribing/processing/sending/error/sent).

### F-03 (Critical): Agent API gefordert, aber in Spec ausgeschlossen

- Beobachtung: Die Implementierungs-Spec sagt, Agenten interagieren nicht mit Herold. Constraints/NFR fordern jedoch explizit Agent API plus tokenbasierte Scopes.
- Evidenz:
  - `spec/herold-spec.prompt.md:215`
  - `spec/herold-spec.prompt.md:240`
  - `spec/constraints.md:94`
  - `spec/non-functional-requirements.md:138`
  - `spec/non-functional-requirements.md:199`
- Risiko:
  - Security-Anforderungen (Least Privilege via Scope) nicht implementierbar
  - Interoperabilitaetsziele verfehlt
- Empfehlung:
  - Entweder Agent API mit Sanctum sauber spezifizieren, oder entsprechende Constraints/NFR formal entfernen.

### F-04 (High): Produktionsannahmen widersprechen sich (SSH/CLI/Cron)

- Beobachtung: Eine Stelle fordert SSH-basierte Migrationen, andere schliessen Shell/CLI in Produktion aus. Gleichzeitig sagt die Spec "kein Cron noetig", waehrend NFR Cron voraussetzt.
- Evidenz:
  - `spec/herold-spec.prompt.md:368`
  - `spec/herold-spec.prompt.md:370`
  - `spec/herold-spec.prompt.md:375`
  - `spec/constraints.md:46`
  - `spec/constraints.md:50`
  - `spec/non-functional-requirements.md:121`
  - `spec/non-functional-requirements.md:125`
- Risiko:
  - Deployment- und Betriebsfehler
  - Nicht reproduzierbares Sicherheitsniveau zwischen Umgebungen
- Empfehlung:
  - Ein einziges, konsistentes Betriebsmodell dokumentieren (mit oder ohne SSH/CLI, mit oder ohne Cron).

### F-05 (High, Security): Recovery-Flow unterdefiniert

- Beobachtung: Recovery basiert auf `.herold-recovery` im Projekt-Root. Absicherung gegen direkte Web-Exposition und atomaren Single-Use ist nicht klar genug beschrieben.
- Evidenz:
  - `spec/herold-spec.prompt.md:391`
  - `spec/herold-spec.prompt.md:394`
  - `spec/herold-spec.prompt.md:399`
  - `spec/herold-spec.prompt.md:403`
- Risiko:
  - Unbeabsichtigte Exposition der Recovery-Datei
  - Wiederverwendung von Recovery-Tokens
  - Erhoehte Angriffsoberflaeche bei Account-Reset
- Empfehlung:
  - Token-Datei ausserhalb des Webroots (z. B. `storage/app/private`) verpflichtend machen.
  - Atomarer "consume-once" Reset, CSRF-Pflicht, Session-Regeneration nach erfolgreichem Reset, uniforme Fehlermeldungen.

### F-06 (High, Security): Log-Redaction als NFR vorhanden, aber nicht implementierungsnah spezifiziert

- Beobachtung: NFR verlangt aktive Redaction und Verbot von Transcript-Logging. In der Implementierungs-Spec fehlt ein konkretes Umsetzungsdesign.
- Evidenz:
  - `spec/non-functional-requirements.md:262`
  - `spec/non-functional-requirements.md:267`
  - `spec/herold-spec.prompt.md:406`
- Risiko:
  - Secret-Leaks in Logs
  - Datenschutz-/Sicherheitsverletzungen durch Transcript-Inhalte
- Empfehlung:
  - Verbindliche Logging-Policy aufnehmen: zu maskierende Keys, strukturierte Event-Logs, no-payload/no-transcript Logging.

### F-07 (Medium): CSP-Phase inkonsistent

- Beobachtung: CSP wird auf "Phase 9" referenziert, aber die Roadmap hat nur 7 Phasen.
- Evidenz:
  - `spec/herold-spec.prompt.md:234`
  - `spec/herold-spec.prompt.md:550`
  - `spec/herold-spec.prompt.md:552`
- Risiko:
  - Wichtige Security-Haertung wird verschoben oder vergessen
- Empfehlung:
  - Phasenreferenz korrigieren und CSP als verbindlichen Deliverable-Punkt festlegen.

### F-08 (Medium): Sprachkonvention widerspricht UI-Typkonfiguration

- Beobachtung: UI soll laut NFR englisch sein, Typ-Labels in der Implementierungs-Spec sind deutsch.
- Evidenz:
  - `spec/herold-spec.prompt.md:158`
  - `spec/herold-spec.prompt.md:165`
  - `spec/herold-spec.prompt.md:174`
  - `spec/non-functional-requirements.md:291`
- Risiko:
  - Akzeptanzkriterium nicht erfuellbar
  - Inkonsistente UX
- Empfehlung:
  - Eine kanonische Sprachvorgabe definieren und Config-Beispiele entsprechend vereinheitlichen.

### F-09 (Medium, Security): Prompt-Injection/Content-Hardening fuer GitHub-Issues nicht definiert

- Beobachtung: Transkript/LLM-Ausgabe wird direkt in Ticket-Body uebernommen; Guardrails fuer nachgelagerte Agenten fehlen.
- Evidenz:
  - `spec/herold-spec.prompt.md:198`
  - `spec/herold-spec.prompt.md:205`
  - `spec/herold-spec.prompt.md:308`
- Risiko:
  - Schaedliche Instruktionen koennen in Issues landen und Agent-Workflows beeinflussen
- Empfehlung:
  - Issue-Template mit klaren "untrusted input"-Sektionen, Sanitization/Normalization-Regeln und explizitem Human-Review vor Publish definieren.

## Widerspruchsmatrix (Kurzform)

- `sync` Pipeline (`spec/herold-spec.prompt.md`) vs `async+queue+cron` (`spec/non-functional-requirements.md`)
- "Agenten nicht ueber Herold" (`spec/herold-spec.prompt.md`) vs "Agent API + Sanctum Scopes" (`spec/constraints.md`, `spec/non-functional-requirements.md`)
- "kein Cron noetig" (`spec/herold-spec.prompt.md`) vs "Cron erforderlich" (`spec/non-functional-requirements.md`)
- "SSH/CLI Migration" (`spec/herold-spec.prompt.md`) vs "kein Shell/CLI in Prod" (`spec/constraints.md`)

## Sicherheitsluecken (konkret nachzuziehen)

- Recovery-Datei sicher ausserhalb Webroot und atomar verbrauchen
- Logging-Redaction verpflichtend (Secrets + Bearer + Transcript)
- CSP verbindlich mit Nonce/Policy-Definition
- Cookie-/Session-Haertung explizit (Secure, HttpOnly, SameSite, Session-Regeneration)
- Prompt-/Issue-Content-Hardening fuer nachgelagerte Agenten

## Empfohlene naechste Schritte

1. Master-Entscheidung: Sync oder Async als verbindliche Architektur.
2. Spec, Constraints und NFR in einer Konsolidierungsrunde auf identische Aussagen bringen.
3. Security-Baseline als "MUST" Kapitel in `spec/herold-spec.prompt.md` aufnehmen.
4. Danach erst Implementierungsauftrag an Coding Agenten freigeben.
