<?php
ob_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/permissions_helper.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Only ADMIN can use this API
if ($_SESSION['role'] !== 'ADMIN') {
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit();
}

$conn   = getDBConnection();
$action = $_GET['action'] ?? '';

// POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data   = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
}

try {
    switch ($action) {
        case 'get_users':           getUsers($conn);                    break;
        case 'get_locations':       getLocations($conn);                break;
        case 'add_location':        addLocation($conn, $data);          break;
        case 'edit_location':       editLocation($conn, $data);         break;
        case 'toggle_location':     toggleLocation($conn, $data);       break;
        case 'delete_location':     deleteLocation($conn, $data);       break;
        case 'add_user':        addUser($conn, $data);              break;
        case 'edit_user':       editUser($conn, $data);             break;
        case 'delete_user':     deleteUser($conn, $data);           break;
        case 'toggle_status':   toggleStatus($conn, $data);         break;
        case 'verify_password': verifyAdminPassword($conn, $data);  break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

// =============================================================================
// VERIFY ADMIN PASSWORD — called before every sensitive action
// =============================================================================
function verifyAdminPassword($conn, $data) {
    $adminId  = (int) $_SESSION['user_id'];
    $password = $data['password'] ?? '';

    if (empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Password is required.']);
        return;
    }

    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ? AND role = 'ADMIN'");
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row || !password_verify($password, $row['password'])) {
        echo json_encode(['success' => false, 'message' => 'Incorrect password.']);
        return;
    }

    echo json_encode(['success' => true]);
}

// =============================================================================
// GET USERS
// =============================================================================
function getUsers($conn) {
    $sql = "
        SELECT u.id, u.name, u.username, u.email, u.role, u.status,
               u.profile_pic, u.created_at, u.warehouse_location,
               GROUP_CONCAT(up.permission ORDER BY up.permission SEPARATOR ',') AS permissions
        FROM users u
        LEFT JOIN user_permissions up ON up.user_id = u.id
        GROUP BY u.id
        ORDER BY u.created_at DESC
    ";
    $result = $conn->query($sql);
    $users  = [];
    while ($row = $result->fetch_assoc()) {
        $row['permissions'] = $row['permissions'] ? explode(',', $row['permissions']) : [];
        $users[] = $row;
    }

    // Stats
    $total    = count($users);
    $admin    = count(array_filter($users, fn($u) => $u['role'] === 'ADMIN'));
    $active   = count(array_filter($users, fn($u) => $u['status'] === 'ACTIVE'));
    $inactive = count(array_filter($users, fn($u) => $u['status'] === 'INACTIVE'));

    echo json_encode([
        'success' => true,
        'data'    => $users,
        'stats'   => compact('total', 'admin', 'active', 'inactive'),
    ]);
}

// =============================================================================
// GET LOCATIONS
// =============================================================================
function getLocations($conn) {
    $result = $conn->query("SELECT name FROM locations WHERE is_active = 1 ORDER BY name ASC");
    $locs   = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) $locs[] = $row['name'];
    }
    echo json_encode(['success' => true, 'data' => $locs]);
}

// =============================================================================
// ADD USER
// =============================================================================
function addUser($conn, $data) {
    $name      = trim($data['name']              ?? '');
    $username  = trim($data['username']          ?? '');
    $email     = trim($data['email']             ?? '');
    $role      = trim($data['role']              ?? 'EMPLOYEE');
    $status    = trim($data['status']            ?? 'ACTIVE');
    $password  = $data['password']               ?? '';
    $perms     = $data['permissions']            ?? [];
    $whloc     = trim($data['warehouse_location'] ?? '');

    if (!$name || !$username || !$email || !$password) {
        echo json_encode(['success' => false, 'message' => 'All fields including password are required.']);
        return;
    }
    if (strlen($password) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
        return;
    }
    $validRoles = ['ADMIN', 'EMPLOYEE', 'WAREHOUSE'];
    if (!in_array($role, $validRoles)) {
        echo json_encode(['success' => false, 'message' => 'Invalid role.']);
        return;
    }
    if ($role === 'WAREHOUSE' && !$whloc) {
        echo json_encode(['success' => false, 'message' => 'Warehouse location is required for Warehouse users.']);
        return;
    }

    // Check duplicates
    $chk = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
    $chk->bind_param("ss", $username, $email);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Username or email already exists.']);
        return;
    }

    $hash  = password_hash($password, PASSWORD_DEFAULT);
    $wlVal = $role === 'WAREHOUSE' ? $whloc : null;
    $ins   = $conn->prepare("INSERT INTO users (name, username, email, password, role, status, warehouse_location, created_at) VALUES (?,?,?,?,?,?,?,NOW())");
    $ins->bind_param("sssssss", $name, $username, $email, $hash, $role, $status, $wlVal);
    $ins->execute();
    $newId = $conn->insert_id;

    // Save permissions (employee only — admin and warehouse don't use permission table)
    if ($role === 'EMPLOYEE') {
        savePermissions($conn, $newId, $perms);
    }

    // Log
    $actor = $_SESSION['name'] ?? 'Admin';
    $log   = $conn->prepare("INSERT INTO activity_logs (user_name, action, description) VALUES (?, 'ADD_USER', ?)");
    $desc  = "User #{$newId} '{$name}' ({$role}) created by {$actor}";
    $log->bind_param("ss", $actor, $desc);
    $log->execute();

    echo json_encode(['success' => true, 'message' => "User '{$name}' added successfully."]);
}

// =============================================================================
// EDIT USER
// =============================================================================
function editUser($conn, $data) {
    $id       = (int)($data['id']              ?? 0);
    $name     = trim($data['name']             ?? '');
    $username = trim($data['username']         ?? '');
    $email    = trim($data['email']            ?? '');
    $role     = trim($data['role']             ?? 'EMPLOYEE');
    $status   = trim($data['status']           ?? 'ACTIVE');
    $password = $data['password']              ?? '';
    $perms    = $data['permissions']           ?? [];
    $whloc    = trim($data['warehouse_location'] ?? '');

    if (!$id || !$name || !$username || !$email) {
        echo json_encode(['success' => false, 'message' => 'Required fields missing.']);
        return;
    }
    $validRoles = ['ADMIN', 'EMPLOYEE', 'WAREHOUSE'];
    if (!in_array($role, $validRoles)) {
        echo json_encode(['success' => false, 'message' => 'Invalid role.']);
        return;
    }
    if ($role === 'WAREHOUSE' && !$whloc) {
        echo json_encode(['success' => false, 'message' => 'Warehouse location is required for Warehouse users.']);
        return;
    }

    if ($id === (int)$_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Use Account Settings to edit your own profile.']);
        return;
    }

    $chk = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ? LIMIT 1");
    $chk->bind_param("ssi", $username, $email, $id);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Username or email already used by another account.']);
        return;
    }

    $wlVal = $role === 'WAREHOUSE' ? $whloc : null;

    if (!empty($password)) {
        if (strlen($password) < 8) {
            echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters.']);
            return;
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $upd  = $conn->prepare("UPDATE users SET name=?, username=?, email=?, role=?, status=?, password=?, warehouse_location=? WHERE id=?");
        $upd->bind_param("sssssssi", $name, $username, $email, $role, $status, $hash, $wlVal, $id);
    } else {
        $upd = $conn->prepare("UPDATE users SET name=?, username=?, email=?, role=?, status=?, warehouse_location=? WHERE id=?");
        $upd->bind_param("ssssssi", $name, $username, $email, $role, $status, $wlVal, $id);
    }
    $upd->execute();

    // Warehouse role: clear permissions (they use location-based access instead)
    if ($role === 'ADMIN' || $role === 'WAREHOUSE') {
        $del = $conn->prepare("DELETE FROM user_permissions WHERE user_id = ?");
        $del->bind_param("i", $id);
        $del->execute();
    } else {
        savePermissions($conn, $id, $perms);
    }

    $actor = $_SESSION['name'] ?? 'Admin';
    $log   = $conn->prepare("INSERT INTO activity_logs (user_name, action, description) VALUES (?, 'EDIT_USER', ?)");
    $desc  = "User #{$id} '{$name}' updated by {$actor}";
    $log->bind_param("ss", $actor, $desc);
    $log->execute();

    echo json_encode(['success' => true, 'message' => "User '{$name}' updated successfully."]);
}

// =============================================================================
// DELETE USER
// =============================================================================
function deleteUser($conn, $data) {
    $id = (int)($data['id'] ?? 0);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Invalid user ID.']);
        return;
    }
    if ($id === (int)$_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'You cannot delete your own account.']);
        return;
    }

    $get = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $get->bind_param("i", $id); $get->execute();
    $row = $get->get_result()->fetch_assoc();
    if (!$row) { echo json_encode(['success' => false, 'message' => 'User not found.']); return; }

    // Delete permissions first (FK)
    $dp = $conn->prepare("DELETE FROM user_permissions WHERE user_id = ?");
    $dp->bind_param("i", $id); $dp->execute();

    $del = $conn->prepare("DELETE FROM users WHERE id = ?");
    $del->bind_param("i", $id); $del->execute();

    $actor = $_SESSION['name'] ?? 'Admin';
    $log   = $conn->prepare("INSERT INTO activity_logs (user_name, action, description) VALUES (?, 'DELETE_USER', ?)");
    $desc  = "User #{$id} '{$row['name']}' deleted by {$actor}";
    $log->bind_param("ss", $actor, $desc);
    $log->execute();

    echo json_encode(['success' => true, 'message' => "User '{$row['name']}' deleted."]);
}

// =============================================================================
// TOGGLE STATUS (Block / Unblock)
// =============================================================================
function toggleStatus($conn, $data) {
    $id     = (int)($data['id']     ?? 0);
    $status = trim($data['status']  ?? '');

    if (!$id || !in_array($status, ['ACTIVE', 'INACTIVE'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
        return;
    }
    if ($id === (int)$_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'You cannot block your own account.']);
        return;
    }

    $upd = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
    $upd->bind_param("si", $status, $id);
    $upd->execute();

    $get = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $get->bind_param("i", $id); $get->execute();
    $row = $get->get_result()->fetch_assoc();

    $actor  = $_SESSION['name'] ?? 'Admin';
    $action = $status === 'INACTIVE' ? 'BLOCK_USER' : 'UNBLOCK_USER';
    $log    = $conn->prepare("INSERT INTO activity_logs (user_name, action, description) VALUES (?, ?, ?)");
    $desc   = "User #{$id} '{$row['name']}' set to {$status} by {$actor}";
    $log->bind_param("sss", $actor, $action, $desc);
    $log->execute();

    $word = $status === 'ACTIVE' ? 'unblocked' : 'blocked';
    echo json_encode(['success' => true, 'message' => "User '{$row['name']}' has been {$word}."]);
}


function addLocation($conn, $data) {
    $name = trim($data['name'] ?? '');
    if (!$name) { echo json_encode(['success' => false, 'message' => 'Location name is required.']); return; }
    if (strlen($name) > 100) { echo json_encode(['success' => false, 'message' => 'Name too long (max 100 chars).']); return; }

    $chk = $conn->prepare("SELECT id FROM locations WHERE name = ?");
    $chk->bind_param("s", $name); $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => "Location '{$name}' already exists."]);
        return;
    }

    $ins = $conn->prepare("INSERT INTO locations (name, is_active) VALUES (?, 1)");
    $ins->bind_param("s", $name); $ins->execute();

    $actor = $_SESSION['name'] ?? 'Admin';
    $log   = $conn->prepare("INSERT INTO activity_logs (user_name, action, description) VALUES (?, 'ADD_LOCATION', ?)");
    $desc  = "Added location '{$name}'";
    $log->bind_param("ss", $actor, $desc); $log->execute();

    echo json_encode(['success' => true, 'message' => "Location '{$name}' added.", 'id' => $conn->insert_id]);
}

function editLocation($conn, $data) {
    $id   = intval($data['id']   ?? 0);
    $name = trim($data['name']   ?? '');
    if (!$id || !$name) { echo json_encode(['success' => false, 'message' => 'ID and name required.']); return; }

    $chk = $conn->prepare("SELECT id FROM locations WHERE name = ? AND id != ?");
    $chk->bind_param("si", $name, $id); $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => "Another location named '{$name}' already exists."]);
        return;
    }

    // Get old name for log
    $old = $conn->prepare("SELECT name FROM locations WHERE id = ?");
    $old->bind_param("i", $id); $old->execute();
    $oldRow  = $old->get_result()->fetch_assoc();
    $oldName = $oldRow['name'] ?? '?';

    $upd = $conn->prepare("UPDATE locations SET name = ? WHERE id = ?");
    $upd->bind_param("si", $name, $id); $upd->execute();

    $actor = $_SESSION['name'] ?? 'Admin';
    $log   = $conn->prepare("INSERT INTO activity_logs (user_name, action, description) VALUES (?, 'EDIT_LOCATION', ?)");
    $desc  = "Renamed location '{$oldName}' → '{$name}'";
    $log->bind_param("ss", $actor, $desc); $log->execute();

    echo json_encode(['success' => true, 'message' => "Location updated to '{$name}'."]);
}

function toggleLocation($conn, $data) {
    $id     = intval($data['id']     ?? 0);
    $active = intval($data['active'] ?? 1); // 1 = activate, 0 = deactivate
    if (!$id) { echo json_encode(['success' => false, 'message' => 'ID required.']); return; }

    $upd = $conn->prepare("UPDATE locations SET is_active = ? WHERE id = ?");
    $upd->bind_param("ii", $active, $id); $upd->execute();

    $get = $conn->prepare("SELECT name FROM locations WHERE id = ?");
    $get->bind_param("i", $id); $get->execute();
    $row = $get->get_result()->fetch_assoc();

    $actor  = $_SESSION['name'] ?? 'Admin';
    $action = $active ? 'ACTIVATE_LOCATION' : 'DEACTIVATE_LOCATION';
    $word   = $active ? 'activated' : 'deactivated';
    $log    = $conn->prepare("INSERT INTO activity_logs (user_name, action, description) VALUES (?, ?, ?)");
    $desc   = "Location '{$row['name']}' {$word}";
    $log->bind_param("sss", $actor, $action, $desc); $log->execute();

    echo json_encode(['success' => true, 'message' => "Location '{$row['name']}' {$word}."]);
}

function deleteLocation($conn, $data) {
    $id = intval($data['id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'message' => 'ID required.']); return; }

    // Check if any WAREHOUSE user is assigned to this location
    $chk = $conn->prepare("SELECT COUNT(*) as cnt FROM users WHERE warehouse_location = (SELECT name FROM locations WHERE id = ?) AND role = 'WAREHOUSE'");
    $chk->bind_param("i", $id); $chk->execute();
    $cnt = $chk->get_result()->fetch_assoc()['cnt'];
    if ($cnt > 0) {
        echo json_encode(['success' => false, 'message' => "Cannot delete — {$cnt} warehouse user(s) are assigned here. Reassign them first."]);
        return;
    }

    $get = $conn->prepare("SELECT name FROM locations WHERE id = ?");
    $get->bind_param("i", $id); $get->execute();
    $row = $get->get_result()->fetch_assoc();
    if (!$row) { echo json_encode(['success' => false, 'message' => 'Location not found.']); return; }

    $del = $conn->prepare("DELETE FROM locations WHERE id = ?");
    $del->bind_param("i", $id); $del->execute();

    $actor = $_SESSION['name'] ?? 'Admin';
    $log   = $conn->prepare("INSERT INTO activity_logs (user_name, action, description) VALUES (?, 'DELETE_LOCATION', ?)");
    $desc  = "Deleted location '{$row['name']}'";
    $log->bind_param("ss", $actor, $desc); $log->execute();

    echo json_encode(['success' => true, 'message' => "Location '{$row['name']}' deleted."]);
}

ob_end_flush();