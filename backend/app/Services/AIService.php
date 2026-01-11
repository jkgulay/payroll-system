<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIService
{
    protected $apiKey;
    protected $apiUrl;
    protected $model;

    public function __construct()
    {
        $this->apiKey = env('OPENROUTER_API_KEY');
        $this->apiUrl = 'https://openrouter.ai/api/v1/chat/completions';
        $this->model = 'meta-llama/llama-3.2-3b-instruct:free';
    }

    /**
     * Send a chat message to the AI with context
     */
    public function chat(string $userMessage, array $conversationHistory = [], array $databaseContext = [])
    {
        try {
            // Build system prompt with database context
            $systemPrompt = $this->buildSystemPrompt($databaseContext);

            // Prepare messages
            $messages = [
                ['role' => 'system', 'content' => $systemPrompt]
            ];

            // Add conversation history
            foreach ($conversationHistory as $message) {
                $messages[] = [
                    'role' => $message['role'],
                    'content' => $message['content']
                ];
            }

            // Add current user message
            $messages[] = [
                'role' => 'user',
                'content' => $userMessage
            ];

            // Make API request
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'HTTP-Referer' => env('APP_URL'),
                'X-Title' => env('APP_NAME'),
                'Content-Type' => 'application/json',
            ])->timeout(60)->post($this->apiUrl, [
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 1000,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                return [
                    'success' => true,
                    'message' => $data['choices'][0]['message']['content'] ?? 'No response',
                    'usage' => $data['usage'] ?? null,
                ];
            }

            Log::error('OpenRouter API Error', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to get response from AI service.',
                'error' => $response->body()
            ];

        } catch (\Exception $e) {
            Log::error('AI Service Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred while processing your request.',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Build system prompt with database context
     */
    protected function buildSystemPrompt(array $databaseContext): string
    {
        $prompt = "You are an AI assistant for a Construction Payroll System. ";
        $prompt .= "You help HR administrators and managers with payroll, employee, attendance, and leave management queries.\n\n";
        
        $prompt .= "IMPORTANT GUIDELINES:\n";
        $prompt .= "- Provide accurate, concise answers based on the database context provided\n";
        $prompt .= "- Use tables and formatting for better readability when showing data\n";
        $prompt .= "- If you don't have enough information, ask clarifying questions\n";
        $prompt .= "- For sensitive data (salaries, tax info), remind users about confidentiality\n";
        $prompt .= "- Always use Philippine currency (PHP ₱) for monetary values\n";
        $prompt .= "- Format dates in a readable format (e.g., January 11, 2026)\n\n";

        if (!empty($databaseContext)) {
            $prompt .= "CURRENT DATABASE CONTEXT:\n";
            $prompt .= "```json\n";
            $prompt .= json_encode($databaseContext, JSON_PRETTY_PRINT);
            $prompt .= "\n```\n\n";
        }

        $prompt .= "Answer the user's question based on this context. If you need more specific data, ";
        $prompt .= "let the user know what additional information would be helpful.";

        return $prompt;
    }

    /**
     * Analyze user query to determine intent
     */
    public function analyzeIntent(string $query): array
    {
        $query = strtolower($query);
        
        $intents = [
            'employee_count' => ['how many employees', 'number of employees', 'total employees', 'employee count'],
            'employee_search' => ['employees in', 'show me employees', 'list employees', 'find employees'],
            'payroll_expense' => ['payroll expense', 'payroll cost', 'total payroll', 'payroll budget'],
            'salary_info' => ['average salary', 'highest paid', 'salary range', 'top paid', 'salary comparison'],
            'attendance' => ['absent', 'attendance', 'present', 'attendance rate'],
            'leave' => ['leave', 'vacation', 'sick leave', 'leave balance', 'pending leave'],
            'overtime' => ['overtime', 'OT', 'extra hours'],
            'tax_compliance' => ['tax', 'sss', 'philhealth', 'hdmf', 'pag-ibig', 'contributions', 'deductions'],
            'documents' => ['documents', 'missing documents', 'required documents'],
            'system_help' => ['how do i', 'how to', 'explain', 'what is', 'help me'],
        ];

        foreach ($intents as $intent => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($query, $keyword) !== false) {
                    return ['intent' => $intent, 'confidence' => 'high'];
                }
            }
        }

        return ['intent' => 'general', 'confidence' => 'low'];
    }
}
