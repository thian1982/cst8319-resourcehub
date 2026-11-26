<?php
declare(strict_types=1);
require __DIR__ . '/_auth.php';
$pageTitle = 'ResourceHub | Manage Users';
$pdo = rh_pdo();
$error = '';
$success = isset($_GET['saved']) ? 'User account saved successfully.' : '';
$old = [];
$action = '';
function user_field($key): string { $v = $_POST[$key] ?? ''; return is_string($v) ? trim($v) : ''; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = user_field('action');
    $old = ['full_name'=>user_field('full_name'), 'email'=>user_field('email'), 'role'=>user_field('role'), 'account_status'=>user_field('account_status'), 'user_id'=>user_field('user_id')];
    $password = user_field('password');
    try {
        if (!rh_csrf_valid($_POST['csrf_token'] ?? null)) { throw new RuntimeException('Your session token expired. Refresh the page and try again.'); }
        if (!in_array($action, ['create','update'], true)) { throw new RuntimeException('Invalid operation.'); }
        if (mb_strlen($old['full_name']) < 2 || mb_strlen($old['full_name']) > 120) { throw new RuntimeException('Name must contain 2 to 120 characters.'); }
        if (strlen($old['email']) > 254 || !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) { throw new RuntimeException('Enter a valid email address.'); }
        if (!in_array($old['role'], ['user','admin'], true) || !in_array($old['account_status'], ['active','inactive'], true)) { throw new RuntimeException('Invalid role or account status.'); }
        if ($action === 'create') {
            if (strlen($password) < 12 || strlen($password) > 72) { throw new RuntimeException('New account password must be 12 to 72 characters.'); }
            $stmt = $pdo->prepare('INSERT INTO users (full_name,email,password_hash,role,account_status) VALUES (:name,:email,:hash,:role,:status)');
            $stmt->execute(['name'=>$old['full_name'],'email'=>$old['email'],'hash'=>password_hash($password, PASSWORD_DEFAULT),'role'=>$old['role'],'status'=>$old['account_status']]);
        } else {
            $id = filter_var($old['user_id'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
            if ($id === false) { throw new RuntimeException('Invalid user account.'); }
            if ($password !== '') { throw new RuntimeException('Password changes are not supported on this screen. Leave password blank.'); }
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT user_id,role,account_status FROM users WHERE user_id = :id FOR UPDATE');
            $stmt->execute(['id'=>$id]);
            $target = $stmt->fetch();
            if (!$target) { throw new RuntimeException('The selected account no longer exists.'); }
            if ((int)$id === (int)$adminUser['user_id'] && ($old['role'] !== 'admin' || $old['account_status'] !== 'active')) { throw new RuntimeException('You cannot remove your own administrator access or deactivate your own account.'); }
            if ($target['role'] === 'admin' && $target['account_status'] === 'active' && ($old['role'] !== 'admin' || $old['account_status'] !== 'active')) {
                $count = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND account_status = 'active'")->fetchColumn();
                if ($count <= 1) { throw new RuntimeException('At least one active administrator must remain.'); }
            }
            $stmt = $pdo->prepare('UPDATE users SET full_name=:name,email=:email,role=:role,account_status=:status WHERE user_id=:id');
            $stmt->execute(['name'=>$old['full_name'],'email'=>$old['email'],'role'=>$old['role'],'status'=>$old['account_status'],'id'=>$id]);
            $pdo->commit();
        }
        header('Location: /admin/admin-users.php?saved=1', true, 303);
        exit;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('ResourceHub admin user save: ' . $e->getMessage());
        $error = $e->getCode() === '23000' ? 'That email address is already registered or the account conflicts with existing data.' : 'Unable to save the account. Please try again.';
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $error = $e->getMessage();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('ResourceHub admin user unexpected error: ' . $e->getMessage());
        $error = 'Unable to save the account. Please try again.';
    }
}
try {
    $users = $pdo->query('SELECT user_id, full_name, email, role, account_status, created_at FROM users ORDER BY user_id ASC')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('ResourceHub admin users listing: ' . $e->getMessage());
    http_response_code(503);
    exit('User directory is temporarily unavailable.');
}
$active = count(array_filter($users, static fn($u) => $u['account_status'] === 'active'));
$admins = count(array_filter($users, static fn($u) => $u['role'] === 'admin'));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= rh_h($pageTitle) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="/assets/css/admin.css" rel="stylesheet">
</head>
<body class="admin-page">
<div class="admin-layout">
<?php $activeAdminPage = 'users'; require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
<main class="admin-main">
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-2"><div><p class="text-secondary mb-1">Administration / Manage Users</p><h1 class="h2 fw-bold mb-0">Manage Users</h1></div><button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#userModal" id="addUser"><i class="bi bi-person-plus me-2"></i>Add User</button></div>
<p class="text-secondary mb-3">View and manage ResourceHub accounts.</p>
<?php if ($success): ?><div class="alert alert-success" role="status"><?= rh_h($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= rh_h($error) ?></div><?php endif; ?>
<div class="row g-3 mb-4"><div class="col-sm-4"><div class="stat"><div class="text-secondary small">Total Users</div><div class="number"><?= count($users) ?></div></div></div><div class="col-sm-4"><div class="stat"><div class="text-secondary small">Active Accounts</div><div class="number"><?= $active ?></div></div></div><div class="col-sm-4"><div class="stat"><div class="text-secondary small">Administrators</div><div class="number"><?= $admins ?></div></div></div></div>
<section class="panel" aria-labelledby="directoryTitle"><div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><h2 id="directoryTitle" class="h5 fw-bold mb-0">User Directory</h2><span id="count" class="small text-secondary" role="status" aria-live="polite"></span></div>
<div class="row g-3 mb-4"><div class="col-lg-6"><label for="search" class="form-label">Search users</label><input id="search" class="form-control" type="search" placeholder="Name or email"></div><div class="col-sm-6 col-lg-3"><label for="role" class="form-label">Role</label><select id="role" class="form-select"><option value="">All Roles</option><option value="user">Regular User</option><option value="admin">Administrator</option></select></div><div class="col-sm-6 col-lg-3"><label for="status" class="form-label">Status</label><select id="status" class="form-select"><option value="">All Statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select></div></div>
<div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th scope="col">User</th><th scope="col">Role</th><th scope="col">Status</th><th scope="col">Joined</th><th scope="col">Actions</th></tr></thead><tbody>
<?php foreach ($users as $user): ?>
<tr class="user-row" data-search="<?= rh_h(strtolower($user['full_name'].' '.$user['email'])) ?>" data-role="<?= rh_h($user['role']) ?>" data-status="<?= rh_h($user['account_status']) ?>"><td><div class="fw-semibold"><?= rh_h($user['full_name']) ?></div><div class="small text-secondary"><?= rh_h($user['email']) ?></div></td><td><?= $user['role'] === 'admin' ? 'Administrator' : 'Regular User' ?></td><td><span class="badge <?= $user['account_status'] === 'active' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= ucfirst(rh_h($user['account_status'])) ?></span></td><td><?= rh_h(substr((string)$user['created_at'],0,10)) ?></td><td><button type="button" class="btn btn-sm btn-outline-secondary edit-user" data-bs-toggle="modal" data-bs-target="#userModal" data-id="<?= (int)$user['user_id'] ?>" data-name="<?= rh_h($user['full_name']) ?>" data-email="<?= rh_h($user['email']) ?>" data-role="<?= rh_h($user['role']) ?>" data-status="<?= rh_h($user['account_status']) ?>">Edit</button></td></tr>
<?php endforeach; ?>
</tbody></table></div><div id="empty" class="alert alert-light border d-none" role="status">No users match these filters.</div><button type="button" id="reset" class="btn btn-outline-secondary btn-sm">Reset Filters</button></section>
<p class="text-secondary small mt-4">Changes to accounts take effect immediately. Account deletion is disabled to preserve reservation history.</p>
</main></div>
<div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="post" action="/admin/admin-users.php"><div class="modal-header"><h2 class="modal-title fs-5" id="userModalTitle">Add User</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body">
<input type="hidden" name="csrf_token" value="<?= rh_h(rh_csrf()) ?>"><input type="hidden" name="action" id="formAction" value="create"><input type="hidden" name="user_id" id="formUserId" value="">
<div class="mb-3"><label for="formName" class="form-label">Full name</label><input class="form-control" id="formName" name="full_name" maxlength="120" minlength="2" required></div>
<div class="mb-3"><label for="formEmail" class="form-label">Email</label><input class="form-control" type="email" id="formEmail" name="email" maxlength="254" required></div>
<div class="row g-3 mb-3"><div class="col-sm-6"><label for="formRole" class="form-label">Role</label><select class="form-select" id="formRole" name="role"><option value="user">Regular User</option><option value="admin">Administrator</option></select></div><div class="col-sm-6"><label for="formStatus" class="form-label">Status</label><select class="form-select" id="formStatus" name="account_status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div></div>
<div id="passwordGroup"><label for="formPassword" class="form-label">Temporary password (12–72 characters)</label><input class="form-control" type="password" id="formPassword" name="password" minlength="12" maxlength="72" autocomplete="new-password"><div class="form-text">Provide the password to the user securely. Password changes for existing users are not supported here.</div></div>
</div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary" id="saveButton">Create User</button></div></form></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const search=document.getElementById('search'),role=document.getElementById('role'),status=document.getElementById('status'),rows=[...document.querySelectorAll('.user-row')];
function filterUsers(){const q=search.value.trim().toLowerCase();let visible=0;rows.forEach(row=>{const show=row.dataset.search.includes(q)&&(!role.value||row.dataset.role===role.value)&&(!status.value||row.dataset.status===status.value);row.classList.toggle('d-none',!show);if(show)visible++;});document.getElementById('count').textContent=visible+' of '+rows.length+' users';document.getElementById('empty').classList.toggle('d-none',visible!==0)}
search.addEventListener('input',filterUsers);role.addEventListener('change',filterUsers);status.addEventListener('change',filterUsers);document.getElementById('reset').addEventListener('click',()=>{search.value='';role.value='';status.value='';filterUsers()});filterUsers();
const modal=document.getElementById('userModal');
function setForm(data){document.getElementById('formAction').value=data.action;document.getElementById('formUserId').value=data.id||'';document.getElementById('formName').value=data.name||'';document.getElementById('formEmail').value=data.email||'';document.getElementById('formRole').value=data.role||'user';document.getElementById('formStatus').value=data.status||'active';document.getElementById('formPassword').value='';document.getElementById('formPassword').required=data.action==='create';document.getElementById('passwordGroup').classList.toggle('d-none',data.action!=='create');document.getElementById('userModalTitle').textContent=data.action==='create'?'Add User':'Edit User';document.getElementById('saveButton').textContent=data.action==='create'?'Create User':'Save Changes'}
document.getElementById('addUser').addEventListener('click',()=>setForm({action:'create'}));
document.querySelectorAll('.edit-user').forEach(button=>button.addEventListener('click',()=>setForm({action:'update',id:button.dataset.id,name:button.dataset.name,email:button.dataset.email,role:button.dataset.role,status:button.dataset.status})));
<?php if ($error && in_array($action,['create','update'],true)): ?>
setForm(<?= json_encode(['action'=>$action,'id'=>$old['user_id'] ?? '', 'name'=>$old['full_name'] ?? '', 'email'=>$old['email'] ?? '', 'role'=>$old['role'] ?? 'user', 'status'=>$old['account_status'] ?? 'active'], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>);bootstrap.Modal.getOrCreateInstance(modal).show();
<?php endif; ?>
</script>
</body></html>
