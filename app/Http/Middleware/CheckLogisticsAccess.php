<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class CheckLogisticsAccess
{
    public function handle($request, Closure $next)
    {
        $user = Auth::user();
        
        // CEO, secretary, general manager always have access
        if (in_array($user->position, ['secretary', 'special_officer'])) {
            return $next($request);
        }
        
        if ($user->role === 'LOG' && $user->position === 'manager') {
            return $next($request);
        }
        
        if (\DB::table('logistics_access')->where('user_id', $user->id)->value('can_access_logistics')) {
            return $next($request);
        }
        
        abort(403, 'Unauthorized access to Logistics module.');
    }
}