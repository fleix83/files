<template>
  <div
    class="upload-zone"
    :class="{ dragging }"
    @dragover.prevent="dragging = true"
    @dragleave.prevent="dragging = false"
    @drop.prevent="handleDrop"
  >
    <div v-if="uploading" class="upload-progress">
      <div class="progress-bar">
        <div class="progress-fill" :style="{ width: progress + '%' }"></div>
      </div>
      <span class="progress-text">{{ progress }}%</span>
    </div>
    <div v-else class="upload-content">
      <p>Dateien hierher ziehen oder</p>
      <label class="btn btn-primary btn-sm">
        Dateien auswählen
        <input type="file" multiple hidden @change="handleSelect" />
      </label>
    </div>
    <p v-if="error" class="upload-error">{{ error }}</p>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useSession } from '../composables/useSession.js';

const props = defineProps({ sessionId: String });
const emit = defineEmits(['uploaded']);
const { uploadFiles } = useSession();

const dragging = ref(false);
const uploading = ref(false);
const progress = ref(0);
const error = ref('');

async function upload(files) {
  if (!files.length) return;
  uploading.value = true;
  progress.value = 0;
  error.value = '';

  try {
    await uploadFiles(props.sessionId, files, (p) => {
      progress.value = p;
    });
    emit('uploaded');
  } catch (err) {
    error.value = err.message || 'Upload fehlgeschlagen. Bitte erneut versuchen.';
  } finally {
    uploading.value = false;
    progress.value = 0;
  }
}

function handleDrop(e) {
  dragging.value = false;
  const files = Array.from(e.dataTransfer.files);
  upload(files);
}

function handleSelect(e) {
  const files = Array.from(e.target.files);
  upload(files);
  e.target.value = '';
}
</script>

<style scoped>
.upload-zone {
  border: 2px dashed #d1d5db;
  border-radius: 8px;
  padding: 1.5rem;
  text-align: center;
  transition: border-color 0.15s, background 0.15s;
  margin-bottom: 1.5rem;
}

.upload-zone.dragging {
  border-color: #729cd8;
  background: #eff6ff;
}

.upload-content p {
  color: #6b7280;
  margin-bottom: 0.5rem;
  font-size: 0.875rem;
}

.upload-progress {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.progress-bar {
  flex: 1;
  height: 8px;
  background: #e5e7eb;
  border-radius: 4px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: #729cd8;
  transition: width 0.2s;
}

.progress-text {
  font-size: 0.8125rem;
  color: #374151;
  min-width: 3rem;
  text-align: right;
}

.upload-error {
  color: #dc2626;
  font-size: 0.8125rem;
  margin-top: 0.5rem;
}
</style>
