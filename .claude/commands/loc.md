---
description: "Lines of Code: Zeigt eine Übersicht der Codezeilen im Projekt, aufgeschlüsselt nach Dateityp."
---

Ermittle die Lines of Code des Projekts anhand der git-tracked Dateien und gib eine übersichtliche Zusammenfassung aus.

## Vorgehen

1. Zähle die Gesamtzeilen aller git-tracked Dateien (`git ls-files | xargs wc -l`).
2. Schlüssele nach Dateityp auf. Relevante Typen: `vue`, `ts`, `sql`, `css`, `json`, `md`, `plantuml`, `sh`.
3. Gib das Ergebnis als Markdown-Tabelle aus, sortiert nach Zeilenanzahl absteigend.
4. Fasse am Ende zusammen, wieviele Zeilen auf **Anwendungscode** (TypeScript + Vue + SQL + CSS) entfallen vs. Konfiguration/Docs/Sonstiges.
