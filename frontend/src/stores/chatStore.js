import { defineStore } from 'pinia';
import chatService from '@/services/chatService';
import { devLog } from "@/utils/devLog";

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
      // Clean and validate message before sending
      const cleanedMessage = message.trim();
      
      // Check if message is too short or empty
      if (cleanedMessage.length < 2) {
        this.messages.push({
          role: 'assistant',
          content: 'Please type a valid question or message. Your message seems too short.',
          timestamp: new Date().toISOString(),
          isError: false,
        });
        return;
      }

      // Add user message
      this.messages.push({
        role: 'user',
        content: cleanedMessage,
        timestamp: new Date().toISOString(),
      });

      // Show typing indicator
      this.isTyping = true;
      this.isOnline = true; // Assume online when attempting

      try {
        // Get conversation history (last 10 messages)
        const conversationHistory = this.messages
          .slice(-10)
          .filter(msg => msg.role) // Ensure valid messages only
          .map(msg => ({
            role: msg.role,
            content: msg.content,
          }));

        // Send to API with timeout handling
        const response = await Promise.race([
          chatService.sendMessage(cleanedMessage, conversationHistory),
          new Promise((_, reject) => 
            setTimeout(() => reject(new Error('Request timeout')), 30000)
          )
        ]);

        if (response.success && response.message) {
          // Add assistant response
          this.messages.push({
            role: 'assistant',
            content: response.message,
            timestamp: new Date().toISOString(),
            intent: response.intent,
          });
          this.isOnline = true;
        } else {
          // Add friendly error message
          this.messages.push({
            role: 'assistant',
            content: 'I understand your question, but I\'m having a bit of trouble processing it right now. Could you try rephrasing it or asking something else?',
            timestamp: new Date().toISOString(),
            isError: false,
          });
        }
      } catch (error) {
        devLog.error('Chat error:', error);
        
        let errorMessage = 'I apologize for the inconvenience. ';
        
        if (error.message === 'Request timeout') {
          errorMessage += 'The request took too long to process. Please try asking a simpler question or try again in a moment.';
        } else if (error.message?.includes('Network')) {
          errorMessage += 'I\'m having trouble connecting to the server. Please check your internet connection and try again.';
          this.isOnline = false;
        } else {
          errorMessage += 'I encountered an issue processing your request. Please try rephrasing your question or ask something else.';
        }
        
        // Add error message
        this.messages.push({
          role: 'assistant',
          content: errorMessage,
          timestamp: new Date().toISOString(),
          isError: true,
        });
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
        devLog.error('Failed to load suggestions:', error);
      }
    },

    async clearHistory() {
      try {
        await chatService.clearHistory();
        this.messages = [];
      } catch (error) {
        devLog.error('Failed to clear history:', error);
      }
    },

    // Restore messages from localStorage
    restoreMessages() {
      const saved = localStorage.getItem('chat_messages');
      if (saved) {
        try {
          this.messages = JSON.parse(saved);
        } catch (error) {
          devLog.error('Failed to restore messages:', error);
        }
      }
    },

    // Save messages to localStorage
    saveMessages() {
      try {
        localStorage.setItem('chat_messages', JSON.stringify(this.messages));
      } catch (error) {
        devLog.error('Failed to save messages:', error);
      }
    },
  },
});
