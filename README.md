


CREATE TABLE deadlines (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  deadline_date VARCHAR(50) NOT NULL,
  title VARCHAR(255) NOT NULL,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

CREATE TABLE goals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

CREATE TABLE group_deliverables (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  is_verified TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

CREATE TABLE sub_tasks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  deliverable_id INT NOT NULL,
  assignee VARCHAR(100) NOT NULL,
  title VARCHAR(255) NOT NULL,
  status ENUM('PENDING','IN PROGRESS','UNDER REVIEW','DONE') NOT NULL DEFAULT 'PENDING',
  FOREIGN KEY (deliverable_id) REFERENCES group_deliverables(id) ON DELETE CASCADE
);

CREATE TABLE weekly_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  member_name VARCHAR(100) NOT NULL,
  week VARCHAR(50) NOT NULL,
  commentary TEXT,
  proof_link VARCHAR(500),
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);
```

### 3. Configure database credentials

Do **not** commit real credentials. Create a dedicated MySQL user with limited privileges instead of using `root`:

```sql
CREATE USER 'teamup_app'@'localhost' IDENTIFIED BY 'a-strong-password';
GRANT SELECT, INSERT, UPDATE, DELETE ON group_project_db.* TO 'teamup_app'@'localhost';
```

Then update the connection settings at the top of `api.php` (or, recommended, load them from environment variables / a `config.php` kept outside the web root and listed in `.gitignore`).

### 4. Run it

Place the files in your web server directory (e.g. `htdocs/teamup/`) and open:

```
http://localhost/teamup/index.html
```

Or, for quick local testing with PHP's built-in server:

```bash
php -S localhost:8000
```

---

## 📖 How to Use

1. **Create a project** on the Home tab: enter a title, a Group Code, and an Admin PIN.
2. **Share the Group Code** with your teammates.
3. Teammates **join** with the Group Code and their name.
4. The admin unlocks **Admin Mode** with the Admin PIN, then adds deliverables, sub-tasks, deadlines and goals.
5. Members **start** their tasks and **submit for verification**; the admin **approves or rejects**.
6. Once all sub-tasks are approved, the admin **verifies the deliverable**.
7. Members post **weekly progress logs** with a proof link.

---

## 🔌 API Overview

All requests go to `api.php?action=<name>`. Data is sent/received as JSON.

| Action | Method | Purpose |
|---|---|---|
| `get_data` | GET | Load the whole project board |
| `create_project` | POST | Create a new project |
| `verify_admin` | POST | Check the Admin PIN |
| `update_task_status` | POST | Change a sub-task's status |
| `add_deliverable` / `edit_deliverable` / `delete_deliverable` | POST | Manage deliverables |
| `verify_deliverable` | POST | Verify / unverify a deliverable |
| `add_sub_task` / `edit_sub_task` / `delete_sub_task` | POST | Manage sub-tasks |
| `add_member` / `remove_member` | POST | Manage members |
| `add_deadline` | POST | Add a deadline |
| `add_goal` / `remove_goal` | POST | Manage goals |
| `submit_weekly` | POST | Submit a weekly progress log |

---

## 🔒 Security Notes

**What's already in place**

- All SQL queries use **PDO prepared statements** (no string-concatenated SQL)
- Admin PINs are stored with `password_hash()` and checked with `password_verify()`
- The frontend escapes user-supplied text before inserting it into the page and validates link URLs (`http/https` only)
- No API keys or secrets are hardcoded in the repository

**Known limitations (please read before deploying publicly)**

- Admin actions are currently gated **in the UI only** — the API does not yet require the Admin PIN or a session token on each write request. Anyone who knows a Group Code and can craft requests could modify that project's data.
- Member identity is name-based, so members can impersonate one another.
- There is no rate limiting on PIN attempts.
- The frontend references `join_member` and `delete_deadline` actions that are not yet implemented in `api.php`.

**Recommended hardening (roadmap)**

- [ ] Issue a signed token/session on admin login and require it on every admin endpoint
- [ ] Scope every `UPDATE`/`DELETE` to the current project (`project_id`) to prevent cross-project edits
- [ ] Implement per-member PINs and a `join_member` endpoint
- [ ] Add rate limiting / lockout for PIN attempts
- [ ] Load DB credentials from environment variables or a git-ignored config file
- [ ] Add security headers (CSP, `X-Content-Type-Options`) and disable `display_errors` in production
- [ ] Serve over HTTPS

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
