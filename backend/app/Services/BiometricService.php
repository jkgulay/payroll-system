<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * ZKTeco/Yunatt Biometric Device Integration
 * Supports ZKTeco attendance devices via file export or SDK
 */
class BiometricService
{
    protected $deviceType;
    protected $deviceIp;
    protected $devicePort;

    public function __construct()
    {
        $this->deviceType = config('payroll.biometric.device_type', 'zkteco');
        $this->deviceIp = config('payroll.biometric.device_ip');
        $this->devicePort = config('payroll.biometric.device_port', 4370);
    }

    /**
     * Connect to ZKTeco device and fetch attendance logs
     * Requires razerdeveloper/zkteco package
     * Install: composer require razerdeveloper/zkteco
     */
    public function fetchFromDevice(string $dateFrom = null, string $dateTo = null): array
    {
        if (!config('payroll.biometric.enabled')) {
            throw new \Exception('Biometric integration is disabled');
        }

        if (!class_exists('\Rats\Zkteco\Lib\ZKTeco')) {
            throw new \Exception('ZKTeco SDK not installed. Run: composer require razerdeveloper/zkteco');
        }

        try {
            $zk = new \Rats\Zkteco\Lib\ZKTeco($this->deviceIp, $this->devicePort);

            if (!$zk->connect()) {
                throw new \Exception('Failed to connect to biometric device');
            }

            // Disable device (prevent new logs during fetch)
            $zk->disableDevice();

            // Get attendance logs
            $attendance = $zk->getAttendance();

            // Re-enable device
            $zk->enableDevice();
            $zk->disconnect();

            // Format attendance data
            $records = [];
            foreach ($attendance as $log) {
                $timestamp = Carbon::parse($log['timestamp']);

                // Filter by date range if provided
                if ($dateFrom && $timestamp->lt(Carbon::parse($dateFrom))) {
                    continue;
                }
                if ($dateTo && $timestamp->gt(Carbon::parse($dateTo))) {
                    continue;
                }

                $records[] = [
                    'biometric_id' => $log['id'],
                    'employee_number' => $log['id'], // Usually same as biometric ID
                    'timestamp' => $log['timestamp'],
                    'date' => $timestamp->format('Y-m-d'),
                    'time' => $timestamp->format('H:i:s'),
                    'status' => $log['status'] ?? 0, // 0=check-in, 1=check-out, 2=break-start, 3=break-end
                    'type' => $log['type'] ?? 'in',
                ];
            }

            return $records;
        } catch (\Exception $e) {
            Log::error('Biometric device connection failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Parse ZKTeco CSV export file
     * Common format: Badge,Date,Time,State,Person
     */
    public function parseCSVExport(string $filePath): array
    {
        $records = [];
        $handle = fopen($filePath, 'r');

        if (!$handle) {
            throw new \Exception('Failed to open file');
        }

        // Read header
        $header = fgetcsv($handle);

        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) < 3) continue;

            // Map CSV columns (adjust based on your device's export format)
            $biometricId = trim($data[0]); // Badge/ID
            $date = trim($data[1]); // Date
            $time = trim($data[2]); // Time
            $state = trim($data[3] ?? '0'); // 0=in, 1=out, 2=break-in, 3=break-out

            $records[] = [
                'biometric_id' => $biometricId,
                'employee_number' => $biometricId,
                'timestamp' => $date . ' ' . $time,
                'date' => $date,
                'time' => $time,
                'status' => $state,
                'type' => $state == 0 ? 'in' : 'out',
            ];
        }

        fclose($handle);
        return $records;
    }

    /**
     * Parse ZKTeco text export file
     * Format: UserID DateTime InOut
     */
    public function parseTextExport(string $filePath): array
    {
        $records = [];
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', trim($line));

            if (count($parts) < 3) continue;

            $biometricId = $parts[0];
            $dateTime = $parts[1] . ' ' . $parts[2];
            $type = isset($parts[3]) ? ($parts[3] == '1' ? 'out' : 'in') : 'in';

            $records[] = [
                'biometric_id' => $biometricId,
                'employee_number' => $biometricId,
                'timestamp' => $dateTime,
                'date' => $parts[1],
                'time' => $parts[2],
                'status' => $type == 'in' ? 0 : 1,
                'type' => $type,
            ];
        }

        return $records;
    }

    /**
     * Sync users from system to biometric device
     */
    public function syncEmployeesToDevice(): array
    {
        if (!config('payroll.biometric.enabled')) {
            throw new \Exception('Biometric integration is disabled');
        }

        if (!class_exists('\Rats\Zkteco\Lib\ZKTeco')) {
            throw new \Exception('ZKTeco SDK not installed');
        }

        try {
            $zk = new \Rats\Zkteco\Lib\ZKTeco($this->deviceIp, $this->devicePort);

            if (!$zk->connect()) {
                throw new \Exception('Failed to connect to biometric device');
            }

            $zk->disableDevice();

            $synced = 0;
            $employees = Employee::active()->whereNotNull('biometric_id')->get();

            foreach ($employees as $employee) {
                // Set user info on device
                $zk->setUser(
                    $employee->biometric_id, // User ID
                    $employee->biometric_id, // Badge ID
                    $employee->full_name, // Name
                    '', // Password (empty for biometric)
                    0, // Role
                    $employee->biometric_id // Card number
                );
                $synced++;
            }

            $zk->enableDevice();
            $zk->disconnect();

            return [
                'synced' => $synced,
                'total' => $employees->count()
            ];
        } catch (\Exception $e) {
            Log::error('Failed to sync employees to biometric device: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Clear all attendance logs from device
     */
    public function clearDeviceLogs(): bool
    {
        if (!config('payroll.biometric.enabled')) {
            throw new \Exception('Biometric integration is disabled');
        }

        if (!class_exists('\Rats\Zkteco\Lib\ZKTeco')) {
            throw new \Exception('ZKTeco SDK not installed');
        }

        try {
            $zk = new \Rats\Zkteco\Lib\ZKTeco($this->deviceIp, $this->devicePort);

            if (!$zk->connect()) {
                throw new \Exception('Failed to connect to biometric device');
            }

            $zk->disableDevice();
            $zk->clearAttendance();
            $zk->enableDevice();
            $zk->disconnect();

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to clear device logs: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get device information
     */
    public function getDeviceInfo(): array
    {
        if (!class_exists('\Rats\Zkteco\Lib\ZKTeco')) {
            return [
                'error' => 'ZKTeco SDK not installed',
                'install_command' => 'composer require razerdeveloper/zkteco'
            ];
        }

        try {
            $zk = new \Rats\Zkteco\Lib\ZKTeco($this->deviceIp, $this->devicePort);

            if (!$zk->connect()) {
                return ['error' => 'Failed to connect to device'];
            }

            $info = [
                'platform' => $zk->platform(),
                'firmware_version' => $zk->fmVersion(),
                'serial_number' => $zk->serialNumber(),
                'device_name' => $zk->deviceName(),
                'user_count' => count($zk->getUser()),
                'attendance_count' => count($zk->getAttendance()),
                'work_code' => $zk->workCode(),
            ];

            $zk->disconnect();

            return $info;
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
