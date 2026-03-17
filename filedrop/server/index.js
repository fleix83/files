import express from 'express';
import cors from 'cors';
import multer from 'multer';
import QRCode from 'qrcode';
import { readdir, mkdir, writeFile, readFile, unlink, stat } from 'fs/promises';
import { existsSync } from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { startCleanup } from './cleanup.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const DATA_DIR = path.join(__dirname, '..', 'data', 'sessions');
const PUBLIC_DIR = path.join(__dirname, 'public');
const PORT = process.env.PORT || 3000;
const PUBLIC_URL = process.env.PUBLIC_URL || `http://localhost:${PORT}`;
const SESSION_TTL_HOURS = parseInt(process.env.SESSION_TTL_HOURS || '24', 10);
const MAX_FILES_PER_SESSION = 20;
const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50 MB

const UUID_REGEX = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

const app = express();
app.use(cors());
app.use(express.json());

// Ensure data directory exists
if (!existsSync(DATA_DIR)) {
  await mkdir(DATA_DIR, { recursive: true });
}

// --- Helpers ---

function sanitizeFilename(name) {
  return path.basename(name).replace(/[^a-zA-Z0-9._\-()äöüÄÖÜéèàêâ ]/g, '_');
}

function validateSessionId(id) {
  return UUID_REGEX.test(id);
}

async function getSessionMeta(sessionId) {
  const metaPath = path.join(DATA_DIR, sessionId, 'meta.json');
  const raw = await readFile(metaPath, 'utf-8');
  return JSON.parse(raw);
}

async function getSessionFiles(sessionId) {
  const sessionDir = path.join(DATA_DIR, sessionId);
  const entries = await readdir(sessionDir);
  const files = [];
  for (const entry of entries) {
    if (entry === 'meta.json') continue;
    const filePath = path.join(sessionDir, entry);
    const stats = await stat(filePath);
    if (stats.isFile()) {
      files.push({
        name: entry,
        size: stats.size,
        uploadedAt: stats.mtime.toISOString(),
      });
    }
  }
  return files.sort((a, b) => new Date(b.uploadedAt) - new Date(a.uploadedAt));
}

// --- Session validation middleware ---

function sessionMiddleware(req, res, next) {
  const { id } = req.params;
  if (!validateSessionId(id)) {
    return res.status(400).json({ error: 'Ungültige Session-ID' });
  }
  const sessionDir = path.join(DATA_DIR, id);
  if (!existsSync(sessionDir)) {
    return res.status(404).json({ error: 'Session nicht gefunden' });
  }
  req.sessionDir = sessionDir;
  next();
}

// --- Multer config ---

const storage = multer.diskStorage({
  destination: (req, _file, cb) => {
    cb(null, req.sessionDir);
  },
  filename: (_req, file, cb) => {
    const safe = sanitizeFilename(Buffer.from(file.originalname, 'latin1').toString('utf-8'));
    cb(null, safe);
  },
});

const upload = multer({
  storage,
  limits: { fileSize: MAX_FILE_SIZE },
});

// --- API Routes ---

// Create session
app.post('/api/sessions', async (_req, res) => {
  try {
    const id = crypto.randomUUID();
    const sessionDir = path.join(DATA_DIR, id);
    await mkdir(sessionDir, { recursive: true });

    const now = new Date();
    const expiresAt = new Date(now.getTime() + SESSION_TTL_HOURS * 60 * 60 * 1000);
    const meta = {
      id,
      createdAt: now.toISOString(),
      expiresAt: expiresAt.toISOString(),
    };
    await writeFile(path.join(sessionDir, 'meta.json'), JSON.stringify(meta, null, 2));
    res.status(201).json(meta);
  } catch (err) {
    console.error('Session creation error:', err);
    res.status(500).json({ error: 'Session konnte nicht erstellt werden' });
  }
});

// Get session info + file list
app.get('/api/sessions/:id', sessionMiddleware, async (req, res) => {
  try {
    const meta = await getSessionMeta(req.params.id);
    if (new Date(meta.expiresAt) < new Date()) {
      return res.status(410).json({ error: 'Session abgelaufen' });
    }
    const files = await getSessionFiles(req.params.id);
    res.json({ ...meta, files });
  } catch (err) {
    console.error('Get session error:', err);
    res.status(500).json({ error: 'Session konnte nicht geladen werden' });
  }
});

// Upload files
app.post('/api/sessions/:id/files', sessionMiddleware, async (req, res) => {
  try {
    const meta = await getSessionMeta(req.params.id);
    if (new Date(meta.expiresAt) < new Date()) {
      return res.status(410).json({ error: 'Session abgelaufen' });
    }
    const existingFiles = await getSessionFiles(req.params.id);
    if (existingFiles.length >= MAX_FILES_PER_SESSION) {
      return res.status(400).json({ error: `Maximal ${MAX_FILES_PER_SESSION} Dateien pro Session` });
    }

    upload.array('files', MAX_FILES_PER_SESSION)(req, res, async (err) => {
      if (err) {
        if (err.code === 'LIMIT_FILE_SIZE') {
          return res.status(413).json({ error: 'Datei zu gross (max. 50 MB)' });
        }
        return res.status(400).json({ error: err.message });
      }
      const files = await getSessionFiles(req.params.id);
      res.json({ files });
    });
  } catch (err) {
    console.error('Upload error:', err);
    res.status(500).json({ error: 'Upload fehlgeschlagen' });
  }
});

// Download file
app.get('/api/sessions/:id/files/:filename', sessionMiddleware, (req, res) => {
  const filename = sanitizeFilename(req.params.filename);
  const filePath = path.join(req.sessionDir, filename);
  if (!existsSync(filePath)) {
    return res.status(404).json({ error: 'Datei nicht gefunden' });
  }
  res.download(filePath, filename);
});

// Delete file
app.delete('/api/sessions/:id/files/:filename', sessionMiddleware, async (req, res) => {
  try {
    const filename = sanitizeFilename(req.params.filename);
    const filePath = path.join(req.sessionDir, filename);
    if (!existsSync(filePath)) {
      return res.status(404).json({ error: 'Datei nicht gefunden' });
    }
    await unlink(filePath);
    const files = await getSessionFiles(req.params.id);
    res.json({ files });
  } catch (err) {
    console.error('Delete error:', err);
    res.status(500).json({ error: 'Datei konnte nicht gelöscht werden' });
  }
});

// QR Code endpoint
app.get('/api/sessions/:id/qr', sessionMiddleware, async (req, res) => {
  try {
    const url = `${PUBLIC_URL}/s/${req.params.id}`;
    const png = await QRCode.toBuffer(url, { width: 300, margin: 2 });
    res.set('Content-Type', 'image/png');
    res.send(png);
  } catch (err) {
    console.error('QR generation error:', err);
    res.status(500).json({ error: 'QR-Code konnte nicht generiert werden' });
  }
});

// --- Static files + SPA fallback (production) ---

if (existsSync(PUBLIC_DIR)) {
  app.use(express.static(PUBLIC_DIR));
  app.get('*', (req, res) => {
    if (!req.path.startsWith('/api')) {
      res.sendFile(path.join(PUBLIC_DIR, 'index.html'));
    }
  });
}

// --- Start ---

startCleanup(DATA_DIR);

app.listen(PORT, () => {
  console.log(`FileDrop Server läuft auf ${PUBLIC_URL}`);
});
