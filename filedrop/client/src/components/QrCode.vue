<template>
  <div class="qr-section">
    <div class="qr-row">
      <img :src="qrUrl" alt="QR-Code" class="qr-image" />
      <div class="qr-info">
        <p class="qr-label">Teile diesen Link:</p>
        <code class="qr-link">{{ sessionUrl }}</code>
        <div class="qr-actions">
          <button class="btn btn-secondary btn-sm" @click="copyLink">
            {{ copied ? 'Kopiert!' : 'Link kopieren' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useSession } from '../composables/useSession.js';

const props = defineProps({ sessionId: String });
const { getQrCodeUrl } = useSession();

const qrUrl = computed(() => getQrCodeUrl(props.sessionId));
const sessionUrl = computed(() => `${window.location.origin}/s/${props.sessionId}`);
const copied = ref(false);

async function copyLink() {
  try {
    await navigator.clipboard.writeText(sessionUrl.value);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
  } catch {
    // fallback
    const input = document.createElement('input');
    input.value = sessionUrl.value;
    document.body.appendChild(input);
    input.select();
    document.execCommand('copy');
    document.body.removeChild(input);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
  }
}
</script>

<style scoped>
.qr-section {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 1rem;
}

.qr-row {
  display: flex;
  gap: 1rem;
  align-items: center;
}

.qr-image {
  width: 120px;
  height: 120px;
  flex-shrink: 0;
}

.qr-info {
  min-width: 0;
}

.qr-label {
  font-size: 0.8125rem;
  color: #6b7280;
  margin-bottom: 0.25rem;
}

.qr-link {
  display: block;
  font-size: 0.75rem;
  background: #f3f4f6;
  padding: 0.375rem 0.5rem;
  border-radius: 4px;
  word-break: break-all;
  margin-bottom: 0.5rem;
}

.qr-actions {
  display: flex;
  gap: 0.5rem;
}

@media (max-width: 480px) {
  .qr-row {
    flex-direction: column;
    text-align: center;
  }

  .qr-image {
    width: 160px;
    height: 160px;
  }

  .qr-actions {
    justify-content: center;
  }
}
</style>
