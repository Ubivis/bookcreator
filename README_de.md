# Markdown zu PDF Buchgenerator

Ein PHP-Programm zur Erstellung professioneller PDF-Bücher aus Markdown-Dateien. Der Generator unterstützt eine strukturierte Organisation in Akte und Kapitel, automatische Inhaltsverzeichnisse, typografische Verbesserungen und Bildeinbindung.

![Version](https://img.shields.io/badge/version-1.0.0-blue)
![PHP](https://img.shields.io/badge/php-%3E%3D7.4-green)

## Features

- Konvertierung von Markdown-Dateien in strukturierte PDF-Bücher
- Organisation in Akte und Kapitel
- Automatisches Inhaltsverzeichnis mit Lesezeichen
- GitHub-Integration (öffentliche und private Repositories)
- Lokaler Datei-Upload
- Typografische Verbesserungen (Anführungszeichen, Gedankenstriche, etc.)
- Flexible Konfiguration über JSON-Metadaten
- Benutzerdefinierte Akttitel über JSON-Konfiguration
- Bildeinbindung für Akte und innerhalb des Textes
- Verschiedene Buchformate (A4, A5, etc.)

## Installation

### Voraussetzungen

- PHP 7.4 oder höher
- Composer
- Webserver mit PHP-Unterstützung (z.B. Apache, Nginx)

### Installation über Composer

1. Repository klonen oder als ZIP-Datei herunterladen:
   ```bash
   git clone https://github.com/username/markdown-pdf-buchgenerator.git
   cd markdown-pdf-buchgenerator
   ```

2. Abhängigkeiten installieren:
   ```bash
   composer install
   ```

3. Output-Verzeichnis erstellen und Berechtigungen setzen:
   ```bash
   mkdir -p output
   chmod 755 output
   ```

4. Webserver so konfigurieren, dass er auf das Verzeichnis zeigt.

### Installation auf Plesk

1. Erstellen Sie eine neue Subdomain oder verwenden Sie eine bestehende.
2. Laden Sie die Dateien auf den Server hoch.
3. Stellen Sie sicher, dass die `composer.json` im Root-Verzeichnis liegt.
4. Führen Sie über die Plesk-Oberfläche oder SSH den Composer-Befehl aus:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
5. Erstellen Sie ein output-Verzeichnis und setzen Sie die korrekten Berechtigungen:
   ```bash
   mkdir -p output
   chmod 755 output
   ```

## Verwendung

Der Generator kann auf zwei Arten verwendet werden:

1. Über den Webbrowser (empfohlen)
2. Über die Kommandozeile (für fortgeschrittene Benutzer)

### Webbrowser-Interface

Öffnen Sie die URL des Generators in Ihrem Browser und Sie erhalten ein einfaches Interface mit zwei Tabs:

#### 1. Datei-Upload

Wählen Sie eine lokale Markdown-Datei von Ihrem Computer aus und klicken Sie auf "PDF generieren".

#### 2. GitHub-Repository

Geben Sie die URL eines GitHub-Repositories ein und wählen Sie zwischen zwei Modi:

- **Einzelne Markdown-Datei**: Wenn Ihr Buch in einer einzigen Markdown-Datei gespeichert ist.
- **Verzeichnisstruktur**: Wenn Ihr Buch in Akte und Kapitel organisiert ist (empfohlen).

Für private Repositories geben Sie bitte einen gültigen GitHub-Token ein.

## Verzeichnisstruktur und Organisation

### Empfohlene Verzeichnisstruktur auf GitHub

```
MeinBuch/
├── metadata.json         # Metadaten und Konfiguration
├── cover.jpg             # Cover-Bild (optional)
├── Akt1.png              # Aktbilder (optional)
├── Akt2.png
├── 1. Akt/
│   ├── 01. Kapitel.md
│   ├── 02. Kapitel.md
│   └── bilder/           # Kapitelbilder (optional)
│       └── szene1.jpg
├── 2. Akt/
│   ├── 01. Kapitel.md
│   └── ...
└── ...
```

### Markdown-Struktur

Wenn Sie eine einzelne Markdown-Datei verwenden, sollte diese wie folgt strukturiert sein:

```markdown
# Buchtitel
**Autor:** Name des Autors

## 1. Akt
### 1. Kapitel
Inhalt des ersten Kapitels...

### 2. Kapitel
Inhalt des zweiten Kapitels...

## 2. Akt
### 3. Kapitel
...
```

## Konfiguration mit metadata.json

Sie können eine `metadata.json`-Datei im Hauptverzeichnis Ihres Buchs erstellen, um verschiedene Aspekte des PDFs zu konfigurieren:

```json
{
  "title": "Mein Buch",
  "author": "Max Mustermann",
  "language": "de",
  "description": "Eine spannende Geschichte über...",
  "publisher": "Selbstverlag",
  "date": "2025-05-20",
  "cover_image": "cover.jpg",
  
  "format": "A5",
  "font": "DejaVuSerif",
  "font_size": 12,
  "margin_left": 20,
  "margin_right": 20,
  "margin_top": 25,
  "margin_bottom": 25,
  "hyphenate": true,
  
  "acts": [
    {
      "number": 1,
      "title": "Der Anfang"
    },
    {
      "number": 2,
      "title": "Die Wendung"
    },
    {
      "number": 3,
      "title": "Das Finale"
    }
  ]
}
```

### Verfügbare Einstellungen

| Parameter | Beschreibung | Beispiel |
|-----------|--------------|----------|
| title | Titel des Buchs | "Mein Buch" |
| author | Name des Autors | "Max Mustermann" |
| language | Sprache des Buchs | "de" |
| description | Kurzbeschreibung | "Eine spannende Geschichte..." |
| publisher | Verlag oder Publisher | "Selbstverlag" |
| date | Publikationsdatum | "2025-05-20" |
| cover_image | Pfad zum Cover-Bild | "cover.jpg" |
| format | Seitenformat | "A5", "A4" |
| font | Schriftart | "DejaVuSerif" |
| font_size | Schriftgröße | 12 |
| margin_left | Linker Rand in mm | 20 |
| margin_right | Rechter Rand in mm | 20 |
| margin_top | Oberer Rand in mm | 25 |
| margin_bottom | Unterer Rand in mm | 25 |
| hyphenate | Silbentrennung aktivieren | true/false |
| acts | Array von Akt-Definitionen | Siehe nächster Abschnitt |

### Benutzerdefinierte Akttitel

Mit dem `acts`-Array können Sie benutzerdefinierte Titel für Ihre Akte definieren. Dies ermöglicht es Ihnen, aussagekräftigere Titel als nur "1. Akt", "2. Akt" usw. zu verwenden.

```json
{
  "acts": [
    {
      "number": 1,
      "title": "Der mysteriöse Fall"
    },
    {
      "number": 2,
      "title": "Verschwörungen und Verrat"
    },
    {
      "number": 3,
      "title": "Rettung und Enthüllung"
    }
  ]
}
```

- `number`: Die Nummer des Akts (entspricht der Nummer in den Verzeichnisnamen wie "1. Akt")
- `title`: Der zu verwendende Titel für diesen Akt

Mit dieser Konfiguration werden die Akte im PDF als "1. Akt: Der mysteriöse Fall", "2. Akt: Verschwörungen und Verrat" usw. angezeigt. Dies verbessert die Navigation und gibt dem Leser einen besseren Überblick über die Struktur Ihres Buchs.

Die benutzerdefinierten Akttitel werden in der PDF-Ausgabe an folgenden Stellen verwendet:
- Als Überschrift auf den Akt-Titelseiten
- In den Lesezeichen für die Navigation
- Im Inhaltsverzeichnis

## Bildeinbindung

### Aktbilder

Für jede Akt-Titelseite können Sie ein Bild einbinden:

1. Benennen Sie die Bilder nach dem Schema `Akt1.png`, `Akt2.png`, etc.
2. Platzieren Sie die Bilder in einem der folgenden Orte:
   - Im Hauptverzeichnis
   - Im jeweiligen Akt-Verzeichnis (z.B. `1. Akt/Akt1.png`)
   - Im `images/`-Verzeichnis

### Inline-Bilder in Kapiteln

Sie können Bilder in Ihren Markdown-Dateien mit der Standard-Markdown-Syntax einbinden:

```markdown
![Beschreibung des Bildes](bilder/mein-bild.jpg)
```

Bilder sollten relativ zum Kapitel-Verzeichnis gespeichert werden.

### Cover-Bild

Ein Cover-Bild kann in der metadata.json angegeben werden:

```json
{
  "cover_image": "cover.jpg"
}
```

## Tipps und Best Practices

- **Strukturierung**: Verwenden Sie eine klare Verzeichnisstruktur mit nummerierten Akten und Kapiteln.
- **Bilder**: Halten Sie Bilder in einem angemessenen Format und einer angemessenen Größe (unter 1MB pro Bild).
- **Konsistenz**: Verwenden Sie konsistente Formatierung in allen Markdown-Dateien.
- **Kapitelgröße**: Teilen Sie Ihren Text in überschaubare Kapitel auf.
- **Versionskontrolle**: Bei Verwendung von GitHub können Sie die Versionshistorie Ihres Buchs verfolgen.
- **Akttitel**: Nutzen Sie aussagekräftige Akttitel über die JSON-Konfiguration, um die Navigation zu verbessern.

## Fehlerbehebung

### Bilder werden nicht angezeigt

- Stellen Sie sicher, dass die Bilder in den richtigen Verzeichnissen liegen.
- Überprüfen Sie die Bildpfade in Ihren Markdown-Dateien.
- Prüfen Sie das Debug-Log unter `output/debug_log.txt` für detaillierte Informationen.
- Prüfen Sie, ob die Bildgrößen unter den Limits Ihres Webservers liegen. Bei großen Bildern (>1MB) kann es zu Problemen kommen.

### GitHub-Zugriffsprobleme

- Stellen Sie sicher, dass Ihr GitHub-Token die richtigen Berechtigungen hat.
- Für private Repositories benötigen Sie mindestens `repo`-Berechtigungen.

### PDF-Generierungsfehler

- Überprüfen Sie die PHP-Fehlerprotokolle Ihres Servers.
- Stellen Sie sicher, dass alle Abhängigkeiten korrekt installiert sind.
- Prüfen Sie, ob das `output`-Verzeichnis existiert und beschreibbar ist.
- Bei Problemen mit Akttiteln, überprüfen Sie das Format Ihrer JSON-Datei.

## GitHub-Token erhalten

Um auf private GitHub-Repositories zugreifen zu können, benötigen Sie einen Personal Access Token:

1. Melden Sie sich bei GitHub an
2. Gehen Sie zu Einstellungen → Developer settings → Personal access tokens → Tokens (classic)
3. Klicken Sie auf "Generate new token"
4. Geben Sie einen Namen ein (z.B. "PDF-Buchgenerator")
5. Wählen Sie den Scope "repo" aus
6. Klicken Sie auf "Generate token"
7. Kopieren Sie den generierten Token (er wird nur einmal angezeigt!)

## Lizenz

Dieses Projekt steht unter der MIT-Lizenz. Siehe die LICENSE-Datei für Details.
