---
description: "Orientierung im Worktree: Erkennt den aktuellen Arbeitskontext und setzt klare Grenzen für Dateisuche und -bearbeitung."
---

Du arbeitest möglicherweise in einem **Git-Worktree**, der innerhalb der Verzeichnisstruktur des Haupt-Repos liegt. Deine erste Aufgabe ist es, dich zu orientieren.

## Schritt 1: Kontext ermitteln

Führe folgende Befehle aus:

1. `pwd` — aktuelles Arbeitsverzeichnis
2. `git rev-parse --show-toplevel` — Git-Toplevel (= Worktree-Root)
3. `git rev-parse --git-common-dir` — gemeinsames `.git`-Verzeichnis (zeigt auf das Haupt-Repo, wenn Worktree)
4. `git branch --show-current` — aktueller Branch

## Schritt 2: Worktree erkennen

Prüfe, ob der Pfad das Muster `.claude/worktrees/` enthält.

- **Ja → Du bist in einem Worktree.**
  - Der **Worktree-Root** (Ergebnis von `git rev-parse --show-toplevel`) ist dein Arbeitsbereich.
  - Das **Haupt-Repo** liegt weiter oben im Dateisystem (ableitbar aus `--git-common-dir`).
- **Nein → Du bist direkt im Haupt-Repo.** Keine besonderen Einschränkungen.

## Schritt 3: Grenzen setzen (nur bei Worktree)

Wenn du in einem Worktree bist, gelten diese **strikten Regeln** für die gesamte Sitzung:

### ERLAUBT (Worktree-Root und darunter)
- Dateien lesen, bearbeiten, erstellen innerhalb des Worktree-Root
- `Glob`, `Grep`, `Read`, `Edit`, `Write` mit Pfaden unterhalb des Worktree-Root
- Git-Operationen (`git status`, `git diff`, `git commit`, etc.)

### VERBOTEN
- **Keine** Dateioperationen in übergeordneten Verzeichnissen (also oberhalb des Worktree-Root)
- **Keine** Dateioperationen direkt im Haupt-Repo-Verzeichnis
- **Keine** Suche (`Glob`, `Grep`) mit Pfaden, die auf das Haupt-Repo oder andere Worktrees zeigen
- **Kein** `cd` in Verzeichnisse oberhalb des Worktree-Root

### Ausnahme
- Das Lesen von Dateien außerhalb des Worktree ist erlaubt, wenn der Benutzer **explizit** einen absoluten Pfad außerhalb angibt.

## Schritt 4: Zusammenfassung ausgeben

Gib eine kompakte Zusammenfassung aus:

```
Orientierung abgeschlossen.
- Kontext: Worktree | Haupt-Repo
- Worktree-Root: <Pfad>
- Branch: <Branch-Name>
- Haupt-Repo: <Pfad>
- Arbeitsbereich: Nur Dateien unter <Worktree-Root>
```

Merke dir diese Grenzen für die gesamte Sitzung. Wenn du bei einer Dateioperation unsicher bist, ob der Pfad innerhalb des Worktree liegt, prüfe das zuerst.
