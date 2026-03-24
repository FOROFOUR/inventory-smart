<?php
/**
 * permissions_helper.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Include this file on every protected page AFTER session_start() and
 * getDBConnection().
 *
 * Usage:
 *   require_once __DIR__ . '/permissions_helper.php';
 *   requirePermission('inventory_view');   // redirects if no access
 *
 * Available permission keys:
 *   dashboard        inventory_view     asset_transfer
 *   upload_assets    qr_management      reports
 *   account_settings user_management    (admin-only, not assignable to employee)
 *   preparing        receiving          completed
 *   orders           (admin-only by default, assignable via user management)
 */

// ── All defined permissions ────────────────────────────────────────────────────
define('ALL_PERMISSIONS', [
    'dashboard'        => 'Dashboard',
    'inventory_view'   => 'Inventory (View Only)',
    'asset_transfer'   => 'Asset Transfer / Pull-out',
    'upload_assets'    => 'Upload Assets',
    'qr_management'    => 'QR Management',
    'reports'          => 'Reports',
    'account_settings' => 'Account Settings',
    'orders'           => 'Orders',
    'preparing'        => 'Preparing',
    'receiving'        => 'Receiving',
    'completed'        => 'Completed',
]);

// ── Default permissions given to new EMPLOYEE accounts ───────────────────────
// NOTE: orders/preparing/receiving/completed are NOT included by default.
//       Admin must explicitly grant them per user.
define('DEFAULT_EMPLOYEE_PERMISSIONS', [
    'dashboard',
    'inventory_view',
    'asset_transfer',
    'upload_assets',
    'qr_management',
    'reports',
    'account_settings',
]);

/**
 * Load permissions for a user into $_SESSION['permissions']
 * Admin always gets everything. Called once per session or after permission change.
 */
function loadPermissions(mysqli $conn, int $userId, string $role): void
{
    if ($role === 'ADMIN') {
        // Admin has all permissions including user_management and orders
        $_SESSION['permissions'] = array_merge(
            array_keys(ALL_PERMISSIONS),
            ['user_management']
        );
        return;
    }

    $stmt = $conn->prepare(
        "SELECT permission FROM user_permissions WHERE user_id = ?"
    );
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $perms = [];
    while ($row = $result->fetch_assoc()) {
        $perms[] = $row['permission'];
    }

    // Always allow account_settings regardless
    if (!in_array('account_settings', $perms)) {
        $perms[] = 'account_settings';
    }

    $_SESSION['permissions'] = $perms;
}

/**
 * Check if the current session user has a given permission.
 */
function hasPermission(string $permission): bool
{
    if (!isset($_SESSION['permissions'])) return false;
    return in_array($permission, $_SESSION['permissions'], true);
}

/**
 * Redirect to dashboard with an error if user lacks permission.
 * Call at the top of every protected page.
 */
function requirePermission(string $permission): void
{
    if (!hasPermission($permission)) {
        header("Location: dashboard.php?error=access_denied");
        exit();
    }
}

/**
 * Get all permissions for a specific user (for the edit modal).
 */
function getUserPermissions(mysqli $conn, int $userId, string $role): array
{
    if ($role === 'ADMIN') {
        return array_merge(array_keys(ALL_PERMISSIONS), ['user_management']);
    }

    $stmt = $conn->prepare(
        "SELECT permission FROM user_permissions WHERE user_id = ?"
    );
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $perms = [];
    while ($row = $result->fetch_assoc()) {
        $perms[] = $row['permission'];
    }
    return $perms;
}

/**
 * Save permissions for a user (replaces existing).
 * Admin permissions are not stored in DB — they are implicit.
 */
function savePermissions(mysqli $conn, int $userId, array $permissions): void
{
    // Delete existing
    $del = $conn->prepare("DELETE FROM user_permissions WHERE user_id = ?");
    $del->bind_param("i", $userId);
    $del->execute();

    if (empty($permissions)) return;

    $validKeys = array_keys(ALL_PERMISSIONS);
    $ins = $conn->prepare(
        "INSERT IGNORE INTO user_permissions (user_id, permission) VALUES (?, ?)"
    );
    foreach ($permissions as $perm) {
        if (in_array($perm, $validKeys, true)) {
            $ins->bind_param("is", $userId, $perm);
            $ins->execute();
        }
    }

    // Always ensure account_settings
    $ac = 'account_settings';
    $ins->bind_param("is", $userId, $ac);
    $ins->execute();
}