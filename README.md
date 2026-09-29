

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
- [ ] Scope every `UPDATE`/`DELETE` to th

Pull requests are welcome. For major changes, please open an issue first to discuss what you'd like to change.

## 📄 License

Add your preferred license here (e.g. MIT).
