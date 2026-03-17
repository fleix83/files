<template>
  <div class="file-list">
    <h3>Dateien</h3>
    <p v-if="!files.length" class="empty">Noch keine Dateien hochgeladen.</p>
    <table v-else>
      <thead>
        <tr>
          <th>Dateiname</th>
          <th>Grösse</th>
          <th>Hochgeladen</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="file in files" :key="file.name">
          <td class="filename">{{ file.name }}</td>
          <td class="size">{{ formatSize(file.size) }}</td>
          <td class="date">{{ formatDate(file.uploadedAt) }}</td>
          <td class="actions">
            <button class="btn btn-secondary btn-sm" @click="download(file.name)">Download</button>
            <button class="btn btn-danger btn-sm" @click="remove(file.name)">Löschen</button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { useSession } from '../composables/useSession.js';

const props = defineProps({ files: Array, sessionId: String });
const emit = defineEmits(['deleted']);
const { downloadFile, deleteFile } = useSession();

function download(filename) {
  downloadFile(props.sessionId, filename);
}

async function remove(filename) {
  try {
    await deleteFile(props.sessionId, filename);
    emit('deleted');
  } catch {
    // ignore
  }
}

function formatSize(bytes) {
  if (bytes < 1024) return bytes + ' B';
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

function formatDate(iso) {
  const d = new Date(iso);
  return d.toLocaleString('de-CH', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}
</script>

<style scoped>
.file-list h3 {
  font-size: 1rem;
  margin-bottom: 0.75rem;
}

.empty {
  color: #9ca3af;
  font-size: 0.875rem;
}

table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}

thead th {
  text-align: left;
  padding: 0.5rem 0.5rem;
  border-bottom: 2px solid #e5e7eb;
  font-weight: 600;
  color: #6b7280;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.025em;
}

tbody td {
  padding: 0.5rem 0.5rem;
  border-bottom: 1px solid #f3f4f6;
  vertical-align: middle;
}

.filename {
  word-break: break-all;
  max-width: 200px;
}

.size,
.date {
  white-space: nowrap;
  color: #6b7280;
}

.actions {
  white-space: nowrap;
  text-align: right;
  display: flex;
  gap: 0.375rem;
  justify-content: flex-end;
}

@media (max-width: 600px) {
  thead {
    display: none;
  }

  tbody tr {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem 1rem;
    padding: 0.75rem 0;
    border-bottom: 1px solid #f3f4f6;
  }

  tbody td {
    border: none;
    padding: 0;
  }

  .filename {
    width: 100%;
    font-weight: 500;
  }

  .actions {
    width: 100%;
    margin-top: 0.25rem;
  }
}
</style>
