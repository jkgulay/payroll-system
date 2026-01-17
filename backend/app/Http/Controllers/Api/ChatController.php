<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AIService;
use App\Services\DatabaseContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    protected $aiService;
    protected $dbContextService;

    public function __construct(AIService $aiService, DatabaseContextService $dbContextService)
    {
        $this->aiService = $aiService;
        $this->dbContextService = $dbContextService;
    }

    /**
     * Handle chat messages
     */
    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string|min:2|max:1000',
            'conversation_history' => 'nullable|array|max:20',
            'conversation_history.*.role' => 'required_with:conversation_history|in:user,assistant',
            'conversation_history.*.content' => 'required_with:conversation_history|string|max:2000',
        ]);

        try {
            $userMessage = trim($request->input('message'));
            $conversationHistory = $request->input('conversation_history', []);

            // Additional validation for message content
            if (preg_match('/^[^a-zA-Z0-9]+$/', $userMessage)) {
                return response()->json([
                    'success' => true,
                    'message' => 'I need a valid question with letters or numbers. Could you please rephrase that?',
                    'intent' => ['intent' => 'invalid', 'confidence' => 'high'],
                ], 200);
            }

            // Analyze query intent (now more robust)
            $intent = $this->aiService->analyzeIntent($userMessage);

            // Get relevant database context
            $databaseContext = $this->dbContextService->getContextForQuery($userMessage, $intent);

            // Get AI response
            $response = $this->aiService->chat($userMessage, $conversationHistory, $databaseContext);

            if ($response['success']) {
                return response()->json([
                    'success' => true,
                    'message' => $response['message'],
                    'intent' => $intent,
                    'usage' => $response['usage'] ?? null,
                ], 200);
            }

            // Log the failure reason
            Log::warning('AI Chat Failed', [
                'user_message' => $userMessage,
                'intent' => $intent,
                'error' => $response['message'] ?? 'Unknown error',
            ]);

            // Return the actual error message from AI service
            return response()->json([
                'success' => true, // Keep true to avoid error UI
                'message' => $response['message'] ?? 'I\'m having trouble processing that request. Please try again later.',
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Handle validation errors gracefully
            return response()->json([
                'success' => true,
                'message' => 'Your message seems to have some formatting issues. Please make sure it\'s between 2 and 1000 characters.',
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Chat Controller Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user_message' => $request->input('message', 'N/A'),
            ]);

            $errorMessage = 'I encountered a small hiccup while processing your request. Please try again, or rephrase your question.';
            
            // In development, show more details
            if (config('app.debug')) {
                $errorMessage .= ' (Debug: ' . $e->getMessage() . ')';
            }

            return response()->json([
                'success' => true, // Changed to true to avoid error styling
                'message' => $errorMessage,
            ], 200);
        }
    }

    /**
     * Get suggested questions based on category
     */
    public function getSuggestedQuestions(Request $request)
    {
        $category = $request->input('category', 'all');

        $suggestions = [
            'employee' => [
                'How many employees are currently active?',
                'Show me employees in the IT department',
                'Who joined the company in the last 3 months?',
                'What positions do we have in the company?',
            ],
            'payroll' => [
                'What\'s the total payroll expense for December 2025?',
                'Compare payroll costs between Q3 and Q4 2025',
                'Who are the top 5 highest-paid employees?',
                'Calculate total overtime pay for this month',
                'What\'s the average salary in the Sales department?',
            ],
            'attendance' => [
                'Who was absent yesterday?',
                'Show me employees with low attendance this month',
                'What\'s the overall attendance rate?',
            ],
            'leave' => [
                'How many leave requests are pending approval?',
                'What\'s the average leave balance across all employees?',
                'Show me leave statistics for this month',
            ],
            'compliance' => [
                'Generate a summary of tax deductions for 2025',
                'Show me employees missing required documents',
                'Calculate total SSS/PhilHealth/HDMF contributions',
            ],
            'help' => [
                'How do I process payroll for the current period?',
                'What\'s the formula for overtime calculation?',
                'Explain the leave accrual policy',
                'How is withholding tax calculated?',
            ],
        ];

        if ($category === 'all') {
            $allQuestions = [];
            foreach ($suggestions as $questions) {
                $allQuestions = array_merge($allQuestions, $questions);
            }
            return response()->json([
                'success' => true,
                'suggestions' => $allQuestions,
            ]);
        }

        return response()->json([
            'success' => true,
            'suggestions' => $suggestions[$category] ?? [],
        ]);
    }

    /**
     * Clear conversation history
     */
    public function clearHistory(Request $request)
    {
        // In a production environment, you might want to store conversation history in the database
        // For now, we'll just return a success response
        return response()->json([
            'success' => true,
            'message' => 'Conversation history cleared',
        ]);
    }
}
