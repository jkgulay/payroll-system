<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MealAllowance;
use App\Models\MealAllowanceItem;
use App\Models\Employee;
use App\Models\PositionRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

class MealAllowanceController extends Controller
{
    /**
     * Display a listing of meal allowances
     */
    public function index(Request $request)
    {
        $query = MealAllowance::with(['items', 'creator', 'approver', 'project', 'position']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by position
        if ($request->has('position_id')) {
            $query->where('position_id', $request->position_id);
        }

        // Filter by project
        if ($request->has('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->where('period_start', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->where('period_end', '<=', $request->date_to);
        }

        // Search by reference or title
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%");
            });
        }

        return response()->json($query->latest()->paginate(15));
    }

    /**
     * Get employees by position for creating meal allowance
     */
    public function getEmployeesByPosition(Request $request)
    {
        $validated = $request->validate([
            'position_id' => 'required|exists:position_rates,id',
            'project_id' => 'nullable|exists:projects,id',
        ]);

        $query = Employee::with('positionRate')
            ->where('position_id', $validated['position_id'])
            ->where('activity_status', 'active');

        if (isset($validated['project_id'])) {
            $query->where('project_id', $validated['project_id']);
        }

        $employees = $query->get()->map(function ($employee) {
            return [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
                'position' => $employee->position,
                'position_code' => $employee->positionRate->code ?? '',
                'basic_salary' => $employee->getDailyRateAttribute(),
            ];
        });

        return response()->json($employees);
    }

    /**
     * Store a newly created meal allowance
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'location' => 'nullable|string|max:255',
            'project_id' => 'nullable|exists:projects,id',
            'position_id' => 'nullable|exists:position_rates,id',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.employee_id' => 'required|exists:employees,id',
            'items.*.no_of_days' => 'required|integer|min:1|max:31',
            'items.*.amount_per_day' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            // Create meal allowance
            $mealAllowance = MealAllowance::create([
                'title' => $validated['title'],
                'period_start' => $validated['period_start'],
                'period_end' => $validated['period_end'],
                'location' => $validated['location'] ?? null,
                'project_id' => $validated['project_id'] ?? null,
                'position_id' => $validated['position_id'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
                'status' => 'draft',
            ]);

            // Create meal allowance items
            foreach ($validated['items'] as $index => $item) {
                $employee = Employee::with('positionRate')->find($item['employee_id']);

                MealAllowanceItem::create([
                    'meal_allowance_id' => $mealAllowance->id,
                    'employee_id' => $item['employee_id'],
                    'employee_name' => $employee->full_name,
                    'position_code' => $employee->positionRate->code ?? '',
                    'no_of_days' => $item['no_of_days'],
                    'amount_per_day' => $item['amount_per_day'],
                    'sort_order' => $index,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Meal allowance created successfully',
                'data' => $mealAllowance->load('items'),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create meal allowance',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified meal allowance
     */
    public function show(MealAllowance $mealAllowance)
    {
        return response()->json($mealAllowance->load(['items.employee', 'creator', 'approver', 'project', 'position']));
    }

    /**
     * Update the specified meal allowance
     */
    public function update(Request $request, MealAllowance $mealAllowance)
    {
        $user = auth()->user();

        // Allow draft updates for HR/Accountant, but only admin can update approved/pending
        if ($mealAllowance->status !== 'draft' && $user->role !== 'admin') {
            return response()->json([
                'message' => 'Only admin can update meal allowances that are not in draft status',
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'period_start' => 'sometimes|date',
            'period_end' => 'sometimes|date|after_or_equal:period_start',
            'location' => 'nullable|string|max:255',
            'project_id' => 'nullable|exists:projects,id',
            'position_id' => 'nullable|exists:position_rates,id',
            'notes' => 'nullable|string',
            'items' => 'sometimes|array|min:1',
            'items.*.employee_id' => 'required|exists:employees,id',
            'items.*.no_of_days' => 'required|integer|min:1|max:31',
            'items.*.amount_per_day' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            // Update meal allowance
            $mealAllowance->update([
                'title' => $validated['title'] ?? $mealAllowance->title,
                'period_start' => $validated['period_start'] ?? $mealAllowance->period_start,
                'period_end' => $validated['period_end'] ?? $mealAllowance->period_end,
                'location' => $validated['location'] ?? $mealAllowance->location,
                'project_id' => $validated['project_id'] ?? $mealAllowance->project_id,
                'position_id' => $validated['position_id'] ?? $mealAllowance->position_id,
                'notes' => $validated['notes'] ?? $mealAllowance->notes,
            ]);

            // Update items if provided
            if (isset($validated['items'])) {
                // Delete existing items
                $mealAllowance->items()->delete();

                // Create new items
                foreach ($validated['items'] as $index => $item) {
                    $employee = Employee::with('positionRate')->find($item['employee_id']);

                    MealAllowanceItem::create([
                        'meal_allowance_id' => $mealAllowance->id,
                        'employee_id' => $item['employee_id'],
                        'employee_name' => $employee->full_name,
                        'position_code' => $employee->positionRate->code ?? '',
                        'no_of_days' => $item['no_of_days'],
                        'amount_per_day' => $item['amount_per_day'],
                        'sort_order' => $index,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Meal allowance updated successfully',
                'data' => $mealAllowance->load('items'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update meal allowance',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Submit meal allowance for approval
     */
    public function submit(Request $request, MealAllowance $mealAllowance)
    {
        // Check if user has permission (HR/Accountant)
        $user = auth()->user();
        if (!in_array($user->role, ['admin', 'accountant', 'hr'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($mealAllowance->status !== 'draft') {
            return response()->json(['message' => 'Only draft meal allowances can be submitted'], 400);
        }

        if ($mealAllowance->items()->count() === 0) {
            return response()->json(['message' => 'Cannot submit meal allowance without items'], 400);
        }

        $mealAllowance->update([
            'status' => 'pending_approval',
            'submitted_at' => now(),
        ]);

        return response()->json([
            'message' => 'Meal allowance submitted for approval',
            'data' => $mealAllowance->load('items'),
        ]);
    }

    /**
     * Approve or reject meal allowance (Admin only)
     */
    public function updateApproval(Request $request, MealAllowance $mealAllowance)
    {
        // Check if user is admin
        $user = auth()->user();
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Only admin can approve meal allowances'], 403);
        }

        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'approval_notes' => 'nullable|string|max:500',
            'prepared_by_name' => 'nullable|string|max:100',
            'checked_by_name' => 'nullable|string|max:100',
            'verified_by_name' => 'nullable|string|max:100',
            'recommended_by_name' => 'nullable|string|max:100',
            'approved_by_name' => 'nullable|string|max:100',
        ]);

        if ($mealAllowance->status !== 'pending_approval') {
            return response()->json(['message' => 'Only pending meal allowances can be approved/rejected'], 400);
        }

        $status = $validated['action'] === 'approve' ? 'approved' : 'rejected';

        $updateData = [
            'status' => $status,
            'approved_by' => $user->id,
            'approved_at' => now(),
            'approval_notes' => $validated['approval_notes'] ?? null,
        ];

        // Update signatory names if provided
        if (isset($validated['prepared_by_name'])) {
            $updateData['prepared_by_name'] = $validated['prepared_by_name'];
        }
        if (isset($validated['checked_by_name'])) {
            $updateData['checked_by_name'] = $validated['checked_by_name'];
        }
        if (isset($validated['verified_by_name'])) {
            $updateData['verified_by_name'] = $validated['verified_by_name'];
        }
        if (isset($validated['recommended_by_name'])) {
            $updateData['recommended_by_name'] = $validated['recommended_by_name'];
        }
        if (isset($validated['approved_by_name'])) {
            $updateData['approved_by_name'] = $validated['approved_by_name'];
        }

        $mealAllowance->update($updateData);

        return response()->json([
            'message' => "Meal allowance {$validated['action']}d successfully",
            'data' => $mealAllowance->load('items'),
        ]);
    }

    /**
     * Generate PDF for approved meal allowance
     */
    public function generatePdf(MealAllowance $mealAllowance)
    {
        if ($mealAllowance->status !== 'approved') {
            return response()->json(['message' => 'Only approved meal allowances can generate PDF'], 400);
        }

        try {
            // Increase memory limit for PDF generation
            ini_set('memory_limit', '1024M');
            ini_set('max_execution_time', '300');
            
            // Ensure storage directory exists
            $storagePath = storage_path('app/public/meal_allowances');
            if (!file_exists($storagePath)) {
                mkdir($storagePath, 0755, true);
            }

            // Group items by position/role
            $groupedItems = $mealAllowance->items()
                ->with('employee.positionRate')
                ->orderBy('sort_order')
                ->get()
                ->groupBy(function ($item) {
                    return $item->employee->positionRate->position_name ?? 'Other';
                });

            $data = [
                'mealAllowance' => $mealAllowance,
                'groupedItems' => $groupedItems,
                'grandTotal' => $mealAllowance->grand_total,
            ];

            // Generate PDF using DomPDF with optimized options
            $pdf = Pdf::loadView('pdfs.meal_allowance', $data)
                ->setPaper('letter', 'portrait')
                ->setOption('enable_php', false)
                ->setOption('enable_remote', false)
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('isRemoteEnabled', false)
                ->setOption('debugKeepTemp', false)
                ->setOption('chroot', base_path())
                ->setOption('tempDir', sys_get_temp_dir())
                ->setOption('fontDir', storage_path('fonts'))
                ->setOption('fontCache', storage_path('fonts'));

            // Save PDF to storage
            $filename = 'meal-allowance-' . $mealAllowance->reference_number . '.pdf';
            $path = 'meal_allowances/' . $filename;
            Storage::disk('public')->put($path, $pdf->output());

            // Update meal allowance with PDF path
            $mealAllowance->update(['pdf_path' => $path]);

            return response()->json([
                'message' => 'PDF generated successfully',
                'pdf_url' => asset('storage/' . $path),
                'pdf_path' => $path,
            ]);
        } catch (\Exception $e) {
            Log::error('PDF Generation Error: ' . $e->getMessage(), [
                'meal_allowance_id' => $mealAllowance->id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to generate PDF',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download PDF
     */
    public function downloadPdf(MealAllowance $mealAllowance)
    {
        if (!$mealAllowance->pdf_path || !Storage::disk('public')->exists($mealAllowance->pdf_path)) {
            return response()->json(['message' => 'PDF not found. Please generate it first.'], 404);
        }

        $filePath = storage_path('app/public/' . $mealAllowance->pdf_path);
        return response()->download($filePath);
    }

    /**
     * Delete meal allowance
     */
    public function destroy(MealAllowance $mealAllowance)
    {
        $user = auth()->user();

        // Only admin can delete non-draft meal allowances
        if ($mealAllowance->status !== 'draft' && $user->role !== 'admin') {
            return response()->json(['message' => 'Only admin can delete approved or pending meal allowances'], 403);
        }

        try {
            // Delete associated PDF file if exists
            if ($mealAllowance->pdf_path && Storage::disk('public')->exists($mealAllowance->pdf_path)) {
                Storage::disk('public')->delete($mealAllowance->pdf_path);
            }

            $mealAllowance->delete();

            return response()->json(['message' => 'Meal allowance deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete meal allowance: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get available positions
     */
    public function getPositions()
    {
        $positions = PositionRate::select('id', 'position_name', 'code', 'daily_rate')
            ->orderBy('position_name')
            ->get();

        return response()->json($positions);
    }

    /**
     * Bulk assign meal allowance to all employees with specific position
     */
    public function bulkAssignByPosition(Request $request)
    {
        $validated = $request->validate([
            'position_id' => 'required|exists:position_rates,id',
            'project_id' => 'nullable|exists:projects,id',
            'no_of_days' => 'required|integer|min:1|max:31',
            'amount_per_day' => 'required|numeric|min:0',
        ]);

        $query = Employee::with('positionRate')
            ->where('position_id', $validated['position_id'])
            ->where('activity_status', 'active');

        if (isset($validated['project_id'])) {
            $query->where('project_id', $validated['project_id']);
        }

        $employees = $query->get();

        $items = $employees->map(function ($employee) use ($validated) {
            return [
                'employee_id' => $employee->id,
                'employee_name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
                'position_code' => $employee->positionRate->code ?? '',
                'no_of_days' => $validated['no_of_days'],
                'amount_per_day' => $validated['amount_per_day'],
                'total_amount' => $validated['no_of_days'] * $validated['amount_per_day'],
            ];
        });

        return response()->json([
            'message' => 'Successfully loaded ' . $items->count() . ' employees',
            'items' => $items,
        ]);
    }
}
