<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminSetting;
use App\Models\AuditLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminSecurityController extends Controller
{
    /**
     * Get security stats and settings.
     */
    public function stats(Request $request): JsonResponse
    {
        // Verify admin access
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Admin access only.'], 403);
        }

        // Check for recent threats (danger logs in last 24h)
        $recentThreats = AuditLog::where('status', 'danger')
            ->where('created_at', '>=', Carbon::now()->subDay())
            ->exists();

        $systemStatus = $recentThreats ? 'Attention Required' : 'Secure';

        // 2FA Adoption (check if two_factor_enabled column exists)
        $adminCount = User::where('role', 'admin')->count();
        $admin2fa = 0;
        
        // Try to get 2FA count if column exists
        try {
            $admin2fa = User::where('role', 'admin')
                ->where('two_factor_enabled', true)
                ->count();
        } catch (\Exception $e) {
            // Column doesn't exist, use 0
        }
        
        $twoFaAdoption = $adminCount > 0 
            ? round(($admin2fa / $adminCount) * 100) . '%' 
            : '0%';

        // Failed logins in last 24h
        $failedLogins = AuditLog::where('action', 'like', '%Login Failed%')
            ->where('created_at', '>=', Carbon::now()->subDay())
            ->count();

        // Get security settings
        $settings = AdminSetting::whereIn('key', ['force_2fa', 'strong_passwords', 'session_timeout'])
            ->pluck('value', 'key');

        return response()->json([
            'system_status' => $systemStatus,
            'two_fa_adoption' => $twoFaAdoption,
            'failed_logins' => $failedLogins,
            'settings' => [
                'force_2fa' => filter_var($settings['force_2fa'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'strong_passwords' => filter_var($settings['strong_passwords'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'session_timeout' => filter_var($settings['session_timeout'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ],
        ]);
    }

    /**
     * Get audit logs with optional search.
     */
    public function logs(Request $request): JsonResponse
    {
        // Verify admin access
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Admin access only.'], 403);
        }

        $query = AuditLog::query()->latest();

        // Search filter
        if ($request->has('query') && $request->query('query')) {
            $search = $request->query('query');
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                    ->orWhere('user', 'like', "%{$search}%")
                    ->orWhere('ip', 'like', "%{$search}%")
                    ->orWhere('details', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->has('status') && $request->query('status')) {
            $query->where('status', $request->query('status'));
        }

        $logs = $query->take(50)->get()->map(function ($log) {
            return [
                'id' => $log->id,
                'action' => $log->action,
                'user' => $log->user,
                'ip' => $log->ip,
                'details' => $log->details,
                'status' => $log->status,
                'time' => $log->created_at->diffForHumans(),
                'created_at' => $log->created_at,
            ];
        });

        return response()->json($logs);
    }

    /**
     * Update a security setting.
     */
    public function updateSetting(Request $request): JsonResponse
    {
        // Verify admin access
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Admin access only.'], 403);
        }

        $request->validate([
            'key' => 'required|string|in:force_2fa,strong_passwords,session_timeout',
            'value' => 'required|boolean',
        ]);

        AdminSetting::updateOrCreate(
            ['key' => $request->key],
            ['value' => $request->value ? 'true' : 'false']
        );

        // Record audit log
        AuditLog::create([
            'action' => 'Policy Update',
            'user' => $request->user()->email,
            'ip' => $request->ip(),
            'details' => "Updated setting '{$request->key}' to " . ($request->value ? 'enabled' : 'disabled'),
            'status' => 'info',
        ]);

        return response()->json([
            'message' => 'Setting updated successfully',
            'setting' => [
                'key' => $request->key,
                'value' => $request->value,
            ],
        ]);
    }
}
