# user-mgr

Web UI and Ansible tooling to manage Linux user and group accounts in MariaDB, then apply them on hosts as `/etc/passwd` and `/etc/group` entries.

## Stack

| Component | Purpose |
|-----------|---------|
| **web** (`src/`) | PHP UI on port **8080** — CRUD for `users` and `hosts` |
| **db** | MariaDB 10.11, database **`users`**, port **3306** |
| **phpmyadmin** | Port **8081** |
| **ansible/** | Playbooks that read MySQL and run `user` / `group` / `gpasswd` on targets |

```bash
make up          # docker compose up -d
make show-users  # quick SQL listing (needs mysql client on host)
make show-groups
```

Default admin login: see `ADMIN_USER` / `ADMIN_PASS` in `docker-compose.yml`.

---

## Database schema

Database name: **`users`** (see `docker-compose.yml` and `src/config.php`).

### Table `users`

Primary Unix accounts (one row per login).

| Column | Type | Notes |
|--------|------|--------|
| `id` | INT PK AI | Surrogate key |
| `name` | VARCHAR(32) UNIQUE | Login name |
| `uid` | INT UNIQUE | UID (UI suggests next ≥ 5000) |
| `gid` | INT | Primary GID (often same as `uid`) |
| `email` | VARCHAR | GECOS field in `/etc/passwd` |
| `shell` | VARCHAR | Default `/bin/bash` |
| `home` | VARCHAR | Home path (UI default `/share/home/<name>`) |
| `public_key` | TEXT | SSH public key (stored only; not applied by current playbooks) |
| `access_host` | VARCHAR | **`none`**, **`all`**, or a host **FQDN** from `hosts` |

Canonical DDL: [`sql`](sql) and sample data in [`user-mgr-dump_2026-10-24_214517.sql`](user-mgr-dump_2026-10-24_214517.sql).

### Table `hosts`

Per-machine **supplementary** group (machine group) plus member list.

| Column | Type | Notes |
|--------|------|--------|
| `id` | INT PK AI | |
| `fqdn` | VARCHAR UNIQUE | e.g. `tom1.tsand.org` |
| `groupname` | VARCHAR UNIQUE | FQDN with `.` → `-`, e.g. `tom1-tsand-org` |
| `gid` | INT UNIQUE | Host group GID (UI suggests next ≥ 6000) |
| `members` | TEXT | Comma-separated; always includes `ansible,sudo,wheel` plus users whose `access_host` matches this host |

When you save a user, [`src/host_membership.php`](src/host_membership.php) updates each host’s `members` list from `access_host` rules. When you add a host, it runs a full reconcile for all users.

### UID / GID ranges (convention)

| Range | Use |
|-------|-----|
| ≥ 5000 | User UID and primary group GID (`users`) |
| ≥ 6000 | Host supplementary group GID (`hosts`) |

---

## How `index.php` works

[`src/index.php`](src/index.php) is the main UI after login ([`auth.php`](src/auth.php)).

1. **Connect** to MySQL via `db_connect($config)`.
2. **Handle actions** (GET/POST), then **redirect** (PRG) so refreshes do not repeat writes:
   - **Users**: `?delete=` → delete user and remove from all host `members`; `?edit=` → load form; `POST save` → insert/update user and call `sync_user_host_members()`.
   - **Hosts**: `?delete_host=`; `?edit_host=`; `POST save_host` → insert/update host; new host triggers `reconcile_all_user_host_members()`.
3. **Auto-fill** for new records: next UID/GID from `MAX(uid/gid)+1`; next host GID from `hosts`; default `members` = `ansible,sudo,wheel`; JS sets `home` → `/share/home/<name>` and `groupname` from FQDN.
4. **Render** two sections: user matrix and host matrix, plus links to dump/import/generate export files.

Export helpers (same data shape Ansible uses):

- [`src/generate-passwd.php`](src/generate-passwd.php) → `exports/sample-passwd.txt`
- [`src/generate-group.php`](src/generate-group.php) → `exports/sample-group.txt`

---

## Ansible: apply accounts from SQL

Playbooks run on the control node, query MariaDB with the `mysql` CLI, build passwd/group line lists, then apply on targets.

### Prerequisites

- `mysql` client on the machine running Ansible (localhost delegate).
- Environment (or defaults in [`ansible/playbooks/vars/user_mgr_db.yml`](ansible/playbooks/vars/user_mgr_db.yml)):

```bash
export DB_HOST=127.0.0.1
export DB_PORT=3306
export DB_NAME=users
export DB_USER=root
export DB_PASS=theresa1
```

- Inventory: [`ansible/inventory.ini`](ansible/inventory.ini) — groups **`ansible_controller`** (local) and **`virt`** (remote hosts).

Always set config path:

```bash
cd ~/prog/user-mgr
export ANSIBLE_CONFIG=ansible/ansible.cfg
```

### Playbooks

| Playbook | Hosts | What it does |
|----------|-------|----------------|
| [`update-group-block.yml`](ansible/playbooks/update-group-block.yml) | `virt` | Host groups + user primary groups from SQL → `group` / `gpasswd` |
| [`update-passwd-block.yml`](ansible/playbooks/update-passwd-block.yml) | `virt` | All users from SQL → `ansible.builtin.user` (run **after** groups) |
| [`update-controller-users.yml`](ansible/playbooks/update-controller-users.yml) | `ansible_controller` | Groups + users on **localhost**; homes under **`/usr/share/home/<user>`** |
| [`sync-accounts-from-db.yml`](ansible/playbooks/sync-accounts-from-db.yml) | `virt` | **Groups then users** in one run (recommended for VMs) |

Example — sync tom1–tom5:

```bash
cd ~/prog/user-mgr
export ANSIBLE_CONFIG=ansible/ansible.cfg
export DB_HOST=127.0.0.1 DB_PORT=3306 DB_NAME=users DB_USER=root DB_PASS=theresa1

ansible-playbook ansible/playbooks/sync-accounts-from-db.yml
# or only controller:
ansible-playbook ansible/playbooks/update-controller-users.yml
```

### What SQL is read

**Groups** ([`tasks/load-group-from-db.yml`](ansible/playbooks/tasks/load-group-from-db.yml)):

- `SELECT groupname, gid, members FROM hosts ORDER BY gid`
- `SELECT name, gid FROM users ORDER BY gid` (primary groups)

**Users** ([`tasks/load-passwd-from-db.yml`](ansible/playbooks/tasks/load-passwd-from-db.yml)):

- `SELECT name, uid, gid, email, shell, home FROM users ORDER BY uid`

**Apply** ([`tasks/apply-group-export.yml`](ansible/playbooks/tasks/apply-group-export.yml), [`tasks/apply-passwd-export.yml`](ansible/playbooks/tasks/apply-passwd-export.yml)):

- Ensures groups exist with correct GID and member list (`gpasswd -M`).
- Creates users with `ansible.builtin.user`, then `pwconv` / `grpconv`.

**Note:** Remote playbooks currently install **every row in `users`** on **every** host in the play. The `access_host` column drives **`hosts.members`** in the database (who appears in each machine group), not yet per-host filtering in `update-passwd-block.yml`. Plan access accordingly or extend the load task with host-specific SQL.

---

## File map

```
sql/                          # CREATE TABLE users + hosts
src/index.php                 # Main UI
src/host_membership.php       # access_host → hosts.members
src/passwd_export.php         # passwd line format
src/group_export.php          # group line format
ansible/playbooks/            # Playbooks and tasks/
exports/                      # sample-passwd.txt, sample-group.txt
```
