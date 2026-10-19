<?php

namespace App\Http\Controllers\Man\Manager;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Man\Concerns\HandlesProductionGates;

/**
 * Department-supervisor quality gates.
 *
 *  - knitting  : passFabric (knitting output)
 *  - dyeing    : passDye / passSoftener / passSqueezer / passIron
 *  - finishing : pushToLogistics (after Checker Quality inspects the package)
 *
 * Route-level `man.department:<dept>` middleware guarantees only the owning
 * department's supervisor can reach each action.
 */
class ManSupervisorGateController extends Controller
{
    use HandlesProductionGates;
}
