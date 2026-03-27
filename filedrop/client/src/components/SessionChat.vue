<template>
  <div class="chat">
    <div class="chat-header">
      <h3>Chat</h3>
      <a v-if="messages.length" href="#" class="export-link" @click.prevent="exportChat">Chat speichern</a>
    </div>
    <div class="chat-messages" ref="messagesEl">
      <p v-if="!messages.length" class="empty">Noch keine Nachrichten.</p>
      <div v-for="msg in messages" :key="msg.id" class="message">
        <span class="blob" :style="{ background: colorFor(msg.name) }">{{ initial(msg.name) }}</span>
        <div class="message-body">
          <span class="message-name" :style="{ color: colorFor(msg.name) }">{{ msg.name }}</span>
          <span class="message-time">{{ formatTime(msg.time) }}</span>
          <div class="message-text">{{ msg.text }}</div>
        </div>
        <button class="copy-btn" @click="copyMessage(msg)" :title="'Nachricht kopieren'">
          <svg v-if="copiedId !== msg.id" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
          <svg v-else xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        </button>
      </div>
    </div>
    <form class="chat-form" @submit.prevent="send">
      <input
        v-model="name"
        class="input input-name"
        type="text"
        placeholder="Name"
        maxlength="50"
      />
      <input
        v-model="text"
        class="input input-text"
        type="text"
        placeholder="Nachricht…"
        maxlength="500"
      />
      <button type="submit" class="btn btn-primary btn-sm" :disabled="!text.trim()">Senden</button>
    </form>
  </div>
</template>

<script setup>
import { ref, watch, nextTick, onMounted } from 'vue';
import { useSession } from '../composables/useSession.js';

const props = defineProps({ sessionId: String, messages: Array });
const emit = defineEmits(['sent']);
const { sendChat } = useSession();

const messagesEl = ref(null);
const name = ref(localStorage.getItem('chat-name') || '');
const text = ref('');
const copiedId = ref(null);


watch(() => name.value, (val) => {
  localStorage.setItem('chat-name', val);
});

watch(() => props.messages.length, async () => {
  await nextTick();
  scrollToBottom();
});

onMounted(() => {
  scrollToBottom();
});

function scrollToBottom() {
  if (messagesEl.value) {
    messagesEl.value.scrollTop = messagesEl.value.scrollHeight;
  }
}

async function send() {
  if (!text.value.trim()) return;
  try {
    await sendChat(props.sessionId, name.value || 'Anon', text.value.trim());
    text.value = '';
    emit('sent');
  } catch {
    // ignore
  }
}

const BLOB_COLORS = [
  '#e74c3c', '#e67e22', '#f1c40f', '#2ecc71', '#1abc9c',
  '#3498db', '#9b59b6', '#e84393', '#00b894', '#6c5ce7',
];

function hashName(name) {
  let h = 0;
  for (let i = 0; i < name.length; i++) {
    h = name.charCodeAt(i) + ((h << 5) - h);
  }
  return Math.abs(h);
}

function colorFor(name) {
  return BLOB_COLORS[hashName(name) % BLOB_COLORS.length];
}

function initial(name) {
  return (name || '?').charAt(0).toUpperCase();
}

async function copyMessage(msg) {
  try {
    await navigator.clipboard.writeText(msg.text);
    copiedId.value = msg.id;
    setTimeout(() => { copiedId.value = null; }, 1500);
  } catch {
    // fallback ignored
  }
}

function exportChat() {
  const lines = props.messages.map((msg) => {
    const time = formatTime(msg.time);
    return `[${time}] ${msg.name}: ${msg.text}`;
  });
  const blob = new Blob([lines.join('\n')], { type: 'text/plain' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `chat-${props.sessionId}.txt`;
  a.click();
  URL.revokeObjectURL(url);
}

function formatTime(iso) {
  const d = new Date(iso);
  return d.toLocaleString('de-CH', {
    hour: '2-digit',
    minute: '2-digit',
  });
}
</script>

<style scoped>
.chat {
  margin-top: 1.5rem;
}

.chat-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.75rem;
}

.chat h3 {
  font-size: 1rem;
  margin-bottom: 0;
}

.export-link {
  font-size: 0.8125rem;
  color: #3498db;
  text-decoration: none;
}

.export-link:hover {
  text-decoration: underline;
}

.empty {
  color: #9ca3af;
  font-size: 0.875rem;
}

.chat-messages {
  max-height: 300px;
  overflow-y: auto;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  padding: 0.75rem;
  margin-bottom: 0.75rem;
  background: #fff;
}

.message {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
  margin-bottom: 0.5rem;
}

.message:last-child {
  margin-bottom: 0;
}

.blob {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-weight: 700;
  font-size: 0.75rem;
  margin-top: 1px;
}

.copy-btn {
  background: none;
  border: none;
  cursor: pointer;
  color: #d1d5db;
  padding: 2px;
  margin-left: auto;
  flex-shrink: 0;
  align-self: flex-start;
  margin-top: 2px;
  border-radius: 3px;
  transition: color 0.15s;
}

.copy-btn:hover {
  color: #6b7280;
}

.message-body {
  min-width: 0;
  flex: 1;
}

.message-name {
  font-weight: 600;
  font-size: 0.8125rem;
  margin-right: 0.5rem;
}

.message-time {
  font-size: 0.75rem;
  color: #9ca3af;
}

.message-text {
  font-size: 0.875rem;
  margin-top: 0.125rem;
  word-break: break-word;
}

.chat-form {
  display: flex;
  gap: 0.5rem;
}

.input {
  font-family: inherit;
  font-size: 0.875rem;
  padding: 0.375rem 0.625rem;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  outline: none;
}

.input:focus {
  border-color: #729cd8;
}

.input-name {
  width: 100px;
  flex-shrink: 0;
}

.input-text {
  flex: 1;
  min-width: 0;
}

@media (max-width: 480px) {
  .chat-form {
    flex-wrap: wrap;
  }

  .input-name {
    width: 100%;
  }

  .input-text {
    flex: 1;
  }
}
</style>
