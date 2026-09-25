# Railway Deployment Guide

This project is a plain PHP + Apache + MySQL application. The repository includes a Dockerfile for Railway deployment.

## 1. Prepare GitHub

Use a **private GitHub repository** because this application contains student and health-related data. Before the first push:

- Remove or exclude debug/test pages from the production repository.
- Never commit `.env`, SMTP passwords, Gemini keys, database passwords, or exported production data.
- The exposed Gemini key in old history must be revoked and regenerated.
- Review `git status` before committing; this workspace contains existing debug and test files.

```powershell
git init
git add Dockerfile .gitignore .env.example includes/db.php includes/email_config.php "AI CHAT BOT/AI CHAT BOT/config.php" index.php db.sql assets auth dashboard includes services PWA uploads
git status
git commit -m "Prepare PHP app for Railway deployment"
git branch -M main
git remote add origin https://github.com/YOUR_USERNAME/YOUR_REPOSITORY.git
git push -u origin main
```

Remove the accidental leading space before `git status` if copying the commands as a block.

## 2. Create the Railway project

1. Open Railway and choose **New Project**.
2. Choose **Deploy from GitHub Repo** and select this repository.
3. Add a **MySQL** service in the same project.
4. Railway detects the root `Dockerfile` and builds the PHP/Apache web service.
5. In the web service, generate a public domain under **Settings > Networking**.

The Dockerfile installs `pdo_mysql` and makes Apache listen on Railway's assigned `PORT`.

## 3. Add database variables

In the web service's **Variables**, add references to the MySQL service. If the MySQL service is named `MySQL`, use the equivalent Railway reference syntax below; select the actual service name if it differs:

```text
DB_HOST=${{MySQL.MYSQLHOST}}
DB_PORT=${{MySQL.MYSQLPORT}}
DB_NAME=${{MySQL.MYSQLDATABASE}}
DB_USER=${{MySQL.MYSQLUSER}}
DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}
```

The application also understands Railway's native `MYSQLHOST`, `MYSQLPORT`, `MYSQLDATABASE`, `MYSQLUSER`, and `MYSQLPASSWORD` variables.

## 4. Import the database

Do not run `init_db.php` against production. Export a clean backup from the local `support_system` database, then import that backup into the Railway MySQL service using its connection values. For example, from a computer with the MySQL client installed:

```powershell
mysql -h RAILWAY_MYSQLHOST -P RAILWAY_MYSQLPORT -u RAILWAY_MYSQLUSER -p RAILWAY_MYSQLDATABASE < production_backup.sql
```

Use the password only when the client prompts for it. Do not put it in the command or in GitHub.

Test the imported database before accepting live users: student login, teacher login, admin login, registration, appointments, library reservations, uploads, reports, and logout.

## 5. Configure email and chatbot secrets

Add these only in Railway **Variables**, never in GitHub:

```text
GEMINI_API_KEY=your_new_rotated_key
SMTP_ENABLED=true
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_ENCRYPTION=tls
SMTP_USERNAME=your_smtp_account
SMTP_PASSWORD=your_smtp_app_password
SMTP_FROM=noreply@your-domain.com
SMTP_FROM_NAME=PASS College Support System
```

For Gmail, use an App Password, not the normal account password. Use Mailtrap first if production email is not ready.

## 6. Protect uploaded files

This system writes to `uploads/` for case documents, images, registrations, and other files. A normal Railway container filesystem is not a reliable permanent file store.

For a small thesis deployment, attach a Railway Volume to the web service and mount it at:

```text
/var/www/html/uploads
```

For a more reliable production deployment, change the upload handlers to use object storage such as Cloudinary, S3-compatible storage, or Cloudflare R2. Back up both the database and uploaded files.

## 7. GitHub auto-deploy workflow

After the first deployment, Railway watches the connected branch. The normal workflow is:

```powershell
git pull
git add path/to/changed-file.php
git commit -m "Describe the change"
git push origin main
```

A push to `main` triggers a new Railway deployment. Check the Railway deployment logs after every push, then test the public URL.

## 8. Important production checks

- Change the default administrator password immediately.
- Rotate the Gemini key that was previously present in source code.
- Do not expose diagnostic, migration, or test PHP pages publicly.
- Keep the GitHub repository private and enable two-factor authentication.
- Schedule MySQL backups and uploaded-file backups.
- Use HTTPS through the Railway domain or a custom domain.
- Test sessions and file uploads after every deployment.
