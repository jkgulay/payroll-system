<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIService
{
    protected $apiKey;
    protected $apiUrl;
    protected $model;
    protected $chatToolService;
    protected $maxToolIterations = 5;

    public function __construct(?ChatToolService $chatToolService = null)
    {
        $this->apiKey = env('OPENROUTER_API_KEY');
        $this->apiUrl = 'https://openrouter.ai/api/v1/chat/completions';
        // Using a model that supports function calling well
        // Options: 'anthropic/claude-3-haiku', 'openai/gpt-4o-mini', 'meta-llama/llama-3.3-70b-instruct:free'
        $this->model = env('OPENROUTER_MODEL', 'anthropic/claude-3.5-haiku');
        $this->chatToolService = $chatToolService ?? new ChatToolService();
    }

    /**
     * Send a chat message to the AI with function calling support
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

            // Build system prompt
            $systemPrompt = $this->buildSystemPromptForTools();

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

            // Get tool definitions
            $tools = $this->chatToolService->getToolDefinitions();

            Log::info('AI Request with Tools', [
                'model' => $this->model,
                'message' => substr($userMessage, 0, 100),
                'tools_count' => count($tools),
            ]);

            // Execute with tool calling loop
            return $this->executeWithTools($messages, $tools);
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
     * Execute API request with tool calling loop
     */
    protected function executeWithTools(array $messages, array $tools): array
    {
        $iteration = 0;
        $totalUsage = ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0];

        while ($iteration < $this->maxToolIterations) {
            $iteration++;

            // Make API request
            $client = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'HTTP-Referer' => env('APP_URL'),
                'X-Title' => env('APP_NAME'),
                'Content-Type' => 'application/json',
            ])->timeout(60);

            // On Windows local dev, PHP often lacks the CA bundle needed to
            // verify external SSL certificates. Disable verification locally.
            if (app()->environment('local')) {
                $client = $client->withoutVerifying();
            }

            $response = $client->post($this->apiUrl, [
                'model' => $this->model,
                'messages' => $messages,
                'tools' => $tools,
                'tool_choice' => 'auto',
                'temperature' => 0.3,
                'max_tokens' => 2000,
                'top_p' => 0.9,
            ]);

            if (!$response->successful()) {
                $errorBody = $response->body();
                $statusCode = $response->status();

                Log::error('OpenRouter API Error', [
                    'status' => $statusCode,
                    'body' => $errorBody,
                    'model' => $this->model,
                ]);

                $errorData = json_decode($errorBody, true);
                $errorMessage = $errorData['error']['message'] ?? 'Unable to connect to AI service.';

                return [
                    'success' => false,
                    'message' => $errorMessage,
                ];
            }

            $data = $response->json();

            // Accumulate usage
            if (isset($data['usage'])) {
                $totalUsage['prompt_tokens'] += $data['usage']['prompt_tokens'] ?? 0;
                $totalUsage['completion_tokens'] += $data['usage']['completion_tokens'] ?? 0;
                $totalUsage['total_tokens'] += $data['usage']['total_tokens'] ?? 0;
            }

            $choice = $data['choices'][0] ?? null;
            if (!$choice) {
                return ['success' => false, 'message' => 'No response from AI'];
            }

            $message = $choice['message'];
            $finishReason = $choice['finish_reason'] ?? 'stop';

            // Check if the model wants to call tools
            if ($finishReason === 'tool_calls' || !empty($message['tool_calls'])) {
                Log::info('AI Tool Calls', [
                    'iteration' => $iteration,
                    'tool_calls' => count($message['tool_calls'] ?? []),
                ]);

                // Add assistant message with tool calls to conversation
                $messages[] = $message;

                // Execute each tool call
                foreach ($message['tool_calls'] as $toolCall) {
                    $toolName = $toolCall['function']['name'];
                    $arguments = json_decode($toolCall['function']['arguments'], true) ?? [];

                    Log::info('Executing Tool', [
                        'tool' => $toolName,
                        'arguments' => $arguments,
                    ]);

                    // Execute the tool
                    $toolResult = $this->chatToolService->executeTool($toolName, $arguments);

                    // Add tool result to messages
                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolCall['id'],
                        'content' => json_encode($toolResult, JSON_PRETTY_PRINT),
                    ];
                }

                // Continue loop to get final response
                continue;
            }

            // No tool calls, return the final response
            Log::info('AI Response Success', [
                'iterations' => $iteration,
                'response_length' => strlen($message['content'] ?? ''),
                'total_usage' => $totalUsage,
            ]);

            return [
                'success' => true,
                'message' => $message['content'] ?? 'No response',
                'usage' => $totalUsage,
                'iterations' => $iteration,
            ];
        }

        return [
            'success' => false,
            'message' => 'Maximum tool iterations reached. Please try a simpler question.',
        ];
    }

    /**
     * Build system prompt optimized for tool usage
     */
    protected function buildSystemPromptForTools(): string
    {
        $prompt = "You are an AI assistant for 'GIOVANNI CONSTRUCTION' Payroll System. ";
        $prompt .= "You help HR administrators and managers with payroll, employee, attendance, and leave management queries.\n\n";

        $prompt .= "AVAILABLE TOOLS:\n";
        $prompt .= "You have access to database query tools to get real-time information. USE THESE TOOLS to answer questions accurately.\n\n";

        $prompt .= "WHEN TO USE TOOLS:\n";
        $prompt .= "- Questions about employee counts, details, or searches → use get_employee_statistics or search_employees\n";
        $prompt .= "- Questions about payroll totals, expenses, or records → use get_payroll_summary or calculate_payroll_totals\n";
        $prompt .= "- Questions about attendance → use get_attendance_summary\n";
        $prompt .= "- Questions about leaves → use get_leave_information\n";
        $prompt .= "- Questions about projects → use get_project_summary\n";
        $prompt .= "- Questions about salaries → use get_salary_information\n";
        $prompt .= "- Questions about SSS, PhilHealth, Pag-IBIG → use get_government_contributions\n\n";

        $prompt .= "RESPONSE GUIDELINES:\n";
        $prompt .= "- ALWAYS use tools when asked about specific data - never guess or make up numbers\n";
        $prompt .= "- Present data clearly using tables or lists when showing multiple items\n";
        $prompt .= "- Use Philippine currency (PHP ₱) for monetary values\n";
        $prompt .= "- Format dates clearly (e.g., January 16, 2026)\n";
        $prompt .= "- Be conversational and friendly while remaining professional\n";
        $prompt .= "- Provide actionable insights when presenting data\n";
        $prompt .= "- If a tool returns an error, explain it gracefully and suggest alternatives\n\n";

        $prompt .= "IMPORTANT: Today's date is " . now()->format('F d, Y') . ". Use this for any date-relative queries.\n";

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
                'how many payroll',
                'number of payroll',
                'total payroll',
                'payroll count',
                'count payroll',
                'how much payroll',
                'payroll total',
                'payrolls created',
                'payroll i created',
                'payroll generated',
                'payrolls i made',
                'payroll i have'
            ],
            'employee_count' => [
                'how many employee',
                'number of employee',
                'total employee',
                'employee count',
                'count employee',
                'how much employee',
                'employee total',
                'total staff',
                'staff count',
                'worker count',
                'many employee',
                'employee number',
                'employees are active',
                'active employee',
                'employees do we have'
            ],
            'employee_search' => [
                'employee in',
                'list employee',
                'find employee',
                'search employee',
                'employee who',
                'employee with',
                'staff in',
                'worker in',
                'people in',
                'member of',
                'see employee',
                'show employee'
            ],
            'payroll_expense' => [
                'payroll expense',
                'payroll cost',
                'total payroll',
                'payroll budget',
                'payroll spending',
                'salary expense',
                'salary cost',
                'wage cost',
                'payrol expense',
                'payroll total',
                'payroll amount'
            ],
            'salary_info' => [
                'average salary',
                'highest paid',
                'salary range',
                'top paid',
                'salary comparison',
                'salary info',
                'wage info',
                'pay scale',
                'top earning',
                'biggest salary',
                'highest salary',
                'average pay',
                'who earn',
                'highest earning'
            ],
            'attendance' => [
                'absent',
                'attendance',
                'present',
                'attendance rate',
                'who was absent',
                'attendance record',
                'who came',
                'who didn\'t come',
                'attendence',
                'atendance',
                'time in',
                'time out',
                'late employee'
            ],
            'leave' => [
                'leave',
                'vacation',
                'sick leave',
                'leave balance',
                'pending leave',
                'day off',
                'time off',
                'holiday',
                'absent request',
                'vacation balance',
                'leave request',
                'leave application',
                'vl',
                'sl'
            ],
            'overtime' => [
                'overtime',
                ' ot ',
                'extra hour',
                'additional hour',
                'work overtime',
                'overtime pay',
                'extra time',
                'beyond hour',
                'over time',
                'ot pay'
            ],
            'tax_compliance' => [
                'tax',
                'sss',
                'philhealth',
                'hdmf',
                'pag-ibig',
                'pagibig',
                'contribution',
                'deduction',
                'withhold',
                'government remittance',
                'compliance',
                'mandatory contribution',
                'phil health',
                'phil-health'
            ],
            'documents' => [
                'document',
                'missing document',
                'required document',
                'file',
                'requirement',
                'compliance document',
                'paper',
                'form',
                'missing file',
                'incomplete document'
            ],
            'system_help' => [
                'how do',
                'how to',
                'explain',
                'what is',
                'help',
                'guide',
                'tutorial',
                'instruct',
                'process',
                'calculate',
                'formula',
                'method',
                'procedure'
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
