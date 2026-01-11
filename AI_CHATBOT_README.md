# AI Chatbot Integration - Implementation Guide

## 🤖 Overview
This payroll system now includes an AI-powered chatbot assistant using **Meta Llama 3.2 3B Instruct** via OpenRouter API. The chatbot helps HR administrators and managers with payroll queries, employee information, attendance tracking, and system guidance.

## 🚀 Features Implemented

### Backend Components

1. **AIService** (`backend/app/Services/AIService.php`)
   - Handles communication with OpenRouter API
   - Uses Meta Llama 3.2 3B Instruct (free tier)
   - Analyzes user intent for better responses
   - Builds context-aware prompts

2. **DatabaseContextService** (`backend/app/Services/DatabaseContextService.php`)
   - Retrieves relevant database information based on user queries
   - Supports multiple query intents:
     - Employee information
     - Payroll analytics
     - Attendance tracking
     - Leave management
     - Tax/compliance data
     - System help

3. **ChatController** (`backend/app/Http/Controllers/Api/ChatController.php`)
   - `/api/chat` (POST) - Send chat messages
   - `/api/chat/suggestions` (GET) - Get suggested questions
   - `/api/chat/clear` (POST) - Clear conversation history

### Frontend Components

1. **ChatBot Component** (`frontend/src/components/ChatBot.vue`)
   - Floating chat button
   - Expandable chat window
   - Markdown rendering support
   - Suggested questions by category
   - Conversation history
   - Typing indicators
   - Responsive design

2. **Chat Store** (`frontend/src/stores/chatStore.js`)
   - Manages chat state
   - Handles message history
   - LocalStorage persistence

3. **Chat Service** (`frontend/src/services/chatService.js`)
   - API communication layer
   - Error handling

## 📋 Use Cases

### 1. Employee Information Queries
- "How many employees are currently active?"
- "Show me employees in the IT department"
- "What's the average salary in the Sales department?"
- "Who joined the company in the last 3 months?"

### 2. Payroll Analytics
- "What's the total payroll expense for December 2025?"
- "Compare payroll costs between Q3 and Q4 2025"
- "Who are the top 5 highest-paid employees?"
- "Calculate total overtime pay for this month"

### 3. Attendance & Leave Management
- "Who was absent yesterday?"
- "Show me employees with low attendance this month"
- "How many leave requests are pending approval?"
- "What's the average leave balance across all employees?"

### 4. Compliance & Reporting
- "Generate a summary of tax deductions for 2025"
- "Show me employees missing required documents"
- "Calculate total SSS/PhilHealth/HDMF contributions"

### 5. System Help
- "How do I process payroll for the current period?"
- "What's the formula for overtime calculation?"
- "Explain the leave accrual policy"

## ⚙️ Configuration

### Backend Setup

1. **Environment Variables** (`.env`)
   ```env
   OPENROUTER_API_KEY=your_openrouter_api_key_here
   ```
   
   ⚠️ **SECURITY NOTE**: Never commit your actual API key to the repository! Get your key from [OpenRouter](https://openrouter.ai/keys) and add it to your local `.env` file only.

2. **API Routes** (already added to `routes/api.php`)
   ```php
   Route::post('/chat', [ChatController::class, 'chat']);
   Route::get('/chat/suggestions', [ChatController::class, 'getSuggestedQuestions']);
   Route::post('/chat/clear', [ChatController::class, 'clearHistory']);
   ```

### Frontend Setup

1. **Dependencies** (already installed)
   ```bash
   npm install marked dompurify
   ```

2. **Component Integration** (already added to `App.vue`)
   ```vue
   <ChatBot v-if="authStore.isAuthenticated" />
   ```

## 🔐 Security Considerations

1. **API Key Protection**
   - API key is stored in backend `.env` file
   - Never exposed to frontend
   - Added to `.gitignore`

2. **Authentication Required**
   - All chat endpoints require `auth:sanctum` middleware
   - Only authenticated users can access the chatbot

3. **HTML Sanitization**
   - All AI responses are sanitized using DOMPurify
   - Prevents XSS attacks

4. **Rate Limiting**
   - Consider adding rate limiting to chat endpoints in production

## 🎨 UI Features

- **Floating Button**: Fixed position chat toggle button
- **Expandable Window**: Full-featured chat interface
- **Suggested Questions**: Organized by category
- **Markdown Support**: Tables, lists, code blocks
- **Responsive Design**: Works on all screen sizes
- **Typing Indicators**: Shows when AI is processing
- **Unread Counter**: Badge shows new messages when minimized

## 📊 Model Information

**Model**: Meta Llama 3.2 3B Instruct (Free)
- **Provider**: OpenRouter
- **Context Length**: 128k tokens
- **Cost**: Free tier
- **Best For**: General queries, fast responses

## 🚀 Testing

1. **Start Backend**
   ```bash
   cd backend
   php artisan serve
   ```

2. **Start Frontend**
   ```bash
   cd frontend
   npm run dev
   ```

3. **Login as Admin/HR** and you'll see the floating chat button

4. **Try Sample Questions**:
   - "How many active employees do we have?"
   - "What's the total payroll for December 2025?"
   - "Show me pending leave requests"

## 🔄 Future Enhancements

1. **Conversation History**: Store in database for persistence
2. **Multi-language Support**: Add support for other languages
3. **Voice Input**: Add speech-to-text capability
4. **File Attachments**: Allow users to upload documents
5. **Advanced Analytics**: Generate charts and visualizations
6. **Export Conversations**: Download chat history as PDF
7. **Admin Dashboard**: Monitor chatbot usage and performance

## 🐛 Troubleshooting

### API Key Not Working
- Verify the key in `.env` file
- Restart Laravel server after changing `.env`
- Check OpenRouter dashboard for key status

### No Response from Chatbot
- Check browser console for errors
- Verify backend API is running
- Check network tab for failed requests
- Ensure user is authenticated

### Database Context Not Loading
- Verify database connection
- Check if models exist
- Review logs in `storage/logs/laravel.log`

## 📝 Notes

- The chatbot appears only for authenticated users
- Conversation history is stored in browser localStorage
- AI responses are context-aware based on database queries
- The free tier model has no rate limits but may have slower response times during peak usage

## 🎉 Conclusion

The AI chatbot is now fully integrated into your payroll system! Users can ask natural language questions and get intelligent responses based on real-time database information.

For any issues or questions, check the logs or contact the development team.
