<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanyInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class CompanyInfoController extends Controller
{
    public function show()
    {
        $companyInfo = CompanyInfo::first();

        if (!$companyInfo) {
            // Return default structure if not exists
            $companyInfo = new CompanyInfo([
                'country' => 'Philippines'
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $companyInfo
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_name' => 'nullable|string|max:255',
            'business_type' => 'nullable|string|max:255',
            'tin' => 'nullable|string|max:255',
            'registration_number' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'province' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'mobile' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // 2MB max
            'report_header' => 'nullable|string|max:255',
            'report_footer' => 'nullable|string|max:255',
            'prepared_by_title' => 'nullable|string|max:255',
            'approved_by_title' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->except(['logo']);

        // Get or create company info (should only be one record)
        $companyInfo = CompanyInfo::first();

        if (!$companyInfo) {
            $companyInfo = new CompanyInfo();
        }

        // Handle logo upload
        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            if ($companyInfo->logo_path && Storage::disk('public')->exists($companyInfo->logo_path)) {
                Storage::disk('public')->delete($companyInfo->logo_path);
            }

            $logoFile = $request->file('logo');
            $logoPath = $logoFile->store('company-logos', 'public');
            $data['logo_path'] = $logoPath;
        }

        $companyInfo->fill($data);
        $companyInfo->save();

        return response()->json([
            'success' => true,
            'message' => 'Company information updated successfully',
            'data' => $companyInfo
        ]);
    }

    public function deleteLogo()
    {
        $companyInfo = CompanyInfo::first();

        if (!$companyInfo) {
            return response()->json([
                'success' => false,
                'message' => 'Company information not found'
            ], 404);
        }

        // Delete logo file from storage if exists
        if ($companyInfo->logo_path && Storage::disk('public')->exists($companyInfo->logo_path)) {
            Storage::disk('public')->delete($companyInfo->logo_path);
        }

        // Clear logo_path in database
        $companyInfo->logo_path = null;
        $companyInfo->save();

        return response()->json([
            'success' => true,
            'message' => 'Logo reset to default successfully',
            'data' => $companyInfo
        ]);
    }
}
