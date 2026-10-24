# Herold v1.0.0-beta.3

Herold macht aus gesprochenen Notizen GitHub-Issues: im Browser aufnehmen, von der KI transkribieren und strukturieren lassen, prüfen, versenden. Dies ist das erste Release, das automatisch ausgeliefert wird; die Notes beschreiben daher den gesamten Funktionsumfang.

## ✨ Funktionen

- **Aufnahme:** Sprachnotizen bis 25 MB im Browser aufnehmen, mit typspezifischen Feldern (YouTube-URL, Tagebuchdatum, Obsidian-Vault, Fälligkeitsdatum).
- **Fünf Notiztypen:** General, YouTube Transcription, Diary, Obsidian und To-Do, jeweils mit eigenem Prompt und GitHub-Label.
- **Verarbeitung:** Transkription mit OpenAI Whisper; die KI erzeugt Titel und strukturierten Text und übernimmt passende Angaben aus dem Gesprochenen. Bei Fehlern bleibt die Aufnahme erhalten.
- **Prüfen und Versenden:** Inhalte vor dem Versand bearbeiten, dann als bereinigtes GitHub-Issue anlegen; der Issue-Link erscheint an der Notiz.
- **Übersicht:** Notizen nach Typ und Status filtern, anhören, löschen.
- **Anmeldung:** API-Key plus Authenticator-App; Wiederherstellung des Zugangs über ein einmaliges Token auf dem Server.

## 🛡️ Neu in diesem Release

- Alle Texte und Felder sind in der Länge begrenzt; Fehler und Zeichenzähler erscheinen direkt am Feld.
- Zu lange Notizen werden vor dem Versand abgefangen und bleiben bearbeitbar.
- Ungültige KI-Werte werden verworfen, statt eigene Eingaben zu überschreiben.
- Ein bekanntes Standardpasswort wird aus bestehenden Installationen entfernt.

## 🚀 Betrieb

- Automatisches Deployment beim Setzen eines Versions-Tags; Migrationen bleiben ein manueller Schritt.
