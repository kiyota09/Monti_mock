<?php

use App\Http\Controllers\Inv\BomController;
use App\Http\Controllers\Man\Manager\ManAssignmentController;
use App\Http\Controllers\Man\Manager\ManSupervisorGateController;
use App\Http\Controllers\Man\Manager\KnittingMachineController;
use App\Http\Controllers\Man\Manager\DyeingWorkflowController;
use App\Http\Controllers\Man\Manager\DyeingProductionRecipeController;
use App\Http\Controllers\Man\Manager\FinishingWorkflowController;
use App\Http\Controllers\Man\Manager\ManufacturingManagerController;
use App\Http\Controllers\Man\Manager\ManStaffController;
use App\Http\Controllers\Man\ManDashboardController;
use App\Http\Controllers\Man\ManufacturingInventoryController;
use App\Http\Controllers\Man\Staff\CheckerQualityController;
use App\Http\Controllers\Man\Staff\DyeingColorController;
use App\Http\Controllers\Man\Staff\DyeingBleachingController;
use App\Http\Controllers\Man\Staff\DyeingTreatmentController;
use App\Http\Controllers\Man\Staff\DyeingIroningController;
use App\Http\Controllers\Man\Staff\DyeingLabChemistController;
use App\Http\Controllers\Man\Staff\FinishingPackagingController;
use App\Http\Controllers\Man\Staff\DyeingSqueezerController;
use App\Http\Controllers\Man\Staff\KnittingYarnController;
use App\Http\Controllers\Man\Staff\KnittingMechanicController;
use App\Http\Controllers\Man\Staff\KnittingInspectorController;
use App\Http\Controllers\Man\Staff\KnittingSampleController;
use App\Http\Controllers\Man\Staff\FinishingInspectorController;
use App\Http\Controllers\Man\Staff\FinishingStationController;
use App\Http\Controllers\Man\Staff\FinishingWorkflowStaffController;
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
Route::prefix('dashboard/man')->name('man.')->middleware(['auth', 'verified', 'module.access:MAN', 'geofence'])->group(function () {

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
        Route::get('/department-staff', [ManStaffController::class, 'index'])
            ->middleware('page.permission:staff,view')->name('manager.staff');
        Route::post('/staff/transfer-requests', [ManStaffController::class, 'requestTransfer'])
            ->middleware('page.permission:staff,edit')->name('manager.staff.transfer-requests.store');
        Route::post('/staff/transfer-requests/{transferRequest}/decision', [ManStaffController::class, 'decideTransfer'])
            ->middleware('page.permission:staff,edit')->name('manager.staff.transfer-requests.decision');
        Route::post('/staff/transfer-requests/bulk-decision', [ManStaffController::class, 'bulkDecideTransfers'])
            ->middleware('page.permission:staff,edit')->name('manager.staff.transfer-requests.bulk-decision');
        Route::post('/staff/transfer-requests/{transferRequest}/cancel', [ManStaffController::class, 'cancelTransfer'])
            ->middleware('page.permission:staff,edit')->name('manager.staff.transfer-requests.cancel');
        Route::get('/production', [ManufacturingManagerController::class, 'production'])
            ->middleware('page.permission:production,view')->name('manager.production');
        Route::post('/production/dyeing-stages/{record}/review', [DyeingWorkflowController::class, 'review'])
            ->middleware(['man.department:dyeing', 'page.permission:production,edit'])
            ->name('manager.production.dyeing.review');
        Route::post('/production/knitting/replacements/{remake}/review', [ManAssignmentController::class, 'reviewDyeingReplacement'])
            ->middleware(['man.department:knitting', 'page.permission:production,edit'])
            ->name('manager.production.knitting.replacements.review');
        Route::post('/production/finishing-work-orders/{workOrder}/push-to-logistics', [FinishingWorkflowController::class, 'sendToLogistics'])
            ->middleware(['man.department:finishing', 'page.permission:production,edit'])
            ->name('manager.production.finishing.push-to-logistics');
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
        // Ownership: dyeing stages → dyeing supervisor; push-to-logistics →
        // finishing supervisor (after
        // Finishing Quality Checker inspects the package).
        Route::prefix('gates/knitting')->name('gates.knitting.')
            ->controller(ManSupervisorGateController::class)
            ->middleware('man.department:knitting')
            ->group(function () {
                Route::post('/order/{id}/check-inventory', 'checkInventory')
                    ->middleware('page.permission:production,edit')->name('check-inventory');
                Route::post('/order/{id}/start-production', 'startProduction')
                    ->middleware('page.permission:production,edit')->name('start-production');
            });

        Route::prefix('gates/dyeing')->name('gates.dyeing.')
            ->controller(ManSupervisorGateController::class)
            ->middleware('man.department:dyeing')
            ->group(function () {
                Route::post('/dye/{id}/pass', 'passDye')
                    ->middleware('page.permission:production,edit')->name('pass-dye');
                Route::post('/bleaching/{id}/pass', 'passBleaching')
                    ->middleware('page.permission:production,edit')->name('pass-bleaching');
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
            ->middleware('page.permission:assignments,view')->name('assignments.index');
        Route::get('/assignments/calendar', [ManAssignmentController::class, 'calendar'])
            ->middleware('page.permission:assignments,view')->name('assignments.calendar');
        Route::post('/assignments', [ManAssignmentController::class, 'store'])
            ->middleware('page.permission:assignments,edit')->name('assignments.store');
        Route::post('/assignments/dyeing-replacements/{remake}/assign', [ManAssignmentController::class, 'assignDyeingReplacement'])
            ->middleware(['man.department:knitting', 'page.permission:assignments,edit'])->name('assignments.dyeing-replacements.assign');
        Route::patch('/assignments/{order}/dyeing-route', [ManAssignmentController::class, 'updateDyeingRoute'])
            ->middleware(['man.department:dyeing', 'page.permission:assignments,edit'])->name('assignments.dyeing-route');
        Route::patch('/assignments/{order}/dyeing-color-group', [ManAssignmentController::class, 'updateDyeingColorGroup'])
            ->middleware(['man.department:dyeing', 'page.permission:assignments,edit'])->name('assignments.dyeing-color-group');
        Route::patch('/assignments/{order}/dyeing-process', [ManAssignmentController::class, 'updateDyeingProcess'])
            ->middleware(['man.department:dyeing', 'page.permission:assignments,edit'])->name('assignments.dyeing-process');
        Route::patch('/assignments/dyeing/queue', [ManAssignmentController::class, 'reorderDyeingQueue'])
            ->middleware(['man.department:dyeing', 'page.permission:assignments,edit'])->name('assignments.dyeing-queue');
        Route::patch('/assignments/dyeing/staff-order', [ManAssignmentController::class, 'reorderDyeingStaff'])
            ->middleware(['man.department:dyeing', 'page.permission:assignments,edit'])->name('assignments.dyeing-staff-order');
        Route::post('/assignments/{order}/release-knitting', [ManAssignmentController::class, 'releaseKnitting'])
            ->middleware(['man.department:knitting', 'page.permission:assignments,edit'])->name('assignments.knitting.release');
        Route::post('/production/orders/{order}/decline-knitting', [ManAssignmentController::class, 'declineKnittingHandoff'])
            ->middleware(['man.department:knitting', 'page.permission:production,edit'])->name('production.knitting.decline');
        Route::post('/production/orders/{order}/release-knitting', [ManAssignmentController::class, 'releaseKnitting'])
            ->middleware(['man.department:knitting', 'page.permission:production,edit'])->name('production.knitting.release');
        Route::patch('/assignments/{assignment}', [ManAssignmentController::class, 'update'])
            ->middleware('page.permission:assignments,edit')->name('assignments.update');
        Route::delete('/assignments/{assignment}', [ManAssignmentController::class, 'destroy'])
            ->middleware('page.permission:assignments,edit')->name('assignments.destroy');
        Route::get('/knitting-machines', [KnittingMachineController::class, 'index'])
            ->middleware(['man.department:knitting', 'page.permission:knitting_machines,view'])->name('knitting-machines.index');
        Route::put('/knitting-machines/{machine}/assign', [KnittingMachineController::class, 'assign'])
            ->middleware(['man.department:knitting', 'page.permission:knitting_machines,edit'])->name('knitting-machines.assign');
        Route::delete('/knitting-machines/{machine}/assign', [KnittingMachineController::class, 'unassign'])
            ->middleware(['man.department:knitting', 'page.permission:knitting_machines,edit'])->name('knitting-machines.unassign');
        Route::post('/knitting-machines', [KnittingMachineController::class, 'registerKnitting'])
            ->middleware(['man.department:knitting', 'page.permission:knitting_machines,edit'])->name('knitting-machines.store');
        Route::put('/knitting-machines/{machine}/needles', [KnittingMachineController::class, 'updateNeedleCount'])
            ->middleware(['man.department:knitting', 'page.permission:knitting_machines,edit'])->name('knitting-machines.needles.update');
        Route::post('/knitting-machines/{machine}/needles/replace', [KnittingMachineController::class, 'replaceNeedles'])
            ->middleware(['man.department:knitting', 'page.permission:knitting_machines,edit'])->name('knitting-machines.needles.replace');
        Route::get('/dyeing-machines', [KnittingMachineController::class, 'dyeing'])
            ->middleware(['man.department:dyeing', 'page.permission:dyeing_machines,view'])->name('dyeing-machines.index');
        Route::put('/dyeing-machines/{machine}/assign', [KnittingMachineController::class, 'assignDyeing'])
            ->middleware(['man.department:dyeing', 'page.permission:dyeing_machines,edit'])->name('dyeing-machines.assign');
        Route::delete('/dyeing-machines/{machine}/assign', [KnittingMachineController::class, 'unassignDyeing'])
            ->middleware(['man.department:dyeing', 'page.permission:dyeing_machines,edit'])->name('dyeing-machines.unassign');
        Route::get('/dyeing-workflow', [DyeingWorkflowController::class, 'index'])
            ->middleware(['man.department:dyeing', 'page.permission:dyeing_workflow,view'])->name('dyeing-workflow.index');
        Route::get('/dyeing-production-recipes', [DyeingProductionRecipeController::class, 'index'])
            ->middleware(['man.department:dyeing', 'page.permission:dyeing_workflow,view'])->name('dyeing-production-recipes.index');
        Route::put('/dyeing-production-recipes/{order}', [DyeingProductionRecipeController::class, 'update'])
            ->middleware(['man.department:dyeing', 'page.permission:dyeing_workflow,edit'])->name('dyeing-production-recipes.update');
        Route::post('/dyeing-workflow/batches', [DyeingWorkflowController::class, 'storeBatch'])
            ->middleware(['man.department:dyeing', 'page.permission:dyeing_workflow,edit'])->name('dyeing-workflow.batches.store');
        Route::post('/dyeing-workflow/stages/{record}/review', [DyeingWorkflowController::class, 'review'])
            ->middleware(['man.department:dyeing', 'page.permission:dyeing_workflow,edit'])->name('dyeing-workflow.review');
        Route::post('/dyeing-workflow/remakes/{remake}/release', [DyeingWorkflowController::class, 'releaseRemake'])
            ->middleware(['man.department:dyeing', 'page.permission:dyeing_workflow,edit'])->name('dyeing-workflow.remakes.release');
        Route::post('/dyeing-workflow/orders/{order}/release', [DyeingWorkflowController::class, 'release'])
            ->middleware(['man.department:dyeing', 'page.permission:dyeing_workflow,edit'])->name('dyeing-workflow.release');
        Route::get('/finishing-workflow', [FinishingWorkflowController::class, 'index'])
            ->middleware(['man.department:finishing', 'page.permission:finishing_workflow,view'])->name('finishing-workflow.index');
        Route::post('/finishing-workflow/start', [FinishingWorkflowController::class, 'start'])
            ->middleware(['man.department:finishing', 'page.permission:finishing_workflow,edit'])->name('finishing-workflow.start');
        Route::post('/finishing-workflow/{workOrder}/drying', [FinishingWorkflowController::class, 'assignDrying'])
            ->middleware(['man.department:finishing', 'page.permission:finishing_workflow,edit'])->name('finishing-workflow.drying');
        Route::post('/finishing-workflow/{workOrder}/send-to-logistics', [FinishingWorkflowController::class, 'sendToLogistics'])
            ->middleware(['man.department:finishing', 'page.permission:finishing_workflow,edit'])->name('finishing-workflow.logistics');
        Route::post('/dyeing-machines', [KnittingMachineController::class, 'registerDyeing'])
            ->middleware(['man.department:dyeing', 'page.permission:dyeing_machines,edit'])->name('dyeing-machines.store');
        Route::patch('/dyeing-machines/{machine}/capacity', [KnittingMachineController::class, 'updateDyeingCapacity'])
            ->middleware(['man.department:dyeing', 'page.permission:dyeing_machines,edit'])->name('dyeing-machines.capacity');
        Route::get('/finishing-machines', [KnittingMachineController::class, 'finishing'])
            ->middleware(['man.department:finishing', 'page.permission:finishing_machines,view'])->name('finishing-machines.index');
        Route::put('/finishing-machines/{machine}/assign', [KnittingMachineController::class, 'assignFinishing'])
            ->middleware(['man.department:finishing', 'page.permission:finishing_machines,edit'])->name('finishing-machines.assign');
        Route::delete('/finishing-machines/{machine}/assign', [KnittingMachineController::class, 'unassignFinishing'])
            ->middleware(['man.department:finishing', 'page.permission:finishing_machines,edit'])->name('finishing-machines.unassign');
        Route::post('/finishing-machines', [KnittingMachineController::class, 'registerFinishing'])
            ->middleware(['man.department:finishing', 'page.permission:finishing_machines,edit'])->name('finishing-machines.store');
        Route::get('/boiler-machines', [KnittingMachineController::class, 'boiler'])
            ->middleware(['man.department:boiler', 'page.permission:boiler_machines,view'])->name('boiler-machines.index');
        Route::post('/boiler-machines', [KnittingMachineController::class, 'registerBoiler'])
            ->middleware(['man.department:boiler', 'page.permission:boiler_machines,edit'])->name('boiler-machines.store');
        Route::put('/boiler-machines/{machine}/steam-machines', [KnittingMachineController::class, 'syncBoilerSteamMachines'])
            ->middleware(['man.department:boiler', 'page.permission:boiler_machines,edit'])->name('boiler-machines.steam-machines.sync');
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
                Route::post('/orders/{salesOrder}/request-setup', 'requestSetup')
                    ->middleware('page.permission:knitting_yarn,edit')->name('request-setup');
                Route::post('/orders/{salesOrder}/submit-for-supervisor', 'submitForSupervisor')
                    ->middleware('page.permission:knitting_yarn,edit')->name('submit-for-supervisor');
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

        // Knitting Inspector (approves or returns knitting output)
        Route::prefix('knitting-inspector')->name('staff.knitting-inspector.')->controller(KnittingInspectorController::class)
            ->middleware('man.role:knitting_inspector')
            ->group(function () {
                Route::get('/', 'dashboard')->middleware('page.permission:dashboard,view')->name('dashboard');
                Route::get('/inspection', 'inspection')->middleware('page.permission:knitting_inspector,view')->name('page');
                Route::get('/history', 'history')->middleware('page.permission:history,view')->name('history');
                Route::get('/reports', 'reports')->middleware('page.permission:reports,view')->name('reports');
                Route::post('/fabric/{id}/pass', 'passFabric')->middleware('page.permission:knitting_inspector,edit')->name('pass-fabric');
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
                Route::get('/pantones', 'pantones')
                    ->middleware('page.permission:pantones,view')->name('pantones');
                Route::get('/knit-fabric-requests', 'knitFabricRequests')
                    ->middleware('page.permission:knit_fabric_requests,view')->name('knit-fabric-requests');
                Route::get('/pantones/{pantoneReference}', 'showPantone')
                    ->middleware('page.permission:pantones,view')->name('pantones.show');
                Route::post('/pantones', 'storePantone')
                    ->middleware('page.permission:dyeing_lab_chemist,edit')->name('pantones.store');
                Route::patch('/pantones/{pantoneReference}', 'updatePantone')
                    ->middleware('page.permission:dyeing_lab_chemist,edit')->name('pantones.update');
                Route::patch('/pantones/{pantoneReference}/fabrics/{pantoneFabricFormula}', 'updatePantoneFabricFormula')
                    ->middleware('page.permission:dyeing_lab_chemist,edit')->name('pantones.fabric-formula.update');
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
                Route::patch('/sample-requests/{sampleRequest}/discard', 'discardSample')
                    ->middleware('page.permission:dyeing_lab_chemist,edit')->name('sample.discard');
                Route::post('/sample-requests/{sampleRequest}/formulate', 'formulateSample')
                    ->middleware('page.permission:dyeing_lab_chemist,edit')->name('sample.formulate');
                Route::patch('/sample-requests/{sampleRequest}/pantone', 'linkSamplePantone')
                    ->middleware('page.permission:dyeing_lab_chemist,edit')->name('sample.pantone');
                Route::delete('/sample-requests/{sampleRequest}/pantone', 'unlinkSamplePantone')
                    ->middleware('page.permission:dyeing_lab_chemist,edit')->name('sample.pantone.unlink');
                Route::post('/sample-requests/{sampleRequest}/request-knit-fabric', 'requestKnitFabric')
                    ->middleware('page.permission:dyeing_lab_chemist,edit')->name('sample.request-knit-fabric');
                Route::post('/test', 'storeTest')
                    ->middleware('page.permission:lab_tests,edit')->name('store-test');
                Route::post('/solution', 'storeSolution')
                    ->middleware('page.permission:lab_tests,edit')->name('store-solution');
                Route::delete('/solution/{solution}', 'destroySolution')
                    ->middleware('page.permission:lab_tests,edit')->name('destroy-solution');
                Route::post('/sign-off', 'signOff')
                    ->middleware('page.permission:lab_tests,edit')->name('sign-off');
            });

        // Small knit fabric samples for Dyeing Lab Chemist requests; separate from Job Order fabric.
        Route::prefix('knitting-sample')->name('staff.knitting-sample.')->controller(KnittingSampleController::class)
            ->middleware('man.role:knitting_sample')->group(function () {
                Route::get('/', 'dashboard')->middleware('page.permission:dashboard,view')->name('dashboard');
                Route::get('/queue', 'index')->middleware('page.permission:knitting_sample,view')->name('page');
                Route::get('/history', 'history')->middleware('page.permission:history,view')->name('history');
                Route::get('/reports', 'reports')->middleware('page.permission:reports,view')->name('reports');
                Route::patch('/{sample}/start', 'start')->middleware('page.permission:knitting_sample,edit')->name('start');
                Route::post('/{sample}/complete', 'complete')->middleware('page.permission:knitting_sample,edit')->name('complete');
                Route::post('/queue/bulk-complete', 'bulkComplete')->middleware('page.permission:knitting_sample,edit')->name('bulk-complete');
                Route::patch('/{sample}/discard', 'discard')->middleware('page.permission:knitting_sample,edit')->name('discard');
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

        // Dyeing Bleaching
        Route::prefix('dyeing-bleaching')->name('staff.dyeing-bleaching.')->controller(DyeingBleachingController::class)
            ->middleware('man.role:dyeing_bleaching')
            ->group(function () {
                Route::get('/', 'index')
                    ->middleware('page.permission:dashboard,view')->name('dashboard');
                Route::get('/bleaching', 'bleaching')
                    ->middleware('page.permission:dyeing_bleaching,view')->name('page');
                Route::get('/history', 'history')
                    ->middleware('page.permission:history,view')->name('history');
                Route::get('/reports', 'reports')
                    ->middleware('page.permission:reports,view')->name('reports');
                Route::post('/bleach', 'storeBleaching')
                    ->middleware('page.permission:dyeing_bleaching,edit')->name('store-bleach');
            });

        // Dyeing Post Treatment staff workspace
        Route::prefix('dyeing-treatment')->name('staff.dyeing-treatment.')->controller(DyeingTreatmentController::class)
            ->middleware('man.role:dyeing_treatment')->group(function () {
                Route::get('/', 'dashboard')->middleware('page.permission:dashboard,view')->name('dashboard');
                Route::get('/work', 'work')->middleware('page.permission:dyeing_treatment,view')->name('page');
                Route::post('/work/submit', 'submit')->middleware('page.permission:dyeing_treatment,edit')->name('submit');
                Route::get('/history', 'history')->middleware('page.permission:history,view')->name('history');
                Route::get('/reports', 'reports')->middleware('page.permission:reports,view')->name('reports');
            });

        // Dyeing Pre Treatment staff workspace
        Route::prefix('dyeing-pre-treatment')->name('staff.dyeing-pre-treatment.')->controller(DyeingTreatmentController::class)
            ->middleware('man.role:dyeing_pre_treatment')->group(function () {
                Route::get('/', 'dashboard')->middleware('page.permission:dashboard,view')->name('dashboard');
                Route::get('/work', 'work')->middleware('page.permission:dyeing_pre_treatment,view')->name('page');
                Route::post('/work/submit', 'submit')->middleware('page.permission:dyeing_pre_treatment,edit')->name('submit');
                Route::get('/history', 'history')->middleware('page.permission:history,view')->name('history');
                Route::get('/reports', 'reports')->middleware('page.permission:reports,view')->name('reports');
            });

        // Finishing Squeezer
        Route::prefix('finishing-squeezer')->name('staff.finishing-squeezer.')->controller(DyeingSqueezerController::class)
            ->middleware('man.role:finishing_squeezer')
            ->group(function () {
                Route::get('/', 'index')
                    ->middleware('page.permission:dashboard,view')->name('dashboard');
                Route::get('/squeezer', [FinishingWorkflowStaffController::class, 'work'])
                    ->middleware('page.permission:finishing_squeezer,view')->name('page');
                Route::post('/work/{item}/complete', [FinishingWorkflowStaffController::class, 'complete'])
                    ->middleware('page.permission:finishing_squeezer,edit')->name('work.complete');
                Route::get('/reports', 'reports')
                    ->middleware('page.permission:reports,view')->name('reports');
                Route::get('/history', 'history')
                    ->middleware('page.permission:history,view')->name('history');

                Route::post('/squeeze', 'storeSqueezer')
                    ->middleware('page.permission:finishing_squeezer,edit')->name('store-squeeze');
                Route::post('/machine-report', 'reportMachine')
                    ->middleware('page.permission:finishing_squeezer,edit')->name('report-machine');
            });

        // Finishing Heatsetting
        Route::prefix('finishing-ironing')->name('staff.finishing-ironing.')->controller(DyeingIroningController::class)
            ->middleware('man.role:finishing_ironing')
            ->group(function () {
                Route::get('/', 'index')
                    ->middleware('page.permission:dashboard,view')->name('dashboard');
                Route::get('/ironing', [FinishingWorkflowStaffController::class, 'work'])
                    ->middleware('page.permission:finishing_ironing,view')->name('page');
                Route::post('/work/{item}/complete', [FinishingWorkflowStaffController::class, 'complete'])
                    ->middleware('page.permission:finishing_ironing,edit')->name('work.complete');
                Route::get('/reports', 'reports')
                    ->middleware('page.permission:reports,view')->name('reports');
                Route::get('/history', 'history')
                    ->middleware('page.permission:history,view')->name('history');

                Route::post('/iron', 'storeIron')
                    ->middleware('page.permission:finishing_ironing,edit')->name('store-iron');
                Route::post('/machine-report', 'reportMachine')
                    ->middleware('page.permission:finishing_ironing,edit')->name('report-machine');
            });

        // Finishing Hydro station workspace
        Route::prefix('finishing-hydro')->name('staff.finishing-hydro.')->controller(FinishingStationController::class)
            ->middleware('man.role:finishing_hydro')->group(function () {
                Route::get('/', 'dashboard')->middleware('page.permission:dashboard,view')->name('dashboard');
                Route::get('/work', [FinishingWorkflowStaffController::class, 'work'])->middleware('page.permission:finishing_hydro,view')->name('page');
                Route::post('/work/{item}/complete', [FinishingWorkflowStaffController::class, 'complete'])->middleware('page.permission:finishing_hydro,edit')->name('work.complete');
                Route::get('/history', 'history')->middleware('page.permission:history,view')->name('history');
                Route::get('/reports', 'reports')->middleware('page.permission:reports,view')->name('reports');
            });

        // Finishing Tubetex station workspace
        Route::prefix('finishing-tubetex')->name('staff.finishing-tubetex.')->controller(FinishingStationController::class)
            ->middleware('man.role:finishing_tubetex')->group(function () {
                Route::get('/', 'dashboard')->middleware('page.permission:dashboard,view')->name('dashboard');
                Route::get('/work', [FinishingWorkflowStaffController::class, 'work'])->middleware('page.permission:finishing_tubetex,view')->name('page');
                Route::post('/work/{item}/complete', [FinishingWorkflowStaffController::class, 'complete'])->middleware('page.permission:finishing_tubetex,edit')->name('work.complete');
                Route::get('/history', 'history')->middleware('page.permission:history,view')->name('history');
                Route::get('/reports', 'reports')->middleware('page.permission:reports,view')->name('reports');
            });

        // Finishing Shrinkage Inspector handles squeezer and ironing quality gates.
        // Final package inspection remains with Finishing Quality Checker.
        Route::prefix('finishing-inspector')->name('staff.finishing-inspector.')->controller(FinishingInspectorController::class)
            ->middleware('man.role:finishing_inspector')
            ->group(function () {
                Route::get('/', 'index')->middleware('page.permission:dashboard,view')->name('dashboard');
                Route::get('/inspection', [FinishingWorkflowStaffController::class, 'inspection'])->middleware('page.permission:finishing_inspector,view')->name('page');
                Route::post('/work/{item}/shrinkage', [FinishingWorkflowStaffController::class, 'inspectShrinkage'])->middleware('page.permission:finishing_inspector,edit')->name('shrinkage.inspect');
                Route::get('/history', 'history')->middleware('page.permission:history,view')->name('history');
                Route::get('/reports', 'reports')->middleware('page.permission:reports,view')->name('reports');
                Route::post('/squeezer/{id}/pass', 'passSqueezer')->middleware('page.permission:finishing_inspector,edit')->name('pass-squeezer');
                Route::post('/iron/{id}/pass', 'passIron')->middleware('page.permission:finishing_inspector,edit')->name('pass-iron');
            });

        // Finishing Packaging (ironing flows directly here; forming stage removed)
        Route::prefix('finishing-packaging')->name('staff.finishing-packaging.')->controller(FinishingPackagingController::class)
            ->middleware('man.role:finishing_packaging')
            ->group(function () {
                Route::get('/', 'index')
                    ->middleware('page.permission:dashboard,view')->name('dashboard');
                Route::get('/packaging', [FinishingWorkflowStaffController::class, 'pack'])
                    ->middleware('page.permission:finishing_packaging,view')->name('page');
                Route::post('/package-group/{workOrder}', [FinishingWorkflowStaffController::class, 'storePackageGroup'])
                    ->middleware('page.permission:finishing_packaging,edit')->name('package-group.store');
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

        // Finishing Quality Checker inspects packaging outputs only. The
        // knitting/dyeing gates moved to the department supervisors (man.gates.*).
        Route::prefix('checker-quality')->name('staff.checker-quality.')->controller(CheckerQualityController::class)
            ->middleware(['man.role:checker_quality'])
            ->group(function () {
                Route::get('/', 'index')
                    ->middleware('page.permission:dashboard,view')->name('dashboard');
                Route::get('/production', [FinishingWorkflowStaffController::class, 'quality'])
                    ->middleware('page.permission:checker_quality,view')->name('production');
                // Work page alias: every staff role exposes a `.page` route
                // for the sidebar; checker-quality's work page is production.
                Route::get('/work', [FinishingWorkflowStaffController::class, 'quality'])
                    ->middleware('page.permission:checker_quality,view')->name('page');
                Route::post('/finishing/{workOrder}/quality-check', [FinishingWorkflowStaffController::class, 'checkQuality'])
                    ->middleware('page.permission:checker_quality,edit')->name('finishing.quality-check');
                Route::get('/history', 'history')
                    ->middleware('page.permission:history,view')->name('history');
                Route::get('/reports', 'reports')
                    ->middleware('page.permission:reports,view')->name('reports');

                Route::post('/package/{id}/inspect', 'inspectPackage')
                    ->middleware('page.permission:checker_quality,edit')->name('inspect-package');
            });
    });
});
