# Deploying Brooks Agro ERP on DigitalOcean

Setup: **App Platform** (runs the system) + **Managed PostgreSQL** (the data, backed up daily) + **Spaces** (uploaded receipts and contracts). Expected cost about **$32/month**.

Everything below is done in the DigitalOcean dashboard (cloud.digitalocean.com). Choose **Frankfurt** for every part so they sit together.

---

## 1. Create the database
1. **Create → Databases → PostgreSQL**.
2. Region **Frankfurt (FRA1)**. Plan: **Basic → Regular → 1 GiB RAM / 1 vCPU / 10 GiB disk** ($15.15/month). Standby nodes: **0**.
3. Name it **`brooks-db`** (the app looks for this exact name).

## 2. Create the file storage (Spaces)
1. **Create → Spaces Object Storage**. Region **Frankfurt (FRA1)**.
2. Name the bucket, e.g. **`brooks-agro-files`**. Choose **Restrict File Listing** (files stay private).
3. **API → Spaces Keys → Generate New Key**. Copy the **Key** and the **Secret** (the secret is shown only once).

## 3. Make the application key
On the computer that has the project, run:

```
php artisan key:generate --show
```

Copy the line that starts with `base64:`. This key protects passwords and sessions; keep it safe and never change it after launch.

## 4. Create the app
1. **Create → Apps → GitHub**, connect the GitHub account and allow access to `zahrazinda126/brooks-agro-erp`.
2. Choose **"Edit app spec" / "Import from app spec"** and paste the contents of `.do/app.yaml` from the project.
3. In **Settings → Environment variables**, replace every `CHANGE_ME`:

| Setting | Value |
|---|---|
| `APP_KEY` | the `base64:…` key from step 3 |
| `SPACES_KEY` / `SPACES_SECRET` | the Spaces key and secret from step 2 |
| `SPACES_BUCKET` | the bucket name, e.g. `brooks-agro-files` |

4. **Create resources**. The first build takes about 5–10 minutes. When it finishes, the app opens at an address like `https://brooks-agro-erp-xxxxx.ondigitalocean.app`. The database tables are created automatically on the first start.

## 5. Your own web address (optional)
**App → Settings → Domains → Add domain**, e.g. `erp.brooksagro.com`, and add the record DigitalOcean shows at your domain registrar. HTTPS is set up automatically. Then set `APP_URL` to `https://erp.brooksagro.com`.

## 6. Moving the existing data from this computer
Do this once, outside working hours, so no one is entering records during the copy.

1. **Database.** On this computer:
   ```
   pg_dump --no-owner --no-privileges -Fc -U postgres brooks_agro_erp > brooks.dump
   ```
   Then restore it into the managed database (connection details are under **Databases → brooks-db → Connection details**):
   ```
   pg_restore --no-owner --no-privileges --clean --if-exists -d "<connection string>" brooks.dump
   ```
2. **Uploaded files.** Copy the folders inside `storage/app/public` and `storage/app/private` into the Spaces bucket, keeping the same folder names (`sales-payment-receipts`, `daily-sales-deposit-receipts`, `fleet`, `contracts`). The Spaces page in the dashboard has an **Upload** button that accepts folders.
3. **Contracts.** Signed contracts remember which storage they were saved on. Point them at Spaces once, in the database console:
   ```sql
   UPDATE attachments SET disk = 'spaces' WHERE disk = 'local';
   ```
4. Sign in and open a sale with a payment receipt, a fuel record and a contract to check the files open.

## 7. After launch
- **Updates:** pushing to the `main` branch on GitHub deploys automatically, with no downtime.
- **Database backups:** automatic and daily (Databases → brooks-db → Backups). For extra safety, download a copy now and then from there.
- **Scheduled jobs** (vaccination reminders, weekly budget drafts, monthly depreciation) run inside the app. Keep the app at **1 instance** so they run once.
- **If pages feel slow:** App → Settings → change the size to Shared 1 vCPU / 2 GiB ($25/month).
- **Email:** `MAIL_MAILER` is `log`, so emails are only written to the logs. In-app notifications work regardless. When you have an email account (e.g. Google Workspace or Mailgun), set the `MAIL_*` settings.
