<template>
  <div class="home">
    <h1>Dateien einfach austauschen</h1>
    <p class="subtitle">
      Erstelle eine Session und teile den Link oder QR-Code.
      Beide Seiten können Dateien hoch- und herunterladen.
    </p>
    <button class="btn btn-primary btn-lg" :disabled="creating" @click="create">
      {{ creating ? 'Wird erstellt…' : 'Neue Session erstellen' }}
    </button>
    <p v-if="error" class="error">{{ error }}</p>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useSession } from '../composables/useSession.js';

const router = useRouter();
const { createSession } = useSession();
const creating = ref(false);
const error = ref('');

async function create() {
  creating.value = true;
  error.value = '';
  try {
    const session = await createSession();
    router.push(`/s/${session.id}`);
  } catch {
    error.value = 'Session konnte nicht erstellt werden. Bitte versuche es erneut.';
  } finally {
    creating.value = false;
  }
}
</script>

<style scoped>
.home {
  text-align: center;
  padding-top: 4rem;
}

h1 {
  font-size: 2.25rem;
  margin-bottom: 0.5rem;
}

.subtitle {
  color: #ffffff;
  margin-bottom: 2rem;
  max-width: 594px;
  margin-left: auto;
  margin-right: auto;
  font-size: 1.7rem;
  line-height: 1.2;
}

.btn-lg {
  padding: 0.75rem 2rem;
  font-size: 1rem;
}

.error {
  color: #dc2626;
  margin-top: 1rem;
  font-size: 0.875rem;
}
</style>
