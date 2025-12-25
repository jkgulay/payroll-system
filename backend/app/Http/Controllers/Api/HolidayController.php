<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    public function index(Request $request)
    {
        $query = Holiday::query();

        if ($request->has('year')) {
            $query->whereYear('date', $request->year);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        return response()->json($query->orderBy('date')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'date' => 'required|date',
            'type' => 'required|in:regular,special',
        ]);

        $holiday = Holiday::create($validated);

        return response()->json($holiday, 201);
    }

    public function show(Holiday $holiday)
    {
        return response()->json($holiday);
    }

    public function update(Request $request, Holiday $holiday)
    {
        $validated = $request->validate([
            'name' => 'string',
            'date' => 'date',
            'type' => 'in:regular,special',
        ]);

        $holiday->update($validated);

        return response()->json($holiday);
    }

    public function destroy(Holiday $holiday)
    {
        $holiday->delete();

        return response()->json(['message' => 'Holiday deleted successfully']);
    }

    public function byYear($year)
    {
        $holidays = Holiday::whereYear('date', $year)->orderBy('date')->get();
        return response()->json($holidays);
    }
}
