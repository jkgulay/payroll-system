<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPayrollPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Define role-based permissions
        $permissions = [
            'process' => ['admin', 'accountant'],
            'check' => ['admin', 'accountant'],
            'recommend' => ['admin', 'manager'],
            'approve' => ['admin'],
            'mark_paid' => ['admin', 'accountant'],
        ];

        // Check if user has required permission
        if (!isset($permissions[$permission]) || !in_array($user->role, $permissions[$permission])) {
            return response()->json([
                'error' => 'You do not have permission to perform this action',
                'required_roles' => $permissions[$permission] ?? []
            ], 403);
        }

        return $next($request);
    }
}
