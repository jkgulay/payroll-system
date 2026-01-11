<template>
  <div class="chat-container" :class="{ 'chat-expanded': isExpanded }">
    <!-- Chat Toggle Button -->
    <button 
      v-if="!isExpanded" 
      class="chat-toggle-btn"
      @click="toggleChat"
      :aria-label="'Open AI Assistant'"
      title="Open AI Assistant"
    >
      <v-icon icon="mdi-robot-excited" size="32" color="white"></v-icon>
      <span class="badge" v-if="unreadCount > 0">{{ unreadCount }}</span>
    </button>

    <!-- Chat Window -->
    <div v-if="isExpanded" class="chat-window">
      <!-- Header -->
      <div class="chat-header">
        <div class="chat-header-content">
          <v-icon icon="mdi-robot-excited" size="32" color="white"></v-icon>
          <div>
            <h3>AI Assistant</h3>
            <span class="status" :class="{ 'online': isOnline }">
              {{ isOnline ? 'Online' : 'Offline' }}
            </span>
          </div>
        </div>
        <div class="chat-header-actions">
          <button @click="showSuggestions = !showSuggestions" title="Suggested Questions">
            <v-icon icon="mdi-lightbulb-on-outline" size="20" color="white"></v-icon>
          </button>
          <button @click="clearConversation" title="Clear Chat">
            <v-icon icon="mdi-delete-outline" size="20" color="white"></v-icon>
          </button>
          <button @click="toggleChat" title="Minimize">
            <v-icon icon="mdi-minus" size="20" color="white"></v-icon>
          </button>
        </div>
      </div>

      <!-- Suggestions Panel -->
      <div v-if="showSuggestions" class="suggestions-panel">
        <div class="suggestions-tabs">
          <button 
            v-for="tab in suggestionTabs" 
            :key="tab.id"
            :class="{ active: activeTab === tab.id }"
            @click="activeTab = tab.id"
          >
            <v-icon :icon="tab.icon" size="16"></v-icon> {{ tab.label }}
          </button>
        </div>
        <div class="suggestions-list">
          <button 
            v-for="(question, index) in filteredSuggestions" 
            :key="index"
            class="suggestion-item"
            @click="sendSuggestedQuestion(question)"
          >
            <v-icon icon="mdi-message-text" size="16" color="#ff9800"></v-icon>
            {{ question }}
          </button>
        </div>
      </div>

      <!-- Messages -->
      <div class="chat-messages" ref="messagesContainer">
        <!-- Welcome Message -->
        <div v-if="messages.length === 0" class="welcome-message">
          <v-icon icon="mdi-robot-excited" size="64" color="#ff9800"></v-icon>
          <h4>Welcome to AI Assistant!</h4>
          <p>I can help you with:</p>
          <ul>
            <li><v-icon icon="mdi-account-group" size="16" color="#ff9800"></v-icon> Employee information and analytics</li>
            <li><v-icon icon="mdi-cash-multiple" size="16" color="#ff9800"></v-icon> Payroll calculations and reports</li>
            <li><v-icon icon="mdi-calendar-check" size="16" color="#ff9800"></v-icon> Attendance and leave management</li>
            <li><v-icon icon="mdi-file-document-check" size="16" color="#ff9800"></v-icon> Compliance and tax information</li>
            <li><v-icon icon="mdi-help-circle" size="16" color="#ff9800"></v-icon> System help and guidance</li>
          </ul>
          <p class="tip"><v-icon icon="mdi-lightbulb-on" size="16" color="#ff6f00"></v-icon> Try asking a question or click the lightbulb icon for suggestions!</p>
        </div>

        <!-- Message List -->
        <div 
          v-for="(message, index) in messages" 
          :key="index"
          class="message"
          :class="message.role"
        >
          <div class="message-avatar">
            <v-icon :icon="message.role === 'user' ? 'mdi-account-circle' : 'mdi-robot'" size="24" color="white"></v-icon>
          </div>
          <div class="message-content">
            <div class="message-text" v-html="formatMessage(message.content)"></div>
            <div class="message-time">{{ formatTime(message.timestamp) }}</div>
          </div>
        </div>

        <!-- Typing Indicator -->
        <div v-if="isTyping" class="message assistant">
          <div class="message-avatar">
            <v-icon icon="mdi-robot" size="24" color="white"></v-icon>
          </div>
          <div class="message-content">
            <div class="typing-indicator">
              <span></span>
              <span></span>
              <span></span>
            </div>
          </div>
        </div>
      </div>

      <!-- Input -->
      <div class="chat-input">
        <form @submit.prevent="sendMessage">
          <textarea
            v-model="inputMessage"
            placeholder="Ask me anything about the payroll system..."
            rows="2"
            @keydown.enter.exact.prevent="sendMessage"
            @keydown.shift.enter="handleShiftEnter"
            :disabled="isTyping"
            ref="messageInput"
          ></textarea>
          <button 
            type="submit" 
            :disabled="!inputMessage.trim() || isTyping"
            title="Send Message (Enter)"
          >
            <v-icon icon="mdi-send" size="20" color="white"></v-icon>
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted, nextTick, watch } from 'vue';
import { useChatStore } from '@/stores/chatStore';
import { marked } from 'marked';
import DOMPurify from 'dompurify';

export default {
  name: 'ChatBot',
  setup() {
    const chatStore = useChatStore();
    
    const isExpanded = ref(false);
    const inputMessage = ref('');
    const messagesContainer = ref(null);
    const messageInput = ref(null);
    const showSuggestions = ref(false);
    const activeTab = ref('employee');
    const unreadCount = ref(0);

    const suggestionTabs = [
      { id: 'employee', label: 'Employees', icon: 'mdi-account-group' },
      { id: 'payroll', label: 'Payroll', icon: 'mdi-cash-multiple' },
      { id: 'attendance', label: 'Attendance', icon: 'mdi-calendar-check' },
      { id: 'leave', label: 'Leave', icon: 'mdi-calendar-account' },
      { id: 'compliance', label: 'Compliance', icon: 'mdi-file-document-check' },
      { id: 'help', label: 'Help', icon: 'mdi-help-circle' },
    ];

    const filteredSuggestions = computed(() => {
      return chatStore.suggestions[activeTab.value] || [];
    });

    const messages = computed(() => chatStore.messages);
    const isTyping = computed(() => chatStore.isTyping);
    const isOnline = computed(() => chatStore.isOnline);

    const toggleChat = () => {
      isExpanded.value = !isExpanded.value;
      if (isExpanded.value) {
        unreadCount.value = 0;
        nextTick(() => {
          messageInput.value?.focus();
          scrollToBottom();
        });
      }
    };

    const sendMessage = async () => {
      if (!inputMessage.value.trim() || isTyping.value) return;

      const message = inputMessage.value.trim();
      inputMessage.value = '';

      await chatStore.sendMessage(message);
      scrollToBottom();
    };

    const sendSuggestedQuestion = async (question) => {
      inputMessage.value = question;
      showSuggestions.value = false;
      await sendMessage();
    };

    const clearConversation = async () => {
      if (confirm('Are you sure you want to clear the conversation?')) {
        await chatStore.clearHistory();
      }
    };

    const formatMessage = (content) => {
      // Convert markdown to HTML
      const html = marked(content);
      // Sanitize HTML to prevent XSS
      return DOMPurify.sanitize(html);
    };

    const formatTime = (timestamp) => {
      const date = new Date(timestamp);
      return date.toLocaleTimeString('en-US', { 
        hour: '2-digit', 
        minute: '2-digit' 
      });
    };

    const scrollToBottom = () => {
      nextTick(() => {
        if (messagesContainer.value) {
          messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
        }
      });
    };

    const handleShiftEnter = (e) => {
      // Allow shift+enter for new line
      return true;
    };

    // Load suggestions on mount
    onMounted(async () => {
      await chatStore.loadSuggestions();
    });

    // Watch for new messages when chat is minimized
    watch(messages, (newMessages, oldMessages) => {
      if (!isExpanded.value && newMessages.length > oldMessages.length) {
        const lastMessage = newMessages[newMessages.length - 1];
        if (lastMessage.role === 'assistant') {
          unreadCount.value++;
        }
      }
    });

    return {
      isExpanded,
      inputMessage,
      messagesContainer,
      messageInput,
      showSuggestions,
      activeTab,
      unreadCount,
      suggestionTabs,
      filteredSuggestions,
      messages,
      isTyping,
      isOnline,
      toggleChat,
      sendMessage,
      sendSuggestedQuestion,
      clearConversation,
      formatMessage,
      formatTime,
      handleShiftEnter,
    };
  },
};
</script>

<style scoped lang="scss">
.chat-container {
  position: fixed;
  bottom: 20px;
  right: 20px;
  z-index: 1000;
}

.chat-toggle-btn {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  background: linear-gradient(135deg, #ff6f00 0%, #ff9800 100%);
  color: white;
  border: none;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(255, 111, 0, 0.3);
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;

  i {
    font-size: 28px;
  }

  .badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #ff4757;
    color: white;
    border-radius: 12px;
    padding: 2px 6px;
    font-size: 11px;
    font-weight: 600;
  }

  &:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 20px rgba(255, 111, 0, 0.4);
  }

  &:active {
    transform: scale(0.95);
  }
}

.chat-window {
  width: 400px;
  height: 600px;
  background: white;
  border-radius: 16px;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  animation: slideUp 0.3s ease;
}

@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.chat-header {
  background: linear-gradient(135deg, #ff6f00 0%, #ff9800 100%);
  color: white;
  padding: 16px;
  display: flex;
  justify-content: space-between;
  align-items: center;

  .chat-header-content {
    display: flex;
    align-items: center;
    gap: 12px;

    i {
      font-size: 28px;
    }

    h3 {
      margin: 0;
      font-size: 18px;
      font-weight: 600;
    }

    .status {
      font-size: 12px;
      opacity: 0.9;
      display: flex;
      align-items: center;
      gap: 4px;

      &.online::before {
        content: '';
        width: 8px;
        height: 8px;
        background: #2ecc71;
        border-radius: 50%;
        display: inline-block;
      }
    }
  }

  .chat-header-actions {
    display: flex;
    gap: 8px;

    button {
      background: rgba(255, 255, 255, 0.2);
      border: none;
      color: white;
      width: 32px;
      height: 32px;
      border-radius: 8px;
      cursor: pointer;
      transition: background 0.2s;

      &:hover {
        background: rgba(255, 255, 255, 0.3);
      }
    }
  }
}

.suggestions-panel {
  background: #f8f9fa;
  border-bottom: 1px solid #e9ecef;
  max-height: 300px;
  overflow-y: auto;

  .suggestions-tabs {
    display: flex;
    padding: 8px;
    gap: 4px;
    overflow-x: auto;
    border-bottom: 1px solid #dee2e6;

    button {
      padding: 6px 12px;
      border: none;
      background: white;
      border-radius: 8px;
      cursor: pointer;
      font-size: 12px;
      white-space: nowrap;
      transition: all 0.2s;

      i {
        margin-right: 4px;
      }

      &.active {
        background: linear-gradient(135deg, #ff6f00 0%, #ff9800 100%);
        color: white;
      }

      &:hover:not(.active) {
        background: #e9ecef;
      }
    }
  }

  .suggestions-list {
    padding: 8px;
    display: flex;
    flex-direction: column;
    gap: 6px;

    .suggestion-item {
      padding: 10px 12px;
      border: none;
      background: white;
      border-radius: 8px;
      cursor: pointer;
      text-align: left;
      font-size: 13px;
      transition: all 0.2s;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);

      i {
        margin-right: 8px;
        color: #ff9800;
      }

      &:hover {
        background: linear-gradient(135deg, #ff6f00 0%, #ff9800 100%);
        color: white;
        transform: translateX(4px);

        i {
          color: white;
        }
      }
    }
  }
}

.chat-messages {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
  background: #f8f9fa;
  
  &::-webkit-scrollbar {
    width: 6px;
  }

  &::-webkit-scrollbar-thumb {
    background: #cbd5e0;
    border-radius: 3px;
  }

  .welcome-message {
    text-align: center;
    padding: 40px 20px;
    color: #64748b;

    i {
      font-size: 48px;
      color: #ff9800;
      margin-bottom: 16px;
    }

    h4 {
      color: #1e293b;
      margin: 0 0 16px 0;
      font-size: 20px;
    }

    p {
      margin: 12px 0;
      font-size: 14px;
    }

    ul {
      text-align: left;
      display: inline-block;
      margin: 12px 0;
      padding-left: 20px;

      li {
        margin: 8px 0;
        font-size: 14px;
      }
    }

    .tip {
      margin-top: 20px;
      padding: 12px;
      background: #fff3e0;
      border-radius: 8px;
      color: #e65100;
      font-size: 13px;
    }
  }

  .message {
    display: flex;
    gap: 12px;
    margin-bottom: 16px;
    animation: fadeIn 0.3s ease;

    &.user {
      flex-direction: row-reverse;

      .message-content {
        background: linear-gradient(135deg, #ff6f00 0%, #ff9800 100%);
        color: white;
        border-radius: 16px 16px 4px 16px;
      }

      .message-time {
        text-align: right;
      }
    }

    &.assistant {
      .message-content {
        background: white;
        color: #1e293b;
        border-radius: 16px 16px 16px 4px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
      }
    }

    .message-avatar {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: linear-gradient(135deg, #ff6f00 0%, #ff9800 100%);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;

      i {
        font-size: 18px;
      }
    }

    .message-content {
      max-width: 70%;
      padding: 12px 16px;

      .message-text {
        font-size: 14px;
        line-height: 1.6;
        word-wrap: break-word;

        :deep(table) {
          width: 100%;
          border-collapse: collapse;
          margin: 8px 0;
          font-size: 13px;

          th, td {
            padding: 8px;
            border: 1px solid #e2e8f0;
            text-align: left;
          }

          th {
            background: #f1f5f9;
            font-weight: 600;
          }
        }

        :deep(code) {
          background: #f1f5f9;
          padding: 2px 6px;
          border-radius: 4px;
          font-size: 13px;
        }

        :deep(pre) {
          background: #1e293b;
          color: #f1f5f9;
          padding: 12px;
          border-radius: 8px;
          overflow-x: auto;
          margin: 8px 0;

          code {
            background: transparent;
            padding: 0;
          }
        }

        :deep(ul), :deep(ol) {
          margin: 8px 0;
          padding-left: 20px;
        }

        :deep(li) {
          margin: 4px 0;
        }
      }

      .message-time {
        font-size: 11px;
        opacity: 0.7;
        margin-top: 6px;
      }
    }
  }
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.typing-indicator {
  display: flex;
  gap: 4px;
  padding: 8px;

  span {
    width: 8px;
    height: 8px;
    background: #cbd5e0;
    border-radius: 50%;
    animation: typing 1.4s infinite;

    &:nth-child(2) {
      animation-delay: 0.2s;
    }

    &:nth-child(3) {
      animation-delay: 0.4s;
    }
  }
}

@keyframes typing {
  0%, 60%, 100% {
    transform: translateY(0);
    opacity: 0.7;
  }
  30% {
    transform: translateY(-10px);
    opacity: 1;
  }
}

.chat-input {
  padding: 16px;
  border-top: 1px solid #e9ecef;
  background: white;

  form {
    display: flex;
    gap: 8px;
    align-items: flex-end;
  }

  textarea {
    flex: 1;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 12px 16px;
    font-size: 14px;
    resize: none;
    max-height: 120px;
    font-family: inherit;
    transition: border-color 0.2s;

    &:focus {
      outline: none;
      border-color: #ff9800;
    }

    &:disabled {
      background: #f8f9fa;
      cursor: not-allowed;
    }
  }

  button[type="submit"] {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: linear-gradient(135deg, #ff6f00 0%, #ff9800 100%);
    color: white;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    flex-shrink: 0;

    &:hover:not(:disabled) {
      transform: scale(1.05);
      box-shadow: 0 4px 12px rgba(255, 111, 0, 0.3);
    }

    &:disabled {
      background: #cbd5e0;
      cursor: not-allowed;
    }
  }
}

@media (max-width: 768px) {
  .chat-window {
    width: calc(100vw - 32px);
    height: calc(100vh - 32px);
    max-width: 100%;
    max-height: 100%;
  }

  .chat-container {
    bottom: 16px;
    right: 16px;
  }
}
</style>
