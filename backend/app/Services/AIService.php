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
        // Using Llama 3.3 70B - free and very powerful
        $this->model = 'meta-llama/llama-3.3-70b-instruct:free';
    }

    /**
     * Send a chat message to the AI with context
     */
    public function chat(string $userMessage, array $conversationHistory = [], array $databaseContext = [])
    {
        try {
            // Validate API key first
            if (empty($this->apiKey)) {
                Log::error('OpenRouter API Key Missing');
                return [
                    'success' => false,
                    'message' => 'AI service is not configured. Please check OPENROUTER_API_KEY.',
                ];
            }

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

            Log::info('AI Request', [
                'model' => $this->model,
                'message' => substr($userMessage, 0, 100),
                'has_context' => !empty($databaseContext),
            ]);

            // Make API request
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'HTTP-Referer' => env('APP_URL'),
                'X-Title' => env('APP_NAME'),
                'Content-Type' => 'application/json',
            ])->timeout(60)->post($this->apiUrl, [
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => 0.3, // Lower for more focused, consistent responses
                'max_tokens' => 2000, // Increased for more detailed responses
                'top_p' => 0.9,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                Log::info('AI Response Success', [
                    'response_length' => strlen($data['choices'][0]['message']['content'] ?? ''),
                ]);
                
                return [
                    'success' => true,
                    'message' => $data['choices'][0]['message']['content'] ?? 'No response',
                    'usage' => $data['usage'] ?? null,
                ];
            }

            $errorBody = $response->body();
            $statusCode = $response->status();
            
            Log::error('OpenRouter API Error', [
                'status' => $statusCode,
                'body' => $errorBody,
                'model' => $this->model,
            ]);

            // Try to parse error message
            $errorData = json_decode($errorBody, true);
            $errorMessage = $errorData['error']['message'] ?? 'Unable to connect to AI service. Please try again.';

            return [
                'success' => false,
                'message' => $errorMessage,
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
        $prompt = "You are an AI assistant for a Construction Payroll System called 'GIOVANNI CONSTRUCTION'. ";
        $prompt .= "You help HR administrators and managers with payroll, employee, attendance, and leave management queries.\n\n";
        
        $prompt .= "CORE CAPABILITIES:\n";
        $prompt .= "1. Answer questions about employees, payroll, attendance, leaves, and system operations\n";
        $prompt .= "2. Analyze data from the database context provided\n";
        $prompt .= "3. Calculate and explain payroll-related computations\n";
        $prompt .= "4. Provide guidance on HR processes and policies\n";
        $prompt .= "5. Generate summaries and reports based on data\n\n";
        
        $prompt .= "RESPONSE GUIDELINES:\n";
        $prompt .= "- ALWAYS provide a helpful answer, even for simple questions\n";
        $prompt .= "- Understand user intent, not just exact wording (be flexible with grammar/typos)\n";
        $prompt .= "- When asked \"how many\" or counts, provide clear numbers from the database context\n";
        $prompt .= "- For simple queries like 'how many payroll', count the items in the context and respond directly\n";
        $prompt .= "- Use tables and lists for better readability when presenting multiple data points\n";
        $prompt .= "- If information is missing, provide general guidance or ask specific clarifying questions\n";
        $prompt .= "- Use Philippine currency (PHP ₱) for monetary values\n";
        $prompt .= "- Format dates clearly (e.g., January 16, 2026)\n";
        $prompt .= "- Be conversational and friendly while remaining professional\n";
        $prompt .= "- NEVER refuse to answer - always attempt to help based on available context\n\n";

        if (!empty($databaseContext)) {
            $prompt .= "=== CURRENT DATABASE CONTEXT ===\n";
            
            // Format the context more clearly
            foreach ($databaseContext as $key => $value) {
                if (is_array($value)) {
                    $prompt .= "\n$key:\n";
                    if (isset($value['count'])) {
                        $prompt .= "  Total Count: {$value['count']}\n";
                    }
                    if (isset($value['data']) && is_array($value['data'])) {
                        $prompt .= "  Data Items: " . count($value['data']) . "\n";
                        $prompt .= "  Details:\n";
                        $prompt .= json_encode($value['data'], JSON_PRETTY_PRINT) . "\n";
                    } elseif (is_array($value)) {
                        $prompt .= json_encode($value, JSON_PRETTY_PRINT) . "\n";
                    }
                } else {
                    $prompt .= "$key: $value\n";
                }
            }
            $prompt .= "\n================================\n\n";
            
            $prompt .= "Use the above data to answer the user's question accurately. ";
            $prompt .= "If the question is about counts or totals, extract the numbers from the context. ";
            $prompt .= "Present data in a clear, organized manner.\n\n";
        } else {
            $prompt .= "Note: No specific database data is available for this query. ";
            $prompt .= "Provide general guidance based on your knowledge of payroll systems, or ask what specific data the user needs.\n\n";
        }

        $prompt .= "Now answer the user's question in a helpful, clear, and concise manner. ";
        $prompt .= "If counting items, state the number directly. Be specific and actionable.";

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
            'payroll_count' => [
                'how many payroll', 'number of payroll', 'total payroll', 'payroll count',
                'count payroll', 'how much payroll', 'payroll total', 'payrolls created',
                'payroll i created', 'payroll generated', 'payrolls i made', 'payroll i have'
            ],
            'employee_count' => [
                'how many employee', 'number of employee', 'total employee', 'employee count',
                'count employee', 'how much employee', 'employee total', 'total staff',
                'staff count', 'worker count', 'many employee', 'employee number',
                'employees are active', 'active employee', 'employees do we have'
            ],
            'employee_search' => [
                'employee in', 'list employee', 'find employee', 'search employee',
                'employee who', 'employee with', 'staff in', 'worker in',
                'people in', 'member of', 'see employee', 'show employee'
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
