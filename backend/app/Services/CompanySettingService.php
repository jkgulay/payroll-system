<?php

namespace App\Services;

use App\Models\CompanySetting;
use Illuminate\Support\Facades\Cache;

class CompanySettingService
{
    /**
     * Get setting value with caching (OPTIMIZED)
     * Cache TTL: 1 hour (3600 seconds)
     */
    public function get(string $key, $default = null)
    {
        $cacheKey = "setting:{$key}";

        return Cache::remember($cacheKey, 3600, function () use ($key, $default) {
            $setting = CompanySetting::where('setting_key', $key)->first();
            if (!$setting) {
                return $default;
            }

            return $this->castValue($setting->setting_value, $setting->setting_type, $default);
        });
    }

    /**
     * Set setting value and invalidate cache
     */
    public function set(string $key, $value, string $type = 'string', ?string $category = null, ?string $description = null): CompanySetting
    {
        $setting = CompanySetting::updateOrCreate(
            ['setting_key' => $key],
            [
                'setting_value' => is_array($value) ? json_encode($value) : (string) $value,
                'setting_type' => $type,
                'category' => $category,
                'description' => $description,
                'is_editable' => true,
            ]
        );

        // OPTIMIZATION: Invalidate cache when setting is updated
        Cache::forget("setting:{$key}");

        return $setting;
    }

    private function castValue($value, string $type, $default = null)
    {
        if ($value === null) {
            return $default;
        }

        return match ($type) {
            'number' => (float) $value,
            'integer' => (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true) ?? $default,
            default => $value,
        };
    }
}
