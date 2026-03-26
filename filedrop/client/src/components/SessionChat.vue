<template>
  <div class="chat">
    <h3>Chat</h3>
    <div class="chat-messages" ref="messagesEl">
      <p v-if="!messages.length" class="empty">Noch keine Nachrichten.</p>
      <div v-for="msg in messages" :key="msg.id" class="message">
        <span class="message-name">{{ msg.name }}</span>
        <span class="message-time">{{ formatTime(msg.time) }}</span>
        <div class="message-text">{{ msg.text }}</div>
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

.chat h3 {
  font-size: 1rem;
  margin-bottom: 0.75rem;
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
  margin-bottom: 0.5rem;
}

.message:last-child {
  margin-bottom: 0;
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
