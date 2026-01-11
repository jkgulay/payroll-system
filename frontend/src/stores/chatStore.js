import { defineStore } from 'pinia';
import chatService from '@/services/chatService';

export const useChatStore = defineStore('chat', {
  state: () => ({
    messages: [],
    isTyping: false,
    isOnline: true,
    suggestions: {
      employee: [],
      payroll: [],
      attendance: [],
      leave: [],
      compliance: [],
      help: [],
    },
  }),

  actions: {
    async sendMessage(message) {
      // Add user message
      this.messages.push({
        role: 'user',
        content: message,
        timestamp: new Date().toISOString(),
      });

      // Show typing indicator
      this.isTyping = true;

      try {
        // Get conversation history (last 10 messages)
        const conversationHistory = this.messages
          .slice(-10)
          .map(msg => ({
            role: msg.role,
            content: msg.content,
          }));

        // Send to API
        const response = await chatService.sendMessage(message, conversationHistory);

        if (response.success) {
          // Add assistant response
          this.messages.push({
            role: 'assistant',
            content: response.message,
            timestamp: new Date().toISOString(),
            intent: response.intent,
          });
        } else {
          // Add error message
          this.messages.push({
            role: 'assistant',
            content: 'I apologize, but I encountered an error while processing your request. Please try again.',
            timestamp: new Date().toISOString(),
            isError: true,
          });
        }
      } catch (error) {
        console.error('Chat error:', error);
        
        // Add error message
        this.messages.push({
          role: 'assistant',
          content: 'I\'m sorry, I\'m having trouble connecting right now. Please check your connection and try again.',
          timestamp: new Date().toISOString(),
          isError: true,
        });

        this.isOnline = false;
      } finally {
        this.isTyping = false;
      }
    },

    async loadSuggestions() {
      try {
        const categories = ['employee', 'payroll', 'attendance', 'leave', 'compliance', 'help'];
        
        for (const category of categories) {
          const response = await chatService.getSuggestions(category);
          if (response.success) {
            this.suggestions[category] = response.suggestions;
          }
        }
      } catch (error) {
        console.error('Failed to load suggestions:', error);
      }
    },

    async clearHistory() {
      try {
        await chatService.clearHistory();
        this.messages = [];
      } catch (error) {
        console.error('Failed to clear history:', error);
      }
    },

    // Restore messages from localStorage
    restoreMessages() {
      const saved = localStorage.getItem('chat_messages');
      if (saved) {
        try {
          this.messages = JSON.parse(saved);
        } catch (error) {
          console.error('Failed to restore messages:', error);
        }
      }
    },

    // Save messages to localStorage
    saveMessages() {
      try {
        localStorage.setItem('chat_messages', JSON.stringify(this.messages));
      } catch (error) {
        console.error('Failed to save messages:', error);
      }
    },
  },
});
