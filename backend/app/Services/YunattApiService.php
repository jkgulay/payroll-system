<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Yunatt Cloud API Integration
 * For clients with Yunatt web portal access
 */
class YunattApiService
{
    protected $apiUrl;
    protected $apiKey;
    protected $companyId;
    protected $username;
    protected $password;
    protected $accessToken;

    public function __construct()
    {
        $this->apiUrl = config('payroll.yunatt.api_url');
        $this->apiKey = config('payroll.yunatt.api_key');
        $this->companyId = config('payroll.yunatt.company_id');
        $this->username = config('payroll.yunatt.username');
        $this->password = config('payroll.yunatt.password');
    }

    /**
     * Login with username/password to get access token
     */
    protected function login(): string
    {
        if (!$this->username || !$this->password) {
            throw new \Exception('Yunatt username/password not configured');
        }

        try {
            $response = Http::post($this->apiUrl . '/auth/login', [
                'username' => $this->username,
                'password' => $this->password,
                'company_id' => $this->companyId,
            ]);

            if (!$response->successful()) {
                throw new \Exception('Yunatt login failed: ' . $response->body());
            }

            $data = $response->json();
            return $data['access_token'] ?? $data['token'] ?? '';
        } catch (\Exception $e) {
            Log::error('Yunatt login error: ' . $e->getMessage());
            throw new \Exception('Failed to login to Yunatt: ' . $e->getMessage());
        }
    }

    /**
     * Get authorization header
     */
    protected function getAuthHeaders(): array
    {
        // Option 1: Use API Key if available (preferred)
        if ($this->apiKey) {
            return [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ];
        }

        // Option 2: Login with username/password to get token
        if (!$this->accessToken) {
            $this->accessToken = $this->login();
        }

        return [
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Fetch attendance records from Yuttan API
     * 
     * @param string $dateFrom Date in Y-m-d format
     * @param string $dateTo Date in Y-m-d format
     * @return array Formatted attendance records
     */
    public function fetchAttendance(string $dateFrom, string $dateTo): array
    {
        if (!$this->apiUrl) {
            throw new \Exception('Yunatt API URL not configured. Check .env file');
        }

        if (!$this->apiKey && (!$this->username || !$this->password)) {
            throw new \Exception('Yunatt credentials not configured. Set either YUNATT_API_KEY or YUNATT_USERNAME/PASSWORD');
        }

        try {
            // Yuttan API endpoint (adjust based on actual API documentation)
            $response = Http::withHeaders($this->getAuthHeaders())
                ->get($this->apiUrl . '/attendance', [
                    'company_id' => $this->companyId,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                ]);

            if (!$response->successful()) {
                throw new \Exception('Yunatt API request failed: ' . $response->body());
            }

            $data = $response->json();

            return $this->formatYunattData($data);
        } catch (\Exception $e) {
            Log::error('Yunatt API error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Format Yunatt API response to standard format
     */
    protected function formatYunattData(array $data): array
    {
        $records = [];

        // Adjust this based on actual Yunatt API response structure
        // Example structure (verify with Yunatt API documentation):
        foreach ($data['data'] ?? $data as $record) {
            $timestamp = Carbon::parse($record['punch_time'] ?? $record['timestamp']);

            $records[] = [
                'biometric_id' => $record['employee_id'] ?? $record['badge_number'],
                'employee_number' => $record['employee_code'] ?? $record['employee_id'],
                'timestamp' => $timestamp->toDateTimeString(),
                'date' => $timestamp->format('Y-m-d'),
                'time' => $timestamp->format('H:i:s'),
                'status' => $this->mapPunchType($record['punch_type'] ?? 0),
                'type' => $record['type'] ?? 'in',
            ];
        }

        return $records;
    }

    /**
     * Map Yunatt punch types to system status
     */
    protected function mapPunchType($punchType): int
    {
        // Adjust based on Yunatt's punch type values
        $mapping = [
            'CHECK_IN' => 0,
            'CHECK_OUT' => 1,
            'BREAK_START' => 2,
            'BREAK_END' => 3,
        ];

        return $mapping[$punchType] ?? 0;
    }

    /**
     * Get employee list from Yunatt
     */
    public function getEmployees(): array
    {
        try {
            $response = Http::withHeaders($this->getAuthHeaders())
                ->get($this->apiUrl . '/employees', [
                    'company_id' => $this->companyId,
                ]);

            if (!$response->successful()) {
                throw new \Exception('Failed to fetch employees from Yunatt');
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Yunatt employee fetch error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Test API connection
     */
    public function testConnection(): array
    {
        try {
            // Test if we can authenticate
            $headers = $this->getAuthHeaders();

            $response = Http::withHeaders($headers)
                ->get($this->apiUrl . '/ping');

            return [
                'status' => $response->successful() ? 'connected' : 'failed',
                'message' => $response->successful()
                    ? 'Successfully connected to Yunatt API'
                    : 'Connection failed: ' . $response->body(),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }
}
