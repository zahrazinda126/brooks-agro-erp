# Finance workbook extraction and critical review

Source: `July 2025-June 2026 Management Report -Feb1 - Copy.xlsm`

## Extracted scope

- 24 worksheets, including 5 hidden/very-hidden sheets.
- 178 defined names.
- 7,059 usable rows from `Consolidated_Data`, dated 1 July 2025 to 31 May 2026.
- 3,799 transaction IDs and 11 exact duplicate rows requiring review.
- 4,833 revenue rows totalling UGX 1,509,707,950.
- 1,298 purchase rows totalling UGX 1,433,487,105.
- 102 asset-management rows totalling UGX 111,141,000.
- 824 banking rows with a signed net total of UGX -2,241,234,673.
- Master lists for revenue, purchases/costs, assets, bank accounts, liabilities, equity, departments and 53 business partners.
- Exact account codes and row order extracted from Profitability, Balance Sheet, 3-Statement Model, Summary and Budget Forecasts.

Generated extraction files (not imported into production tables yet):

- `storage/app/finance-workbook-inventory.json`
- `storage/app/finance-workbook-extract.json`

## Reconciliations that pass

- Consolidated journal revenue equals the Revenue Analysis total: UGX 1,509,707,950.
- Workbook gross profit reconciles as revenue UGX 1,509,707,950 less cost of sales UGX 1,025,719,098 = UGX 483,988,852.
- Workbook operating result reconciles as gross profit UGX 483,988,852 less reported expenditure UGX 405,735,332 = UGX 78,253,520.

## Material issues to resolve

1. The reporting period says July 2025–June 2026, but usable transaction data ends on 31 May 2026 and June columns are zero.
2. Only 354 of 7,059 usable consolidated rows are marked `Reviewed`; 6,680 have no QA status.
3. There are 11 exact duplicate consolidated rows.
4. Cached formula results contain 9,567 errors in `Consolidated_Data`, 11,900 in `Transactions`, 4 in `QA Glitch fix`, and 3 in `Profit Analysis`.
5. Account naming is inconsistent (`Cash` and `cash`, trailing spaces, underscores, spelling variants such as deferred/defered and recruitment/recrutiment).
6. The Balance Sheet does not balance. It reports total assets of UGX -1,606,173,219 and total equity and liabilities of UGX -602,984,057, a difference of UGX -1,003,189,162.
7. Bank and cash values on the Balance Sheet are large negative movements, suggesting the sheet is summing period activity rather than calculating closing balances from opening balances.
8. Capital and equity are all zero, so retained earnings and the balancing side of the statement are absent.
9. Depreciation and accumulated depreciation are zero despite UGX 107,241,000 of non-current assets.
10. Budget Forecasts are structurally present but the displayed forecast values are zero, so actual-versus-budget variances are not meaningful.
11. The Summary sheet contains at least one visibly incorrect variance: operating profit actual is UGX 78,253,520 with zero budget, while variance displays UGX 889,724,184.
12. The current ERP `financial_transactions` table cannot preserve the workbook's account codes, account hierarchy, QA status, document links and paired debit/credit structure without a ledger extension.

## Target report format

The ERP should retain the workbook's management-report presentation:

- Financial year runs July through June, with monthly columns and annual total.
- Income Statement order: Revenue, Cost of Sales, Gross Profit, Operating Expenses, EBITDA/Operating Profit, Depreciation, Finance Costs, Tax and Net Profit.
- Balance Sheet order: Non-current Assets, Current Assets, Receivables, Bank Accounts, Cash, Total Assets, Non-current Liabilities, Current Liabilities, Payables, Capital/Equity, Total Equity and Liabilities.
- Management Summary presents Actual, Budget, Variance and Comments.
- Supporting reports retain revenue, expenditure, profitability, payables and monthly trend analysis.

The visual hierarchy and row order can be matched exactly. Values must be generated from a balanced ledger rather than copied from broken workbook formulas.

## Safe import approach

1. Import the workbook rows into an idempotent staging table with source sheet/row and a content hash.
2. Normalise account and business-partner spelling while retaining the original text.
3. Present the 11 exact duplicates and unreviewed rows for confirmation.
4. Create chart-of-account records from workbook codes and classifications.
5. Obtain or derive opening balances as at 30 June 2025.
6. Convert reviewed source rows into balanced journal entries and reconcile monthly totals to the workbook.
7. Generate the ERP reports in the workbook's row order.
8. Import only after a dry-run report has zero unexplained differences.
