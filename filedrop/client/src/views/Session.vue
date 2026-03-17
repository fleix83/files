<template>
  <div v-if="loading" class="loading">Laden…</div>

  <div v-else-if="error === 'expired'" class="error-state">
    <h2>Session abgelaufen</h2>
    <p>Diese Session ist nicht mehr verfügbar.</p>
    <router-link to="/" class="btn btn-primary">Neue Session erstellen</router-link>
  </div>

  <div v-else-if="error === 'not_found'" class="error-state">
    <h2>Session nicht gefunden</h2>
    <p>Dieser Link ist ungültig oder die Session wurde gelöscht.</p>
    <router-link to="/" class="btn btn-primary">Neue Session erstellen</router-link>
  </div>

  <div v-else-if="session" class="session">
    <div class="session-header">
      <QrCode :session-id="session.id" />
    </div>
    <FileUpload :session-id="session.id" @uploaded="refreshFiles" />
    <FileList :files="files" :session-id="session.id" @deleted="refreshFiles" />
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useRoute } from 'vue-router';
import { useSession } from '../composables/useSession.js';
import QrCode from '../components/QrCode.vue';
import FileUpload from '../components/FileUpload.vue';
import FileList from '../components/FileList.vue';

const route = useRoute();
const { getSession } = useSession();

const session = ref(null);
const files = ref([]);
const loading = ref(true);
const error = ref('');
let pollInterval = null;

async function loadSession() {
  try {
    const data = await getSession(route.params.sessionId);
    session.value = data;
    files.value = data.files;
    error.value = '';
  } catch (err) {
    error.value = err.message;
  }
}

async function refreshFiles() {
  try {
    const data = await getSession(route.params.sessionId);
    files.value = data.files;
  } catch {
    // ignore polling errors
  }
}

onMounted(async () => {
  await loadSession();
  loading.value = false;
  pollInterval = setInterval(refreshFiles, 5000);
});

onUnmounted(() => {
  if (pollInterval) clearInterval(pollInterval);
});
</script>

<style scoped>
.loading {
  text-align: center;
  color: #6b7280;
  padding: 3rem 0;
}

.error-state {
  text-align: center;
  padding: 3rem 0;
}

.error-state h2 {
  margin-bottom: 0.5rem;
}

.error-state p {
  color: #6b7280;
  margin-bottom: 1.5rem;
}

.session-header {
  margin-bottom: 1.5rem;
}
</style>
