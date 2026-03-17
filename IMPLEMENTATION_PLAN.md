# FileDrop - Einfaches File-Exchange Tool

## Übersicht

Webbasiertes Tool für bidirektionalen Dateiaustausch zwischen zwei Parteien. Kein Login, keine Datenbank. Zugang über Session-Link mit QR-Code.

---

## Architektur

```
┌─────────────────┐         ┌─────────────────────┐
│  Vue.js Frontend │ ◄─────► │  Express.js Backend  │
│  (SPA)           │  REST   │                     │
│                  │         │  /sessions/:id/     │
│  - Upload UI     │         │    files/           │
│  - File List     │         │      datei1.pdf     │
│  - QR Code       │         │      datei2.docx    │
│  - Download      │         │                     │
└─────────────────┘         └─────────────────────┘
```

### Kein DB-Ansatz

- Jede Session = ein Ordner auf dem Filesystem: `./data/sessions/{uuid}/`
- Metadaten (Erstellzeit, Ablauf) in einer `meta.json` pro Session
- Cleanup-Job löscht abgelaufene Sessions (z.B. nach 24h)

### Session-Sicherheit

- UUID v4 als Session-ID (122 Bit Entropie, nicht erratbar)
- Kein Passwort nötig: Wer den Link hat, hat Zugang
- Optional: Session-Ablaufzeit konfigurierbar

---

## Tech Stack

| Komponente | Technologie |
|---|---|
| Frontend | Vue 3 (Composition API) + Vite |
| CSS | Minimal, eigenes CSS (kein Framework-Overhead) |
| Backend | Node.js + Express |
| File Storage | Filesystem (kein S3, kein DB) |
| QR Code | `qrcode`-Paket (npm) |
| File Upload | `multer` (Express Middleware) |
| UUID | `crypto.randomUUID()` (Node built-in) |

---

## Projektstruktur

```
filedrop/
├── server/
│   ├── index.js              # Express Server, alle Routes
│   ├── cleanup.js            # Cron-Job für abgelaufene Sessions
│   └── package.json
├── client/
│   ├── src/
│   │   ├── App.vue           # Router-Wrapper
│   │   ├── views/
│   │   │   ├── Home.vue      # "Neue Session erstellen"-Button
│   │   │   └── Session.vue   # Upload/Download/QR-Ansicht
│   │   ├── components/
│   │   │   ├── FileList.vue      # Liste aller Dateien in der Session
│   │   │   ├── FileUpload.vue    # Drag & Drop + Datei-Auswahl
│   │   │   └── QrCode.vue        # QR-Code-Anzeige der Session-URL
│   │   ├── composables/
│   │   │   └── useSession.js     # API-Calls (upload, list, download)
│   │   ├── router.js
│   │   └── main.js
│   ├── index.html
│   ├── vite.config.js
│   └── package.json
├── data/
│   └── sessions/             # Wird zur Laufzeit erstellt
└── README.md
```

---

## API Endpoints

### `POST /api/sessions`
Neue Session anlegen.

**Response:**
```json
{
  "id": "a1b2c3d4-...",
  "createdAt": "2026-03-17T...",
  "expiresAt": "2026-03-18T..."
}
```

### `GET /api/sessions/:id`
Session-Info + Dateiliste abrufen.

**Response:**
```json
{
  "id": "a1b2c3d4-...",
  "createdAt": "...",
  "expiresAt": "...",
  "files": [
    { "name": "bericht.pdf", "size": 204800, "uploadedAt": "..." },
    { "name": "foto.jpg", "size": 1048576, "uploadedAt": "..." }
  ]
}
```

### `POST /api/sessions/:id/files`
Datei(en) hochladen. Multipart/form-data via `multer`.

### `GET /api/sessions/:id/files/:filename`
Einzelne Datei herunterladen.

### `DELETE /api/sessions/:id/files/:filename`
Einzelne Datei löschen.

---

## Implementierungsschritte (für Claude Code)

### Schritt 1: Projekt-Setup

```
Erstelle das Projekt "filedrop" mit folgender Struktur:
- server/ mit Express.js (ESM, package.json mit type: module)
- client/ mit Vue 3 + Vite (vue-router)
- Installiere Dependencies:
  Server: express, multer, cors, qrcode
  Client: vue, vue-router, vite, @vitejs/plugin-vue
```

### Schritt 2: Backend - Express Server

```
Implementiere server/index.js:

1. Express-App mit CORS
2. Static file serving für das Vue-Build (production)
3. Session-Ordner: ./data/sessions/
4. Routes:
   - POST /api/sessions → Ordner anlegen, meta.json schreiben, ID zurückgeben
   - GET /api/sessions/:id → meta.json lesen, fs.readdir für Dateiliste
   - POST /api/sessions/:id/files → multer upload in Session-Ordner
   - GET /api/sessions/:id/files/:filename → res.download()
   - DELETE /api/sessions/:id/files/:filename → fs.unlink()
5. Middleware: Session-Existenz prüfen (404 wenn Ordner fehlt)
6. Dateigrössenlimit: 50 MB pro Datei
7. Erlaubte Dateitypen: alle (kein Filter)

Sicherheit:
- Filename sanitization (path traversal verhindern)
- Session-ID Validierung (nur UUID-Format akzeptieren)
```

### Schritt 3: Backend - Cleanup

```
Implementiere server/cleanup.js:

- Beim Serverstart: Interval alle 15 Minuten
- Liest alle Session-Ordner
- Prüft meta.json → expiresAt
- Löscht abgelaufene Ordner rekursiv
- Standard-Ablauf: 24 Stunden nach Erstellung
```

### Schritt 4: Frontend - Router + App Shell

```
Implementiere client/src/:

router.js:
- "/" → Home.vue
- "/s/:sessionId" → Session.vue

App.vue:
- Minimaler Wrapper mit <router-view>
- Header mit App-Name "FileDrop"

Globales CSS:
- System Font Stack (kein Google Fonts)
- Schlicht, funktional, kein fancy Design
- Responsive (funktioniert auf Mobile und Desktop)
- Farben: Weiss/Grau-Töne, ein Akzent (#2563eb)
```

### Schritt 5: Frontend - Home View

```
Implementiere Home.vue:

- Ein Button: "Neue Session erstellen"
- Klick → POST /api/sessions → Redirect zu /s/:sessionId
- Kurzer Erklärungstext (1-2 Sätze)
```

### Schritt 6: Frontend - Session View

```
Implementiere Session.vue mit Unterkomponenten:

1. QrCode.vue:
   - Zeigt QR-Code der aktuellen URL
   - Generierung serverseitig: GET /api/sessions/:id/qr
     (gibt PNG als Response zurück, generiert via qrcode-Paket)
   - Button "Link kopieren" (navigator.clipboard)

2. FileUpload.vue:
   - Drag & Drop Zone
   - Alternativ: Datei-Auswahl-Button (input type=file)
   - Mehrere Dateien gleichzeitig möglich
   - Upload-Fortschritt anzeigen (XMLHttpRequest oder fetch mit ReadableStream)
   - Nach Upload: Dateiliste aktualisieren

3. FileList.vue:
   - Tabelle: Dateiname | Grösse | Hochgeladen am | Aktionen
   - Aktionen: Download-Button, Löschen-Button
   - Polling alle 5 Sekunden für neue Dateien (kein WebSocket nötig)
   - Leere Liste: Hinweistext "Noch keine Dateien"
```

### Schritt 7: API Composable

```
Implementiere client/src/composables/useSession.js:

- createSession() → POST /api/sessions
- getSession(id) → GET /api/sessions/:id
- uploadFiles(id, files) → POST mit FormData
- downloadFile(id, filename) → Window.open oder fetch+blob
- deleteFile(id, filename) → DELETE
- getQrCodeUrl(id) → URL-String für QR-Endpunkt

Basis-URL aus Vite-Env oder relativ.
```

### Schritt 8: QR-Code Endpunkt (Backend-Ergänzung)

```
Ergänze server/index.js:

GET /api/sessions/:id/qr
- Generiert QR-Code als PNG mit dem qrcode-Paket
- URL = Konfigurierbare Base-URL + /s/:id
- Content-Type: image/png
- Base-URL via Env-Variable PUBLIC_URL (für Deployment)
```

### Schritt 9: Production Build + Serving

```
Konfiguration:

vite.config.js:
- Proxy /api → localhost:3000 (Development)
- Build Output → ../server/public/

server/index.js:
- express.static('./public') für Production
- SPA Fallback: Alle nicht-API-Routes → index.html

package.json (Root):
- "dev": Startet Server + Vite parallel
- "build": Baut Frontend, kopiert nach server/public/
- "start": Startet nur den Server (Production)
```

### Schritt 10: Feinschliff

```
1. Fehlerbehandlung:
   - Session nicht gefunden → Fehlermeldung im Frontend
   - Upload fehlgeschlagen → Retry-Hinweis
   - Abgelaufene Session → Hinweis + "Neue Session"-Link

2. UX-Details:
   - Dateigrössen menschenlesbar (KB, MB)
   - Zeitstempel formatiert
   - Mobile: QR-Code gross genug zum Scannen
   - Copy-to-Clipboard Feedback

3. Sicherheit (Review):
   - Path Traversal in Dateinamen prüfen
   - Session-ID strikt als UUID validieren
   - Rate Limiting für Session-Erstellung (optional)
   - Max. Dateien pro Session begrenzen (z.B. 20)
```

---

## Deployment-Optionen

| Option | Aufwand | Kosten |
|---|---|---|
| Eigener Server (VPS) | Gering | ~5 CHF/Monat |
| Railway / Render | Minimal | Free Tier möglich |
| Lokales Netzwerk | Kein Deployment | Gratis |

Für lokale Nutzung: `node server/index.js` starten, Port im LAN freigeben.

---

## Nicht im Scope (bewusst weggelassen)

- Benutzerkonten / Login
- Ende-zu-Ende-Verschlüsselung
- Versionierung von Dateien
- Chat / Kommentare
- WebSocket (Polling reicht für den Anwendungsfall)

---

## Zusammenfassung Befehle für Claude Code

Die Schritte oben können direkt als Prompts an Claude Code gegeben werden. Empfohlene Reihenfolge:

1. Projekt-Scaffolding + Dependencies
2. Backend komplett (Routes, Cleanup, QR)
3. Frontend Shell (Router, App, CSS)
4. Frontend Views + Komponenten
5. Integration + Build-Pipeline
6. Test + Feinschliff
