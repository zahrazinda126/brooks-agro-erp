# Brooks Agro ERP — System and User Guide

Version: 1.0 • Prepared: 25 September 2026

Audience: Director, CEO, HR, Finance, Production, store staff, sales staff, supervisors, workers, and the technical maintainer.

This guide describes the application found in this project and the changes requested during its development. It explains what users do, how records connect, and where the implementation lives. The source code was reviewed for this document; this is not a claim that every screen has been retested or that a public deployment is running. Account details below are the last confirmed configuration from the work recorded in this conversation, not a new database audit. Passwords and database secrets are deliberately excluded.

## Contents

1. System purpose and structure
2. Roles, access and dashboards
3. Staff records, accounts and security
4. Poultry batches, houses and daily records
5. Mortality assessment
6. Vaccination and health
7. Feed deliveries and inventory
8. Other livestock and garden operations
9. Sales and customer accounts
10. Finance and budgeting
11. Approvals and executive work
12. HR services and payroll
13. Reports, search and communications
14. Requested changes and final behaviour
15. Fresh-start reset and onboarding
16. Project contents and technical design
17. Local hosting, maintenance and deployment
18. Verification, limitations and troubleshooting

## 1. System purpose and structure

Brooks Agro ERP is a browser-based farm management system. It brings farm production, people, stock, sales, finance and management reviews into one application. A person signs in with an account created inside the system and sees the modules allowed by their roles and departmental postings.

The operational units include poultry, goats, rabbits, dogs and the garden. Supporting departments use HR, payroll, inventory, finance and sales screens. The CEO and Director use management summaries, reports and approval queues.

The basic flow is:

```text
Set up staff, access, houses, products and reference lists
                     |
Record farm work, receipts, production and sales
                     |
Review records and submit applicable approvals
                     |
Confirm stock, recognise financial activity and settle balances
                     |
Review dashboards, daily reports and period reports
```

Not every record follows an approval chain. Worker record review, death assessment, delivery confirmation, financial approval and daily report closure are distinct workflows.

### Important terms

| Term | Meaning in this system |
| --- | --- |
| Employee / staff record | The person's HR record, including staff number, employment details and pay information. |
| User / login account | Credentials and access rights. An employee can exist without a login. |
| Role | A responsibility such as Production Manager or Finance Manager. |
| Department | A posting or operational section; users can have more than one. |
| Batch | A poultry flock with a code, arrival information and a running bird balance. |
| House | A physical poultry house or brooder. One batch can be allocated across houses. |
| Stock movement | A receipt, issue, return, adjustment or transfer affecting a balance. |
| Assessment | A manager's interpretation of an already recorded mortality event. |
| Approval | A staged decision on an item such as a budget, purchase, payroll or sale. |
| Reference data | Setup lists such as breeds, feed types, egg grades and medications. |

## 2. Roles, access and dashboards

Access comes from module permissions, roles and departments. Some operations also have explicit role and ownership checks. A visible menu item does not mean the user can perform every action within it. Accounts can hold multiple roles; the dashboard selects a primary layout using a defined priority.

| Role / responsibility | Main work supported |
| --- | --- |
| Director | Executive reporting, final decisions where required, directives, batch edit authorisation and management oversight. |
| CEO | Farm performance summaries, approval review, management reporting and executive coordination. |
| HR Manager | Staff registration, account creation, HR records, payroll preparation, welfare, accommodation and workforce reporting. |
| Finance Manager | Transactions, cash/bank books, suppliers, customers, budgets, finance review, payroll funding and financial reports. |
| Production Manager | Farm output, batch arrivals, poultry houses, feed dispatch weights, mortality assessment, vaccination schedules and production reporting. |
| Store Keeper / Inventory Officer | Receiving stock, independent farm feed weights, store movements and confirmations, subject to store permissions. |
| Sales Manager | Sales entry, payments, customer balances, receipts, product stock and daily cash balancing. |
| Personal Assistant | Director directives, meetings and follow-up of approved funds. |
| Supervisor | Assigned staff and review of worker records. |
| Worker / data-entry account | Operational entries available for the person's assigned work and access. Data-entry-only accounts go directly to daily work. |
| System administrator | Broad setup and access where the role is assigned. The old administrator login was removed in the fresh-start reset. |

These are functional descriptions, not a complete permission matrix. Role and store grants in the database determine actual access.

### Dashboards

- Director and CEO use the executive farm-performance layout, including financial and production indicators.
- Finance uses charts, comparison panels and indicators for income, spending, customers, products and financial position.
- Production uses the same compact visual style, with a 12-month egg collection/damage chart, active livestock totals, feed coverage by poultry batch, garden plot count and records needing attention.
- HR summarises workforce and payroll matters. Sales focuses on its own sales and cash activity. The Personal Assistant dashboard tracks executive follow-up.
- Users without a dedicated layout receive applicable access-based panels.

Production feed requirements currently use an estimate of **0.125 kg per remaining bird per day**. This is a fixed dashboard assumption, not an automatically adjusted nutritional plan. Recorded feed includes approved, submitted and pending entries. A dashboard count should therefore be read alongside its period and status description.

For accounts holding several senior roles, the current dashboard priority is executive, sales, finance, personal assistant, HR, then production. Receiving extra roles does not necessarily combine all those dashboards.

## 3. Staff records, accounts and security

### Registering staff

Open HR, choose Employees, and add the person's employment record. The form covers identity, contact details, department, role, employment type and pay details. Registration and issuing a login are separate operations.

The staff number format is **BAF 001**, **BAF 002**, and so on. There is one BAF series for all employment types. The generator finds the highest matching staff number, including soft-deleted employee records, and adds one. Formatting uses at least three digits: after BAF 999 comes BAF 1000.

The number is editable. Use **HR → Employees → select employee → Edit staff details**. Duplicate staff numbers are rejected. The current highest number determines the next suggestion, so after Director's number was changed to BAF 020, the next suggested number is at least BAF 021 unless later entries have raised it.

### Staff number versus login username

These are separate fields. Editing an employee's staff number does not automatically rename their login. Account details have their own editing screen and access checks.

The last confirmed retained-account mapping was:

| Account | Staff number | Login username |
| --- | --- | --- |
| HR | BAF 001 | BAF 003 |
| CEO | BAF 002 | BAF 002 |
| Director | BAF 020 | BAF 020 |

HR's staff number was changed without a request to rename the HR login. Director's login was subsequently renamed explicitly. Spaces are part of these usernames. Earlier forms such as BAF001 and BAF-01 are superseded.

### Creating a login

1. Open the employee's record and choose the account/login action.
2. Enter a unique username. BAF-format usernames are supported, as are the allowed lowercase name-based usernames.
3. Enter a valid email address. **Email is required for new login accounts**, prefilled from the employee record when available, and checked for uniqueness among users.
4. Choose an assignable role and department.
5. Save and provide the generated temporary credentials to the employee.
6. The employee changes the temporary password at first sign-in.

The email requirement applies to creating login accounts. It does not make email compulsory for every employee-only record, retroactively fill older accounts, or implement email verification. Account edit and personal-profile rules are separate from the new-account creation rule.

Roles have a rank. HR generally issues junior accounts, with a specific exception permitting CEO account creation. Account-edit/reset checks still apply their own seniority rules; the ability to create a role does not imply unrestricted ability to edit every existing account.

### Signing in and passwords

Login uses the exact username and password. Passwords are stored as one-way hashes and cannot be retrieved as readable text. A reset replaces the stored password; it does not reveal the previous one.

Repeated failures can cause a temporary lockout. Password-change requirements, expiry settings and disabled-account checks are implemented. A dedicated password-change page remains accessible when the user must change their password. There is no public self-registration route.

The Director password was reset at the user's request during setup. Its value is not included in this document. Other retained accounts did not automatically receive that same password.

## 4. Poultry batches, houses and daily records

### Opening a batch

Finance can create a pre-arrival poultry order and record its payments. Production selects an existing order where applicable and records the physical arrival: breed, arrival date, age, quantities, transit deaths and housing allocation.

```text
Paid birds + free extra birds - transit deaths = live birds to allocate
```

Allocation must cover the live birds. The current arrival rule directs chicks aged 42 days or less to houses designated as brooders. Batches receive generated codes such as B-001/26. Batch information is used to calculate age and expected production milestones.

Production's batch create/edit forms hide cost per bird, total paid, cost summaries and the supplier/invoice/notes section managed by Finance. Server-side validation also excludes those production-submitted financial fields. On arrival linked to an order, the application can take the cost and supplier from Finance's order internally. Production edits preserve existing recorded costs.

This separation concerns the batch arrival workflow. It is not a statement that every production-related screen has been stripped of all financial fields; see the limitations section.

### Houses and staff assignments

The Houses & Worker Assignment screens support house registration, house type, batch allocation, bird transfers, staff assignments and ending assignments. The fresh-start reset retained 43 configured houses and cleared their birds and old assignments.

Batch totals and house quantities answer different questions. When a batch occupies several houses, a batch total is not the number in one house.

### Correcting a batch

Batch editing is controlled. A manager requests permission with a reason, and the Director can allow a limited editing period. The permission covers a correction; it is cleared after the update so later changes require a new request. Batch closure retains its record and history.

### Daily entries

Workers can record egg collections, deaths/culls, feed consumption and feed servings where permitted. Records link to batches and, where supplied, houses. Egg entries include collection dates/times, sessions, quantities/grades and additional measurements supported by the form.

Supervision and review use their own statuses and permissions. Production reports combine these entries so managers do not have to recreate every observation as a separate report.

## 5. Mortality assessment

Mortality has two stages: a worker records what was observed, then an authorised manager records the assessed cause and response.

The assessment screen displays the worker's record, date, batch, recorded house, losses, age and current batch balance. A missing house displays **House: Not recorded** rather than guessing a location.

**Birds currently in batch [code]** is the present batch balance across its houses. It is not a historical balance at the time of the death and not a whole-farm count. The house displayed identifies the mortality record's location; it does not change the scope of the batch total.

### Selecting or typing the cause

The cause field is an editable input with a full-width dropdown. Existing causes are grouped under category headings. Users can open the list, type to filter it, choose an entry with mouse/keyboard, or type an unlisted cause in the same field.

Only the selected known cause's description is shown. Existing causes flagged as contagious show the spread warning and require an action-taken explanation. The server also enforces this rule; it does not rely only on the browser.

Typing a new cause saves that wording as the linked cause, so it appears in existing reports and when the assessment is reopened. It is not automatically published into the shared active standard list. Typing the name of an existing cause reuses it. The application does not determine whether a newly typed cause is contagious.

If the cause is unknown, choose **Cause not established**. The list is for recording an assessment, not for diagnosing an illness automatically.

### Vet details and saving

Checking **A vet was called** opens a required vet-name field. The name is validated and saved with the assessment. Unchecking the option clears the stored vet name when saved. This checkbox records the event; it does not contact a vet.

Saving records the cause, action, optional assessment notes, vet details, assessor and assessment time, and marks the mortality record as assessed. The Save assessment button uses the compact button style.

## 6. Vaccination and health

The vaccination page separates **planned vaccination schedules** from **vaccinations actually completed**.

### Schedule entries

New schedule entries select an existing active poultry batch and an existing active vaccine. Batch options show batch codes and breed information. Breed and purpose are derived on the server from the selected batch rather than entered independently.

A schedule stores the age due in days, permitted window, optional repeat interval and end age, essential/active flags and notes. New entries are linked to the chosen batch. Their reminders do not apply to another batch merely because it has the same breed. Existing general programmes remain supported and can apply to matching batches.

### Completed vaccinations

A completed record includes batch, vaccine, administration date, birds treated, dose, route, vaccine lot number, manufacturer information, expiry date, optional next due date, administrator and observations. The vaccination page also currently contains a cost field and a separate veterinary-involvement checkbox; the mandatory vet-name change described earlier was implemented for mortality assessment, not for every health form.

### Filters

| Filter | Effect |
| --- | --- |
| Administered from/to | Filters completed vaccination records, including both boundary dates. |
| Batch | Filters completed records and schedule entries; schedules include applicable general programmes. |
| Vaccine | Filters both tables. |
| Schedule status | Filters active/inactive schedule items only. |
| Reset | Removes the filters. |

Filters persist across record pagination. Result counts and no-match messages are displayed. Closed batches and inactive vaccines remain available in the filter lists for historical searches, while new-entry choices use active records.

### Reminders and other health work

The vaccination reminder service compares batch age against due ages and checks for corresponding recorded doses. It creates reminders for qualifying production managers. A daily 06:00 task is defined, and dashboard access also invokes reminder checks. Scheduled execution needs a running scheduler; merely having the schedule code does not start a background service.

The broader flock-health module records treatments and other health events, medication details and withdrawal periods. Withdrawal dates are stored with the event so later medication setup changes do not silently rewrite past records.

## 7. Feed deliveries and inventory

### Feed delivery verification

```text
Production enters supplier weights
              |
Delivery awaits farm receipt
              |
Store staff enter independent farm weights
              |
System records variance and received stock
```

Production records the supplier, delivery information, ordered quantity and each feed weighing line. The supported delivery feed list currently includes Bran, Broken Maize, Sunflower, Soya, Lime and Hendrix. The current delivery-date validation requires Tuesday and disallows future dates.

Farm receipt is a separate action for a Store Keeper or Inventory Officer. Each delivery line needs its farm weight. A completed receipt cannot simply be received again. Variance is based on farm weight minus supplier weight: a negative value means the arrival weight is lower.

The **Add weight entry** button is below the supplier weighing table, aligned right with a small gap. The introductory explanation, three-step workflow display and extra instruction block above the production form were removed at the user's request. The underlying verification process remains.

### Feed use and balances

Feed balances are calculated from stock movements. The feed-issue service checks available stock and rejects an issue larger than the balance. Its database locking is intended to keep concurrent issues from using the same available stock twice.

The dashboard's daily feed estimate is a planning indicator. It should not be confused with a supplier delivery, a confirmed receipt or an actual feed-use entry.

### General inventory

Inventory has stores, registered items and movements. Store-specific role grants distinguish viewing, receiving, issuing, adjusting and viewing value. Receipt confirmation matters: pending receipts are not included in confirmed stock balances.

Movement options cover receipts, issues/farm use, returns, adjustments and transfers according to the screen and permissions. Quantity, location, item and unit must be read together. Some house-related records track supplies issued to poultry houses.

## 8. Other livestock and garden operations

Farm-unit screens cover goats, rabbits, dogs and garden records. The application has models/tables and actions for livestock groups, individual animals, breeding, health, mortality, feeding and production. Goats additionally have breeding and health-routine support. Garden records use crop and location setup and operational record types.

Use the relevant unit rather than entering a goat, rabbit or garden activity as a poultry batch. Operational reports combine unit information and provide views for livestock, health/losses, feeding/inputs and production/harvest. Date-range reports and exports are available in these areas.

The detailed record types vary by unit. The controller's unit configuration determines the available forms; a feature supported for one species should not be assumed to exist identically for all others.

## 9. Sales and customer accounts

The sales module supports sales entry, product stock views, setup, reports, analysis, daily balancing and a receipt archive. Products cover eggs, poultry, other livestock, milk, manure, garden products and other configured sale categories.

1. Register or select a customer and applicable products.
2. Record quantities, prices and the sale date.
3. Follow the applicable approval path where required.
4. Record payments against the sale and issue a receipt.
5. Track outstanding customer balances and handle returns through their own records.
6. Review daily cash and record the Finance handover/closure.

Supported payment methods in the sales controller include cash, bank, agent, mobile money, in-kind, prepayment and farm consumption. Available choices depend on the operation. Receipts, payments, approval and stock effects are related but distinct; recording a sale does not mean it is fully paid.

Price changes have a request/decision workflow. Off-layer poultry sales can identify their source houses. Customer account screens support contact details, account history and balances. Daily balancing includes prior cash, receipts, transfers and Finance closure; uploaded deposit/payment receipts provide supporting records.

## 10. Finance and budgeting

### Finance records

Finance records money received/paid and other supported transaction classifications, with accounts, cost centres, counterparties, payment methods, references and descriptions. The repository includes general ledger, cash-book and bank-book screens, receivables, supplier payables, fixed assets and finance administration.

| Area | Main purpose |
| --- | --- |
| Transactions | Record and classify financial activity. |
| Cash / bank books | Review activity by cash or bank source. |
| Receivables | Track customer amounts due and payments received. |
| Customer prepayments | Record money received before settlement and apply it to balances. |
| Payables | Record supplier obligations, supply lines/details and payments. |
| Batch orders | Record poultry orders and payments before physical arrival. |
| Assets | Register fixed assets and relevant financial details. |
| Finance administration | Maintain accounts, payment methods/accounts, settings and periods. |

Customer prepayments and revenue are treated differently in several report calculations to avoid treating every receipt as earned sales revenue. Financial classification therefore matters; a correct amount in the wrong category can produce misleading reports.

### Budgets and purchases

Department weekly budgets, production draft inputs, HR welfare budgets and Finance budget preparation support submission and approval. Funding decisions can be recorded on budget lines. Purchases have their own submission and review statuses.

A weekly-draft service prepares department drafts from the latest plan. Its scheduled command runs daily at 00:10 and is designed to avoid duplicate initialisation for the same week. This can create new draft records after a fresh-start reset as ordinary application activity resumes.

### Finance reports

The source contains financial overview/KPIs, statements, three-statement reporting, profitability, unit costs, expenditure analysis, profit analysis and performance analysis with comments. Unit economics covers Poultry, Goats, Rabbits, Dogs and Garden and distinguishes allocated from unallocated costs.

Report totals depend on date ranges, transaction classification and the status rules of each report. Dashboard cash movement, recognised revenue, outstanding balances and accounting profit are not interchangeable figures.

The repository also contains a historical finance-workbook analysis document and extraction scripts. Those files describe a workbook review, including reconciliation issues. Their existence does not mean workbook figures have been imported into the current operational database or resolved by this reset.

## 11. Approvals and executive work

Approvals link to the item being reviewed and contain ordered steps, role assignments, statuses, notes, deadlines and comments. Typical financial workflows involve Finance, CEO and Director, but the exact stages depend on the item and submission logic.

An authorised user acts on the pending step. Approval can advance to the next step or complete the item; rejection updates the item and cancels waiting steps as appropriate. The system has delegation records and checks delegated approval authority. Review comments and budget funding decisions are separate supported actions.

The executive area includes messages, management views and reporting. Follow-up functionality records directives, assigned responsibility, due dates, meeting schedules and verification of how approved funds were used. These are tracking records, not an external calendar integration.

## 12. HR services and payroll

HR extends beyond the employee register. Supported record areas include attendance, contracts, advances, deductions, performance, development and welfare-related information. Contract attachments can be stored and downloaded through controlled routes.

Attendance can be recorded automatically when a linked employee signs in or saves system activity. An employee-linked account therefore may create an attendance record during normal use. Performance services and reports combine recorded workforce/production activity; missing source entries limit those indicators.

### Payroll

Payroll is prepared for a period, reviewed through its approval workflow, and funded by Finance. Payroll lines include gross amounts, deductions, net pay and employer contributions. The code contains a Uganda payroll calculator and configurable tax-related settings; this document describes software behaviour, not a certification of current statutory rates.

Funding and employee withdrawal are separate:

```text
Prepare payroll → Review/approve → Fund selected employees
                                        |
                               Salary balances credited
                                        |
                               Record actual withdrawals
```

Partial funding is supported. Staff not selected for funding remain waiting. Withdrawals require a date, amount, method, reference and confirmation and cannot exceed the available funded balance. Statuses distinguish funded, partly funded, partly paid and paid. Employees have salary-balance views where permitted.

### Other HR operations

- Staff welfare budgeting and template entry.
- Hostels, rooms, bed capacity, accommodation assignment and checkout.
- Welfare/accommodation items, issue and return tracking.
- Company vehicle registration, fleet records and fuel receipt support.
- Visitor registration and checkout.
- HR data-entry, reports and KPI views.

## 13. Reports, search and communications

The daily management report assembles departmental information for a selected date. It supports section submissions, reviews, department review, HR observations, comments and closing a department's day. Director-facing snapshots use the readiness/closure workflow; an unreviewed draft is not equivalent to a closed report.

Global search offers permitted records across modules. Other reports provide their own date, unit, staff, batch or category filters. Filter choices are specific to each report rather than universal across the application.

Print actions are available on several screens. The branded export service streams an HTML table using an **.xls** filename for spreadsheet compatibility; it is not a native .xlsx workbook. Available exports depend on the module.

Communications are stored in the application and can be addressed to a user, role or audience. The service also attempts email delivery when the recipient has email and the mail driver is not the log driver. SMTP/mail configuration is required for real email delivery; requiring email during account creation does not by itself configure delivery. SMS and WhatsApp delivery are not established by this implementation.

## 14. Requested changes and final behaviour

This table records the final state of the requests covered in this conversation, rather than every intermediate design that was replaced.

| Request | Implemented result |
| --- | --- |
| Production dashboard similar to Finance/CEO | Shared compact chart/panel/table styling with production data. |
| Production should not enter batch costs | Financial batch fields hidden and excluded from production submissions; stored costs preserved on edits. |
| Supplier/invoice information belongs to Finance | The batch form's supplier, invoice and notes section is hidden for production. |
| Select existing batches and derive breed | Vaccination scheduling uses existing batch codes and takes breed/purpose from the batch. |
| Add vaccination filters | Administered date range, batch, vaccine, schedule status, reset and match counts. |
| Mortality cause list was too long | Replaced the long cards with a compact cause control. |
| Allow typing without a separate Other field | One editable cause field with a custom full-width grouped list; the native small datalist popup was replaced. |
| Clarify birds left | Label identifies the current batch balance and batch code. |
| Show house | Mortality assessment shows the recorded house, or Not recorded. |
| Require vet name | Mortality assessment requires and saves the name when A vet was called is selected. |
| Save button too large | Compact Save assessment button. |
| Move Add weight entry | Button sits below the supplier weighing table. |
| Remove feed-form explanatory blocks | Introductory explanation, workflow steps and extra instruction block removed. |
| Access from other devices | A LAN server was started on port 8080 and its login page verified locally. |
| Start using the system afresh | Backed-up reset removed old operational entries and all but HR/CEO/Director logins. |
| Staff number format | Final format BAF 001, with a space and at least three digits. |
| Existing staff numbers editable | Available through Edit staff details; usernames remain separately editable. |
| HR 001; Director 020 | Staff numbers set to BAF 001 and BAF 020 respectively. |
| Director login BAF 020 | Login changed explicitly; password preserved at that rename. |
| Director password reset | Completed separately at the user's request; value excluded from documentation. |
| Email required for accounts | New login creation requires a valid unique email and shows a required field. |

## 15. Fresh-start reset and onboarding

### What the reset did

During the authorised reset, 83 operational/data tables were emptied, 10 other login accounts were removed and only the HR, CEO and Director logins were retained. Existing batches, workers, feed/stock activity, sales, financial entries, payroll, approvals and activity history were cleared. Passwords of the retained accounts were initially preserved.

Reference/setup records were kept, including roles, modules, permissions, departments, relevant catalogues and 43 poultry houses. House bird balances and product stock quantities were cleared. Batch-specific vaccination schedules and custom mortality causes were removed; standard programme/setup records were retained. This was an operational reset, not a deletion of the application or every database table.

The retained accounts were initially detached from deleted employee records. Minimal employee profiles were then created for those three accounts when staff numbering was configured. Consequently, the later system legitimately has those management staff profiles again. Their salary/contact details need proper completion before ordinary HR and payroll use.

The database backup was created and its archive listing/checksum verified before deletion. It is stored privately under:

```text
storage/app/private/backups/fresh-start-20260920-145737/
```

The directory contains the database dump, manifest and reset result; the old uploaded daily sales receipt directory was moved there too. The one-time reset scripts were removed after use. Backup verification was not a full restore rehearsal. These files contain old operational data and credentials in hashed form and should not be placed in public downloads.

### Starting new operations

1. Sign in with a retained management account and verify its profile and email.
2. Complete the three retained staff profiles as needed.
3. Register other employees and issue the required Finance, Production, Store, Sales and worker accounts.
4. Check role, department, store and house assignments.
5. Confirm reference lists and opening financial/stock positions.
6. Enter Finance orders and new physical batch arrivals, then allocate birds.
7. Record real receipts before issuing stock or feed.
8. Begin daily production, health and sales entries.
9. Review records, approvals and daily reports routinely.

The historic reset counts are not a promise that today's database remains empty. New entries, attendance, audit events and generated drafts can accumulate as the application is used.

## 16. Project contents and technical design

### Technology

| Layer | Implementation found in the repository |
| --- | --- |
| Backend | PHP; composer.json requires PHP ^8.3 and Laravel ^13.8. |
| Pages | Laravel Blade templates with HTML forms and JavaScript. |
| Styling | Custom application CSS; the main layout directly loads public/css/app.css. |
| Frontend tooling | Vite and Tailwind-related packages declared in package.json. |
| Data access | Eloquent models and Laravel query builder. |
| Database | PostgreSQL was used in the local setup/reset. Test configuration uses in-memory SQLite. |
| Authentication | Laravel sessions, password hashing, role/module checks and password-settled middleware. |
| Tests | PHPUnit feature/unit tests through Laravel's test runner. |
| Deployment files | Dockerfile, docker configuration and a Render service manifest. |

### Folder map

| Location | Contents / purpose |
| --- | --- |
| app/Http/Controllers | Request handling for dashboards, modules, authentication, reports and workflows. |
| app/Http/Requests | Form validation and authorisation for supported forms. Other controllers validate requests directly. |
| app/Http/Middleware | Module-access and password-change guards. |
| app/Models | Database entities, relationships, scopes and model calculations. |
| app/Services | Shared business calculations, notifications, exports and stock handling. |
| resources/views | Blade screens, shared forms, layouts, sidebar and report/export templates. |
| resources/css and resources/js | Frontend source assets. |
| public/css, public/js, public/images | Directly served application assets and branding. |
| routes/web.php | Browser routes and middleware assignments. |
| routes/console.php | Scheduled reminders and weekly budget-draft command. |
| config | App, database, session, mail and finance-entry configuration. |
| database/migrations | Versioned database structure and several data/setup changes. |
| database/seeders | Foundation/reference and sample/setup population. Not a safe reset shortcut. |
| storage/app | Uploaded files, extracts and private backup material. |
| storage/logs | Application and local-server logs. |
| tests | Automated regression tests. |
| tools | Workbook extraction/analysis and document-generation utilities. |
| docs | This guide and the earlier finance-workbook analysis. |
| docker, Dockerfile, render.yaml | Container and hosted-deployment definitions. |

### Core record relationships

```text
Roles + Departments + Module permissions → User account ↔ Employee
Employee → HR records, payroll, accommodation and assignments
Batch → Houses, eggs, mortality, feed use and health records
Batch + Vaccine → Vaccination plan → Recorded health event
Supplier delivery → Weighing lines → Farm receipt → Feed stock movements
Inventory store → Item → Confirmed movement → Stock balance
Customer → Sale → Lines + Payments + Returns
Budget / Purchase / Payroll / Sale → Approval → Steps + Comments
Financial transactions + operational records → Reports and dashboards
Important actions → Audit records and, where defined, communications
```

### Business services

| Service | Responsibility |
| --- | --- |
| AttendanceRecorder | Attendance from sign-in/saved activity for linked employees. |
| CommunicationService | In-app messages and configured email attempts. |
| FeedStockService | Feed movement balance and guarded issues. |
| VaccinationReminderService | Batch-age-based vaccination reminders. |
| WeeklyBudgetDraftService | Repeatable weekly draft initialisation. |
| UgandaPayrollCalculator | Implemented payroll tax/contribution calculations. |
| UnitEconomicsService | Unit revenue, expenditure and profitability calculations. |
| PoultryPerformanceService / WorkerPerformanceService | Production and workforce performance calculations. |
| BrandedReportExport | Branded spreadsheet-compatible table downloads. |

## 17. Local hosting, maintenance and deployment

### Run on the existing local computer

The project location used during development is:

```text
C:\laragon\www\brooks-agro-erp
```

From that folder, with PHP and the configured database available:

```powershell
php artisan serve --host=0.0.0.0 --port=8080
```

On the hosting computer, open **http://127.0.0.1:8080**. On another device on the same reachable network, use **http://HOST-IP:8080**.

The address verified earlier was **http://10.243.109.56:8080**. This is a historical LAN address, not a guaranteed current address. Check the host's active Wi-Fi/Ethernet IPv4 address with `ipconfig` if it changes. The hosting computer must remain on, the server must remain running, and network/firewall rules must permit the connection. Firewall access from a second device was not verified during the earlier setup. No public internet deployment was completed as part of that LAN task.

### Environment and installation

Use the installed project's existing .env and database configuration when continuing this installation. Do not replace an existing APP_KEY or run a fresh database migration command to solve a login problem.

For a separate new installation, prepare a dedicated environment/database, install Composer dependencies, generate its own application key, configure PostgreSQL/mail/storage as needed, and apply the migrations after reviewing their data effects. `php artisan migrate` applies pending migrations; it is not the same as `migrate:fresh`, which drops tables.

The repository includes Vite tooling, but the main application layout directly references public CSS. A maintainer changing styles should inspect which assets the affected page actually loads and keep source/served files aligned where appropriate.

### Background tasks

For ongoing local development, the scheduler can run in its own process:

```powershell
php artisan schedule:work
```

For a managed installation, configure the system scheduler to invoke Laravel scheduling appropriately. The intended application timezone should be verified as Africa/Kampala. Configure a queue worker if the chosen queue connection and dispatched work require one. The presence of definitions does not mean those processes are currently running.

### Useful maintenance commands

```powershell
php artisan route:list
php artisan migrate:status
php artisan view:cache
php artisan view:clear
php artisan test
```

Use maintenance mode and a verified backup for planned disruptive database changes. Back up the database and relevant uploaded files together; copying code alone does not preserve operational records. Restore into a separate database first when checking a backup. Never expose private backups through the public web directory.

### Deployment definitions that still need verification

Docker and Render files are present, but their presence is not proof of a working deployment. Two concrete issues found during this review are:

- Dockerfile expects package-lock.json and runs npm ci, but package-lock.json was absent from the reviewed workspace. The dependency lock/build path needs resolving before relying on that build.
- render.yaml names a DATABASE_URL secret, while config/database.php reads DB_URL for connection URLs. Configure matching variables or reconcile the files before deployment.

The container startup script runs migrations automatically. Review pending migrations, persistent database/upload storage, mail settings, APP_URL, scheduler processes and backups before putting a hosted instance into use.

## 18. Verification, limitations and troubleshooting

### Existing automated checks

The repository includes focused tests for account email creation, custom mortality causes and vet names, production batch finance-field exclusion, batch vaccination isolation/filters, feed stock balances, global search, budget funding, welfare budgets, branded exports and payroll calculations.

During the work recorded here, focused tests for the recently changed workflows passed, and Blade templates were compiled after form changes. This documentation task did not rerun the entire test suite or perform a new browser audit of every module. Tests are evidence for the behaviours they cover, not blanket certification of the system.

### Boundaries of what has been implemented

- The route `/m/{key}` is explicitly a placeholder for seeded modules without a dedicated implementation. A configured menu/module should not be counted as a finished workflow solely because it exists in setup.
- Production finance-field removal was implemented for batch entry and details. For example, completed vaccination entry still has a cost field in the reviewed source.
- Mandatory vet names were added to mortality assessment; other health/vaccination forms have their own fields and validation.
- The cause control records a user's assessment. Custom wording does not receive automatic medical classification or diagnosis.
- Vaccination date filters apply to administration history, not to every calculated future due date.
- Staff number changes do not automatically rename login accounts. The account table in this guide explains the requested exceptions already applied.
- The staff number generator checks numbers currently present, including soft-deleted rows. It does not maintain a separate permanent registry of numbers that were manually renamed away.
- The production dashboard's feed estimate is fixed at 125 g per bird per day and includes unapproved recorded statuses as described above.
- Email delivery needs a working mail configuration. Required email entry alone does not establish outbound email or password-recovery delivery.
- The historical reset retained reference/setup data. It was not a reusable factory-reset feature.
- Older files contain some visibly misencoded text. This document uses clean text, but it does not imply all older UI wording has been corrected.

### Common questions

| Problem | What to check |
| --- | --- |
| Username/password rejected | Use the exact current login username, including spaces; check lockout and whether an authorised password reset is needed. |
| Staff number changed but login did not | This is expected; edit the account username separately. |
| Cannot create a login without email | Enter a valid email in the required account field. |
| Cannot edit a batch | Check access and whether the Director has granted a current edit window. |
| Cannot save contagious mortality cause | Enter the action taken. |
| Cannot save after ticking vet called | Enter the vet's name. |
| House says Not recorded | The mortality entry has no linked house; do not interpret the batch total as a house total. |
| Feed issue rejected | Confirm received stock, item/feed name, unit and available quantity. |
| Delivery date rejected | The implemented supplier feed-delivery form currently accepts Tuesdays only. |
| No vaccination filter results | Check the administration dates, selected batch/vaccine and schedule status; use Reset. |
| Stock receipt absent from balance | Check whether it is still pending confirmation. |
| Another device cannot connect | Check host IP, port, server process, same-network reachability and firewall rules. |
| Approval queue seems empty | Check status, pending role/stage and any delegation. |
| Dashboard blank after reset | Recreate the required accounts and enter real operational data; zero results may be correct. |
| Email notification missing | Check recipient email, configured mail driver and application logs. |

### Source references for maintenance

The main implementation references are routes/web.php; routes/console.php; app/Http/Controllers/DashboardController.php; the HR, Poultry, Operations and Finance controller directories; app/Http/Controllers/VaccinationScheduleController.php; app/Http/Controllers/SalesController.php; app/Http/Controllers/PayrollController.php; app/Http/Controllers/ApprovalController.php; app/Http/Controllers/DailyManagementReportController.php; app/Models; app/Services; resources/views; and tests.

The Markdown guide is the editable source for the accompanying Word document. Regenerate the Word copy with `python tools/build_system_guide.py` after editing this file.
