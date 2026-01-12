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
            // Validate and clean user message
            $userMessage = $this->preprocessMessage($userMessage);
            
            if (empty($userMessage)) {
                return [
                    'success' => true,
                    'message' => 'I didn\'t receive a message. Could you please type your question again?',
                ];
            }

            // Build system prompt with database context
            $systemPrompt = $this->buildSystemPrompt($databaseContext);

            // Prepare messages
            $messages = [
                ['role' => 'system', 'content' => $systemPrompt]
            ];

            // Add conversation history (limit to last 10 messages to avoid context overflow)
            $recentHistory = array_slice($conversationHistory, -10);
            foreach ($recentHistory as $message) {
                if (isset($message['role']) && isset($message['content'])) {
                    $messages[] = [
                        'role' => $message['role'],
                        'content' => $this->preprocessMessage($message['content'])
                    ];
                }
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
        $prompt .= "- ALWAYS respond with helpful information, even if the user's grammar is imperfect\n";
        $prompt .= "- Understand the intent behind the question, not just exact wording\n";
        $prompt .= "- Be forgiving of typos, misspellings, and grammar errors\n";
        $prompt .= "- Provide accurate, concise answers based on the database context provided\n";
        $prompt .= "- Use tables and markdown formatting for better readability when showing data\n";
        $prompt .= "- If you don't have enough information, ask clarifying questions\n";
        $prompt .= "- For sensitive data (salaries, tax info), remind users about confidentiality\n";
        $prompt .= "- Always use Philippine currency (PHP ₱) for monetary values\n";
        $prompt .= "- Format dates in a readable format (e.g., January 11, 2026)\n";
        $prompt .= "- If the question seems unclear, interpret it in the most reasonable way and provide an answer\n";
        $prompt .= "- NEVER say you cannot understand or cannot process - always try your best to help\n\n";

        if (!empty($databaseContext)) {
            $prompt .= "CURRENT DATABASE CONTEXT:\n";
            $prompt .= "```json\n";
            $prompt .= json_encode($databaseContext, JSON_PRETTY_PRINT);
            $prompt .= "\n```\n\n";
        } else {
            $prompt .= "Note: No specific database context available for this query. ";
            $prompt .= "Provide general guidance or ask for more specific details.\n\n";
        }

        $prompt .= "Answer the user's question based on this context. Be helpful, friendly, and professional. ";
        $prompt .= "If you need more specific data, let the user know what additional information would be helpful.";

        return $prompt;
    }

    /**
     * Analyze user query to determine intent
     * Enhanced to handle grammar variations and typos
     */
    public function analyzeIntent(string $query): array
    {
        // Normalize the query: lowercase, remove extra spaces, basic cleanup
        $query = strtolower(trim(preg_replace('/\s+/', ' ', $query)));
        
        // Remove common filler words that don't affect intent
        $fillerWords = ['please', 'can you', 'could you', 'would you', 'i want to', 'i need to', 'tell me', 'show'];
        foreach ($fillerWords as $filler) {
            $query = str_replace($filler, '', $query);
        }
        $query = trim($query);
        
        // Enhanced intent patterns with more variations and common misspellings
        $intents = [
            'employee_count' => [
                'how many employee', 'number of employee', 'total employee', 'employee count',
                'count employee', 'how much employee', 'employee total', 'total staff',
                'staff count', 'worker count', 'many employee', 'employee number'
            ],
            'employee_search' => [
                'employee in', 'list employee', 'find employee', 'search employee',
                'employee who', 'employee with', 'staff in', 'worker in',
                'people in', 'member of', 'see employee'
            ],
            'payroll_expense' => [
                'payroll expense', 'payroll cost', 'total payroll', 'payroll budget',
                'payroll spending', 'salary expense', 'salary cost', 'wage cost',
                'payrol expense', 'payroll total', 'payroll amount'
            ],
            'salary_info' => [
                'average salary', 'highest paid', 'salary range', 'top paid',
                'salary comparison', 'salary info', 'wage info', 'pay scale',
                'top earning', 'biggest salary', 'highest salary', 'average pay',
                'who earn', 'highest earning'
            ],
            'attendance' => [
                'absent', 'attendance', 'present', 'attendance rate',
                'who was absent', 'attendance record', 'who came', 'who didn\'t come',
                'attendence', 'atendance', 'time in', 'time out', 'late employee'
            ],
            'leave' => [
                'leave', 'vacation', 'sick leave', 'leave balance', 'pending leave',
                'day off', 'time off', 'holiday', 'absent request', 'vacation balance',
                'leave request', 'leave application', 'vl', 'sl'
            ],
            'overtime' => [
                'overtime', ' ot ', 'extra hour', 'additional hour',
                'work overtime', 'overtime pay', 'extra time', 'beyond hour',
                'over time', 'ot pay'
            ],
            'tax_compliance' => [
                'tax', 'sss', 'philhealth', 'hdmf', 'pag-ibig', 'pagibig',
                'contribution', 'deduction', 'withhold', 'government remittance',
                'compliance', 'mandatory contribution', 'phil health', 'phil-health'
            ],
            'documents' => [
                'document', 'missing document', 'required document', 'file',
                'requirement', 'compliance document', 'paper', 'form',
                'missing file', 'incomplete document'
            ],
            'system_help' => [
                'how do', 'how to', 'explain', 'what is', 'help',
                'guide', 'tutorial', 'instruct', 'process', 'calculate',
                'formula', 'method', 'procedure'
            ],
        ];

        // Check for intent matches with scoring
        $scores = [];
        foreach ($intents as $intent => $keywords) {
            $score = 0;
            foreach ($keywords as $keyword) {
                // Exact match gets highest score
                if (strpos($query, $keyword) !== false) {
                    $score += 10;
                }
                // Fuzzy match for common typos
                else if ($this->fuzzyMatch($query, $keyword)) {
                    $score += 5;
                }
            }
            if ($score > 0) {
                $scores[$intent] = $score;
            }
        }

        // Return the intent with highest score
        if (!empty($scores)) {
            arsort($scores);
            $topIntent = array_key_first($scores);
            $confidence = $scores[$topIntent] >= 10 ? 'high' : 'medium';
            return ['intent' => $topIntent, 'confidence' => $confidence];
        }

        return ['intent' => 'general', 'confidence' => 'low'];
    }

    /**
     * Simple fuzzy matching for common typos
     */
    private function fuzzyMatch(string $query, string $keyword): bool
    {
        // Check if most characters are present
        $keywordChars = str_split($keyword);
        $matchCount = 0;
        foreach ($keywordChars as $char) {
            if (strpos($query, $char) !== false) {
                $matchCount++;
            }
        }
        // If 80% of characters match, consider it a fuzzy match
        return ($matchCount / count($keywordChars)) >= 0.8;
    }

    /**
     * Preprocess and clean user message
     */
    private function preprocessMessage(string $message): string
    {
        // Trim whitespace
        $message = trim($message);
        
        // Remove excessive whitespace
        $message = preg_replace('/\s+/', ' ', $message);
        
        // Remove null bytes and other control characters
        $message = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $message);
        
        // Ensure message isn't just special characters
        if (preg_match('/^[^a-zA-Z0-9]+$/', $message)) {
            return '';
        }
        
        // If message is too short (less than 2 characters), consider it invalid
        if (strlen($message) < 2) {
            return '';
        }
        
        return $message;
    }
}
