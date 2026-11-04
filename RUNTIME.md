# Runtime: allocate a new user

Operational steps to add a Linux login account to the **user-mgr** database and apply it on inventory hosts. For architecture, schema, and Ansible internals, see [README.md](README.md).

---

## What “allocate” means here

1. **Record** the account in MariaDB (`users` table).
2. **Update** each machine group’s `hosts.members` list according to **Access Host Rule** (who belongs in that host’s supplementary group).
3. **Deploy** so Ansible creates primary groups, host groups, passwd entries, and home directories on targets.

Saving in the UI only completes steps 1–2. The account does not exist on VMs until you **Deploy** (or run the sync playbook from the shell).

---

## Before you start

| Requirement | Check |
|-------------|--------|
| Stack running | `make up` — web **8080**, DB **3306** ([docker-compose.yml](docker-compose.yml)) |
| Admin login | `ADMIN_USER` / `ADMIN_PASS` in `docker-compose.yml` (default `admin` / see compose file) |
| Target hosts in inventory | [ansible/inventory.ini](ansible/inventory.ini) — `virt` group lists machines that receive accounts |
| SSH from web container | `./ansible-keys` mounted at `/root/.ssh` in the **web** service; Ansible connects as `ansible_user=ansible` |
| Host rows for per-host access | If the user should only access specific machines, those FQDNs must exist under **Host Profiles** in the UI first |

**UID/GID convention:** user UID and primary GID ≥ **5000** (UI auto-suggests `MAX+1`). Host supplementary group GIDs ≥ **6000**.

---

## Step 1 — Open the UI and sign in

1. Browse to `http://<host>:8080/` (or your mapped port).
2. Sign in via [src/login.php](src/login.php).

You land on [src/index.php](src/index.php) — **User Profiles** and **Host Profiles**.

---

## Step 2 — (Optional) Register the target host

Skip if the user’s **Access Host Rule** is `all` or `none`, or if the host is already listed.

1. Scroll to **Host Profiles**.
2. Fill **FQDN**, **Group name** (auto-derived from FQDN), **GID**, and **Members** (always includes `ansible`; add other **usernames** as needed).
3. Click **Save Host**.

Adding a host runs a full reconcile: every user’s `access_host` is re-evaluated against all hosts.

---

## Step 3 — Register the user profile

Under **User Profiles**, use **Register Profile** (not **Modify**).

| Field | What to enter |
|-------|----------------|
| **Name** | Login name (unique). Typing the name auto-fills **Home** as `/share/home/<name>`. |
| **UID** | Leave the suggested next UID unless you have a numbering plan. |
| **GID** | Usually same as UID (primary group = username). |
| **Email** | GECOS / comment field in `/etc/passwd`. |
| **Shell** | Default `/bin/bash`. |
| **Home** | Path used on **virt** hosts during deploy (default `/share/home/<name>`). |
| **Access Host Rule** | See below. |
| **SSH Public Key** | Optional; stored in DB only — **not** installed by current Ansible playbooks. |

### Access Host Rule

| Value | Effect in DB | Typical intent |
|-------|----------------|----------------|
| **none** | User is not added to any host’s `members` list | Account defined but not in machine groups |
| **all** | User is added to **every** host row’s `members` | Broad access |
| **&lt;FQDN&gt;** | User is added only on the matching host’s `members` | One machine (must exist in **Host Profiles**) |

On **Save Profile**, the UI inserts the row and calls `sync_user_host_members()` ([src/host_membership.php](src/host_membership.php)).

Confirm the user appears in the table and, if relevant, that the host’s **Members** column includes the new username.

Quick DB check from the host (optional):

```bash
make show-users
make show-groups
```

---

## Step 4 — Deploy to the operating system

### Option A — UI (normal path)

1. Click **Deploy** (confirms Ansible sync).
2. Wait for the yellow **Deploy log** panel. Exit code **0** means the playbook finished without Ansible failure.
3. Review task output for each inventory host.

Deploy runs inside the **web** container:

- Reconciles all `hosts.members` from `access_host` again.
- Executes `sudo /ansible/run-sync-from-db.sh` → [sync-accounts-from-db.yml](ansible/playbooks/sync-accounts-from-db.yml) (groups, then users).

### Option B — Shell on dev25 (same playbook)

```bash
cd ~/prog/user-mgr
export ANSIBLE_CONFIG=ansible/ansible.cfg
export DB_HOST=127.0.0.1 DB_PORT=3306 DB_NAME=users DB_USER=root DB_PASS=theresa1

ansible-playbook ansible/playbooks/sync-accounts-from-db.yml
# Optional: one host
ansible-playbook ansible/playbooks/sync-accounts-from-db.yml --limit sv-quantum-lx26.infinera.com
```

Use `make ansible-sync-all` as a shortcut for the same playbook when `ANSIBLE_CONFIG` is set via the Makefile.

### Controller-only accounts

To create matching local accounts on the Ansible controller with homes under **`/usr/share/home/<user>`** (not the MySQL `home` path):

```bash
ansible-playbook ansible/playbooks/update-controller-users.yml
```

The yellow **Deploy** button does **not** run this playbook; it only targets **`virt`** hosts.

---

## Step 5 — Verify on the target

On a synced VM (as root or via Ansible):

```bash
getent passwd <username>
getent group <username>
id <username>
ls -ld /share/home/<username>   # or path from the user row
```

Check supplementary membership in the host’s machine group (name like `sv-quantum-lx26-infinera-com`):

```bash
getent group <host-groupname>
```

---

## Step 6 — SSH access (manual today)

1. Install the public key from the UI into `~/.ssh/authorized_keys` on each host the user should log into, **or** extend automation to read `users.public_key`.
2. Ensure `sshd` allows key login and the user’s shell/home exist (deploy creates the account and home with `create_home: true` on virt hosts).

---

## Important behavior (avoid surprises)

**All users in SQL on every virt host.** [update-passwd-block.yml](ansible/playbooks/update-passwd-block.yml) applies **every** row in `users` to **every** host in the `virt` play. The **Access Host Rule** controls **`hosts.members`** (machine group membership), not whether the passwd entry exists on a given VM. Restrict logins with SSH, sudo, or future host-scoped passwd logic if you need tighter isolation.

**Deploy order.** Groups always run before users. Do not run passwd-only playbooks before groups exist.

**SSH keys in DB.** Documented for inventory; playbooks do not push `public_key` yet.

**Name changes.** Editing a user re-runs membership sync; deploy again so `/etc/passwd` matches.

**Delete.** Deleting a user removes them from host `members` in the DB; deploy (or manual cleanup) is needed to remove system accounts on targets.

---

## Troubleshooting

| Symptom | Likely cause |
|---------|----------------|
| Deploy log shows SSH failures | Missing/wrong keys in `ansible-keys/`, host not reachable, or `ansible_user` / sudo on target |
| `sudo: a password is required` in web deploy | [ansible/deploy-sudoers](ansible/deploy-sudoers) not installed in image — rebuild web: `make build` |
| User in DB but not on VM | Deploy not run, or playbook failed — read deploy log / re-run with `-vv` |
| User missing from host **Members** | Wrong **Access Host Rule** or host FQDN mismatch — edit user and save, or run deploy (reconcile) |
| Duplicate UID/GID error on save | Choose unused UID/GID ≥ 5000 |

---

## Checklist (copy for a change ticket)

- [ ] Stack up; admin login works  
- [ ] Host row exists (if not `all` / `none`)  
- [ ] User saved with correct UID/GID, home, and **Access Host Rule**  
- [ ] Host **Members** looks correct in UI  
- [ ] **Deploy** (or `sync-accounts-from-db.yml`) completed with exit 0  
- [ ] Verified `getent passwd` / home on target(s)  
- [ ] SSH key installed manually if login required  
