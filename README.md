# cst8319-resourcehub
ResourceHub — Equipment and Resources Reservation System for CST8319 Software Development Project.

## Technology Stack 

- PHP
- My SQL
- PHP PDO
- HTML5
- CSS3
- Bootstrap
- JavaScript
- DreamHost
- Git / GitHub


- # Database Database name: cst8319

## Website structure

- `public/`: homepage, catalogue, login, profile and reservation pages.
- `public/admin/`: administration pages.
- `public/includes/`: shared authentication helpers and navigation.
- `public/assets/css/`: stylesheets.
- `config/`: PDO connection code and configuration template.
- `database/`: database schema, data scripts and queries.
- `tests/demo3.py`: automated integration test script.

This import provides the existing working application as the starting point for the team's planned architecture refactoring.

## Run locally using the DreamHost database

Requirements: Git, XAMPP with PHP 8.1 or newer, PDO MySQL and mbstring enabled, and authorized SSH/database access.

1. Clone the repository. Until this import is merged, switch to `feature/import-website`.
2. In your own clone, copy `config/database.example.php` to `config/database.local.php`.
3. Configure that private file using the connection details supplied securely by the team lead. Never commit or upload passwords or `database.local.php`.
4. Open your authorized SSH tunnel. For the team's current local setup, the database connection uses `127.0.0.1` on forwarded port `3307`. Keep the tunnel open.
5. Open PowerShell in the project root and run:

   ```powershell
   & 'C:\xampp\php\php.exe' -S 127.0.0.1:8080 -t public
   ```

6. Open http://127.0.0.1:8080/ in your browser.

The PHP development server serves `public/` as the website root. Apache is not required for this command. GitHub stores the source code; it does not run the PHP website.

## Shared database precautions

This setup connects to the live DreamHost database. Booking, cancellation and administration actions can change live records. Coordinate any testing that writes data with the team lead.

Do not import the schema, seed data or demo fixtures into the live database. Cloning or updating the website source does not require reinitializing the database.

## Automated tests

The integration test script is intended to run against disposable local database and PHP services using XAMPP and Python 3.10 or newer. It is separate from the DreamHost connection.

Test results must identify the revision and environment tested. Importing the script into GitHub does not establish that the imported branch has passed the suite.
