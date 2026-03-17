<?php
ob_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/permissions_helper.php';

if (session_status() === PHP_SESSION_NONE) session_start();

include 'sidebar.php';

$conn = getDBConnection();

if (!isset($_SESSION['user_id'])) { header("Location: landing.php"); exit(); }

if (!isset($_SESSION['permissions'])) {
    $rs = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $rs->bind_param("i", $_SESSION['user_id']); $rs->execute();
    $rr = $rs->get_result()->fetch_assoc();
    loadPermissions($conn, $_SESSION['user_id'], $rr['role'] ?? 'EMPLOYEE');
}
requirePermission('user_management');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Management — IBIS</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<link rel="stylesheet" href="css/style.css">
<style>
*{box-sizing:border-box;}
body{font-family:'Poppins',sans-serif;background:#f4f6fb;margin:0;padding:0;overflow-x:hidden;}

.content{margin-left:88px;padding:32px 36px;transition:margin-left .3s ease;min-height:100vh;}
.sidebar:not(.close)~.content{margin-left:260px;}

.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;padding-bottom:22px;border-bottom:1.5px solid #e8ecf3;}
.page-header-left{display:flex;align-items:center;gap:14px;}
.page-header-icon{width:48px;height:48px;border-radius:14px;background:linear-gradient(135deg,#4f8ef7,#2563eb);display:flex;align-items:center;justify-content:center;font-size:1.35rem;color:#fff;box-shadow:0 4px 14px rgba(79,142,247,.35);}
.page-header h1{font-size:1.45rem;font-weight:700;color:#0f172a;margin:0 0 3px;}
.page-header p{font-size:.82rem;color:#94a3b8;margin:0;}
.breadcrumb{font-size:.78rem;color:#94a3b8;display:flex;align-items:center;gap:6px;}
.breadcrumb span{color:#0f172a;font-weight:500;}

.summary-row{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-bottom:26px;}
.summary-card{background:#fff;border-radius:16px;padding:20px 22px;border:1px solid #e8ecf3;box-shadow:0 1px 4px rgba(15,23,42,.04),0 4px 16px rgba(15,23,42,.05);display:flex;align-items:center;gap:16px;}
.sc-icon{width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;color:#fff;flex-shrink:0;}
.sc-label{font-size:.74rem;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:.4px;}
.sc-value{font-size:1.7rem;font-weight:700;color:#0f172a;line-height:1.1;}

.main-card{background:#fff;border-radius:18px;border:1px solid #e8ecf3;box-shadow:0 1px 4px rgba(15,23,42,.04),0 4px 24px rgba(15,23,42,.06);overflow:hidden;}
.card-toolbar{padding:18px 24px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;gap:12px;flex-wrap:wrap;background:#fafbfe;}
.search-wrap{position:relative;flex:1;min-width:220px;}
.search-wrap i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:1.1rem;pointer-events:none;}
.search-wrap input{width:100%;padding:9px 12px 9px 38px;border:1.5px solid #e2e8f0;border-radius:10px;font-family:'Poppins',sans-serif;font-size:.875rem;color:#0f172a;background:#f8fafc;outline:none;transition:border-color .2s,box-shadow .2s;}
.search-wrap input:focus{border-color:#4f8ef7;box-shadow:0 0 0 3px rgba(79,142,247,.12);background:#fff;}
.filter-select{padding:9px 14px;border:1.5px solid #e2e8f0;border-radius:10px;font-family:'Poppins',sans-serif;font-size:.875rem;color:#0f172a;background:#f8fafc;outline:none;cursor:pointer;}
.filter-select:focus{border-color:#4f8ef7;}
.btn-add{padding:9px 18px;background:linear-gradient(135deg,#4f8ef7,#2563eb);color:#fff;border:none;border-radius:10px;font-family:'Poppins',sans-serif;font-size:.875rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:7px;box-shadow:0 4px 12px rgba(37,99,235,.25);transition:filter .2s,transform .2s;white-space:nowrap;}
.btn-add:hover{filter:brightness(1.07);transform:translateY(-1px);}

.table-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;}
thead{background:linear-gradient(135deg,#1e293b,#334155);}
thead th{padding:13px 16px;text-align:left;color:#fff;font-size:.78rem;font-weight:600;text-transform:uppercase;letter-spacing:.5px;white-space:nowrap;}
tbody tr{border-bottom:1px solid #f1f5f9;transition:background .15s;}
tbody tr:last-child{border-bottom:none;}
tbody tr:hover{background:#f8faff;}
tbody td{padding:13px 16px;font-size:.875rem;color:#334155;vertical-align:middle;}

.user-info{display:flex;align-items:center;gap:12px;}
.user-avatar{width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid #e8ecf3;flex-shrink:0;}
.user-name{font-weight:600;color:#0f172a;}
.user-email{font-size:.75rem;color:#94a3b8;margin-top:1px;}

.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.4px;}
.role-admin{background:#eff6ff;color:#2563eb;border:1px solid #bfdbfe;}
.role-employee{background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;}
.role-warehouse{background:#fff7ed;color:#c2410c;border:1px solid #fed7aa;}
.status-active{background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;}
.status-inactive{background:#fff1f2;color:#be123c;border:1px solid #fecdd3;}
.status-dot{width:6px;height:6px;border-radius:50%;background:currentColor;flex-shrink:0;}

.action-btns{display:flex;gap:6px;}
.btn-icon{width:32px;height:32px;border-radius:8px;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1rem;transition:all .2s;}
.btn-edit{background:#eff6ff;color:#2563eb;} .btn-edit:hover{background:#2563eb;color:#fff;}
.btn-block{background:#fff7ed;color:#c2410c;} .btn-block:hover{background:#c2410c;color:#fff;}
.btn-unblock{background:#f0fdf4;color:#16a34a;} .btn-unblock:hover{background:#16a34a;color:#fff;}
.btn-delete{background:#fff1f2;color:#be123c;} .btn-delete:hover{background:#be123c;color:#fff;}

.empty-state{text-align:center;padding:60px 20px;color:#94a3b8;}
.empty-state i{font-size:3.5rem;display:block;margin-bottom:12px;opacity:.3;}

.modal-overlay{display:none;position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:9999;align-items:center;justify-content:center;backdrop-filter:blur(3px);}
.modal-overlay.active{display:flex;}
.modal-box{background:#fff;border-radius:20px;width:100%;max-width:540px;box-shadow:0 20px 60px rgba(15,23,42,.2);animation:popIn .25s cubic-bezier(.34,1.56,.64,1);display:flex;flex-direction:column;max-height:92vh;overflow:hidden;}
@keyframes popIn{from{opacity:0;transform:scale(.92) translateY(10px);}to{opacity:1;transform:scale(1) translateY(0);}}
.modal-header{padding:20px 24px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;background:#fafbfe;flex-shrink:0;}
.modal-header h3{font-size:1rem;font-weight:700;color:#0f172a;margin:0;}
.modal-close{background:none;border:none;font-size:1.4rem;color:#94a3b8;cursor:pointer;line-height:1;transition:color .2s;}
.modal-close:hover{color:#0f172a;}
.modal-body{padding:24px;overflow-y:auto;flex:1;}
.modal-footer{padding:16px 24px;border-top:1px solid #f1f5f9;display:flex;justify-content:flex-end;gap:10px;background:#fafbfe;flex-shrink:0;}

.pw-confirm-box{max-width:400px;}
.pw-confirm-info{display:flex;align-items:center;gap:12px;padding:14px 16px;background:#fffbeb;border:1px solid #fde68a;border-radius:12px;margin-bottom:20px;}
.pw-confirm-info i{font-size:1.4rem;color:#f59e0b;flex-shrink:0;}
.pw-confirm-info p{font-size:.84rem;color:#92400e;line-height:1.5;margin:0;}
.pw-confirm-info strong{color:#78350f;}
.pw-error{display:none;font-size:.8rem;color:#be123c;margin-top:6px;display:flex;align-items:center;gap:5px;}
.pw-error.show{display:flex;}

.form-group{margin-bottom:18px;}
.form-group label{display:flex;align-items:center;gap:6px;font-size:.74rem;font-weight:700;color:#64748b;margin-bottom:7px;text-transform:uppercase;letter-spacing:.5px;}
.form-group label i{font-size:.9rem;color:#94a3b8;}
.input-wrap{position:relative;}
.input-wrap .ii{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:1rem;pointer-events:none;z-index:1;}
.input-wrap input,.input-wrap select{width:100%;padding:10px 12px 10px 38px;border:1.5px solid #e2e8f0;border-radius:10px;font-family:'Poppins',sans-serif;font-size:.875rem;color:#0f172a;background:#f8fafc;outline:none;appearance:none;transition:border-color .2s,box-shadow .2s,background .2s;}
.input-wrap input:focus,.input-wrap select:focus{border-color:#4f8ef7;box-shadow:0 0 0 3.5px rgba(79,142,247,.13);background:#fff;}
.input-wrap input::placeholder{color:#cbd5e1;}
.eye{position:absolute;right:12px;top:50%;transform:translateY(-50%);color:#94a3b8;cursor:pointer;font-size:1rem;z-index:2;}
.eye:hover{color:#4f8ef7;}
.form-row-2{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.hint{font-size:.75rem;color:#94a3b8;margin:5px 0 0;display:flex;align-items:center;gap:5px;}

.section-divider{margin:20px 0 16px;padding-bottom:10px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;}
.section-divider span{font-size:.74rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;display:flex;align-items:center;gap:7px;}
.section-divider span i{font-size:1rem;color:#94a3b8;}
.btn-toggle-all{font-size:.72rem;color:#4f8ef7;cursor:pointer;font-weight:600;background:none;border:none;font-family:'Poppins',sans-serif;padding:0;}
.btn-toggle-all:hover{text-decoration:underline;}
.perm-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;}
.perm-item{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:11px;cursor:pointer;border:1.5px solid #f1f5f9;transition:border-color .2s,background .2s;user-select:none;}
.perm-item:hover{border-color:#bfdbfe;background:#f8fbff;}
.perm-item.checked{border-color:#bfdbfe;background:#eff6ff;}
.perm-item.locked{cursor:not-allowed;opacity:.55;}
.perm-item input[type=checkbox]{width:15px;height:15px;accent-color:#2563eb;cursor:pointer;flex-shrink:0;}
.perm-item-icon{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.95rem;flex-shrink:0;}
.perm-item-label{font-size:.8rem;font-weight:600;color:#334155;line-height:1.2;}
.perm-item-sub{font-size:.68rem;color:#94a3b8;margin-top:1px;}
.admin-notice{padding:12px 14px;background:#eff6ff;border-radius:11px;border:1px solid #bfdbfe;font-size:.8rem;color:#1d4ed8;display:flex;align-items:flex-start;gap:9px;line-height:1.4;}
.admin-notice i{font-size:1.1rem;flex-shrink:0;margin-top:1px;}

.btn-cancel{padding:9px 20px;border-radius:10px;border:1.5px solid #e2e8f0;background:#f8fafc;font-family:'Poppins',sans-serif;font-size:.875rem;font-weight:500;color:#64748b;cursor:pointer;transition:border-color .2s;}
.btn-cancel:hover{border-color:#94a3b8;}
.btn-submit{padding:9px 24px;border-radius:10px;border:none;background:linear-gradient(135deg,#4f8ef7,#2563eb);color:#fff;font-family:'Poppins',sans-serif;font-size:.875rem;font-weight:600;cursor:pointer;box-shadow:0 4px 12px rgba(37,99,235,.25);transition:filter .2s,transform .2s;display:flex;align-items:center;gap:7px;}
.btn-submit:hover:not(:disabled){filter:brightness(1.07);transform:translateY(-1px);}
.btn-submit:disabled{opacity:.6;cursor:not-allowed;transform:none !important;filter:none !important;}
.btn-submit.danger{background:linear-gradient(135deg,#f87171,#dc2626);box-shadow:0 4px 12px rgba(220,38,38,.25);}
.btn-submit.warning{background:linear-gradient(135deg,#fb923c,#c2410c);box-shadow:0 4px 12px rgba(194,65,12,.25);}
.btn-submit.success{background:linear-gradient(135deg,#34d399,#059669);box-shadow:0 4px 12px rgba(5,150,105,.25);}
.confirm-icon{font-size:3rem;display:block;margin-bottom:10px;}

.toast{position:fixed;top:22px;right:22px;z-index:99999;padding:13px 20px;border-radius:12px;font-family:'Poppins',sans-serif;font-size:.875rem;font-weight:500;display:flex;align-items:center;gap:10px;box-shadow:0 8px 24px rgba(15,23,42,.15);animation:slideIn .3s ease;max-width:360px;}
.toast-success{background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;}
.toast-error{background:#fff1f2;color:#9f1239;border:1px solid #fecdd3;}
@keyframes slideIn{from{opacity:0;transform:translateX(20px);}to{opacity:1;transform:translateX(0);}}

@media(max-width:1024px){.summary-row{grid-template-columns:repeat(2,1fr);}}
@media(max-width:640px){.content{padding:20px 16px;}.summary-row{grid-template-columns:1fr 1fr;gap:12px;}.form-row-2,.perm-grid{grid-template-columns:1fr;}}
</style>
</head>
<body>
<div class="content">

  <div class="page-header">
    <div class="page-header-left">
      <div class="page-header-icon"><i class='bx bx-group'></i></div>
      <div>
        <h1>User Management</h1>
        <p>Manage accounts, roles, and page access permissions</p>
      </div>
    </div>
    <div class="breadcrumb">
      <i class='bx bx-home-alt'></i> Home
      <i class='bx bx-chevron-right'></i>
      <span>User Management</span>
    </div>
  </div>

  <div class="summary-row">
    <div class="summary-card">
      <div class="sc-icon" style="background:linear-gradient(135deg,#4f8ef7,#2563eb);box-shadow:0 3px 10px rgba(37,99,235,.3)"><i class='bx bx-group'></i></div>
      <div><div class="sc-label">Total Users</div><div class="sc-value" id="statTotal">—</div></div>
    </div>
    <div class="summary-card">
      <div class="sc-icon" style="background:linear-gradient(135deg,#a78bfa,#7c3aed);box-shadow:0 3px 10px rgba(124,58,237,.3)"><i class='bx bx-shield-alt-2'></i></div>
      <div><div class="sc-label">Admins</div><div class="sc-value" id="statAdmin">—</div></div>
    </div>
    <div class="summary-card">
      <div class="sc-icon" style="background:linear-gradient(135deg,#34d399,#059669);box-shadow:0 3px 10px rgba(5,150,105,.3)"><i class='bx bx-user-check'></i></div>
      <div><div class="sc-label">Active</div><div class="sc-value" id="statActive">—</div></div>
    </div>
    <div class="summary-card">
      <div class="sc-icon" style="background:linear-gradient(135deg,#f87171,#dc2626);box-shadow:0 3px 10px rgba(220,38,38,.25)"><i class='bx bx-user-x'></i></div>
      <div><div class="sc-label">Inactive</div><div class="sc-value" id="statInactive">—</div></div>
    </div>
  </div>

  <div class="main-card">
    <div class="card-toolbar">
      <div class="search-wrap">
        <i class='bx bx-search'></i>
        <input type="text" id="searchInput" placeholder="Search by name, username, or email…">
      </div>
      <select class="filter-select" id="roleFilter">
        <option value="">All Roles</option>
        <option value="ADMIN">Admin</option>
        <option value="EMPLOYEE">Employee</option>
        <option value="WAREHOUSE">Warehouse</option>
      </select>
      <select class="filter-select" id="statusFilter">
        <option value="">All Status</option>
        <option value="ACTIVE">Active</option>
        <option value="INACTIVE">Inactive</option>
      </select>
      <button class="btn-add" onclick="openAddModal()">
        <i class='bx bx-user-plus'></i> Add User
      </button>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>User</th>
            <th>Username</th>
            <th>Role</th>
            <th>Status</th>
            <th>Joined</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="userTableBody">
          <tr><td colspan="7" style="text-align:center;padding:40px;color:#94a3b8;">
            <i class='bx bx-loader-alt bx-spin' style="font-size:1.5rem;"></i>
          </td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ══ PASSWORD CONFIRM MODAL ══════════════════════════════════════════════ -->
<div class="modal-overlay" id="pwModal">
  <div class="modal-box pw-confirm-box">
    <div class="modal-header">
      <h3 id="pwModalTitle">Confirm Identity</h3>
      <button class="modal-close" onclick="cancelPwModal()"><i class='bx bx-x'></i></button>
    </div>
    <div class="modal-body">
      <div class="pw-confirm-info">
        <i class='bx bx-lock-alt'></i>
        <p>Enter your admin password to confirm <strong id="pwActionLabel">this action</strong>.</p>
      </div>
      <div class="form-group" style="margin-bottom:4px;">
        <label><i class='bx bx-lock'></i> Admin Password</label>
        <div class="input-wrap">
          <i class='bx bx-lock ii'></i>
          <input type="password" id="pwConfirmInput" placeholder="Enter your password" onkeydown="if(event.key==='Enter')confirmPwAndProceed()">
          <i class='bx bx-hide eye' id="pwEye" onclick="togglePwConfirm()"></i>
        </div>
        <p class="pw-error" id="pwError"><i class='bx bx-error-circle'></i> <span id="pwErrorMsg">Incorrect password.</span></p>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn-cancel" onclick="cancelPwModal()">Cancel</button>
      <button class="btn-submit" id="pwConfirmBtn" onclick="confirmPwAndProceed()">
        <i class='bx bx-check'></i> <span>Confirm</span>
      </button>
    </div>
  </div>
</div>

<!-- ══ ADD / EDIT MODAL ════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="userModal">
  <div class="modal-box">
    <div class="modal-header">
      <h3 id="modalTitle">Add New User</h3>
      <button class="modal-close" onclick="closeModal('userModal')"><i class='bx bx-x'></i></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="editUserId">
      <div class="form-row-2">
        <div class="form-group">
          <label><i class='bx bx-user'></i> Full Name</label>
          <div class="input-wrap"><i class='bx bx-user ii'></i>
            <input type="text" id="fieldName" placeholder="e.g. Juan Dela Cruz">
          </div>
        </div>
        <div class="form-group">
          <label><i class='bx bx-at'></i> Username</label>
          <div class="input-wrap"><i class='bx bx-at ii'></i>
            <input type="text" id="fieldUsername" placeholder="e.g. juandc">
          </div>
        </div>
      </div>
      <div class="form-group">
        <label><i class='bx bx-envelope'></i> Email Address</label>
        <div class="input-wrap"><i class='bx bx-envelope ii'></i>
          <input type="email" id="fieldEmail" placeholder="e.g. juan@email.com">
        </div>
      </div>
      <div class="form-row-2">
        <div class="form-group">
          <label><i class='bx bx-shield-alt-2'></i> Role</label>
          <div class="input-wrap"><i class='bx bx-shield-alt-2 ii'></i>
            <select id="fieldRole" onchange="onRoleChange()">
              <option value="EMPLOYEE">Employee</option>
              <option value="WAREHOUSE">Warehouse</option>
              <option value="ADMIN">Admin</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label><i class='bx bx-toggle-left'></i> Status</label>
          <div class="input-wrap"><i class='bx bx-toggle-left ii'></i>
            <select id="fieldStatus">
              <option value="ACTIVE">Active</option>
              <option value="INACTIVE">Inactive</option>
            </select>
          </div>
        </div>
      </div>
      <div class="form-group">
        <label><i class='bx bx-lock'></i> Password</label>
        <div class="input-wrap"><i class='bx bx-lock ii'></i>
          <input type="password" id="fieldPassword" placeholder="Min. 8 characters">
          <i class='bx bx-hide eye' id="userPwEye" onclick="toggleUserPw()"></i>
        </div>
        <p class="hint" id="pwHint" style="display:none;"><i class='bx bx-info-circle'></i> Leave blank to keep current password.</p>
      </div>
      <div class="form-group" id="warehouseLocGroup" style="display:none;position:relative;">
        <label><i class='bx bx-map-pin'></i> Warehouse Location</label>
        <div class="input-wrap" style="position:relative;">
          <i class='bx bx-map-pin ii'></i>
          <input type="text" id="fieldWarehouseLoc" placeholder="Search or select location…" autocomplete="off"
                 oninput="filterLocOptions(this.value)" onfocus="showLocDropdown()" onblur="setTimeout(hideLocDropdown,200)">
          <i class='bx bx-chevron-down eye' style="pointer-events:none;"></i>
        </div>
        <div id="locDropdown" style="display:none;position:absolute;z-index:9999;background:#fff;border:1.5px solid #4f8ef7;
             border-top:none;border-radius:0 0 10px 10px;max-height:200px;overflow-y:auto;
             box-shadow:0 8px 20px rgba(15,23,42,.12);width:100%;left:0;top:100%;"></div>
        <p class="hint"><i class='bx bx-info-circle'></i> This user will only see assets in this location.
          <a href="location_management.php" target="_blank" style="color:var(--primary-color);font-weight:600;margin-left:.3rem;">
            <i class='bx bx-link-external' style="vertical-align:middle;font-size:.85rem;"></i> Manage Locations
          </a>
        </p>
      </div>

      <div id="permSection">
        <div class="section-divider">
          <span><i class='bx bx-key'></i> Page Access Permissions</span>
          <button class="btn-toggle-all" id="btnToggleAll" onclick="toggleAll()">Select All</button>
        </div>
        <div class="admin-notice" id="adminNotice" style="display:none;">
          <i class='bx bx-shield-alt-2'></i>
          <div>Admins automatically have <strong>full access</strong> to all pages.</div>
        </div>
        <div class="admin-notice" id="whNotice" style="display:none;background:#fffbeb;border-color:#fde68a;color:#92400e;">
          <i class='bx bx-map-pin' style="color:#f59e0b;"></i>
          <div>Warehouse users only see assets in their assigned location. They <strong>cannot</strong> access other pages.</div>
        </div>
        <div class="perm-grid" id="permGrid"></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn-cancel" onclick="closeModal('userModal')">Cancel</button>
      <button class="btn-submit" id="submitBtn" onclick="if(validateUserForm()) requestPwThen('save_user')">
        <i class='bx bx-user-plus' id="submitIcon"></i>
        <span id="submitLabel">Add User</span>
      </button>
    </div>
  </div>
</div>

<!-- ══ DELETE CONFIRM ══════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="deleteModal">
  <div class="modal-box" style="max-width:380px;">
    <div class="modal-header">
      <h3>Delete User</h3>
      <button class="modal-close" onclick="closeModal('deleteModal')"><i class='bx bx-x'></i></button>
    </div>
    <div class="modal-body" style="text-align:center;">
      <i class='bx bx-trash confirm-icon' style="color:#dc2626;"></i>
      <p style="font-size:.95rem;font-weight:600;color:#0f172a;margin-bottom:6px;">Are you sure?</p>
      <p style="font-size:.84rem;color:#64748b;">Delete <strong id="delName"></strong>?<br>This cannot be undone.</p>
    </div>
    <div class="modal-footer">
      <button class="btn-cancel" onclick="closeModal('deleteModal')">Cancel</button>
      <button class="btn-submit danger" onclick="requestPwThen('delete_user')"><i class='bx bx-trash'></i> Delete</button>
    </div>
  </div>
</div>

<!-- ══ BLOCK/UNBLOCK CONFIRM ═══════════════════════════════════════════════ -->
<div class="modal-overlay" id="blockModal">
  <div class="modal-box" style="max-width:380px;">
    <div class="modal-header">
      <h3 id="blkTitle">Block User</h3>
      <button class="modal-close" onclick="closeModal('blockModal')"><i class='bx bx-x'></i></button>
    </div>
    <div class="modal-body" style="text-align:center;">
      <i class='bx confirm-icon' id="blkIcon" style="color:#c2410c;"></i>
      <p style="font-size:.95rem;font-weight:600;color:#0f172a;margin-bottom:6px;" id="blkMsg"></p>
      <p style="font-size:.84rem;color:#64748b;" id="blkSub"></p>
    </div>
    <div class="modal-footer">
      <button class="btn-cancel" onclick="closeModal('blockModal')">Cancel</button>
      <button class="btn-submit" id="blkBtn" onclick="requestPwThen('toggle_block')">
        <i class='bx' id="blkBtnIcon"></i> <span id="blkBtnLabel">Block</span>
      </button>
    </div>
  </div>
</div>

<script>
function validateUserForm() {
    const id   = document.getElementById('editUserId').value;
    const name = document.getElementById('fieldName').value.trim();
    const user = document.getElementById('fieldUsername').value.trim();
    const mail = document.getElementById('fieldEmail').value.trim();
    const pw   = document.getElementById('fieldPassword').value;
    if (!name) { showToast('Full name is required.','error'); document.getElementById('fieldName').focus(); return false; }
    if (!user) { showToast('Username is required.','error'); document.getElementById('fieldUsername').focus(); return false; }
    if (!mail) { showToast('Email is required.','error'); document.getElementById('fieldEmail').focus(); return false; }
    if (!mail.includes('@')) { showToast('Enter a valid email address.','error'); document.getElementById('fieldEmail').focus(); return false; }
    if (!id && pw.length < 8) { showToast('Password must be at least 8 characters.','error'); document.getElementById('fieldPassword').focus(); return false; }
    if (id && pw && pw.length < 8) { showToast('New password must be at least 8 characters.','error'); document.getElementById('fieldPassword').focus(); return false; }
    return true;
}

const PERMS = [
  { key:'dashboard',        label:'Dashboard',        sub:'Main overview',        icon:'bx-grid-alt',        color:'#3b82f6', bg:'#eff6ff' },
  { key:'inventory_view',   label:'Inventory',        sub:'View only',            icon:'bx-package',         color:'#8b5cf6', bg:'#f5f3ff' },
  { key:'asset_transfer',   label:'Asset Transfer',   sub:'Pull-out requests',    icon:'bx-transfer-alt',    color:'#f59e0b', bg:'#fffbeb' },
  { key:'upload_assets',    label:'Upload Assets',    sub:'Bulk import',          icon:'bx-upload',          color:'#10b981', bg:'#f0fdf4' },
  { key:'qr_management',    label:'QR Management',    sub:'Generate & print QRs', icon:'bx-qr-scan',         color:'#06b6d4', bg:'#ecfeff' },
  { key:'reports',          label:'Reports',          sub:'View reports',         icon:'bx-bar-chart-alt-2', color:'#6366f1', bg:'#eef2ff' },
  { key:'account_settings', label:'Account Settings', sub:'Always enabled',       icon:'bx-cog',             color:'#64748b', bg:'#f8fafc', locked:true },
];
const DEFAULT_PERMS = PERMS.map(p => p.key);

let allUsers = [], delTarget = null, blkTarget = null, pendingAction = null, modalToReopenOnCancel = null;

function requestPwThen(action) {
    pendingAction = action;
    closeModal('blockModal'); closeModal('deleteModal'); closeModal('userModal');
    modalToReopenOnCancel = action === 'save_user' ? 'userModal' : action === 'delete_user' ? 'deleteModal' : null;
    const labels = { save_user:'save this user', delete_user:'delete this user', toggle_block: blkTarget?.blocking ? 'block this user' : 'unblock this user' };
    document.getElementById('pwActionLabel').textContent = labels[action] || 'this action';
    document.getElementById('pwConfirmInput').value = '';
    document.getElementById('pwError').classList.remove('show');
    const btn = document.getElementById('pwConfirmBtn');
    btn.className = 'btn-submit';
    if (action === 'delete_user') btn.classList.add('danger');
    else if (action === 'toggle_block' && blkTarget?.blocking) btn.classList.add('warning');
    openModal('pwModal');
    setTimeout(() => document.getElementById('pwConfirmInput').focus(), 100);
}

function cancelPwModal() {
    closeModal('pwModal');
    if (modalToReopenOnCancel) openModal(modalToReopenOnCancel);
    modalToReopenOnCancel = null;
}

async function confirmPwAndProceed() {
    const pw  = document.getElementById('pwConfirmInput').value;
    const err = document.getElementById('pwError');
    const btn = document.getElementById('pwConfirmBtn');
    if (!pw) { document.getElementById('pwErrorMsg').textContent = 'Please enter your password.'; err.classList.add('show'); return; }
    btn.disabled = true; btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Verifying…';
    try {
        const r = await fetch('user_management_api.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'verify_password',password:pw}) });
        const d = await r.json();
        if (!d.success) {
            document.getElementById('pwErrorMsg').textContent = d.message || 'Incorrect password.';
            err.classList.add('show'); btn.disabled = false; btn.innerHTML = '<i class="bx bx-check"></i> <span>Confirm</span>';
            document.getElementById('pwConfirmInput').value = ''; document.getElementById('pwConfirmInput').focus(); return;
        }
        closeModal('pwModal'); btn.disabled = false; btn.innerHTML = '<i class="bx bx-check"></i> <span>Confirm</span>';
        if      (pendingAction === 'save_user')    await doSaveUser();
        else if (pendingAction === 'delete_user')  await doDeleteUser();
        else if (pendingAction === 'toggle_block') await doToggleBlock();
    } catch(e) {
        err.classList.add('show'); document.getElementById('pwErrorMsg').textContent = 'Request failed. Try again.';
        btn.disabled = false; btn.innerHTML = '<i class="bx bx-check"></i> <span>Confirm</span>';
    }
}

async function loadUsers() {
    try {
        const r = await fetch('user_management_api.php?action=get_users');
        const d = await r.json();
        if (d.success) { allUsers = d.data; updateStats(d.stats); renderTable(); }
    } catch(e) { showToast('Failed to load users','error'); }
}

function updateStats(s) {
    document.getElementById('statTotal').textContent    = s.total;
    document.getElementById('statAdmin').textContent    = s.admin;
    document.getElementById('statActive').textContent   = s.active;
    document.getElementById('statInactive').textContent = s.inactive;
}

function renderTable() {
    const q  = document.getElementById('searchInput').value.toLowerCase();
    const rl = document.getElementById('roleFilter').value;
    const st = document.getElementById('statusFilter').value;
    const list = allUsers.filter(u =>
        (!q  || u.name.toLowerCase().includes(q) || u.username.toLowerCase().includes(q) || u.email.toLowerCase().includes(q)) &&
        (!rl || u.role   === rl) &&
        (!st || u.status === st)
    );
    const tb = document.getElementById('userTableBody');
    if (!list.length) {
        tb.innerHTML = `<tr><td colspan="7"><div class="empty-state"><i class='bx bx-user-x'></i><p>No users found</p></div></td></tr>`;
        return;
    }
    tb.innerHTML = list.map(u => {
        const av = u.profile_pic
            ? `uploads/${u.profile_pic}`
            : `https://ui-avatars.com/api/?name=${encodeURIComponent(u.name)}&background=4f8ef7&color=fff&size=80`;
        const roleClass = { ADMIN:'role-admin', EMPLOYEE:'role-employee', WAREHOUSE:'role-warehouse' }[u.role] || 'role-employee';
        const blkBtn = u.status === 'INACTIVE'
            ? `<button class="btn-icon btn-unblock" title="Unblock" onclick="openBlkModal(${u.id},'${esc(u.name)}',false)"><i class='bx bx-user-check'></i></button>`
            : `<button class="btn-icon btn-block"   title="Block"   onclick="openBlkModal(${u.id},'${esc(u.name)}',true)"><i class='bx bx-user-x'></i></button>`;
        return `<tr>
            <td style="color:#94a3b8;font-size:.78rem;">#${u.id}</td>
            <td>
                <div class="user-info">
                    <img src="${av}" class="user-avatar" onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(u.name)}&background=4f8ef7&color=fff&size=80'">
                    <div><div class="user-name">${esc(u.name)}</div><div class="user-email">${esc(u.email)}</div></div>
                </div>
            </td>
            <td style="font-family:monospace;font-size:.8rem;color:#475569;">@${esc(u.username)}</td>
            <td><span class="badge ${roleClass}">${u.role}</span></td>
            <td><span class="badge ${u.status==='ACTIVE'?'status-active':'status-inactive'}"><span class="status-dot"></span>${u.status}</span></td>
            <td style="color:#94a3b8;font-size:.78rem;">${fmtDate(u.created_at)}</td>
            <td>
                <div class="action-btns">
                    <button class="btn-icon btn-edit"   title="Edit"   onclick="openEditModal(${u.id})"><i class='bx bx-edit'></i></button>
                    ${blkBtn}
                    <button class="btn-icon btn-delete" title="Delete" onclick="openDelModal(${u.id},'${esc(u.name)}')"><i class='bx bx-trash'></i></button>
                </div>
            </td>
        </tr>`;
    }).join('');
}

function renderPermGrid(selected = []) {
    document.getElementById('permGrid').innerHTML = PERMS.map(p => {
        const chk = selected.includes(p.key) ? 'checked' : '';
        const dis = p.locked ? 'disabled' : '';
        const cls = ['perm-item', selected.includes(p.key) ? 'checked' : '', p.locked ? 'locked' : ''].join(' ').trim();
        return `<label class="${cls}" id="pitem_${p.key}">
            <input type="checkbox" value="${p.key}" ${chk} ${dis} onchange="onPermChange(this,'${p.key}')">
            <div class="perm-item-icon" style="background:${p.bg};color:${p.color}"><i class='bx ${p.icon}'></i></div>
            <div><div class="perm-item-label">${p.label}</div><div class="perm-item-sub">${p.sub}</div></div>
        </label>`;
    }).join('');
}

function onPermChange(cb, key) { document.getElementById('pitem_' + key).classList.toggle('checked', cb.checked); }

function onRoleChange() {
    const role = document.getElementById('fieldRole').value;
    const isAdmin = role === 'ADMIN', isWH = role === 'WAREHOUSE';
    document.getElementById('adminNotice').style.display       = isAdmin ? 'flex'  : 'none';
    document.getElementById('whNotice').style.display          = isWH    ? 'flex'  : 'none';
    document.getElementById('permGrid').style.display          = (isAdmin || isWH) ? 'none' : 'grid';
    document.getElementById('btnToggleAll').style.display      = (isAdmin || isWH) ? 'none' : 'inline';
    document.getElementById('warehouseLocGroup').style.display = isWH    ? 'block' : 'none';
}

function toggleAll() {
    const cbs = [...document.querySelectorAll('#permGrid input[type=checkbox]:not(:disabled)')];
    const all = cbs.every(c => c.checked);
    cbs.forEach(c => { c.checked = !all; onPermChange(c, c.value); });
    document.getElementById('btnToggleAll').textContent = all ? 'Select All' : 'Deselect All';
}

function getSelectedPerms() { return [...document.querySelectorAll('#permGrid input[type=checkbox]:checked')].map(c => c.value); }

function openAddModal() {
    document.getElementById('modalTitle').textContent  = 'Add New User';
    document.getElementById('submitLabel').textContent = 'Add User';
    document.getElementById('submitIcon').className    = 'bx bx-user-plus';
    document.getElementById('editUserId').value = '';
    document.getElementById('fieldName').value = '';
    document.getElementById('fieldUsername').value = '';
    document.getElementById('fieldEmail').value = '';
    document.getElementById('fieldRole').value = 'EMPLOYEE';
    document.getElementById('fieldStatus').value = 'ACTIVE';
    document.getElementById('fieldPassword').value = '';
    document.getElementById('fieldWarehouseLoc').value = '';
    document.getElementById('pwHint').style.display = 'none';
    renderPermGrid(DEFAULT_PERMS); onRoleChange(); openModal('userModal');
}

function openEditModal(uid) {
    const u = allUsers.find(x => Number(x.id) === Number(uid)); if (!u) return;
    document.getElementById('modalTitle').textContent  = 'Edit User';
    document.getElementById('submitLabel').textContent = 'Save Changes';
    document.getElementById('submitIcon').className    = 'bx bx-save';
    document.getElementById('editUserId').value    = u.id;
    document.getElementById('fieldName').value     = u.name;
    document.getElementById('fieldUsername').value = u.username;
    document.getElementById('fieldEmail').value    = u.email;
    document.getElementById('fieldRole').value     = u.role;
    document.getElementById('fieldStatus').value   = u.status;
    document.getElementById('fieldPassword').value = '';
    document.getElementById('fieldWarehouseLoc').value = u.warehouse_location || '';
    document.getElementById('pwHint').style.display = 'block';
    renderPermGrid(u.permissions || DEFAULT_PERMS); onRoleChange(); openModal('userModal');
}

async function doSaveUser() {
    const id    = document.getElementById('editUserId').value;
    const name  = document.getElementById('fieldName').value.trim();
    const uname = document.getElementById('fieldUsername').value.trim();
    const email = document.getElementById('fieldEmail').value.trim();
    const role  = document.getElementById('fieldRole').value;
    const stat  = document.getElementById('fieldStatus').value;
    const pw    = document.getElementById('fieldPassword').value;
    const whloc = document.getElementById('fieldWarehouseLoc').value.trim();
    const perms = (role === 'ADMIN' || role === 'WAREHOUSE') ? [] : getSelectedPerms();
    if (!name || !uname || !email) { showToast('Name, username, and email are required.','error'); return; }
    if (!id && pw.length < 8)      { showToast('Password must be at least 8 characters.','error'); return; }
    if (role === 'WAREHOUSE' && !whloc) { showToast('Warehouse location is required.','error'); return; }
    try {
        const r = await fetch('user_management_api.php', { method:'POST', headers:{'Content-Type':'application/json'},
            body:JSON.stringify({action:id?'edit_user':'add_user',id,name,username:uname,email,role,status:stat,password:pw,permissions:perms,warehouse_location:whloc||null}) });
        const d = await r.json();
        if (d.success) { closeModal('userModal'); showToast(d.message,'success'); loadUsers(); }
        else showToast(d.message || 'Error saving user','error');
    } catch(e) { showToast('Request failed','error'); }
}

function openDelModal(id, name) { delTarget = id; document.getElementById('delName').textContent = name; openModal('deleteModal'); }

async function doDeleteUser() {
    if (!delTarget) return;
    try {
        const r = await fetch('user_management_api.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'delete_user',id:delTarget}) });
        const d = await r.json();
        if (d.success) { showToast(d.message,'success'); loadUsers(); }
        else showToast(d.message || 'Error','error');
    } catch(e) { showToast('Request failed','error'); }
}

function openBlkModal(id, name, blocking) {
    blkTarget = { id, blocking };
    document.getElementById('blkTitle').textContent    = blocking ? 'Block User' : 'Unblock User';
    document.getElementById('blkIcon').className       = `bx ${blocking ? 'bx-user-x' : 'bx-user-check'} confirm-icon`;
    document.getElementById('blkIcon').style.color     = blocking ? '#c2410c' : '#16a34a';
    document.getElementById('blkMsg').textContent      = (blocking ? 'Block ' : 'Unblock ') + name + '?';
    document.getElementById('blkSub').textContent      = blocking ? 'This user will no longer be able to log in.' : 'This user will regain access to the system.';
    document.getElementById('blkBtnLabel').textContent = blocking ? 'Block' : 'Unblock';
    const btn = document.getElementById('blkBtn');
    btn.className = `btn-submit ${blocking ? 'warning' : 'success'}`;
    document.getElementById('blkBtnIcon').className = `bx ${blocking ? 'bx-user-x' : 'bx-user-check'}`;
    openModal('blockModal');
}

async function doToggleBlock() {
    if (!blkTarget) return;
    try {
        const r = await fetch('user_management_api.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'toggle_status',id:blkTarget.id,status:blkTarget.blocking?'INACTIVE':'ACTIVE'}) });
        const d = await r.json();
        if (d.success) { showToast(d.message,'success'); loadUsers(); }
        else showToast(d.message || 'Error','error');
    } catch(e) { showToast('Request failed','error'); }
}

function openModal(id)  { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }

function togglePwConfirm() {
    const i = document.getElementById('pwConfirmInput'), e = document.getElementById('pwEye');
    i.type = i.type === 'password' ? 'text' : 'password';
    e.classList.toggle('bx-hide'); e.classList.toggle('bx-show');
}
function toggleUserPw() {
    const i = document.getElementById('fieldPassword'), e = document.getElementById('userPwEye');
    i.type = i.type === 'password' ? 'text' : 'password';
    e.classList.toggle('bx-hide'); e.classList.toggle('bx-show');
}
function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;'); }
function fmtDate(d) { return d ? new Date(d).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}) : '—'; }
function showToast(msg, type='success') {
    const t = document.createElement('div');
    t.className = `toast toast-${type}`;
    t.innerHTML = `<i class='bx ${type==='success'?'bx-check-circle':'bx-error-circle'}'></i> ${esc(msg)}`;
    document.body.appendChild(t);
    setTimeout(() => { t.style.transition='opacity .4s'; t.style.opacity='0'; setTimeout(()=>t.remove(),400); }, 3200);
}

let WH_LOCATIONS = [];
async function loadLocations() {
    try { const r = await fetch('user_management_api.php?action=get_locations'); const d = await r.json(); if (d.success) WH_LOCATIONS = d.data; } catch(e) {}
}
function renderLocOptions(filtered) {
    const dd = document.getElementById('locDropdown');
    dd.innerHTML = !filtered.length
        ? `<div style="padding:10px 14px;font-size:.8rem;color:#94a3b8;">No locations found</div>`
        : filtered.map(loc => `<div onclick="selectLoc('${loc.replace(/'/g,"\\'")}');" style="padding:9px 14px;font-size:.84rem;color:#334155;cursor:pointer;" onmouseenter="this.style.background='#eff6ff'" onmouseleave="this.style.background=''">${loc}</div>`).join('');
}
function showLocDropdown() { filterLocOptions(document.getElementById('fieldWarehouseLoc').value); document.getElementById('locDropdown').style.display='block'; }
function hideLocDropdown() { document.getElementById('locDropdown').style.display='none'; }
function filterLocOptions(q) {
    const f = q.trim() ? WH_LOCATIONS.filter(l=>l.toLowerCase().includes(q.toLowerCase())) : WH_LOCATIONS;
    renderLocOptions(f);
    if (f.length) document.getElementById('locDropdown').style.display='block';
}
function selectLoc(loc) { document.getElementById('fieldWarehouseLoc').value=loc; hideLocDropdown(); }

document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', e => {
    if (e.target !== o) return;
    if (o.id === 'pwModal') cancelPwModal(); else o.classList.remove('active');
}));
document.getElementById('searchInput').addEventListener('input',   renderTable);
document.getElementById('roleFilter').addEventListener('change',   renderTable);
document.getElementById('statusFilter').addEventListener('change', renderTable);
document.addEventListener('DOMContentLoaded', () => { loadUsers(); loadLocations(); });
</script>
</body>
</html>
<?php ob_end_flush(); ?>