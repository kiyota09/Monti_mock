<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| This file is the main entry point for the application's web routes.
| Each business module has been split into its own file under this
| same routes/ directory to keep things maintainable. Add new modules
| by creating a routes/<module>.php file and requiring it below.
|
*/

// Core application routes (welcome page, career applications,
// authenticated dashboard/profile, employee UI, trainee portal)
require __DIR__.'/Core.php';

// Human Resources Management
require __DIR__.'/Hrm.php';

// Applicants Module (applicant master, connected to HRM recruitment)
require __DIR__.'/Applicants.php';

// Workforce Management (scheduling, leave, absences)
require __DIR__.'/Workforce.php';

// Supply Chain Management
require __DIR__.'/Scm.php';

// Financial Operations
require __DIR__.'/Fin.php';

// Manufacturing Plant
require __DIR__.'/Man.php';

// Warehouse
require __DIR__.'/Warehouse.php';

// Inventory
require __DIR__.'/Inventory.php';

// Order Processing
require __DIR__.'/Ord.php';

// Logistics
require __DIR__.'/Log.php';

// Customer Relationship Management
require __DIR__.'/Crm.php';

// E-Commerce
require __DIR__.'/Eco.php';

// Procurement
require __DIR__.'/Pro.php';

// Project Automation
require __DIR__.'/Proj.php';

// IT & Systems Admin
require __DIR__.'/It.php';

// CEO Dashboard
require __DIR__.'/Ceo.php';

// Vice Presidency (execution arm)
require __DIR__.'/Vp.php';

// Secretary Workspace (executive office)
require __DIR__.'/Secretary.php';

// B2B Client Gateway (auth + partner portal)
require __DIR__.'/Client.php';

// Vendor/Supplier Gateway (auth + supplier portal)
require __DIR__.'/Supplier.php';

// Agency Gateway (auth + agency portal)
require __DIR__.'/Agency.php';

/*
|--------------------------------------------------------------------------
| Framework Generated Auth Routes (Breeze)
|--------------------------------------------------------------------------
*/
require __DIR__.'/auth.php';