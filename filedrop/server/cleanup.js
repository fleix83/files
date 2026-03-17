import { readdir, readFile, rm } from 'fs/promises';
import path from 'path';

const CLEANUP_INTERVAL_MS = 15 * 60 * 1000; // 15 minutes

async function cleanExpiredSessions(dataDir) {
  try {
    const entries = await readdir(dataDir);
    const now = new Date();

    for (const entry of entries) {
      const sessionDir = path.join(dataDir, entry);
      const metaPath = path.join(sessionDir, 'meta.json');

      try {
        const raw = await readFile(metaPath, 'utf-8');
        const meta = JSON.parse(raw);

        if (new Date(meta.expiresAt) < now) {
          await rm(sessionDir, { recursive: true, force: true });
          console.log(`Abgelaufene Session gelöscht: ${entry}`);
        }
      } catch {
        // Skip sessions without valid meta.json
      }
    }
  } catch (err) {
    console.error('Cleanup error:', err);
  }
}

export function startCleanup(dataDir) {
  // Run once at startup
  cleanExpiredSessions(dataDir);

  // Then every 15 minutes
  setInterval(() => cleanExpiredSessions(dataDir), CLEANUP_INTERVAL_MS);
}
