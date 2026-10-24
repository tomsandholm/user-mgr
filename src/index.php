<?php
require __DIR__ . '/auth.php';
require_login();

try {
    $pdo = db_connect($config);
} catch (\PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// --- USER STATE ---
$id = '';
$name = '';
$uid = '';
$gid = '';
$email = '';
$shell = '/bin/bash';
$home = '';
$public_key = '';
$access_host = 'all';
$update_mode = false;
$message = '';

// --- HOST STATE ---
$host_id = '';
$fqdn = '';
$groupname = '';
$host_gid = '';
$members = 'ansible,sudo,wheel';
$host_update_mode = false;

const DEFAULT_HOST_MEMBERS = ['ansible', 'sudo', 'wheel'];

function next_uid_gid(PDO $pdo): array
{
    $row = $pdo->query(
        "SELECT COALESCE(MAX(uid), 4999) + 1 AS next_uid,
                COALESCE(MAX(gid), 4999) + 1 AS next_gid
         FROM users"
    )->fetch();
    return [(int)$row['next_uid'], (int)$row['next_gid']];
}

function next_host_gid(PDO $pdo): int
{
    $row = $pdo->query(
        "SELECT COALESCE(MAX(gid), 5999) + 1 AS next_gid FROM hosts"
    )->fetch();
    return (int)$row['next_gid'];
}

function fqdn_to_groupname(string $fqdn): string
{
    return str_replace('.', '-', strtolower(trim($fqdn)));
}

function normalize_members(string $members): string
{
    $parts = preg_split('/[\s,]+/', trim($members), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $parts = array_map('strtolower', $parts);
    $ordered = DEFAULT_HOST_MEMBERS;
    foreach ($parts as $p) {
        if (!in_array($p, $ordered, true)) {
            $ordered[] = $p;
        }
    }
    return implode(',', $ordered);
}

function normalize_access_host(string $access): string
{
    $access = strtolower(trim($access));
    if ($access === '%') {
        return 'all';
    }
    return $access;
}

function host_machine_group_key(array $host): string
{
    return str_replace('.', '-', strtolower(trim($host['groupname'] ?? '')));
}

function access_host_matches_host(string $normalized_access, array $host): bool
{
    if ($normalized_access === 'none' || $normalized_access === '') {
        return false;
    }
    if ($normalized_access === 'all') {
        return true;
    }
    $fqdn = strtolower(trim($host['fqdn'] ?? ''));
    $groupname = strtolower(trim($host['groupname'] ?? ''));
    $as_group = str_replace('.', '-', $normalized_access);
    return $normalized_access === $fqdn
        || $normalized_access === $groupname
        || $as_group === $groupname
        || $as_group === host_machine_group_key($host);
}

function parse_members_list(string $members): array
{
    return array_values(array_filter(
        array_map('trim', explode(',', $members)),
        fn(string $m) => $m !== ''
    ));
}

function sync_user_host_members(
    PDO $pdo,
    string $username,
    string $old_access_host = '',
    string $new_access_host = '',
    string $old_username = ''
): void {
    $username = trim($username);
    $old_username = trim($old_username) !== '' ? trim($old_username) : $username;
    if ($username === '') {
        return;
    }

    $old_access = normalize_access_host($old_access_host);
    $new_access = normalize_access_host($new_access_host);
    $assignment_changed = $old_username !== $username || $old_access !== $new_access;
    if (!$assignment_changed) {
        return;
    }

    $hosts = $pdo->query('SELECT id, fqdn, groupname, members FROM hosts')->fetchAll();
    $update = $pdo->prepare('UPDATE hosts SET members = ? WHERE id = ?');

    foreach ($hosts as $host) {
        $members = parse_members_list((string)($host['members'] ?? ''));
        $machine_key = host_machine_group_key($host);

        $remove_user = $new_access === 'none'
            || $old_access === 'all'
            || ($old_access !== '' && $old_access !== 'none' && (
                $old_access === $machine_key
                || access_host_matches_host($old_access, $host)
            ));

        $add_user = $new_access === 'all'
            || ($new_access !== '' && $new_access !== 'none' && access_host_matches_host($new_access, $host));

        if ($remove_user) {
            $members = array_values(array_filter(
                $members,
                fn(string $m) => $m !== $old_username
            ));
        }
        if ($add_user && !in_array($username, $members, true)) {
            $members[] = $username;
        }

        $normalized = normalize_members(implode(',', $members));
        if ($normalized !== (string)$host['members']) {
            $update->execute([$normalized, $host['id']]);
        }
    }
}

function remove_user_from_host_members(PDO $pdo, string $username): void
{
    $username = trim($username);
    if ($username === '') {
        return;
    }

    $hosts = $pdo->query('SELECT id, members FROM hosts')->fetchAll();
    $update = $pdo->prepare('UPDATE hosts SET members = ? WHERE id = ?');

    foreach ($hosts as $host) {
        $members = array_values(array_filter(
            parse_members_list((string)($host['members'] ?? '')),
            fn(string $m) => $m !== $username
        ));
        $normalized = normalize_members(implode(',', $members));
        if ($normalized !== (string)$host['members']) {
            $update->execute([$normalized, $host['id']]);
        }
    }
}

// --- USER: DELETE ---
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare('SELECT name FROM users WHERE id = ?');
    $stmt->execute([$_GET['delete']]);
    $deleted = $stmt->fetch();
    $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$_GET['delete']]);
    if ($deleted) {
        remove_user_from_host_members($pdo, $deleted['name']);
    }
    header("Location: index.php?msg=Record+Deleted");
    exit;
}

// --- HOST: DELETE ---
if (isset($_GET['delete_host'])) {
    $stmt = $pdo->prepare("DELETE FROM hosts WHERE id = ?");
    $stmt->execute([$_GET['delete_host']]);
    header("Location: index.php?msg=Host+Deleted#hosts");
    exit;
}

// --- USER: EDIT ---
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $update_mode = true;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row) {
        $name = $row['name'];
        $uid = $row['uid'];
        $gid = $row['gid'];
        $email = $row['email'];
        $shell = $row['shell'];
        $home = $row['home'];
        $public_key = $row['public_key'];
        $access_host = $row['access_host'];
    }
}

// --- HOST: EDIT ---
if (isset($_GET['edit_host'])) {
    $host_id = $_GET['edit_host'];
    $host_update_mode = true;
    $stmt = $pdo->prepare("SELECT * FROM hosts WHERE id = ?");
    $stmt->execute([$host_id]);
    $row = $stmt->fetch();
    if ($row) {
        $fqdn = $row['fqdn'];
        $groupname = $row['groupname'];
        $host_gid = $row['gid'];
        $members = $row['members'];
    }
}

// --- USER: SAVE ---
if (isset($_POST['save'])) {
    $name = trim($_POST['name']);
    $uid = trim($_POST['uid']);
    $gid = trim($_POST['gid']);
    $email = trim($_POST['email']);
    $shell = trim($_POST['shell']);
    $home = trim($_POST['home']);
    $public_key = trim($_POST['public_key']);
    $access_host = trim($_POST['access_host']);
    $update_mode = ($_POST['update_mode'] ?? '0') === '1';
    $id = $_POST['id'] ?? '';

    $prev_name = '';
    $prev_access_host = '';
    if ($update_mode && $id !== '') {
        $prev_stmt = $pdo->prepare('SELECT name, access_host FROM users WHERE id = ?');
        $prev_stmt->execute([$id]);
        $prev_row = $prev_stmt->fetch();
        if ($prev_row) {
            $prev_name = (string)$prev_row['name'];
            $prev_access_host = (string)$prev_row['access_host'];
        }
    }

    if (!empty($name) && $uid !== '' && $gid !== '' && $email !== '') {
        try {
            if ($update_mode) {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, uid = ?, gid = ?, email = ?, shell = ?, home = ?, public_key = ?, access_host = ? WHERE id = ?");
                $stmt->execute([$name, $uid, $gid, $email, $shell, $home, $public_key, $access_host, $id]);
                sync_user_host_members($pdo, $name, $prev_access_host, $access_host, $prev_name);
                header("Location: index.php?msg=Record+Updated");
                exit;
            } else {
                $stmt = $pdo->prepare("INSERT INTO users (name, uid, gid, email, shell, home, public_key, access_host) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $uid, $gid, $email, $shell, $home, $public_key, $access_host]);
                sync_user_host_members($pdo, $name, '', $access_host);
                header("Location: index.php?msg=Record+Added");
                exit;
            }
        } catch (\PDOException $e) {
            if ($e->getCode() == 23000) {
                $message = "<div class='alert alert-danger'>Error: Name or UID already exists!</div>";
            } else {
                $message = "<div class='alert alert-danger'>Database Error: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    } else {
        $message = "<div class='alert alert-danger'>Name, UID, GID, and email are required!</div>";
    }
}

// --- HOST: SAVE ---
if (isset($_POST['save_host'])) {
    $fqdn = strtolower(trim($_POST['fqdn'] ?? ''));
    $groupname = trim($_POST['groupname'] ?? '');
    $host_gid = trim($_POST['host_gid'] ?? '');
    $members = normalize_members($_POST['members'] ?? '');
    $host_update_mode = ($_POST['host_update_mode'] ?? '0') === '1';
    $host_id = $_POST['host_id'] ?? '';

    if ($groupname === '' && $fqdn !== '') {
        $groupname = fqdn_to_groupname($fqdn);
    }

    if ($fqdn !== '' && $groupname !== '' && $host_gid !== '') {
        try {
            if ($host_update_mode) {
                $stmt = $pdo->prepare("UPDATE hosts SET fqdn = ?, groupname = ?, gid = ?, members = ? WHERE id = ?");
                $stmt->execute([$fqdn, $groupname, $host_gid, $members, $host_id]);
                header("Location: index.php?msg=Host+Updated#hosts");
                exit;
            } else {
                $stmt = $pdo->prepare("INSERT INTO hosts (fqdn, groupname, gid, members) VALUES (?, ?, ?, ?)");
                $stmt->execute([$fqdn, $groupname, $host_gid, $members]);
                header("Location: index.php?msg=Host+Added#hosts");
                exit;
            }
        } catch (\PDOException $e) {
            if ($e->getCode() == 23000) {
                $message = "<div class='alert alert-danger'>Error: FQDN, groupname, or GID already exists!</div>";
            } else {
                $message = "<div class='alert alert-danger'>Database Error: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    } else {
        $message = "<div class='alert alert-danger'>FQDN, groupname, and GID are required!</div>";
    }
}

// Prefill next uid/gid for new user registrations
if (!$update_mode && $uid === '' && $gid === '') {
    [$uid, $gid] = next_uid_gid($pdo);
}

// Prefill next host gid and default members for new hosts
if (!$host_update_mode) {
    if ($host_gid === '') {
        $host_gid = next_host_gid($pdo);
    }
    if ($members === '' || $members === 'ansible,sudo,wheel') {
        $members = implode(',', DEFAULT_HOST_MEMBERS);
    }
}

$users = $pdo->query("SELECT * FROM users ORDER BY id DESC")->fetchAll();
$hosts = $pdo->query("SELECT * FROM hosts ORDER BY id DESC")->fetchAll();

if (isset($_GET['msg'])) {
    $message = "<div class='alert alert-success'>" . htmlspecialchars($_GET['msg']) . "</div>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Account Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container-fluid px-4 mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Server Account Management Panel</h2>
        <div class="d-flex align-items-center gap-3">
            <a href="#users" class="btn btn-link btn-sm">Users</a>
            <a href="#hosts" class="btn btn-link btn-sm">Hosts</a>
            <a href="dump.php" class="btn btn-outline-primary btn-sm">Dump users &amp; hosts</a>
            <a href="import.php" class="btn btn-outline-success btn-sm">Import dump</a>
            <span class="text-muted small">Signed in as <strong><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></strong></span>
            <a href="logout.php" class="btn btn-outline-secondary btn-sm">Log out</a>
        </div>
    </div>

    <?php echo $message; ?>

    <!-- USERS PANE -->
    <section id="users" class="mb-5">
        <h3 class="mb-3">User Profiles</h3>
        <div class="row">
            <div class="col-xl-4 col-lg-5 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><?php echo $update_mode ? "Modify Target Profile" : "Register Profile"; ?></h5>
                    </div>
                    <div class="card-body">
                        <form action="index.php" method="POST">
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
                            <input type="hidden" name="update_mode" value="<?php echo $update_mode ? '1' : '0'; ?>">

                            <div class="mb-3">
                                <label class="form-label font-monospace fw-bold">Name (Username)</label>
                                <input type="text" name="name" id="name" class="form-control" value="<?php echo htmlspecialchars($name); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label font-monospace fw-bold">UID (User ID)</label>
                                <input type="number" name="uid" class="form-control" value="<?php echo htmlspecialchars((string)$uid); ?>" required>
                                <?php if (!$update_mode): ?>
                                    <div class="form-text">Next available UID (auto-filled)</div>
                                <?php endif; ?>
                            </div>
                            <div class="mb-3">
                                <label class="form-label font-monospace fw-bold">GID (Group ID)</label>
                                <input type="number" name="gid" class="form-control" value="<?php echo htmlspecialchars((string)$gid); ?>" required>
                                <?php if (!$update_mode): ?>
                                    <div class="form-text">Next available GID (auto-filled)</div>
                                <?php endif; ?>
                            </div>
                            <div class="mb-3">
                                <label class="form-label font-monospace fw-bold">Email</label>
                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label font-monospace fw-bold">Shell Executable</label>
                                <input type="text" name="shell" class="form-control" value="<?php echo htmlspecialchars($shell); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label font-monospace fw-bold">Home Directory Absolute Path</label>
                                <input type="text" name="home" id="home" class="form-control" list="home-suggestions"
                                       value="<?php echo htmlspecialchars($home); ?>"
                                       placeholder="/share/home/username">
                                <datalist id="home-suggestions">
                                    <?php
                                    $home_paths = [];
                                    foreach ($users as $u) {
                                        if (!empty($u['home'])) {
                                            $home_paths[$u['home']] = true;
                                        }
                                    }
                                    foreach (array_keys($home_paths) as $path):
                                    ?>
                                        <option value="<?php echo htmlspecialchars($path); ?>">
                                    <?php endforeach; ?>
                                </datalist>
                                <div class="form-text">Auto-filled as <code>/share/home/&lt;name&gt;</code> while typing the username</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label font-monospace fw-bold">Access Host Rule</label>
                                <?php
                                // Normalize legacy values for the select
                                $access_select = $access_host;
                                if ($access_select === '%' || $access_select === '') {
                                    $access_select = 'all';
                                }
                                $host_fqdns = array_column($hosts, 'fqdn');
                                $host_groupnames = [];
                                foreach ($hosts as $h) {
                                    $host_groupnames[$h['groupname']] = $h['fqdn'];
                                }
                                if (isset($host_groupnames[$access_select])) {
                                    $access_select = $host_groupnames[$access_select];
                                }
                                ?>
                                <select name="access_host" class="form-select font-monospace" required>
                                    <option value="none" <?php echo $access_select === 'none' ? 'selected' : ''; ?>>none</option>
                                    <option value="all" <?php echo $access_select === 'all' ? 'selected' : ''; ?>>all</option>
                                    <?php foreach ($hosts as $h): ?>
                                        <option value="<?php echo htmlspecialchars($h['fqdn']); ?>"
                                            <?php echo $access_select === $h['fqdn'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($h['fqdn']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <?php if ($access_select !== 'none' && $access_select !== 'all' && !in_array($access_select, $host_fqdns, true)): ?>
                                        <option value="<?php echo htmlspecialchars($access_select); ?>" selected>
                                            <?php echo htmlspecialchars($access_select); ?> (current)
                                        </option>
                                    <?php endif; ?>
                                </select>
                                <div class="form-text">none, all, or a host FQDN from the hosts table</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label font-monospace fw-bold">SSH Public Key</label>
                                <textarea name="public_key" class="form-control font-monospace" rows="4"><?php echo htmlspecialchars($public_key); ?></textarea>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" name="save" class="btn btn-success">
                                    <?php echo $update_mode ? "Update Profile" : "Save Profile"; ?>
                                </button>
                                <?php if ($update_mode): ?>
                                    <a href="index.php" class="btn btn-secondary">Cancel Edit</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-xl-8 col-lg-7">
                <div class="card shadow-sm">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">Active Profiles Matrix</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle mb-0 small">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>UID</th>
                                        <th>GID</th>
                                        <th>Email</th>
                                        <th>Shell</th>
                                        <th>Home</th>
                                        <th>Access Host</th>
                                        <th>Public Key</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($users) > 0): ?>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($user['id']); ?></td>
                                                <td class="fw-bold font-monospace"><?php echo htmlspecialchars($user['name']); ?></td>
                                                <td class="font-monospace"><?php echo htmlspecialchars($user['uid']); ?></td>
                                                <td class="font-monospace"><?php echo htmlspecialchars($user['gid']); ?></td>
                                                <td class="font-monospace"><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td class="font-monospace"><?php echo htmlspecialchars($user['shell']); ?></td>
                                                <td class="font-monospace"><?php echo htmlspecialchars($user['home']); ?></td>
                                                <td class="font-monospace"><?php echo htmlspecialchars($user['access_host']); ?></td>
                                                <td>
                                                    <?php if (!empty($user['public_key'])): ?>
                                                        <span class="badge bg-secondary font-monospace" title="<?php echo htmlspecialchars($user['public_key']); ?>">Configured</span>
                                                    <?php else: ?>
                                                        <span class="text-muted">None</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="index.php?edit=<?php echo $user['id']; ?>" class="btn btn-info text-white">Edit</a>
                                                        <a href="index.php?delete=<?php echo $user['id']; ?>" class="btn btn-danger" onclick="return confirm('Delete this profile?');">Delete</a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="10" class="text-center text-muted py-4">No profiles found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- HOSTS PANE -->
    <section id="hosts" class="mb-5">
        <h3 class="mb-3">Host Groups</h3>
        <div class="row">
            <div class="col-xl-4 col-lg-5 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><?php echo $host_update_mode ? "Modify Host Group" : "Declare Host"; ?></h5>
                    </div>
                    <div class="card-body">
                        <form action="index.php#hosts" method="POST">
                            <input type="hidden" name="host_id" value="<?php echo htmlspecialchars($host_id); ?>">
                            <input type="hidden" name="host_update_mode" value="<?php echo $host_update_mode ? '1' : '0'; ?>">

                            <div class="mb-3">
                                <label class="form-label font-monospace fw-bold">FQDN</label>
                                <input type="text" name="fqdn" id="fqdn" class="form-control"
                                       value="<?php echo htmlspecialchars($fqdn); ?>"
                                       placeholder="tom1.tsand.org" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label font-monospace fw-bold">Group Name</label>
                                <input type="text" name="groupname" id="groupname" class="form-control"
                                       value="<?php echo htmlspecialchars($groupname); ?>" required>
                                <div class="form-text">FQDN with dots converted to dashes</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label font-monospace fw-bold">GID (Group ID)</label>
                                <input type="number" name="host_gid" class="form-control"
                                       value="<?php echo htmlspecialchars((string)$host_gid); ?>" required>
                                <?php if (!$host_update_mode): ?>
                                    <div class="form-text">Starts at 6000; next available auto-filled</div>
                                <?php endif; ?>
                            </div>
                            <div class="mb-3">
                                <label class="form-label font-monospace fw-bold">Members</label>
                                <input type="text" name="members" class="form-control font-monospace"
                                       value="<?php echo htmlspecialchars($members); ?>" required>
                                <div class="form-text">Always begins with <code>ansible,sudo,wheel</code>; add more comma-separated</div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" name="save_host" class="btn btn-success">
                                    <?php echo $host_update_mode ? "Update Host" : "Save Host"; ?>
                                </button>
                                <?php if ($host_update_mode): ?>
                                    <a href="index.php#hosts" class="btn btn-secondary">Cancel Edit</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-xl-8 col-lg-7">
                <div class="card shadow-sm">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">Declared Hosts</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle mb-0 small">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>FQDN</th>
                                        <th>Group Name</th>
                                        <th>GID</th>
                                        <th>Members</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($hosts) > 0): ?>
                                        <?php foreach ($hosts as $host): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($host['id']); ?></td>
                                                <td class="fw-bold font-monospace"><?php echo htmlspecialchars($host['fqdn']); ?></td>
                                                <td class="font-monospace"><?php echo htmlspecialchars($host['groupname']); ?></td>
                                                <td class="font-monospace"><?php echo htmlspecialchars($host['gid']); ?></td>
                                                <td class="font-monospace"><?php echo htmlspecialchars($host['members']); ?></td>
                                                <td class="text-center">
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="index.php?edit_host=<?php echo $host['id']; ?>#hosts" class="btn btn-info text-white">Edit</a>
                                                        <a href="index.php?delete_host=<?php echo $host['id']; ?>" class="btn btn-danger" onclick="return confirm('Delete this host?');">Delete</a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">No hosts declared.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
(function () {
    const HOME_PREFIX = '/share/home/';
    const nameInput = document.getElementById('name');
    const homeInput = document.getElementById('home');
    if (nameInput && homeInput) {
        let homeManual = false;
        const initialName = nameInput.value.trim();
        const expectedHome = initialName ? HOME_PREFIX + initialName : '';
        if (homeInput.value && homeInput.value !== expectedHome) {
            homeManual = true;
        }

        homeInput.addEventListener('input', function () {
            const n = nameInput.value.trim();
            const auto = n ? HOME_PREFIX + n : '';
            homeManual = homeInput.value !== auto;
        });

        nameInput.addEventListener('input', function () {
            if (homeManual) return;
            const n = nameInput.value.trim();
            homeInput.value = n ? HOME_PREFIX + n : '';
        });

        if (!homeManual && !homeInput.value && nameInput.value.trim()) {
            homeInput.value = HOME_PREFIX + nameInput.value.trim();
        }
    }

    const fqdnInput = document.getElementById('fqdn');
    const groupInput = document.getElementById('groupname');
    if (fqdnInput && groupInput) {
        let groupManual = false;
        const initialFqdn = fqdnInput.value.trim().toLowerCase();
        const expectedGroup = initialFqdn.replace(/\./g, '-');
        if (groupInput.value && groupInput.value !== expectedGroup) {
            groupManual = true;
        }

        groupInput.addEventListener('input', function () {
            const auto = fqdnInput.value.trim().toLowerCase().replace(/\./g, '-');
            groupManual = groupInput.value !== auto;
        });

        fqdnInput.addEventListener('input', function () {
            if (groupManual) return;
            groupInput.value = fqdnInput.value.trim().toLowerCase().replace(/\./g, '-');
        });

        if (!groupManual && !groupInput.value && fqdnInput.value.trim()) {
            groupInput.value = fqdnInput.value.trim().toLowerCase().replace(/\./g, '-');
        }
    }
})();
</script>

</body>
</html>
