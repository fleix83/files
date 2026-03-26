const API_BASE = '/files/api';

export function useSession() {
  async function createSession() {
    const res = await fetch(`${API_BASE}/sessions`, { method: 'POST' });
    if (!res.ok) throw new Error('Session konnte nicht erstellt werden');
    return res.json();
  }

  async function getSession(id) {
    const res = await fetch(`${API_BASE}/sessions/${id}`);
    if (res.status === 410) throw new Error('expired');
    if (res.status === 404) throw new Error('not_found');
    if (!res.ok) throw new Error('Fehler beim Laden der Session');
    return res.json();
  }

  async function uploadFiles(id, files, onProgress) {
    return new Promise((resolve, reject) => {
      const formData = new FormData();
      for (const file of files) {
        formData.append('files', file);
      }

      const xhr = new XMLHttpRequest();
      xhr.open('POST', `${API_BASE}/sessions/${id}/files`);

      if (onProgress) {
        xhr.upload.addEventListener('progress', (e) => {
          if (e.lengthComputable) {
            onProgress(Math.round((e.loaded / e.total) * 100));
          }
        });
      }

      xhr.onload = () => {
        if (xhr.status >= 200 && xhr.status < 300) {
          resolve(JSON.parse(xhr.responseText));
        } else {
          try {
            const err = JSON.parse(xhr.responseText);
            reject(new Error(err.error || 'Upload fehlgeschlagen'));
          } catch {
            reject(new Error('Upload fehlgeschlagen'));
          }
        }
      };

      xhr.onerror = () => reject(new Error('Netzwerkfehler'));
      xhr.send(formData);
    });
  }

  async function downloadFile(id, filename) {
    const url = `${API_BASE}/sessions/${id}/files/${encodeURIComponent(filename)}`;
    try {
      const res = await fetch(url);
      if (!res.ok) throw new Error('Download fehlgeschlagen');
      const blob = await res.blob();
      const blobUrl = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = blobUrl;
      a.download = filename;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(blobUrl);
    } catch {
      // Fallback to direct link
      window.location.href = url;
    }
  }

  async function deleteFile(id, filename) {
    const res = await fetch(`${API_BASE}/sessions/${id}/files/${encodeURIComponent(filename)}`, {
      method: 'DELETE',
    });
    if (!res.ok) throw new Error('Datei konnte nicht gelöscht werden');
    return res.json();
  }

  function getQrCodeUrl(id) {
    return `${API_BASE}/sessions/${id}/qr`;
  }

  async function getChat(id) {
    const res = await fetch(`${API_BASE}/sessions/${id}/chat`);
    if (!res.ok) return [];
    return res.json();
  }

  async function sendChat(id, name, text) {
    const res = await fetch(`${API_BASE}/sessions/${id}/chat`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name, text }),
    });
    if (!res.ok) throw new Error('Nachricht konnte nicht gesendet werden');
    return res.json();
  }

  return { createSession, getSession, uploadFiles, downloadFile, deleteFile, getQrCodeUrl, getChat, sendChat };
}
