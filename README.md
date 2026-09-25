# Brooks Agro ERP

Farm operations, poultry and livestock production, stock, sales, finance, HR, payroll and management reporting for Brooks Agro Farm.

## System documentation

- [Complete system and user guide](docs/SYSTEM_GUIDE.md)
- [Editable Word document](docs/Brooks_Agro_ERP_System_Guide.docx)
- [Historical finance-workbook analysis](docs/finance-workbook-analysis.md)

The system guide explains roles, module workflows, requested changes, staff numbering, the fresh-start reset, local hosting, project structure and known implementation boundaries. It records the latest confirmed staff-number/login mapping without including passwords.

## Run the existing local installation

From this project directory, with its configured database available:

```powershell
php artisan serve --host=0.0.0.0 --port=8080
```

Open `http://127.0.0.1:8080` on the host computer. Other reachable devices use `http://HOST-IP:8080`. Check the current IPv4 address with `ipconfig`; previously used addresses may change. Network and firewall access are required.

For local scheduled work, run `php artisan schedule:work` in a separate terminal.

## Project requirements

The Composer manifest specifies PHP ^8.3 and Laravel ^13.8. The local setup uses PostgreSQL; automated tests use SQLite in memory. Pages are Blade templates, and the main layout loads `public/css/app.css` directly. Vite tooling is also present.

Use the existing `.env` for this installation. Do not overwrite its application key or reset the database as part of routine startup. Database migrations and seeders include data changes; review them before using a new installation procedure.

## Verification

```powershell
php artisan test
php artisan view:cache
```

Tests cover selected workflows; see the guide for coverage and limits. Docker and Render definitions exist but need the specific configuration/build checks described in the guide before deployment.

## Update the Word guide

Edit `docs/SYSTEM_GUIDE.md`, then run:

```powershell
python tools/build_system_guide.py
```

The document builder uses Python's standard library and does not require extra packages.
