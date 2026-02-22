# SEO Alt Text Generator - Bilder-Alt.de (DE) 

Erstelle automatisch barrierefreie und suchmaschinenfreundliche Alt-Texte direkt beim Bildupload in Contao – mit Hilfe von KI und optionalen SEO-Keywords.
Unsere Erweiterung für das Contao CMS spart Zeit, verbessert deine Bildbeschreibungen und lässt sich nahtlos in deinen bestehenden Workflow integrieren.

## So funktioniert die Contao-Integration

Die Erweiterung ist im offiziellen Contao Extension Repository verfügbar.
Nach der Installation brauchst du nur einen kostenlosen API-Schlüssel von SEO Alt Text Generator – und kannst direkt loslegen:

1. Installation der Erweiterung `$ composer require bluebranch/bilder-alt` oder über den [Contao Manager](https://extensions.contao.org/?q=bild&pages=1&p=bluebranch/bilder-alt)
2. Sichere Dir 25 Credits und registriere Dich auf [app.bilder-alt.de](https://app.bilder-alt.de/login)
3. API-Key erstellen und in den Contao Einstellungen hinterlegen

Fertig!

Mehr Informationen unter https://www.bilder-alt.de/contao-erweiterung

## Alt-Texte automatisch beim Bildupload generieren

Mit der offiziellen Contao-Erweiterung von SEO Alt Text Generator wird jeder Bild-Upload effizienter.
Sobald ein neues Bild in die Mediathek geladen wird, erstellt die KI automatisch einen passenden Alt-Text – in natürlicher Sprache und optional mit deinem Wunsch-Keyword.

## SEO-Texte für Seiten mit KI generieren

Neben Alt-Texten für Bilder unterstützt die Erweiterung jetzt auch die automatische Generierung von **SEO-Titeln** (pageTitle) und **Meta-Beschreibungen** für Contao-Seiten.

### Direkt auf der Seite

Beim Bearbeiten einer Seite im Contao-Backend erscheinen zwei neue Buttons unterhalb der Seiten-Einstellungen:

- **Titel generieren** – analysiert den Seiteninhalt und schlägt einen optimierten SEO-Titel vor
- **Beschreibung generieren** – erstellt eine passende Meta-Beschreibung für die Seite

Der generierte Text wird direkt in das jeweilige Feld eingetragen und kann vor dem Speichern noch manuell angepasst werden.

> Hinweis: Die Seite muss veröffentlicht sein, damit die KI den Inhalt abrufen kann. Für unveröffentlichte Seiten wird ein entsprechender Hinweis angezeigt.

### Mehrfachverarbeitung (Batch)

Über die Seitenübersicht lässt sich eine neue Batch-Ansicht öffnen, in der alle regulären Seiten aufgelistet sind. Dort stehen sechs Schaltflächen zur Verfügung:

| Schaltfläche | Beschreibung |
|---|---|
| Titel generieren | Generiert den SEO-Titel für alle ausgewählten Seiten |
| Beschreibung generieren | Generiert die Meta-Beschreibung für alle ausgewählten Seiten |
| Titel & Beschreibung generieren | Generiert beides für alle ausgewählten Seiten |
| Titel generieren (nur leere) | Überspringt Seiten, bei denen bereits ein SEO-Titel vorhanden ist |
| Beschreibung generieren (nur leere) | Überspringt Seiten, bei denen bereits eine Meta-Beschreibung vorhanden ist |
| Titel & Beschreibung generieren (nur leere) | Generiert nur das, was pro Seite noch fehlt |

Die Verarbeitung erfolgt sequenziell mit Fortschrittsanzeige. Jede Generierung kostet 2 Credits.

## Vorteile für Redakteur:innen und Entwickler

- Zeit sparen: Keine händischen Alt-Texte mehr nötig
- Barrierefreiheit verbessern: Automatisch formulierte, verständliche Beschreibungen
- SEO unterstützen: Keywords fließen intelligent ein
- Einfache Einbindung: Kein separates Tool – direkt im Contao Backend nutzbar
- DSGVO-konform: Datenverarbeitung ausschließlich in Deutschland

# SEO Alt Text Generator - Bilder-Alt.de (EN)

Automatically create accessible and SEO-friendly alt texts during image upload in Contao – powered by AI and optional SEO keywords.  
Our extension for the Contao CMS saves time, improves image descriptions, and integrates seamlessly into your existing workflow.

## How the Contao Integration Works

The extension is available in the official Contao Extension Repository.  
After installation, all you need is a free API key from SEO Alt Text Generator – and you’re ready to go:

1. Install the extension using `$ composer require bluebranch/bilder-alt` or via the [Contao Manager](https://extensions.contao.org/?q=bild&pages=1&p=bluebranch/bilder-alt)
2. Register at [app.bilder-alt.de](https://app.bilder-alt.de/login) and claim your 25 free credits
3. Generate your API key and enter it in the Contao settings

That’s it!

More information at: https://www.bilder-alt.de/contao-erweiterung

## AI-Generated SEO Texts for Pages

In addition to alt texts for images, the extension now supports automatic generation of **SEO titles** (pageTitle) and **meta descriptions** for Contao pages.

### Directly on the Page

When editing a page in the Contao backend, two new buttons appear below the page settings:

- **AI-Icon Generate title** – analyses the page content and suggests an optimised SEO title
- **AI-Icon Generate description** – creates a suitable meta description for the page

The generated text is inserted directly into the corresponding field and can still be adjusted manually before saving.

> Note: The page must be published so that the AI can fetch its content. An informative error message is shown for unpublished pages.

### Batch Processing

A new batch view can be opened from the page list, showing all regular pages. Six buttons are available:

| Button | Description |
|---|---|
| Generate title | Generates the SEO title for all selected pages |
| Generate description | Generates the meta description for all selected pages |
| Generate title & description | Generates both for all selected pages |
| Generate title (empty only) | Skips pages that already have an SEO title |
| Generate description (empty only) | Skips pages that already have a meta description |
| Generate title & description (empty only) | Only generates what is missing per page |

Processing runs sequentially with a progress indicator. Each generation costs 2 credits.

## Vielen Dank

Unser Team Dank für die Unterstützung und das Benutzen von dem SEO Alt Text Generator.

Das Team von [www.bluebranch.de](https://www.bluebranch.de/)

<3

## Changes

### 1.6.x - 2026-02-22

- Add AI-powered SEO title and meta description generation for pages
- Add two buttons ("Generate title" / "Generate description") to the page edit form
- Add batch processing view for pages with six action buttons
- Add "empty only" batch modes that skip pages with already existing values
- Show credit balance in batch view with live update during processing

### 1.5.x - 2025-10-24

- Improve dark mode | thanks to https://github.com/lukasbableck
- Add twig default pipe to meta.alt | thanks to https://github.com/lukasbableck
- Add url encode for file path in batch view | thanks to https://github.com/lukasbableck
- Implement user and group rights for bilder alt
- Add a simple service to handle and check rights
- Fix position with PaletteManipulator

### 1.4.x - 2025-07-15

- Implement a new column to the batch table to show existing alt text
- Implement a new button to process only images without alt text
- Fix warnings
- Fix typos

### 1.3.3 - 2025-06-30
- Fix credits steps by process
- Show result text in the message box

### 1.3.0 - 2025-06-21
- Support für svg, heic, heif, tif, tiff hinzugefügt

### 1.2.0 - 2025-06-17
- Multi File Select ist nun möglich + Ordnerwahl
- Dateiname und bestehende Alt-Beschreibung werden ebenfalls an die API geschickt
