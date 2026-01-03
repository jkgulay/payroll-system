<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FileSecurityService
{
    /**
     * Allowed MIME types for file uploads
     */
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'image/jpeg',
        'image/png',
    ];

    /**
     * Allowed file extensions
     */
    private const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];

    /**
     * Maximum file size in bytes (10MB)
     */
    private const MAX_FILE_SIZE = 10485760;

    /**
     * Dangerous file signatures (magic bytes) to detect
     */
    private const DANGEROUS_SIGNATURES = [
        '4D5A' => 'Executable (EXE/DLL)',
        '504B0304' => 'ZIP Archive',
        '52617221' => 'RAR Archive',
        '1F8B' => 'GZIP Archive',
        '7573746172' => 'TAR Archive',
    ];

    /**
     * Validate file security
     */
    public function validateFile(UploadedFile $file): array
    {
        $errors = [];

        // Check file was uploaded via HTTP POST
        if (!$file->isValid()) {
            $errors[] = 'File upload failed or file is corrupted';
            return ['valid' => false, 'errors' => $errors];
        }

        // Check file size
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            $errors[] = 'File size exceeds maximum allowed size (10MB)';
        }

        // Check MIME type
        if (!in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES)) {
            $errors[] = 'File type not allowed: ' . $file->getMimeType();
        }

        // Check file extension
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, self::ALLOWED_EXTENSIONS)) {
            $errors[] = 'File extension not allowed: ' . $extension;
        }

        // Verify extension matches MIME type
        if (!$this->extensionMatchesMimeType($extension, $file->getMimeType())) {
            $errors[] = 'File extension does not match file content';
        }

        // Check filename for malicious patterns
        $filename = $file->getClientOriginalName();
        if (!$this->isFilenameSafe($filename)) {
            $errors[] = 'Filename contains invalid or suspicious characters';
        }

        // Check for double extensions
        if ($this->hasDoubleExtension($filename)) {
            $errors[] = 'Multiple file extensions are not allowed';
        }

        // Check file signature (magic bytes)
        if (!$this->hasValidFileSignature($file)) {
            $errors[] = 'File signature validation failed';
        }

        // Check for embedded executables
        if ($this->containsDangerousContent($file)) {
            $errors[] = 'File contains potentially dangerous content';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Verify extension matches MIME type
     */
    private function extensionMatchesMimeType(string $extension, string $mimeType): bool
    {
        $validCombinations = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
        ];

        return isset($validCombinations[$extension]) && 
               $validCombinations[$extension] === $mimeType;
    }

    /**
     * Check if filename is safe
     */
    private function isFilenameSafe(string $filename): bool
    {
        // Check for null bytes
        if (strpos($filename, "\0") !== false) {
            return false;
        }

        // Check for path traversal attempts
        if (preg_match('/\.\./', $filename)) {
            return false;
        }

        // Check for special characters that could be problematic
        if (preg_match('/[<>:"|?*\x00-\x1F]/', $filename)) {
            return false;
        }

        // Check for script tags or suspicious patterns
        if (preg_match('/<script|javascript:|data:/i', $filename)) {
            return false;
        }

        return true;
    }

    /**
     * Check for double extensions (e.g., file.pdf.exe)
     */
    private function hasDoubleExtension(string $filename): bool
    {
        $parts = explode('.', $filename);
        return count($parts) > 2;
    }

    /**
     * Validate file signature (magic bytes)
     */
    private function hasValidFileSignature(UploadedFile $file): bool
    {
        try {
            $handle = fopen($file->getRealPath(), 'rb');
            if (!$handle) {
                return false;
            }

            $bytes = fread($handle, 8);
            fclose($handle);

            $hex = strtoupper(bin2hex($bytes));

            // Check for known safe signatures based on extension
            $extension = strtolower($file->getClientOriginalExtension());
            
            switch ($extension) {
                case 'pdf':
                    return str_starts_with($hex, '25504446'); // %PDF
                case 'png':
                    return str_starts_with($hex, '89504E47'); // PNG signature
                case 'jpg':
                case 'jpeg':
                    return str_starts_with($hex, 'FFD8FF'); // JPEG signature
                case 'doc':
                    return str_starts_with($hex, 'D0CF11E0'); // DOC signature
                case 'docx':
                    return str_starts_with($hex, '504B0304'); // DOCX (ZIP-based)
                default:
                    return true;
            }
        } catch (\Exception $e) {
            Log::error('File signature validation failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check for dangerous content in file
     */
    private function containsDangerousContent(UploadedFile $file): bool
    {
        try {
            $handle = fopen($file->getRealPath(), 'rb');
            if (!$handle) {
                return true; // Assume dangerous if can't read
            }

            // Read first 1024 bytes for signature check
            $content = fread($handle, 1024);
            fclose($handle);

            $hex = strtoupper(bin2hex($content));

            // Check for dangerous signatures
            foreach (self::DANGEROUS_SIGNATURES as $signature => $type) {
                if (strpos($hex, $signature) !== false) {
                    Log::warning("Dangerous file signature detected: {$type}");
                    return true;
                }
            }

            // Check for embedded executables (MZ header)
            if (strpos($content, 'MZ') === 0) {
                Log::warning('Executable header detected in uploaded file');
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Dangerous content check failed: ' . $e->getMessage());
            return true; // Assume dangerous if check fails
        }
    }

    /**
     * Sanitize filename for storage
     */
    public function sanitizeFilename(string $filename): string
    {
        // Remove any path information
        $filename = basename($filename);
        
        // Remove special characters
        $filename = preg_replace('/[^a-zA-Z0-9\._-]/', '_', $filename);
        
        // Remove multiple dots (except the last one)
        $parts = explode('.', $filename);
        if (count($parts) > 2) {
            $extension = array_pop($parts);
            $filename = implode('_', $parts) . '.' . $extension;
        }
        
        return $filename;
    }

    /**
     * Basic virus scan simulation (placeholder for real AV integration)
     */
    public function scanForViruses(UploadedFile $file): array
    {
        // TODO: Integrate with ClamAV or similar antivirus solution
        // For now, perform basic checks
        
        $path = $file->getRealPath();
        $suspicious = false;
        $reason = '';

        // Check file entropy (high entropy might indicate encryption/packing)
        $entropy = $this->calculateFileEntropy($path);
        if ($entropy > 7.5) {
            $suspicious = true;
            $reason = 'High file entropy detected (possible packed/encrypted content)';
        }

        return [
            'clean' => !$suspicious,
            'suspicious' => $suspicious,
            'reason' => $reason,
            'note' => 'Basic scan only. Consider integrating ClamAV for production.',
        ];
    }

    /**
     * Calculate file entropy
     */
    private function calculateFileEntropy(string $path): float
    {
        try {
            $handle = fopen($path, 'rb');
            if (!$handle) {
                return 0.0;
            }

            $data = fread($handle, 65536); // Read first 64KB
            fclose($handle);

            if (empty($data)) {
                return 0.0;
            }

            $frequencies = array_count_values(str_split($data));
            $length = strlen($data);
            $entropy = 0.0;

            foreach ($frequencies as $count) {
                $probability = $count / $length;
                $entropy -= $probability * log($probability, 2);
            }

            return $entropy;
        } catch (\Exception $e) {
            Log::error('Entropy calculation failed: ' . $e->getMessage());
            return 0.0;
        }
    }
}
