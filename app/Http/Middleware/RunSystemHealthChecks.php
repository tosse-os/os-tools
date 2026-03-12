<?php

namespace App\Http\Middleware;

use App\Services\SystemHealthService;
use Closure;
use Illuminate\Http\Request;

class RunSystemHealthChecks
{
    public function __construct(private readonly SystemHealthService $systemHealthService)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        if (auth()->check() && !$request->session()->has('system_health_checked')) {
            $warnings = $this->systemHealthService->collectWarnings();

            if ($warnings !== []) {
                $request->session()->flash('system_warnings', $warnings);
            }

            $request->session()->put('system_health_checked', true);
        }

        return $next($request);
    }
}
