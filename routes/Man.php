<?php

use App\Http\Controllers\Inv\BomController;
use App\Http\Controllers\Man\Manager\ManAssignmentController;
use App\Http\Controllers\Man\Manager\ManSupervisorGateController;
use App\Http\Controllers\Man\Manager\ManufacturingManagerController;
use App\Http\Controllers\Man\ManDashboardController;
use App\Http\Controllers\Man\ManufacturingInventoryController;
use App\Http\Controllers\Man\Staff\CheckerQualityController;
use App\Http\Controllers\Man\Staff\DyeingColorController;
use App\Http\Controllers\Man\Staff\DyeingFabricSoftenerController;
use App\Http\Controllers\Man\Staff\DyeingIroningController;
use App\Http\Controllers\Man\Staff\DyeingLabChemistController;
use App\Http\Controllers\Man\Staff\FinishingPackagingController;
use App\Http\Controllers\Man\Staff\DyeingSqueezerController;
use App\Http\Controllers\Man\Staff\KnittingYarnController;
use App\Http\Controllers\Man\Staff\KnittingMechanicController;
use App\Http\Controllers\Man\Staff\MaintenanceCheckerController;
use App\Http\Controllers\Man\Staff\BoilerOperatorController;
use App\Http\Controllers\Man\Staff\PollutionControlController;
use App\Http\Controllers\Man\Staff\SafetyOfficerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Manufacturing Plant (MAN) Routes
|--------------------------------------------------------------------------
| View routes carry page.permission:<page>,view and mutating routes carry
| page.permission:<page>,edit, so explicit per-page grants are enforced.
| Plant staff without explicit rows keep full access via the middleware's
| native shortcut (all manufacturing_role holders are MAN/staff).
|--------------------------------------------------------------------------
*/
Route::prefix('dashboard/man')->name('man.')->middleware(['auth', 'verified', 'module.access:MAN'])->group(function () {

    // ---- These routes are now inside the manager middleware group below ----
    // Route::get('/interview', [InterviewController::class, 'index'])->name('interview.index');
    // Route::get('/trainee', [TraineeController::class, 'index'])->name('trainee.index');

    // Department-supervisor level (no manufacturing manager: the module is
    // handled by the knitting / dyeing / finishing / maintenance supervisors).
    // NOTE: supervisor assignment + staff-role updates are fulfilled via the
    // CEO module (view/request) and IT Access Control — no MAN access page.
    Route::middleware(['can.access.man.manager'])->group(function () {

        // Manufacturing Inventory (Production Inventory)
        Route::get('/inventory', [ManufacturingInventoryController::class, 'index'])
            ->middleware('page.permission:inventory,view')->name('inventory.index');
        Route::post('/inventory/{item}/container', [ManufacturingInventoryController::class, 'updateContainer'])
            ->middleware('page.permission:inventory,edit')->name('inventory.container.update');
        Route::post('/inventory/{item}/consume', [ManufacturingInventoryController::class, 'consume'])
            ->middleware('page.permission:inventory,edit')->name('inventory.consume');

        // Manufacturing Manager Dashboard & Functions
        Route::get('/', [ManufacturingManagerController::class, 'index'])
            ->middleware('page.permission:dashboard,view')->name('manager.dashboard');
        Route::get('/production', [ManufacturingManagerController::class, 'production'])
            ->middleware('page.permission:production,view')->name('manager.production');
        Route::get('/rejected', [ManufacturingManagerController::class, 'rejected'])
            ->middleware('page.permission:reject,view')->name('manager.rejected');
        Route::post('/rejected/{fabric}/recolor', [ManufacturingManagerController::class, 'recolorFabric'])
            ->middleware('page.permission:reject,edit')->name('manager.rejected.recolor');
        Route::post('/rejected/{fabric}/total-reject', [ManufacturingManagerController::class, 'rejectFabricTotally'])
            ->middleware('page.permission:reject,edit')->name('manager.rejected.total-reject');
        Route::post('/orders/{id}/forward-to-checker', [ManufacturingManagerController::class, 'forwardToChecker'])
            ->middleware('page.permission:production,edit')->name('manager.forward-to-checker');
        Route::post('/staff/{id}/update-role', [ManufacturingManagerController::class, 'updateStaffRole'])
            ->middleware('page.permission:access,edit')->name('manager.update-staff-role');
        Route::post('/handover', [ManufacturingManagerController::class, 'storeHandover'])
            ->middleware('page.permission:production,edit')->name('manager.handover.store');

        // ── Supervisor quality gates (department-scoped) ──────────────────────
        // Ownership: knitting output → knitting supervisor; every dyeing stage →
        // dyeing supervisor; push-to-logistics → finishing supervisor (after
        // Checker Quality inspects the package).
        Route::prefix('gates/knitting')->name('gates.knitting.')
            ->controller(ManSupervisorGateController::class)
            ->middleware('man.department:knitting')
            ->group(function () {
                Route::post('/order/{id}/check-inventory', 'checkInventory')
                    ->middleware('page.permission:production,edit')->name('check-inventory');
                Route::post('/order/{id}/start-production', 'startProduction')
                    ->middleware('page.permission:production,edit')->name('start-production');
                Route::post('/fabric/{id}/pass', 'passFabric')
                    ->middleware('page.permission:production,edit')->name('pass-fabric');
            });

        Route::prefix('gates/dyeing')->name('gates.dyeing.')
            ->controller(ManSupervisorGateController::class)
            ->middleware('man.department:dyeing')
            ->group(function () {
                Route::post('/dye/{id}/pass', 'passDye')
                    ->middleware('page.permission:production,edit')->name('pass-dye');
                Route::post('/softener/{id}/pass', 'passSoftener')
                    ->middleware('page.permission:production,edit')->name('pass-softener');
                Route::post('/squeezer/{id}/pass', 'passSqueezer')
                    ->middleware('page.permission:production,edit')->name('pass-squeezer');
                Route::post('/iron/{id}/pass', 'passIron')
                    ->middleware('page.permission:production,edit')->name('pass-iron');
            });

        Route::prefix('gates/finishing')->name('gates.finishing.')
            ->controller(ManSupervisorGateController::class)
            ->middleware('man.department:finishing')
            ->group(function () {
                Route::post('/package/{id}/push-to-logistics', 'pushToLogistics')
                    ->middleware('page.permission:production,edit')->name('push-to-logistics');
            });

        // ── Job Order assignment board ────────────────────────────────────────
        Route::get('/assignments', [ManAssignmentController::class, 'index'])
            ->middleware('page.permission:production,view')->name('assignments.index');
        Route::post('/assignments', [ManAssignmentController::class, 'store'])
            ->middleware('page.permission:production,edit')->name('assignments.store');
        Route::patch('/assignments/{assignment}', [ManAssignmentController::class, 'update'])
            ->middleware('page.permission:production,edit')->name('assignments.update');
        Route::delete('/assignments/{assignment}', [ManAssignmentController::class, 'destroy'])
            ->middleware('page.permission:production,edit')->name('assignments.destroy');
    });

    // ── Recipes (moved from Inventory → Manufacturing; dyeing lab chemist owns it).
    // Same BomController, new canonical route man.recipes. inv.bom is kept
    // as a legacy alias so old links/bookmarks keep working.
    Route::get('/recipes', [BomController::class, 'index'])
        ->middleware('page.permission:recipes,view')->name('recipes');
    Route::post('/recipes', [BomController::class, 'store'])
        ->middleware('page.permission:recipes,edit')->name('recipes.store');
    Route::put('/recipes/{id}', [BomController::class, 'update'])
        ->middleware('page.permission:recipes,edit')->name('recipes.update');
    Route::delete('/recipes/{id}', [BomController::class, 'destroy'])
        ->middleware('page.permission:recipes,edit')->name('recipes.destroy');

    // ──────────────────────────────────────────────────────────────────────────
    // Staff-specific routes (only for users with matching manufacturing_role)
    // Reads require production/view, writes require production/edit.
    // ──────────────────────────────────────────────────────────────────────────
    Route::middleware(['module.access:MAN'])->group(function () {

        // Staff dashboard entry point (redirects to role-specific dashboard)
        Route::get('/staff', [ManDashboardController::class, 'staffDashboard'])
            ->middleware(['position:staff', 'page.permission:dashboard,view'])
            ->name('employee.dashboard');

        // Knitting Yarn
        Route::prefix('knitting-yarn')->name('staff.knitting-yarn.')->controller(KnittingYarnController::class)
            ->middleware('man.role:knitting_yarn')
            ->group(function () {
                Route::get('/', 'index')
                    ->middleware('page.permission:dashboard,view')->name('dashboard');
                Route::get('/knitting-yarn', 'knittingYarn')
                    ->middleware('page.permission:knitting_yarn,view')->name('page');
                Route::get('/reports', 'reports')
                    ->middleware('page.permission:reports,view')->name('reports');
                Route::get('/history', 'history')
                    ->middleware('page.permission:history,view')->name('history');
                Route::get('/flag-reports', 'flagReports')
                    ->middleware('page.permission:flag_reports,view')->name('flag-reports');

                Route::post('/fabric', 'storeFabric')
                    ->middleware('page.permission:knitting_yarn,edit')->name('store-fabric');
                Route::post('/machine-report', 'reportMachine')
                    ->middleware('page.permission:knitting_yarn,edit')->name('report-machine');
                Route::post('mark-done/{id}', 'markDone')
                    ->middleware('page.permission:knitting_yarn,edit')->name('mark-done');
                Route::post('unmark-done/{id}', 'unmarkDone')
                    ->middleware('page.permission:knitting_yarn,edit')->name('unmark-done');
                // Flag Reports: yarn staff → knitting mechanics channel
                Route::post('/flag-report', 'storeFlag')
                    ->middleware('page.permission:flag_reports,edit')->name('flag-report');
            });

        // Knitting Mechanic (setup, changeover, calibration, preventive)
        Route::prefix('knitting-mechanic')->name('staff.knitting-mechanic.')->controller(KnittingMechanicController::class)
            ->middleware('man.role:knitting_mechanic')
            ->group(function () {
                Route::get('/', 'index')
                    ->middleware('page.permission:dashboard,view')->name('dashboard');
                Route::get('/setups', 'setups')
                    ->middleware('page.permission:knitting_mechanic,view')->name('page');
                Route::get('/history', 'history')
                    ->middleware('page.permission:history,view')->name('history');
                Route::get('/reports', 'reports')
                    ->middleware('page.permission:reports,view')->name('reports');

                Route::post('/setup', 'storeSetup')
                    ->middleware('page.permission:knitting_mechanic,edit')->name('store-setup');
                Route::patch('/setup/{setup}/status', 'updateStatus')
                    ->middleware('page.permission:knitting_mechanic,edit')->name('update-status');
                Route::post('/machine-report', 'reportMachine')
                    ->middleware('page.permission:knitting_mechanic,edit')->name('report-machine');
                // Incoming yarn-staff flags: acknowledge → resolve
                Route::patch('/flag/{flag}/acknowledge', 'acknowledgeFlag')
                    ->middleware('page.permission:flag_reports,edit')->name('flag.acknowledge');
                Route::patch('/flag/{flag}/resolve', 'resolveFlag')
                    ->middleware('page.permission:flag_reports,edit')->name('flag.resolve');
            });

        // Dyeing Lab Chemist (shade development, recipe, testing, transfer)
        Route::prefix('dyeing-lab-chemist')->name('staff.dyeing-lab-chemist.')->controller(DyeingLabChemistController::class)
            ->middleware('man.role:dyeing_lab_chemist')
            ->group(function () {
                Route::get('/', 'index')
                    ->middleware('page.permission:dashboard,view')->name('dashboard');
                Route::get('/shades', 'shades')
                    ->middleware('page.permission:dyeing_lab_chemist,view')->name('page');
                Route::get('/tests', 'tests')
                    ->middleware('page.permission:lab_tests,view')->name('tests');
                Route::get('/inventory', 'inventory')
                    ->middleware('page.permission:lab_inventory,view')->name('inventory');
                Route::get('/transfer', 'transfer')
                    ->middleware('page.permission:lab_transfer,view')->name('transfer');
                Route::get('/history', 'history')
                    ->middleware('page.permission:history,view')->name('history');

                Route::post('/dip', 'storeDip')
                    ->middleware('page.permission:dyeing_lab_chemist,edit')->name('store-dip');
                Route::post('/trial', 'storeTrial')
                    ->middleware('page.permission:dyeing_lab_chemist,edit')->name('store-trial');
                Route::patch('/dip/{dip}/status', 'updateDipStatus')
                    ->middleware('page.permission:dyeing_lab_chemist,edit')->name('update-dip-status');
                // CRM fabric sample requests (from inquiry conversations)
                Route::patch('/sample-requests/{sampleRequest}/start', 'startSample')
                    ->middleware('page.permission:dyeing_lab_chemist,edit')->name('sample.start');
                Route::post('/sample-requests/{sampleRequest}/formulate', 'formulateSample')
                    ->middleware('page.permission:dyeing_lab_chemist,edit')->name('sample.formulate');
                Route::post('/test', 'storeTest')
                    ->middleware('page.permission:lab_tests,edit')->name('store-test');
                Route::post('/solution', 'storeSolution')
                    ->middleware('page.permission:lab_tests,edit')->name('store-solution');
                Route::delete('/solution/{solution}', 'destroySolution')
                    ->middleware('page.permission:lab_tests,edit')->name('destroy-solution');
                Route::post('/sign-off', 'signOff')
                    ->middleware('page.permission:lab_tests,edit')->name('sign-off');
            });

        // Dyeing Color
        Route::prefix('dyeing-color')->name('staff.dyeing-color.')->controller(DyeingColorController::class)
            ->middleware('man.role:dyeing_color')
            ->group(function () {
                Route::get('/', 'index')
                    ->middleware('page.permission:dashboard,view')->name('dashboard');
                Route::get('/dyeing-color', 'dyeingColor')
                    ->middleware('page.permission:dyeing_color,view')->name('page');
                Route::get('/reports', 'reports')
                    ->middleware('page.permission:reports,view')->name('reports');
                Route::get('/history', 'history')
                    ->middleware('page.permission:history,view')->name('history');

                Route::post('/dye', 'storeDye')
                    ->middleware('page.permission:dyeing_color,edit')->name('store-dye');
                Route::post('/machine-report', 'reportMachine')
                    ->middleware('page.permission:dyeing_color,edit')->name('report-machine');
            });

        // Dyeing Fabric Softener
        Route::prefix('dyeing-fabric-softener')->name('staff.dyeing-fabric-softener.')->controller(DyeingFabricSoftenerController::class)
            ->middleware('man.role:dyeing_fabric_softener')
            ->group(function () {
                Route::get('/', 'index')
                    ->middleware('page.permission:dashboard,view')->name('dashboard');
                Route::get('/dyeing-fabric-softener', 'dyeingFabricSoftener')
                    ->middleware('page.permission:dyeing_fabric_softener,view')->name('page');
                Route::get('/reports', 'reports')
                    ->middleware('page.permission:reports,view')->name('reports');
                Route::get('/history', 'history')
                    ->middleware('page.permission:history,view')->name('history');

                Route::post('/soften', 'storeSoftener')
                    ->middleware('page.permission:dyeing_fabric_softener,edit')->name('store-soften');
                Route::post('/machine-report', 'reportMachine')
                    ->middleware('page.permission:dyeing_fabric_softener,edit')->name('report-machine');
            });

        // Dyeing Squeezer
        Route::prefix('dyeing-squeezer')->name('staff.dyeing-squeezer.')->controller(DyeingSqueezerController::class)
            ->middleware('man.role:dyeing_squeezer')
            ->group(function () {
                Route::get('/', 'index')
                    ->middleware('page.permission:dashboard,view')->name('dashboard');
                Route::get('/dyeing-squeezer', 'dyeingSqueezer')
                    ->middleware('page.permission:dyeing_squeezer,view')->name('page');
                Route::get('/reports', 'reports')
                    ->middleware('page.permission:reports,view')->name('reports');
                Route::get('/history', 'history')
                    ->middleware('page.permission:history,view')->name('history');

                Route::post('/squeeze', 'storeSqueezer')
                    ->middleware('page.permission:dyeing_squeezer,edit')->name('store-squeeze');
                Route::post('/machine-report', 'reportMachine')
                    ->middleware('page.permission:dyeing_squeezer,edit')->name('report-machine');
            });

        // Dyeing Ironing
        Route::prefix('dyeing-ironing')->name('staff.dyeing-ironing.')->controller(DyeingIroningController::class)
            ->middleware('man.role:dyeing_ironing')
            ->group(function () {
                Route::get('/', 'index')
                    ->middleware('page.permission:dashboard,view')->name('dashboard');
                Route::get('/dyeing-ironing', 'dyeingIroning')
                    ->middleware('page.permission:dyeing_ironing,view')->name('page');
                Route::get('/reports', 'reports')
                    ->middleware('page.permission:reports,view')->name('reports');
                Route::get('/history', 'history')
                    ->middleware('page.permission:history,view')->name('history');

                Route::post('/iron', 'storeIron')
                    ->middleware('page.permission:dyeing_ironing,edit')->name('store-iron');
                Route::post('/machine-report', 'reportMachine')
                    ->middleware('page.permission:dyeing_ironing,edit')->name('report-machine');
            });

        // Finishing Packaging (ironing flows directly here; forming stage removed)
        Route::prefix('finishing-packaging')->name('staff.finishing-packaging.')->controller(FinishingPackagingController::class)
            ->middleware('man.role:finishing_packaging')
            ->group(function () {
                Route::get('/', 'index')
                    ->middleware('page.permission:dashboard,view')->name('dashboard');
                Route::get('/packaging', 'packaging')
                    ->middleware('page.permission:finishing_packaging,view')->name('page');
                Route::get('/reports', 'reports')
                    ->middleware('page.permission:reports,view')->name('reports');
                Route::get('/history', 'history')
                    ->middleware('page.permission:history,view')->name('history');

                Route::post('/package', 'storePackage')
                    ->middleware('page.permission:finishing_packaging,edit')->name('store-package');
                Route::post('/machine-report', 'reportMachine')
                    ->middleware('page.permission:finishing_packaging,edit')->name('report-machine');
            });

        // Maintenance Checker
        Route::prefix('maintenance-checker')->name('staff.maintenance-checker.')->controller(MaintenanceCheckerController::class)
            ->middleware('man.role:maintenance_checker')
            ->group(function () {
                Route::get('/', 'index')
                    ->middleware('page.permission:dashboard,view')->name('dashboard');
                Route::get('/maintenance', 'maintenance')
                    ->middleware('page.permission:maintenance_checker,view')->name('page');
                Route::get('/reports', 'reports')
                    ->middleware('page.permission:reports,view')->name('reports');

                Route::post('/machine', 'storeMachine')
                    ->middleware('page.permission:maintenance_checker,edit')->name('store-machine');
                Route::patch('/machine/{id}', 'updateMachineStatus')
                    ->middleware('page.permission:maintenance_checker,edit')->name('update-machine');
                Route::patch('/report/{id}', 'resolveReport')
                    ->middleware('page.permission:maintenance_checker,edit')->name('resolve-report');
            });

        // Boiler Operator (boiler sub-department under Maintenance)
        Route::prefix('boiler-operator')->name('staff.boiler-operator.')->controller(BoilerOperatorController::class)
            ->middleware('man.role:boiler_operator')
            ->group(function () {
                Route::get('/', 'index')
                    ->middleware('page.permission:dashboard,view')->name('dashboard');
                Route::get('/logs', 'logs')
                    ->middleware('page.permission:boiler_operator,view')->name('page');
                Route::get('/history', 'history')
                    ->middleware('page.permission:history,view')->name('history');
                Route::get('/reports', 'reports')
                    ->middleware('page.permission:reports,view')->name('reports');

                Route::post('/log', 'storeLog')
                    ->middleware('page.permission:boiler_operator,edit')->name('store-log');
                Route::post('/machine-report', 'reportMachine')
                    ->middleware('page.permission:boiler_operator,edit')->name('report-machine');
            });

        // Pollution Control Operator (Maintenance department, DENR compliance)
        Route::prefix('pollution-control')->name('staff.pollution-control.')->controller(PollutionControlController::class)
            ->middleware('man.role:pollution_control_operator')
            ->group(function () {
                Route::get('/', 'index')
                    ->middleware('page.permission:dashboard,view')->name('dashboard');
                Route::get('/logs', 'logs')
                    ->middleware('page.permission:pollution_control,view')->name('page');
                Route::get('/history', 'history')
                    ->middleware('page.permission:history,view')->name('history');

                Route::post('/record', 'storeRecord')
                    ->middleware('page.permission:pollution_control,edit')->name('store-record');
            });

        // Safety Officer (Maintenance department, OSH compliance)
        Route::prefix('safety-officer')->name('staff.safety-officer.')->controller(SafetyOfficerController::class)
            ->middleware('man.role:safety_officer')
            ->group(function () {
                Route::get('/', 'index')
                    ->middleware('page.permission:dashboard,view')->name('dashboard');
                Route::get('/incidents', 'incidents')
                    ->middleware('page.permission:safety_officer,view')->name('page');
                Route::get('/history', 'history')
                    ->middleware('page.permission:history,view')->name('history');

                Route::post('/incident', 'storeIncident')
                    ->middleware('page.permission:safety_officer,edit')->name('store-incident');
                Route::patch('/incident/{incident}/status', 'updateStatus')
                    ->middleware('page.permission:safety_officer,edit')->name('update-status');
            });

        // Quality Checker (Finishing) — inspects packaging outputs only. The
        // knitting/dyeing gates moved to the department supervisors (man.gates.*).
        Route::prefix('checker-quality')->name('staff.checker-quality.')->controller(CheckerQualityController::class)
            ->middleware(['man.role:checker_quality'])
            ->group(function () {
                Route::get('/', 'index')
                    ->middleware('page.permission:dashboard,view')->name('dashboard');
                Route::get('/production', 'production')
                    ->middleware('page.permission:checker_quality,view')->name('production');
                // Work page alias: every staff role exposes a `.page` route
                // for the sidebar; checker-quality's work page is production.
                Route::get('/work', 'production')
                    ->middleware('page.permission:checker_quality,view')->name('page');

                Route::post('/package/{id}/inspect', 'inspectPackage')
                    ->middleware('page.permission:checker_quality,edit')->name('inspect-package');
            });
    });
});
