<?php

namespace App\Http\Middleware;

use App\Utils\ModuleUtil;
use Closure;
use Illuminate\Http\Request;

class EnforceActiveSubscription
{
    protected $moduleUtil;

    public function __construct(ModuleUtil $moduleUtil)
    {
        $this->moduleUtil = $moduleUtil;
    }

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (
            ! $user
            || empty($user->business_id)
            || $user->can('superadmin')
            || $this->isBillingOrLogoutRequest($request)
            || $this->moduleUtil->isSubscribed($user->business_id)
            || $this->isReadOnlyRequest($request)
        ) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'error' => [
                    'message' => __('superadmin::lang.trial_read_only'),
                ],
                'subscription_expired' => true,
            ], 402);
        }

        return redirect()
            ->action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'index'])
            ->with('status', [
                'success' => 0,
                'msg' => __('superadmin::lang.trial_read_only'),
            ]);
    }

    private function isBillingOrLogoutRequest(Request $request): bool
    {
        $path = trim($request->path(), '/');

        return $path === 'logout'
            || $request->routeIs('logout')
            || preg_match('/^subscription(?:\/|$)/', $path) === 1;
    }

    private function isReadOnlyRequest(Request $request): bool
    {
        if (! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return false;
        }

        $route = $request->route();
        $actionMethod = is_object($route) ? $route->getActionMethod() : '';

        return preg_match(
            '/(destroy|delete|activate|deactivate|toggle|reset|remove|update|send|install|sync|import|store|save|run|approve|cancel|process|close|open|adjust|clear|add|generate|recalculate|retry|dispatch|assign|mark|confirm|change|set|restore|backup|register|submit)/i',
            $actionMethod
        ) !== 1;
    }
}
