<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmployeeAccess
{
    /**
     * Handle an incoming request.
     *
     * Ensures that users with the 'employee' role can only access their own employee data.
     * Admin, accountant, and payrollist roles have full access.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        // Admin, accountant, and payrollist have full access - no restrictions
        if (in_array($user->role, ['admin', 'accountant', 'payrollist'])) {
            return $next($request);
        }
        
        // For employee role, check if they're accessing their own data
        if ($user->role === 'employee') {
            // Get the employee ID from the route parameter
            $routeEmployeeId = $request->route('employee');
            
            // If route has employee parameter, ensure it matches the user's linked employee
            if ($routeEmployeeId) {
                // Convert to integer if it's an object (model binding)
                $requestedEmployeeId = is_object($routeEmployeeId) ? $routeEmployeeId->id : (int)$routeEmployeeId;
                
                // Check if user has employee_id linked
                if (!$user->employee_id) {
                    return response()->json([
                        'message' => 'No employee record linked to your account.'
                    ], 403);
                }
                
                // Ensure the employee is accessing their own data
                if ($requestedEmployeeId !== $user->employee_id) {
                    return response()->json([
                        'message' => 'You can only access your own employee data.'
                    ], 403);
                }
            }
        }
        
        return $next($request);
    }
}
