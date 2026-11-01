<?php

/*
 | Sidebar highlighting: which menu key is active for the current route.
 | Checked top to bottom; the first matching route pattern wins. Values:
 |   'key'            a fixed module key
 |   '@route:param'   the value of that route parameter
 |   '@input:param'   the request input, falling back to the route parameter
 | Used by resources/views/partials/sidebar.blade.php.
 */

return [
    'active_keys' => [
        'hr.records.*' => '@route:module',
        'executive.*' => '@input:screen',
        'communications.*' => 'messages',
        'directives.*' => 'directives',
        'dashboard' => 'dashboard',
        'approvals.*' => 'approvals',
        'hr.data-entry.*' => 'hr-entry',
        'hr.accommodation.*' => 'accommodation',
        'hr.vehicles.*' => 'vehicles',
        'hr.fleet.*' => 'fleet',
        'hr.welfare-budget.*' => 'budget',
        'hr.reports.*' => 'reports',
        'hr.kpis.*' => 'kpi-dashboards',
        'employees.*' => 'hr',
        'daily.*' => 'egg-entry',
        'batches.*' => 'batches',
        'collections.*' => 'egg-inventory',
        'consumption.*' => 'bird-consumption',
        'health.*' => 'health',
        'mortality.assess.*' => 'mortality-assess',
        'inventory.*' => 'inventory',
        'feed-types.*' => 'feed-types',
        'sales.*' => 'sales',
        'finance.transactions.*' => 'transactions',
        'finance.accounts.*' => 'accounts',
        'finance.cash-book.*' => 'cash-book',
        'finance.bank-book.*' => 'bank-book',
        'finance.general-ledger.*' => 'general-ledger',
        'finance.receivables.*' => 'receivables',
        'finance.payables.*' => 'payables',
        'finance.budgets.*' => 'budget',
        'finance.assets.*' => 'assets',
        'finance.farm.purchases*' => 'purchases',
        'finance.farm.production*' => 'production',
        'finance.farm.veterinary*' => 'veterinary',
        'finance.reports.*' => 'reports',
        'finance.admin.*' => '@route:screen',
        'payroll.*' => 'payroll',
        'owner.customers' => 'customers',
        'visitors.*' => 'visitors',
        'poultry.performance.*' => 'staff-performance',
    ],
];
