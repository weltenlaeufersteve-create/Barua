# Handoff: Unified Inbox Web App

## Overview
Farbkodierte Unified-Inbox-Mail-App (Inspiration: BlackBerry Mail / Spark Mail). Drei Ansichten: Mail-Liste (3-spaltig), Vollbild-Composer, Settings-Popup. Dark Mode ist Standard, mit Umschalter zu Light Mode.

## About the Design Files
Die Datei `Unified Inbox.dc.html` ist ein **Design-Referenz-Prototyp** (HTML/inline React), kein Produktionscode. Ziel ist, dieses Design in der Ziel-Codebase (z.B. React/Vue/Swift/native) mit deren bestehenden Patterns/Libraries nachzubauen — nicht die Datei direkt zu übernehmen. Falls noch kein Framework existiert, das am besten passende wählen.

## Fidelity
**High-fidelity**: Farben, Typografie, Abstände und Interaktionen sind final gemeint und sollten pixelgenau übernommen werden.

## Screens / Views

### 1. Inbox (Hauptansicht)
3-spaltiges Layout, volle Höhe (`100vh`), `display:flex`.
- **Spalte 1 – Ordner-Sidebar** (248px, fixe Breite, `overflow-y:auto`): Titel "Postfach" + Dark/Light-Switch (38×22px Pill, Knob 18px), Inbox-Eintrag (aktiv hervorgehoben), Konten-Liste (farbiger 9px Punkt + Name + Unread-Zahl), Trennlinie, statische Ordner (Angeheftet, Gesnoozed, Entwürfe, Gesendet, Archiv, Spam, Papierkorb — je mit Zähler), Abschnitt "ORDNER" (Heute Ungelesen, Angeheftet+PDF, Anhänge, Erinnerungen), unten Settings-Eintrag (Zahnrad-Icon).
- **Spalte 2 – Mail-Liste** (400px fix, `borderRight`): Header mit "Inbox" (19px/700) + "Fokussierte Liste" (12.5px, tertiary), Icons Suche/Neu (30×30px, hover-Background). Liste gruppiert nach Datum (Heute/Gestern/Letzte Woche), jede Zeile: farbiger 8px Account-Punkt, Absendername (fett wenn ungelesen), Betreff, Vorschau-Text (truncate), Uhrzeit rechts.
- **Spalte 3 – Lesebereich** (flex:1): Betreff (20px/700), Absender-Avatar (34px Kreis mit Initiale, Account-Farbe), Name+E-Mail, Zeit. Body-Text mit `white-space:pre-wrap` (Absätze via `\n\n`). Footer-Toolbar (Antworten/Weiterleiten/Archivieren-Pills, `flexWrap:wrap` für schmale Fenster).

### 2. Composer (Vollbild-Overlay)
`position:absolute;inset:0`, zwei Spalten:
- **Links** (260px): Konten-Liste zur Auswahl des Absenders (farbiger Punkt, Name/E-Mail, aktive Zeile hervorgehoben).
- **Rechts**: Header ("Neue E-Mail" + Senden-Button in Account-Farbe + Schließen-X), Felder "An" / "Betreff" (Inline-Inputs mit Trennlinie), Textarea für Nachricht (flex:1), Toolbar unten (Anhang/Bild-Icons).

### 3. Settings (zentriertes Modal)
`position:absolute;inset:0` mit Overlay-Backdrop, Modal 760×540px, `border-radius:16px`, `box-shadow`.
- **Linke Tab-Leiste** (200px): Konten & Farben / Signaturen / Allgemein (aktiver Tab hervorgehoben).
- **Konten & Farben**: pro Konto Avatar, Name/E-Mail, 6 Farb-Swatches (20px Kreise, aktive Farbe mit Ring markiert) — Klick ändert Account-Farbe global (auch in der Mail-Liste).
- **Signaturen**: pro Konto Textarea (56px hoch) mit Signatur-Text.
- **Allgemein**: Sprache-Select (Deutsch/English), Zeitzone-Select, Dark/Hell-Umschalter-Buttons.

## Interactions & Behavior
- Klick auf Mail-Zeile → zeigt sie im Lesebereich.
- Klick "Neu" → öffnet Composer-Overlay; X/Senden schließt es.
- Klick Zahnrad → öffnet Settings-Modal; X schließt es.
- Dark/Light-Switch (Sidebar-Header oder Settings→Allgemein) togglet Theme global, State bleibt erhalten.
- Farb-Swatch-Klick in Settings ändert die Account-Farbe sofort überall (Sidebar, Mail-Liste, Composer, Lesebereich).
- Kein Seitenwechsel/Routing — alles ein Zustand (State) in einer Komponente.

## State Management
- `theme`: 'dark' | 'light'
- `accounts`: Array {id, name, email, color, initial, unread, signature} — color & signature editierbar
- `selectedEmailId`: aktuell im Lesebereich angezeigte Mail
- `composerOpen`: bool, `composerFromId`: gewähltes Absender-Konto
- `settingsOpen`: bool, `settingsTab`: 'accounts' | 'signatures' | 'general'

## Design Tokens

### Farben — Dark Mode (oklch)
- Hintergrund: `oklch(0.16 0.004 260)`
- Sidebar/Modal-BG: `oklch(0.185 0.004 260)`
- Mail-Liste BG: `oklch(0.17 0.004 260)`
- Lesebereich BG: `oklch(0.155 0.004 260)`
- Border: `oklch(0.3 0.006 260 / 0.6)`
- Text primär: `oklch(0.93 0.004 260)`, sekundär: `oklch(0.68 0.006 260)`, tertiär: `oklch(0.5 0.006 260)`
- Hover-BG: `oklch(0.24 0.006 260)`, Selected-BG: `oklch(0.26 0.008 260)`

### Farben — Light Mode (oklch)
- Hintergrund: `oklch(0.985 0.002 260)`, Sidebar: `oklch(0.97 0.003 260)`
- Text primär: `oklch(0.22 0.005 260)`, sekundär: `oklch(0.45 0.006 260)`, tertiär: `oklch(0.6 0.006 260)`
- Border: `oklch(0.88 0.004 260)`, Hover-BG: `oklch(0.93 0.004 260)`

### Konto-Farbpalette (Swatches, Hex)
`#5B8DEF` (blau), `#F2994A` (orange), `#EC6FAE` (pink), `#3FC1BA` (türkis), `#9B7BF0` (lila), `#4FAE7E` (grün)

### Typografie
Font: **Inter** (Google Fonts), Fallback `system-ui, -apple-system, sans-serif`. Gewichte 400/500/600/700.
- Screen-Titel: 19–20px/700
- Section-Header (Ordner-Gruppen): 11–11.5px/600, letter-spacing 0.03–0.04em, tertiary color
- Listen-Text: 13–13.5px, 500/700 je nach Read-Status
- Body-Text Lesebereich: 14.5px, line-height 1.7

### Radien & Abstände
- Icon-Buttons: 8px border-radius, 30×30px
- Zeilen/Karten: 8–10px border-radius
- Modal: 16px border-radius
- Sidebar-Padding: 18px 12px; Content-Padding meist 20–32px

## Assets
Keine externen Bild-Assets. Icons sind inline SVG (stroke-basiert, `currentColor`/textSecondary, strokeWidth ~1.6). Avatare sind Farbkreise mit Initiale (kein Bild).

## Files
- `Unified Inbox.dc.html` — vollständiger Prototyp (Inbox + Composer + Settings in einer Datei, State-getrieben)
