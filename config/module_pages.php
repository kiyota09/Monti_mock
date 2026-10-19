<?php

return [
    'hrm' => [
        'dashboard'    => 'Dashboard',
        'employee'     => 'Employees',
        'agencies'     => 'Agencies',
        'workers'      => 'Agency Workers',
        'requests'     => 'Manpower Requests',
        'agency_attendance' => 'Agency Attendance',
        'agency_billing'    => 'Agency Billing',
        'application'  => 'Applications',
        'interview'    => 'Interview',
        'trainee'      => 'Trainee',
        'onboarding'   => 'Onboarding',
        'payroll'      => 'Payroll',
        'analytics'    => 'Analytics',
        // HRM_NEW dynamic pages (old 8 keys kept for BC)
        'workforce'    => 'Workforce',
        'recruitment'  => 'Recruitment',
        'onboarding_templates' => 'Onboarding Templates',
        'organization' => 'Organization',
        'performance'  => 'Performance',
        'training'     => 'Training',
        'attendance'   => 'Attendance',
        'leave'        => 'Leave',
        'archive'      => 'Archive',
    ],

    'crm' => [
        'dashboard'          => 'Dashboard',
        'leads'              => 'Leads',
        'customer_profiles'  => 'Accounts',
        'opportunities'      => 'Opportunities',
        'inquiry'            => 'Inquiries',
        'messaging'          => 'Messaging',
        'approvals'          => 'Approvals',
        'quotations'         => 'Quotations',
        'activities'         => 'Activities',
        'cases'              => 'Cases',
        'campaigns'          => 'Campaigns',
        'investigation'      => 'Due Diligence',
    ],

    'scm' => [
        'dashboard'    => 'Dashboard',
        'sales'        => 'Sales Orders',
        'procurement'  => 'Procurement Orders',
        'planning'     => 'Demand & Materials Planning',
        'purchase'     => 'Purchase Orders',
        'deliveries'   => 'Inbound Deliveries',
        'vendor'       => 'Vendors',
        'analytics'    => 'Analytics',
    ],

    'man' => [
        // Shared overview (supervisors / legacy MAN staff)
        'dashboard'  => 'Dashboard',
        'production' => 'Production Orders',
        'reject'     => 'Rejected Items',
        'inventory'  => 'Production Inventory',
        'recipes'    => 'Recipes',
        // Role workspaces (one key per manufacturing_role)
        'knitting_yarn'          => 'Knitting Yarn',
        'knitting_mechanic'      => 'Machine Setups',
        'dyeing_color'           => 'Dyeing Color',
        'dyeing_fabric_softener' => 'Fabric Softener',
        'dyeing_squeezer'        => 'Dyeing Squeezer',
        'dyeing_ironing'         => 'Dyeing Ironing',
        'dyeing_lab_chemist'     => 'Shade Development',
        'finishing_packaging'    => 'Finishing Packaging',
        'checker_quality'        => 'Quality Inspection',
        'maintenance_checker'    => 'Maintenance',
        'pollution_control'      => 'Pollution Control Logs',
        'safety_officer'         => 'Safety Incidents',
        'boiler_operator'        => 'Boiler Logs',
        // Role sub-pages
        'history'       => 'My History',
        'reports'       => 'Reports',
        'flag_reports'  => 'Flag Reports',
        'lab_tests'     => 'Tests & CoA',
        'lab_inventory' => 'Lab Inventory',
        'lab_transfer'  => 'Bulk Transfer',
    ],

    'inv' => [
        'dashboard' => 'Dashboard',
        'materials' => 'Materials',
        'products'  => 'Products',
        'bom'       => 'BOM',
        'checker'   => 'Checker',
    ],

    'ord' => [
        'dashboard'   => 'Dashboard',
        'orders'      => 'Orders',
        'productions' => 'Productions',
        'delivery'    => 'Delivery',
        'returns'     => 'Returns',
    ],

    'war' => [
        'warehouse' => 'Overview',
        'receiving' => 'Receiving',
        'monitor'   => 'Monitor',
        'packages'  => 'Packages',
        'reject'    => 'Rejects',
    ],

    'eco' => [
        'dashboard' => 'Dashboard',
        'store'     => 'Store',
        'supplier'  => 'Suppliers',
        'client'    => 'Clients',
        'credit'    => 'Credit',
        'push'      => 'Push',
    ],

    'log' => [
        'dashboard' => 'Dashboard',
        'load'      => 'Load',
        'dispatch'  => 'Dispatch',
        'fleet'     => 'Fleet',
        'drivers'   => 'Drivers',
        'routes'    => 'Routes',
        'tracking'  => 'Tracking',
        'proof'     => 'Proof of Delivery',
        'reports'   => 'Reports',
    ],

    'pro' => [
        'dashboard' => 'Dashboard',
        'requests' => 'Material Requests',
        'quotations' => 'Quotations',
        'receipt' => 'Receipt',
        'tracking' => 'Order Tracking',
    ],

    'proj' => [
        'dashboard' => 'Dashboard',
    ],

    'it' => [
        'dashboard' => 'Dashboard',
        'tickets' => 'Service Desk',
        'assets' => 'Assets',
        'monitoring' => 'Monitoring',
        'knowledge' => 'Knowledge Base',
        'location' => 'Geolocation',
        'changes' => 'Changes',
        'access' => 'Access Control',
        'access_control' => 'Org Access Control',
        'access_logs' => 'Access Logs',
    ],

    'fin' => [
        'dashboard' => 'Dashboard',
        'receivables' => 'Receivables',
        'payables' => 'Payables',
        'expenses' => 'Expenses',
        'payroll' => 'Payroll',
        'reports' => 'Reports',
        'approvals' => 'PO Approvals',
    ],
];