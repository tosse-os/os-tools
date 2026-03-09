Project overview

This repository contains a Laravel-based application with additional internal OS tools and Node.js utilities.

The project provides internal tools, scanning utilities, and services used by the OS platform.

Main technologies used in this repository:

PHP / Laravel
Node.js utilities
Composer for PHP dependencies


Repository structure

app/
Laravel application logic.

routes/
Application routes and API endpoints.

config/
Laravel configuration files.

database/
Database migrations and seeders.

resources/
Frontend resources and views.

public/
Public entry point.

tests/
PHPUnit test suite.

tools/
Internal OS tools used by the platform.

node-scanner/
Node.js based scanning utilities.


Current implemented system components

The SEO analysis platform already implements the following components.

These features must NOT be reimplemented unless explicitly requested.

### Scan Pipeline

Controller
→ Laravel Job
→ Node Scanner
→ JSON output in storage/scans/{scanId}
→ Laravel persistence services
→ Report generation

### Core Models

Project
Analysis
Report
ReportResult
Issue

### Implemented Features

- Project → Analysis → Reports hierarchy
- Modular SEO result storage using report_results
- Node-based crawler and scanner
- Local SEO scanning
- Multi URL scanning
- Asynchronous queue processing
- Scan progress monitoring
- Queue monitoring interface

### Issue Detection System

The application already includes an SEO issue detection system.

Implemented components:

- issues database table
- Issue model
- IssueDetectionService
- severity levels: critical, warning, info
- issue persistence per report

Detected issue types currently include:

- missing_h1
- duplicate_title
- thin_content
- missing_schema
- missing_alt

### Scanner Modules (Node)

Implemented checks include:

- heading check
- alt attribute check
- status code check

Crawler functionality includes:

- link discovery
- internal crawl queue
- multi-page scanning


Development rules

Follow Laravel conventions and best practices.

Do not change existing API contracts unless explicitly instructed.

Prefer modifying existing files instead of creating new ones.

Avoid unnecessary refactoring unless explicitly requested.

Do not restructure the repository.

Do not modify database migrations unless the task explicitly requires it.

Avoid modifying configuration files unless required for the task.


Code quality

Keep code readable and maintainable.

Follow the existing coding style used in the repository.

Avoid introducing new frameworks or major dependencies unless explicitly requested.

Prefer simple and targeted changes over large architectural changes.


Testing

Always run tests and fix failing tests before committing changes.

Run the test suite using:

php artisan test


Dependencies

Install PHP dependencies using:

composer install


Node utilities

Some tools in this repository use Node.js.

If working with Node-based utilities install dependencies using:

npm install

Node utilities are located mainly in:

node-scanner/
tools/


Commits

Only commit the minimal changes required to complete the task.

Do not introduce unrelated changes.

Ensure the project still runs after modifications.

Run tests before finishing the task.


Agent behavior

Focus only on the requested task.

Avoid changing unrelated parts of the codebase.

Do not add new dependencies unless necessary.

Prefer stability and maintainability over aggressive refactoring.

Ensure that the application remains functional after modifications.
