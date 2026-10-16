<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Guards the B2B order-to-cash chain against dead routes: every route in
 * the client → negotiation → production → delivery → finance pipeline must
 * resolve to an existing controller method. (Several routes historically
 * pointed at missing methods and 500'd on first use.)
 */
class B2BPipelineRouteAuditTest extends TestCase
{
    public function test_every_pipeline_route_resolves_to_an_existing_controller_method(): void
    {
        $prefixes = [
            'client\.', 'eco\.', 'crm\.', 'ord\.', 'man\.', 'warehouse',
            'logistics\.', 'fin\.manager', 'fin\.employee',
            'supplier\.', 'pro\.manager', 'ceo\.approvals\.credit',
        ];

        $broken = [];
        $checked = 0;
        foreach (Route::getRoutes() as $route) {
            $name = $route->getName() ?? '';
            $wanted = false;
            foreach ($prefixes as $p) {
                if (preg_match('/^' . $p . '/', $name)) {
                    $wanted = true;
                    break;
                }
            }
            if (! $wanted) {
                continue;
            }
            $checked++;
            $action = $route->getActionName();
            if (! str_contains($action, '@')) {
                continue;
            }
            [$class, $method] = explode('@', $action);
            if (! class_exists($class)) {
                $broken[] = "{$name} => missing class {$class}";
            } elseif (! method_exists($class, $method)) {
                $broken[] = "{$name} => missing method {$class}@{$method}";
            }
        }

        $this->assertGreaterThan(50, $checked, 'Expected the pipeline route set to be sizable.');
        $this->assertSame([], $broken, 'Dead pipeline routes: ' . implode('; ', $broken));
    }
}
