# 🫒 Let's Team Up! — Group Project Distribution System

A lightweight web app for organizing group projects: create a team board, share a **Group Code**, assign sub-tasks under group deliverables, track progress, and let an admin verify finished work.

Built with plain **HTML/CSS/JavaScript** (single-page frontend) and a small **PHP + MySQL** JSON API.

---

## ✨ Features

- **Create / join a project board** using a Group Code
- **Personal PIN per member** — no full account signup, but your name on the board is protected by a PIN only you know
- **Roles:** Leader and Member tags for each teammate
- **Group Task Deliverables** with linked **sub-tasks** assigned per member
- **Task workflow:** `PENDING → IN PROGRESS → UNDER REVIEW → DONE`
- **Admin Mode** (PIN-protected) to add/edit/delete members, deadlines, goals, deliverables and tasks, and to approve/reject submissions
- **Deliverable verification** — locked until every sub-task is approved
- **Overall progress bar** computed from approved sub-tasks
- **Weekly progress logs** with commentary and a proof link
- Every sensitive action is checked **on the server**, not just hidden in the UI (see [Security](#-security))

---

## 🗂️ Project Structure

```
.
├── index.html          # Frontend (UI + client-side logic)
├── api.php              # Backend JSON API (PHP + PDO + MySQL)
├── config.example.php   # Template for your database credentials — safe to commit
├── config.local.php     # Your real credentials — NEVER commit this (see .gitignore)
├── .gitignore
└── README.md
```

---

## 🧰 Requirements

- PHP 8.0+ with the `pdo_mysql` extension
- MySQL 5.7+ / MariaDB 10.3+
- A web server (Apache/Nginx, XAMPP/Laragon for local dev, or a shared host such as InfinityFree)

---

## 🚀 Setup

### 1. Get the files

```bash
git clone https://github.com/<your-username>/<your-repo>.git
cd <your-repo>
```

### 2. Create the database

`api.php` does **not** run `CREATE DATABASE` (some shared hosts, like InfinityFree, don't allow that from PHP). Create the database yourself once, from phpMyAdmin or your host's control panel — any name is fine, you'll put it in `config.local.php`.

Tables are created **automatically** the first time `api.php` runs (all prefixed `tu_`), so no SQL import is needed after that.

### 3. Configure database credentials

Copy the template and fill in your real values:

```bash
cp config.example.php config.local.php
```

```php
<?php
// config.local.php — never committed (see .gitignore)
return [
    'host' => 'localhost',        // XAMPP/Laragon: localhost. InfinityFree: something like sqlXXX.infinityfree.com
    'name' => 'group_project_db', // shared hosts often prefix this, e.g. if0_12345_group_project_db
    'user' => 'YOUR_USER',
    'pass' => 'YOUR_PASSWORD',
];
```

`api.php` refuses to run with a clear error until this file exists with real values — it will never silently use a blank or default password.

### 4. Run it

Place the files on your web server (e.g. `htdocs/teamup/`) and open:

```
http://localhost/teamup/index.html
```

Or test the API directly first:

```
http://localhost/teamup/api.php?action=ping
```

You should see `{"status":"success","success":true,"message":"API and database are working."}`.

---

## 📖 How to Use

1. **Create a project** on the Home tab: enter a title, a Group Code, and an Admin PIN.
2. **Share the Group Code** with your teammates.
3. Teammates **join** with the Group Code, their name, and a **Personal PIN** they choose the first time (the same PIN logs them back in later).
4. The admin unlocks **Admin Mode** with the Admin PIN, then adds members, deadlines, goals, deliverables, and sub-tasks.
5. Members **start** their own tasks and **submit for verification** using their Personal PIN; the admin **approves or rejects** using the Admin PIN.
6. Once all sub-tasks under a deliverable are approved, the admin **verifies the deliverable**.
7. Members post **weekly progress logs** (their Personal PIN is checked automatically).

---

## 🔌 API Overview

All requests go to `api.php?action=<name>`. Data is sent/received as JSON, e.g. `{"status":"success", ...}` or `{"status":"error","message":"..."}`.

| Action | Method | Auth required | Purpose |
|---|---|---|---|
| `ping` | GET | — | Health check |
| `get_data` | GET | — | Load the whole project board |
| `create_project` | POST | — | Create a new project (sets the Admin PIN) |
| `join_member` | POST | Personal PIN | Join the board / log back in as a member |
| `verify_admin` | POST | Admin PIN | Check the Admin PIN (used to unlock Admin Mode in the UI) |
| `update_task_status` | POST | Personal PIN (own task) **or** Admin PIN | Move a sub-task through the workflow |
| `add_deliverable` / `edit_deliverable` / `delete_deliverable` | POST | Admin PIN | Manage deliverables |
| `verify_deliverable` | POST | Admin PIN | Verify / unverify a deliverable |
| `add_sub_task` / `edit_sub_task` / `delete_sub_task` | POST | Admin PIN | Manage sub-tasks |
| `add_member` / `remove_member` | POST | Admin PIN | Manage members |
| `add_deadline` / `delete_deadline` | POST | Admin PIN | Manage deadlines |
| `add_goal` / `remove_goal` | POST | Admin PIN | Manage goals |
| `submit_weekly` | POST | Personal PIN | Submit a weekly progress log |

**"Auth required" is enforced by `api.php` itself**, not just by the frontend — every action above re-checks the PIN against the database on every request (see below).

---

## 🔒 Security

This project went through a review and hardening pass. `api.php` re-checks authorization on the server for every request — it does not trust the browser UI to gate anything.

**In place**

- All SQL queries use **PDO prepared statements** with `PDO::ATTR_EMULATE_PREPARES` off — no string-concatenated SQL.
- Admin and Personal PINs are stored with `password_hash()` and checked with `password_verify()` — never in plain text.
- **Every admin action requires `project_code` + `admin_pin` on that specific request**, verified against the database each time (no PIN, no session to steal or forget to check — see `requireAdmin()` in `api.php`).
- **`update_task_status`** accepts *either* the Admin PIN *or* the assigned member's own Personal PIN — a member can move their own task without admin help, but cannot touch anyone else's.
- Every edit/delete is scoped to the project that owns the record (no cross-project edits by guessing an ID).
- Real credentials live only in `config.local.php`, which is git-ignored; `api.php` refuses to start if it's missing or still has placeholder values.
- No `CREATE DATABASE` at runtime — works on restricted shared hosts.
- Rate limiting (a handful of attempts per 10 minutes) on PIN checks and project creation, tracked in the `tu_attempts` table.
- CORS is same-origin only (no `Access-Control-Allow-Origin: *`).
- PHP errors and exceptions are logged server-side and never echoed to the browser; JSON responses never leak file paths, hostnames, or stack traces.
- `admin_pin_hash` is never included in `get_data` responses.
- The frontend escapes all user-supplied text before inserting it into the page and only allows `http(s)://` proof links.

**Still worth knowing**

- The Group Code itself is the "who can even see this project" gate — treat it like a shared secret, not a public link.
- A member's identity is their name + Personal PIN; there's no email verification, so the admin should remove/re-add a member if a PIN is ever compromised.
- PINs are checked per request rather than via a login session, which keeps things simple and reliable on shared hosting — but means the PIN is sent with every relevant request (always over HTTPS in production, please).

**Possible next steps**

- [ ] Serve over HTTPS in production (required if PINs are being sent on every request)
- [ ] Add a lockout/cooldown UI so rate-limited users get clear feedback instead of a generic error
- [ ] Rotate/expire the `tu_attempts` table periodically (a small cleanup already runs probabilistically on write)
- [ ] Optional: email or recovery flow for a member who forgets their Personal PIN

---

## 🛠️ Tech Stack

- HTML5, CSS3, Vanilla JavaScript
- PHP 8 (PDO)
- MySQL / MariaDB
- Google Fonts (Playfair Display, Plus Jakarta Sans)

---

## 🤝 Contributing

Pull requests are welcome. For major changes, please open an issue first to discuss what you'd like to change.

## 📄 License

Add your preferred license here (e.g. MIT).
