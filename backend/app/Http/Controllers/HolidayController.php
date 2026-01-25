<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class HolidayController extends Controller
{
    /**
     * Display a listing of holidays
     */
    public function index(Request $request)
    {
        try {
            $query = Holiday::with(['creator', 'updater']);

            // Filter by year
            if ($request->has('year')) {
                $query->forYear($request->year);
            }

            // Filter by type
            if ($request->has('type')) {
                $query->byType($request->type);
            }

            // Filter by active status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->is_active);
            }

            // Filter by date range
            if ($request->has('start_date') && $request->has('end_date')) {
                $query->inDateRange($request->start_date, $request->end_date);
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'date');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 15);
            $holidays = $perPage === 'all' 
                ? $query->get() 
                : $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $holidays,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching holidays: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch holidays',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created holiday
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'date' => 'required|date',
                'type' => 'required|in:regular,special',
                'description' => 'nullable|string',
                'is_recurring' => 'nullable|boolean',
                'is_active' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $holiday = Holiday::create([
                'name' => $request->name,
                'date' => $request->date,
                'type' => $request->type,
                'description' => $request->description,
                'is_recurring' => $request->get('is_recurring', false),
                'is_active' => $request->get('is_active', true),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Holiday created successfully',
                'data' => $holiday->load(['creator', 'updater']),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating holiday: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create holiday',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified holiday
     */
    public function show($id)
    {
        try {
            $holiday = Holiday::with(['creator', 'updater'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $holiday,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching holiday: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Holiday not found',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Update the specified holiday
     */
    public function update(Request $request, $id)
    {
        try {
            $holiday = Holiday::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'date' => 'sometimes|required|date',
                'type' => 'sometimes|required|in:regular,special',
                'description' => 'nullable|string',
                'is_recurring' => 'nullable|boolean',
                'is_active' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $holiday->update(array_merge(
                $request->only(['name', 'date', 'type', 'description', 'is_recurring', 'is_active']),
                ['updated_by' => auth()->id()]
            ));

            return response()->json([
                'success' => true,
                'message' => 'Holiday updated successfully',
                'data' => $holiday->fresh(['creator', 'updater']),
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating holiday: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update holiday',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified holiday
     */
    public function destroy($id)
    {
        try {
            $holiday = Holiday::findOrFail($id);
            $holiday->delete();

            return response()->json([
                'success' => true,
                'message' => 'Holiday deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting holiday: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete holiday',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk create holidays (useful for importing annual holidays)
     */
    public function bulkStore(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'holidays' => 'required|array',
                'holidays.*.name' => 'required|string|max:255',
                'holidays.*.date' => 'required|date',
                'holidays.*.type' => 'required|in:regular,special',
                'holidays.*.description' => 'nullable|string',
                'holidays.*.is_recurring' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $createdHolidays = [];
            foreach ($request->holidays as $holidayData) {
                $holiday = Holiday::create([
                    'name' => $holidayData['name'],
                    'date' => $holidayData['date'],
                    'type' => $holidayData['type'],
                    'description' => $holidayData['description'] ?? null,
                    'is_recurring' => $holidayData['is_recurring'] ?? false,
                    'is_active' => true,
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]);
                $createdHolidays[] = $holiday;
            }

            return response()->json([
                'success' => true,
                'message' => count($createdHolidays) . ' holidays created successfully',
                'data' => $createdHolidays,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error bulk creating holidays: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create holidays',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check if a specific date is a holiday
     */
    public function checkDate(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'date' => 'required|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $holiday = Holiday::getHolidayForDate($request->date);
            $isHoliday = $holiday !== null;

            $payMultiplier = $holiday ? $holiday->getPayMultiplier($request->date) : 1.0;

            return response()->json([
                'success' => true,
                'data' => [
                    'is_holiday' => $isHoliday,
                    'holiday' => $holiday,
                    'pay_multiplier' => $payMultiplier,
                    'date' => $request->date,
                    'day_of_week' => Carbon::parse($request->date)->format('l'),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error checking holiday date: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to check holiday',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get holidays for a specific year
     */
    public function getYearHolidays(Request $request, $year)
    {
        try {
            $holidays = Holiday::active()
                ->forYear($year)
                ->orderBy('date')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'year' => $year,
                    'total' => $holidays->count(),
                    'holidays' => $holidays,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching year holidays: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch holidays',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
