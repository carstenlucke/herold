# Bausteine der Spezifikation nach Siedersleben

Struktur einer Spezifikation nach dem Modell von **Johannes Siedersleben** (oft im Kontext der SD&M-Methodik verwendet). Die Spezifikation ist in funktionale Blöcke, Daten, Schnittstellen und übergreifende Aspekte unterteilt.

**Quelle:** SIEDERSLEBEN, J. (ed.) 2003. *Softwaretechnik — Praxiswissen für Softwareingenieure.* München: Carl Hanser Verlag. Kapitel 4 „Bausteine der Spezifikation" (Krug & Siedersleben).

Hier ist die systematische Aufstellung der Bestandteile:

--- 

### 1. Projektgrundlagen
Diese Sektion bildet das Fundament und den Kontext des Projekts.
* **P1 – Ziele und Rahmenbedingungen:** Warum wird das System gebaut? Was sind die geschäftlichen Ziele und technischen/organisatorischen Einschränkungen?
* **P2 – Architekturüberblick:** Eine grobe Skizze, wie das System strukturell aufgebaut ist.

### 2. Abläufe und Funktionen
Hier wird das „Was“ des Systems aus fachlicher Sicht beschrieben.
* **F1 – Geschäftsprozesse:** Die übergeordneten Arbeitsabläufe, die durch die Software unterstützt werden.
* **F2 – Anwendungsfälle (Use Cases):** Konkrete Interaktionsszenarien zwischen Nutzern und System.
* **F3 – Anwendungsfunktionen:** Die spezifischen Funktionen, die das System zur Verfügung stellt.

### 3. Daten
Die statische Sicht auf die Informationen des Systems.
* **D1 – Datenmodell:** Die logische Struktur der Daten (z. B. ER-Modell).
* **D2 – Datentypenverzeichnis:** Definition der verwendeten Formate und Wertebereiche.

### 4. Benutzerschnittstelle
Beschreibung der Interaktion mit dem Endanwender.
* **B1 – Dialogspezifikation:** Maskenentwürfe, Navigation und Benutzerführung (UI/UX).
* **B2 – Batch:** Spezifikation von Hintergrundverarbeitungen, die ohne direkte Nutzerinteraktion laufen.
* **B3 – Druckausgaben:** Layouts und Inhalte von Reports, Listen oder Dokumenten.

### 5. Schnittstellen zu Alt- und Nachbarsystemen
Die Integration des Systems in die bestehende IT-Landschaft.
* **S1 – Nachbarsystem-Schnittstellen:** Wie kommuniziert das System mit anderen aktiven Systemen?
* **S2 – Datenmigration:** Wie werden Altdaten in das neue System übernommen?
* **S3 – Inbetriebnahme:** Pläne und Voraussetzungen für das Rollout.

### 6. Übergreifendes (Querschnittsthemen)
Aspekte, die nicht einer einzelnen Funktion zugeordnet werden können, sondern das gesamte System betreffen.
* **N1 – Nichtfunktionale Anforderungen:** Performance, Sicherheit, Verfügbarkeit, Wartbarkeit.
* **N2 – Querschnittskonzepte:** Einheitliche Lösungen für wiederkehrende Probleme (z. B. Fehlerbehandlung, Logging, Berechtigungskonzept).

### 7. Ergänzende Bausteine
Hilfsmittel zum Verständnis des Dokuments.
* **E1 – Leseanleitung:** Hilfe für den Leser, wie das Dokument zu nutzen ist.
* **E2 – Glossar:** Definition der verwendeten Fachbegriffe, um Missverständnisse zu vermeiden.

---

**Zusammenfassend** lässt sich sagen, dass eine Spezifikation nach Siedersleben versucht, alle relevanten Sichten (Funktion, Daten, Benutzeroberfläche, Technik und Kontext) in klar voneinander abgegrenzten Bausteinen zu kapseln. Dies sorgt für eine hohe Modularität und bessere Wartbarkeit der Dokumentation.

---

## Einschätzung der Bausteine für Herold

Bewertung der Relevanz jedes Bausteins für das Projekt Herold inklusive Quelle für vorhandenes Material und vorgesehener Zieldatei.

### 1. Projektgrundlagen

| Baustein | Relevant | Quelle | Zieldatei |
|----------|----------|--------|-----------|
| **P1 – Ziele und Rahmenbedingungen** | Ja | `prompts/herold-spec.prompt.md` (Zielsetzung) | `P1-ziele-rahmenbedingungen.md` (+ Anhang `P1-constraints.md`) |
| **P2 – Architekturüberblick** | Ja | `CLAUDE.md` (Architecture), `docs/arch/ARCHITECTURE_DECISIONS.md` | `P2-architekturueberblick.md` |

### 2. Abläufe und Funktionen

| Baustein | Relevant | Quelle | Zieldatei |
|----------|----------|--------|-----------|
| **F1 – Geschäftsprozesse** | Ja (schlank, ein Hauptprozess „Sprachnotiz → Issue") | `prompts/herold-spec.prompt.md`, `CLAUDE.md` (Pipeline) | `F1-geschaeftsprozesse.md` |
| **F2 – Anwendungsfälle** | Ja | Vue-Pages, Routes, `prompts/herold-spec.prompt.md` | `F2-anwendungsfaelle.md` |
| **F3 – Anwendungsfunktionen** | Ja | Controller, Services, `prompts/herold-spec.prompt.md` | `F3-anwendungsfunktionen.md` |

### 3. Daten

| Baustein | Relevant | Quelle | Zieldatei |
|----------|----------|--------|-----------|
| **D1 – Datenmodell** | Ja | `docs/spec/DATA_MODEL.md`, `docs/spec/diagrams/datamodel.plantuml` | `D1-datenmodell.md` (oder Verweis auf Bestehendes) |
| **D2 – Datentypenverzeichnis** | Ja (klein: Enums, Wertebereiche, Formate) | Migrations, Enums (`NoteStatus`), Models | `D2-datentypen.md` |

### 4. Benutzerschnittstelle

| Baustein | Relevant | Quelle | Zieldatei |
|----------|----------|--------|-----------|
| **B1 – Dialogspezifikation** | Ja | `DESIGN.md`, Vue-Pages | `B1-dialogspezifikation.md` |
| **B2 – Batch** | Nein — Pipeline ist synchron, `crontab` auf Hosting blockiert | — | im Wurzeldokument als „nicht anwendbar" markieren |
| **B3 – Druckausgaben** | Nein — keine Reports oder PDFs vorgesehen | — | im Wurzeldokument als „nicht anwendbar" markieren |

### 5. Schnittstellen zu Alt- und Nachbarsystemen

| Baustein | Relevant | Quelle | Zieldatei |
|----------|----------|--------|-----------|
| **S1 – Nachbarsystem-Schnittstellen** | Ja | OpenAI (STT + Generation), GitHub Issues API | `S1-nachbarsysteme.md` |
| **S2 – Datenmigration** | Nein — Greenfield, kein Altdatenbestand | — | im Wurzeldokument als „nicht anwendbar" markieren |
| **S3 – Inbetriebnahme** | Ja | `docs/FTP_DEPLOYMENT.md`, `CLAUDE.md` (Production Environment) | `S3-inbetriebnahme.md` |

### 6. Übergreifendes

| Baustein | Relevant | Quelle | Zieldatei |
|----------|----------|--------|-----------|
| **N1 – Nichtfunktionale Anforderungen** | Ja | bestehend (umbenannt) | `N1-nichtfunktional.md` |
| **N2 – Querschnittskonzepte** | Ja (Auth/2FA, Logging, Fehlerbehandlung, Sanitization, Secret-Handling) | Code, `config/`, `app/Http/Middleware/` | `N2-querschnittskonzepte.md` |

### 7. Ergänzende Bausteine

| Baustein | Relevant | Quelle | Zieldatei |
|----------|----------|--------|-----------|
| **E1 – Leseanleitung** | Ja | — | direkt im Wurzeldokument |
| **E2 – Glossar** | Ja | bestehend (migriert) | `E2-glossar.md` |

---

## Vorgeschlagene Dateistruktur

```
docs/spec/
  README.md                       (Wurzeldokument, Orchestrator + E1 Leseanleitung)
  P1-ziele-rahmenbedingungen.md
  P1-constraints.md               (Anhang zu P1, Volere Section 3)
  P2-architekturueberblick.md
  F1-geschaeftsprozesse.md
  F2-anwendungsfaelle.md
  F3-anwendungsfunktionen.md
  D1-datenmodell.md
  D2-datentypen.md
  B1-dialogspezifikation.md
  S1-nachbarsysteme.md
  S3-inbetriebnahme.md
  N1-nichtfunktional.md
  N2-querschnittskonzepte.md
  E2-glossar.md
  diagrams/                       (PlantUML-Quellen)
  diagrams-png/                   (gerenderte PNGs)
```

**Bausteine ohne eigene Datei (im Wurzeldokument als „nicht anwendbar" mit Begründung):** B2, B3, S2.

---

## Offene Entscheidungen

1. **Umbenennung vorhandener Dateien** in das `Bx-…`-Schema (`DATA_MODEL.md`, `GLOSSARY.md`, `non-functional-requirements.md`, `constraints.md`) — oder belassen und nur aus dem Wurzeldokument verlinken?
2. **Sprache der Bausteindokumente** — Deutsch (passt zu Siedersleben und `prompts/herold-spec.prompt.md`) oder Englisch (passt zu CLAUDE.md-Regel „Documentation in English")?
3. **Bausteinauswahl** oben — Zustimmung, Streichungen oder Ergänzungen?