# 🧠 Invoease (invoease.co.uk)

[![Laravel Version](https://img.shields.io/badge/laravel-v13-red.svg)](https://laravel.com)
[![Livewire Version](https://img.shields.io/badge/livewire-v4-blue.svg)](https://livewire.laravel.com)
[![Tailwind Version](https://img.shields.io/badge/tailwindcss-v4-cyan.svg)](https://tailwindcss.com)
[![Pest Tests](https://img.shields.io/badge/tests-100%20passing-emerald.svg)](https://pestphp.com)
[![Style Standard](https://img.shields.io/badge/code%20style-pint-purple.svg)](https://github.com/laravel/pint)

**Invoease** is a modern, enterprise-grade, bilingual B2B SaaS platform specifically designed for local service businesses in the United Kingdom (e.g., cleaning companies, gardening services, property maintenance, home care, and tradespeople) to seamlessly manage customers, schedule team members, and automate invoicing and payouts.

🌐 **Live in Production:** [https://invoease.co.uk](https://invoease.co.uk)

---

## ✨ Core Features & Workflows

### 1. Dynamic Dashboards (Role-Based)
* **Management Dashboard:** A birds-eye view of financial metrics (Monthly Revenue, Pending Payouts, Active Customers, Completed Services), recent invoices list, recent service activities, and a monthly top collaborators leaderboard.
* **Collaborator Dashboard:** Simplified and private workspace displaying personal hours worked, monthly earnings, pending payouts, and assigned schedules.

### 2. Quotes & Estimates Management (Full Screen)
* **Visual Form Builder:** A clean full-screen layout to build professional quotes with dynamic item additions, customized notes fields per row, and automatic rate filling.
* **One-Click Invoice Conversion:** Lets managers convert accepted quotes to rascunho (draft) invoices instantly, automatically duplicating items and notes, with secure UUID parameter redirection.
* **Origin Banner Warning:** The generated invoice clearly displays a high-visibility badge: `"Generated from Quote Q0001 on DD/MM/YYYY"` to retain absolute financial traceability.

### 3. Smart Scheduling & Weekly Agenda
* **Recurrence Management:** Schedule services weekly, fortnightly, monthly, or as one-offs.
* **Smart Ocurrences:** Support completing occurrences with notes, rescheduling to custom days/times, or skipping individual occurrences for the week (e.g. holidays).
* **Assigned Teams:** Multi-selection of collaborators on both recurring and manual services with easy checkbox interfaces.
* **Agenda Calendars Filter:** Dynamically filters both schedules and overridden instance cards in the agenda by custom calendar categories.

### 4. Dynamic Calendars & Customizable Multi-Rates
* **Unlimited Calendar Categories:** Move beyond hardcoded types to manage unlimited dynamic Calendars (e.g., Residential, Commercial, Industrial, Home Cleaning) per company.
* **Collaborator Multi-Rates:** Configures dynamic collaborator hourly rates per calendar, rendering dynamic inputs in the team registration form.
* **Auto-Prefilled Settings:** New companies automatically receive 'House' and 'Office' categories seeded, with all legacy data preserved and converted flawlessly.

### 5. One-Click Invoice Generation
* **Sequential Numbering:** Automatically increments invoice numbers based on the business's custom "Last Invoice Number" setting, eliminating database COUNT dependency.
* **Auto-Calculated Totals:** Merges completed service instances, calculates team shares, and generates clean, sequentially numbered PDF Invoices.
* **Manual Services:** Easily add manual/one-off items to an invoice, with automatic rate updating based on the selected service location's rate.
* **Draft Editing on Reselection:** Allows editing of any draft invoice by going back directly to the select services screen, pre-filling selected checkboxes and notes dynamically.

### 6. Payouts & Team Financials
* **Share-Based Calculations:** Automatically splits service hours among assigned team members and calculates individual payouts based on their custom hourly rates.
* **Payout Reports:** Generates professional PDF payout reports for collaborators for any custom date range.
* **Batch Payments:** Lets managers review detailed service logs and mark outstanding collaborator payouts as "Paid" in bulk.

### 5. Multi-Channel Email Outbox & Logs
* **Sender Branding:** Email sender name dynamically displays as `[App Name] - [Company Name]` (e.g., `Invoease - Invoease Services Ltd`) pulling directly from configuration.
* **PDF Attachments & CC:** Instantly emails generated PDF invoices to customers with company administrators automatically added in CC.
* **Email History Logs:** Dedicated "Email Logs" tab within the Invoices screen to track success/failure logs (with full error reporting for SMTP debug) and a one-click **Resend** option.
* **Anti-Double-Send Safety:** Warns and prompts the user with a confirmation modal if an invoice email was already dispatched.

### 6. Built-in Superadmin Subscription Control
* **10-day Free Trial:** New registrations automatically receive a 10-day trial attached to their company.
* **Subscribers Management:** A central admin dashboard to list all registered companies, check subscription dates, and manually extend access by 30 days/1 year, or suspend access immediately.
* **Automatic Expiration Block:** A middleware intercepts and locks expired company users, displaying a clean "Subscription Expired" card, while superadmins remain completely exempt.

### 7. Dual-Language Localization (EN & PT-BR)
* **Localized Interface:** The entire platform translates instantly between **English (UK)** and **Portuguese (Brasil)** from a single profile option.
* **Enforced English Client-Facing PDFs:** While the app body adapts, PDF Invoices, PDF Payout Reports, and Client Email bodies always enforce strictly professional English.

---

## 🛠️ State-of-the-Art Tech Stack

* **Backend Framework:** [Laravel 13.x](https://laravel.com)
* **Frontend Reactivity:** [Livewire v4](https://livewire.laravel.com) (Volt Single File Components) & [Alpine.js](https://alpinejs.dev)
* **CSS Engine:** [Tailwind CSS v4](https://tailwindcss.com) (using the high-performance compiler)
* **UI Components:** [Flux UI](https://fluxui.dev) (premium and responsive components)
* **Authentication:** [Laravel Fortify](https://github.com/laravel/fortify) (with native Passkeys/WebAuthn and 2FA TOTP support)
* **Design Pattern Architecture:** `r2luna/brain` Clean Architecture (Workflows, Actions, Queries)
* **PDF Generator:** [Barryvdh Laravel DomPDF](https://github.com/barryvdh/laravel-dompdf)
* **Automated Testing:** [Pest PHP v4](https://pestphp.com)

---

## 💻 Local Installation & Setup

Ensure you have **PHP 8.3+** and **Composer** installed.

1. **Clone the repository:**
   ```bash
   git clone <repository-url>
   cd services-app
   ```

2. **Run the composer installer:**
   ```bash
   composer install
   ```

3. **Configure Environment:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Prepare Database:**
   ```bash
   touch database/database.sqlite
   php artisan migrate --seed
   ```

5. **Install Frontend Assets:**
   ```bash
   npm install
   npm run build
   ```

6. **Start Local Servers:**
   ```bash
   php artisan serve
   ```

---

## 🧪 Test Suite

The platform includes **100 robust Pest integration tests** covering 100% of the Livewire components, workflows, security middleware, and email logging system.

To run the entire test suite:
```bash
composer test
```
*(Or `php artisan test --compact`)*

To run Pint coding standard format check:
```bash
composer lint
```

---

## 🔑 Default Credentials (for testing)

For testing purposes, the seeder populates the following accounts:

### 1. Superadmin Account (To manage subscriptions)
* **Email:** `admin@invoease.co.uk`
* **Password:** `admin123`

### 2. Company Owner Account (To manage business)
* **Email:** `brianlucas67@gmail.com`
* **Password:** `admin@admin`

---

## 📄 License
This project is open-sourced software licensed under the [MIT License](LICENSE).
