import api from './api';

const chatService = {
  /**
   * Send a chat message
   */
  async sendMessage(message, conversationHistory = []) {
    try {
      const response = await api.post('/chat', {
        message,
        conversation_history: conversationHistory,
      });

      return {
        success: true,
        message: response.data.message,
        intent: response.data.intent,
        usage: response.data.usage,
      };
    } catch (error) {
      console.error('Chat API Error:', error);
      return {
        success: false,
        message: error.response?.data?.message || 'Failed to get response',
        error: error.response?.data || error.message,
      };
    }
  },

  /**
   * Get suggested questions by category
   */
  async getSuggestions(category = 'all') {
    try {
      const response = await api.get('/chat/suggestions', {
        params: { category },
      });

      return {
        success: true,
        suggestions: response.data.suggestions,
      };
    } catch (error) {
      console.error('Suggestions API Error:', error);
      return {
        success: false,
        suggestions: [],
      };
    }
  },

  /**
   * Clear conversation history
   */
  async clearHistory() {
    try {
      const response = await api.post('/chat/clear');
      return {
        success: true,
        message: response.data.message,
      };
    } catch (error) {
      console.error('Clear History API Error:', error);
      return {
        success: false,
        message: 'Failed to clear history',
      };
    }
  },
};

export default chatService;
