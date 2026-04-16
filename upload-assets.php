<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: landing.php");
    exit();
}

$conn = getDBConnection();

$categories = [];
$result = $conn->query("SELECT id, name FROM categories ORDER BY name");
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}

$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user     = $stmt->get_result()->fetch_assoc();
$userName = $user['name'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Assets - Inventory Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/upload-asset.css">
    <style>
        .home {
            position: relative;
            left: 260px;
            width: calc(100% - 260px);
            min-height: 220vh;
            padding: 2rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* ── RESPONSIVE: Tablet (≤1024px) ── */
        @media (max-width: 1024px) {
            .home {
                left: 0;
                width: 100%;
                padding: 1.25rem;
            }

            .page-header-section {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 1rem;
            }

            .header-stats {
                width: 100%;
            }
        }

        /* ── RESPONSIVE: Mobile (≤768px) ── */
        @media (max-width: 768px) {
            .home {
                left: 0;
                width: 100%;
                padding: 1rem;
                min-height: unset;
            }

            /* Page header */
            .page-header-section {
                flex-direction: column !important;
                gap: 0.75rem;
            }

            .header-content {
                flex-direction: row;
                align-items: center;
            }

            .header-icon {
                width: 38px !important;
                height: 38px !important;
                font-size: 1.2rem !important;
            }

            .header-text h1 {
                font-size: 1.2rem !important;
            }

            /* Tabs */
            .tabs-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none;
                gap: 0.25rem !important;
            }

            .tabs-container::-webkit-scrollbar { display: none; }

            .tab-button {
                white-space: nowrap;
                font-size: 0.8rem !important;
                padding: 0.5rem 0.85rem !important;
            }

            /* Form fields stack to 1 column */
            .form-row {
                grid-template-columns: 1fr !important;
            }

            .form-field {
                min-width: unset !important;
            }

            /* Image upload grid */
            .image-upload-grid {
                grid-template-columns: repeat(auto-fill, minmax(90px, 1fr)) !important;
                gap: 0.5rem !important;
            }

            .upload-box {
                min-height: 90px !important;
            }

            /* Content card padding */
            .content-card {
                padding: 1rem !important;
                border-radius: 10px !important;
            }

            /* Excel guide steps */
            .excel-guide {
                flex-direction: column !important;
            }

            .guide-step {
                flex-direction: row !important;
                gap: 0.75rem !important;
            }

            /* Form actions buttons */
            .form-actions {
                flex-direction: column !important;
                gap: 0.5rem !important;
            }

            .form-actions .btn {
                width: 100% !important;
                justify-content: center !important;
            }

            .upload-actions {
                flex-direction: column !important;
            }

            .upload-actions .btn {
                width: 100% !important;
                justify-content: center !important;
            }

            /* History panel filters */
            #history-panel > div[style*="display:flex"] {
                flex-direction: column !important;
            }

            /* History table - horizontal scroll */
            #history-panel table {
                min-width: 700px;
            }

            /* Confirm box */
            .confirm-box {
                flex-direction: column !important;
            }

            .confirm-info {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 0.5rem !important;
            }

            .confirm-actions {
                width: 100%;
                flex-direction: column !important;
            }

            .confirm-actions .btn {
                width: 100% !important;
                justify-content: center !important;
            }

            /* Batch modal */
            #batchModal {
                padding: 0.5rem !important;
            }

            #batchModal > div {
                border-radius: 10px !important;
                margin: 0 !important;
            }

            #batchModal > div > div:last-child {
                max-height: 80vh !important;
                padding: 1rem !important;
            }

            /* Summary stats */
            .preview-summary {
                flex-direction: row;
                flex-wrap: wrap;
                gap: 0.5rem;
            }

            .summary-stat {
                font-size: 0.8rem !important;
                padding: 0.4rem 0.75rem !important;
            }

            /* Drop zone */
            .file-drop-zone {
                padding: 1.5rem 1rem !important;
            }

            .drop-zone-content i {
                font-size: 2.5rem !important;
            }

            .drop-zone-content h3 {
                font-size: 1rem !important;
            }

            /* Header stat */
            .stat-item {
                flex-direction: row !important;
                gap: 0.4rem !important;
                align-items: center !important;
            }
        }

        /* ── RESPONSIVE: Small phones (≤480px) ── */
        @media (max-width: 480px) {
            .home {
                padding: 0.75rem;
            }

            .header-text h1 {
                font-size: 1rem !important;
            }

            .tab-button span {
                display: none;
            }

            .tab-button {
                padding: 0.5rem 0.75rem !important;
                font-size: 1.1rem !important;
            }

            .image-upload-grid {
                grid-template-columns: repeat(3, 1fr) !important;
            }

            .alert {
                font-size: 0.85rem !important;
                padding: 0.75rem 1rem !important;
            }
        }

        .preview-table-wrap {
            overflow-x: auto;
            margin-top: 1rem;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
        }

        .preview-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }

        .preview-table th {
            background: #f3f4f6;
            padding: 0.6rem 0.75rem;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
            white-space: nowrap;
        }

        .preview-table td {
            padding: 0.5rem 0.75rem;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: top;
        }

        .row-valid   { background: #f0fdf4; }
        .row-invalid { background: #fff5f5; }

        .error-tag {
            display: inline-block;
            background: #fee2e2;
            color: #b91c1c;
            border-radius: 4px;
            padding: 2px 6px;
            font-size: 0.75rem;
            margin: 2px 2px 2px 0;
        }

        .preview-summary {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }

        .summary-stat {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.9rem;
        }

        .summary-stat.ok   { background: #dcfce7; color: #15803d; }
        .summary-stat.err  { background: #fee2e2; color: #b91c1c; }
        .summary-stat.info { background: #eff6ff; color: #1d4ed8; }

        /* ── Blocked import banner ── */
        .blocked-box {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            background: #fff7ed;
            border: 2px solid #fb923c;
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
        }
        .blocked-box i { font-size: 2rem; color: #ea580c; flex-shrink: 0; margin-top: 2px; }
        .blocked-box h4 { margin: 0 0 0.25rem; color: #9a3412; font-size: 1rem; }
        .blocked-box p  { margin: 0; color: #7c3a1b; font-size: 0.875rem; line-height: 1.5; }

        /* ── Confirm box (all rows valid) ── */
        .confirm-box {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
        }

        .confirm-info    { display: flex; align-items: center; gap: 1rem; }
        .confirm-actions { display: flex; gap: 0.75rem; }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            font-size: 0.95rem;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-danger {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>
    <script>
    const BASE_URL = '<?php echo rtrim(dirname($_SERVER["PHP_SELF"]), "/"); ?>/';
</script>

    <section class="home">
        <div class="page-container">

            <!-- Page Header -->
            <div class="page-header-section">
                <div class="header-content">
                    <div class="header-icon"><i class='bx bx-upload'></i></div>
                    <div class="header-text">
                        <h1>Upload Assets</h1>
                        <p>Add new assets to your inventory system</p>
                    </div>
                </div>
                <div class="header-stats">
                    <div class="stat-item">
                        <span class="stat-value"><?php
                            $countResult = $conn->query("SELECT COUNT(*) as total FROM assets");
                            echo number_format($countResult->fetch_assoc()['total'] ?? 0);
                        ?></span>
                        <span class="stat-label">Total Assets</span>
                    </div>
                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="tabs-wrapper">
                <div class="tabs-container">
                    <button class="tab-button active" data-tab="manual">
                        <i class='bx bx-edit-alt'></i>
                        <span>Manual Entry</span>
                    </button>
                    <button class="tab-button" data-tab="excel">
                        <i class='bx bxs-file-import'></i>
                        <span>Excel Upload</span>
                    </button>
            <button class="tab-button" data-tab="history">
    <i class='bx bx-history'></i>
    <span>History</span>
</button>

                </div>
            </div>

            <!-- ── MANUAL ENTRY TAB ── -->
            <div class="tab-panel active" id="manual-panel">
                <div class="content-card">
                    <form id="manualAssetForm" enctype="multipart/form-data" class="modern-form">

                        <div class="form-section">
                            <div class="section-header">
                                <i class='bx bx-info-circle'></i>
                                <h3>Asset Information</h3>
                            </div>
                            <div class="form-row">

                                <div class="form-field">
                                    <label>Category <span class="req">*</span></label>
                                    <div class="select-wrapper">
                                        <select id="category" name="category_id" required>
                                            <option value="">Choose a category</option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <i class='bx bx-chevron-down'></i>
                                        <div class="form-field" id="others_cat_field" style="display:none;">
                                            <label>New Category Name <span class="req">*</span></label>
                                            <input type="text" id="others_cat_input" name="others_cat_name"
                                                placeholder="e.g., Medical Equipment, Furniture..." autocomplete="off">
                                            <small style="color:#6b7280;font-size:0.8rem;margin-top:0.25rem;display:block;">
                                                This will be saved as a new category permanently.
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-field">
                                    <label>Sub-Category <span class="req">*</span></label>
                                    <div class="select-wrapper">
                                        <select id="sub_category" name="sub_category_id" required>
                                            <option value="">Select category first</option>
                                        </select>
                                        <i class='bx bx-chevron-down'></i>
                                        <div class="form-field" id="others_sub_field" style="display:none;">
                                            <label>New Sub-Category Name <span class="req">*</span></label>
                                            <input type="text" id="others_sub_input" name="others_sub_category"
                                                placeholder="e.g., Barcode Scanner, Smart TV..." autocomplete="off">
                                            <small style="color:#6b7280;font-size:0.8rem;margin-top:0.25rem;display:block;">
                                                This will be saved as a new sub-category permanently.
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-field">
                                    <label>Brand</label>
                                    <input type="text" id="brand" name="brand" placeholder="e.g., Dell, HP, Cisco">
                                </div>

                                <div class="form-field">
                                    <label>Model</label>
                                    <input type="text" id="model" name="model" placeholder="e.g., Latitude 5420">
                                </div>

                                <div class="form-field">
                                    <label>Serial Number</label>
                                    <input type="text" id="serial_number" name="serial_number" placeholder="e.g., SN123456">
                                </div>

                                <div class="form-field">
                                    <label>Quantity <span class="req">*</span></label>
                                    <input type="number" id="quantity" name="beg_balance_count" min="1" value="1" required>
                                </div>

                                <div class="form-field">
                                    <label>Condition <span class="req">*</span></label>
                                    <div class="select-wrapper">
                                        <select id="condition" name="condition" required>
                                            <option value="NEW">New</option>
                                            <option value="USED">Used</option>
                                        </select>
                                        <i class='bx bx-chevron-down'></i>
                                    </div>
                                </div>

                                <div class="form-field">
                                    <label>Status <span class="req">*</span></label>
                                    <div class="select-wrapper">
                                        <select id="status" name="status" required>
                                            <option value="WORKING">Working</option>
                                            <option value="FOR CHECKING">For Checking</option>
                                            <option value="NOT WORKING">Not Working</option>
                                        </select>
                                        <i class='bx bx-chevron-down'></i>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <div class="form-section">
                            <div class="section-header">
                                <i class='bx bx-map'></i>
                                <h3>Location Details</h3>
                            </div>
                            <div class="form-row">
                                <div class="form-field">
                                    <label>Location <span class="req">*</span></label>
                                    <input type="text" id="location" name="location" placeholder="e.g., Main Warehouse" required>
                                </div>
                                <div class="form-field">
                                    <label>Sub-Location</label>
                                    <input type="text" id="sub_location" name="sub_location" placeholder="e.g., Rack A, Shelf 3">
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <div class="section-header">
                                <i class='bx bx-file-blank'></i>
                                <h3>Additional Information</h3>
                            </div>
                            <div class="form-row">
                                <div class="form-field full-width">
                                    <label>Description</label>
                                    <textarea id="description" name="description" rows="4" placeholder="Enter asset description, specifications, or notes..."></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <div class="section-header">
                                <i class='bx bx-image'></i>
                                <h3>Asset Images</h3>
                                <span class="section-badge">Max 3 images</span>
                            </div>
                            <div class="images-upload-container">
                                <div class="image-upload-grid" id="imageGrid">

                                    <label class="upload-box active" id="uploadBox1">
                                        <input type="file" class="image-input" name="images[]" accept="image/*" data-index="0">
                                        <div class="upload-content">
                                            <i class='bx bx-cloud-upload'></i>
                                            <span>Upload Image</span>
                                            <small>PNG, JPG, JPEG</small>
                                        </div>
                                        <div class="image-preview-wrapper" style="display:none;">
                                            <img class="preview-image" src="" alt="">
                                            <button type="button" class="remove-image-btn"><i class='bx bx-x'></i></button>
                                        </div>
                                    </label>

                                    <label class="upload-box" id="uploadBox2">
                                        <input type="file" class="image-input" name="images[]" accept="image/*" data-index="1">
                                        <div class="upload-content">
                                            <i class='bx bx-cloud-upload'></i>
                                            <span>Upload Image</span>
                                            <small>PNG, JPG, JPEG</small>
                                        </div>
                                        <div class="image-preview-wrapper" style="display:none;">
                                            <img class="preview-image" src="" alt="">
                                            <button type="button" class="remove-image-btn"><i class='bx bx-x'></i></button>
                                        </div>
                                    </label>

                                    <label class="upload-box" id="uploadBox3">
                                        <input type="file" class="image-input" name="images[]" accept="image/*" data-index="2">
                                        <div class="upload-content">
                                            <i class='bx bx-cloud-upload'></i>
                                            <span>Upload Image</span>
                                            <small>PNG, JPG, JPEG</small>
                                        </div>
                                        <div class="image-preview-wrapper" style="display:none;">
                                            <img class="preview-image" src="" alt="">
                                            <button type="button" class="remove-image-btn"><i class='bx bx-x'></i></button>
                                        </div>
                                    </label>

                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="reset" class="btn btn-secondary">
                                <i class='bx bx-reset'></i>
                                <span>Reset Form</span>
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class='bx bx-save'></i>
                                <span>Save Asset</span>
                            </button>
                        </div>

                    </form>
                </div>
            </div>

            <!-- ── EXCEL UPLOAD TAB ── -->
            <div class="tab-panel" id="excel-panel">
                <div class="content-card">

                    <div class="excel-guide">
                        <div class="guide-step">
                            <div class="step-number">1</div>
                            <div class="step-content">
                                <h4>Download Template</h4>
                                <p>Get the pre-formatted Excel template with all required columns</p>
                                <a href="download-template.php" class="btn btn-outline">
                                    <i class='bx bx-download'></i>
                                    <span>Download Excel Template</span>
                                </a>
                            </div>
                        </div>
                        <div class="guide-step">
                            <div class="step-number">2</div>
                            <div class="step-content">
                                <h4>Fill Your Data</h4>
                                <p>Complete the template with your asset information</p>
                                <div class="requirements-list">
                                    <div class="req-item">
                                        <i class='bx bx-check-circle'></i>
                                        <span><strong>Required:</strong> Category, Sub-Category, Location, Status</span>
                                    </div>
                                    <div class="req-item">
                                        <i class='bx bx-info-circle'></i>
                                        <span><strong>Optional:</strong> Brand, Model, Serial Number, Description, Photo Drive URL</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="guide-step">
                            <div class="step-number">3</div>
                            <div class="step-content">
                                <h4>Upload & Import</h4>
                                <p>Upload your completed file to import all assets at once</p>
                            </div>
                        </div>
                    </div>

                    <!-- Google Drive tip banner -->
                    <div style="display:flex;align-items:flex-start;gap:0.75rem;
                        background:#e8f0fe;border:1px solid #c5d8fb;border-radius:10px;
                        padding:1rem 1.25rem;margin-bottom:1.5rem;font-size:0.875rem;color:#1a3c6e;">
                        <i class='bx bxl-google' style="font-size:1.5rem;color:#4285f4;flex-shrink:0;margin-top:2px;"></i>
                        <div>
                            <strong>How to add photos via Google Drive:</strong><br>
                            1. Create a folder in Google Drive and upload your asset photos.<br>
                            2. Right-click the folder → <em>Share</em> → set access to <em>"Anyone with the link can view"</em> → Copy link.<br>
                            3. Paste the link in the <code style="background:#d0e3ff;padding:1px 5px;border-radius:4px;">photo_drive_url_1</code>, <code style="background:#d0e3ff;padding:1px 5px;border-radius:4px;">photo_drive_url_2</code>, or <code style="background:#d0e3ff;padding:1px 5px;border-radius:4px;">photo_drive_url_3</code> columns of your Excel file (up to 3 photos per asset).
                        </div>
                    </div>

                    <form id="excelUploadForm" enctype="multipart/form-data" class="excel-upload-form">
                        <div class="file-drop-zone" id="dropZone">
                            <input type="file" id="excel_file" name="excel_file" accept=".xlsx,.xls,.csv" required hidden>
                            <div class="drop-zone-content">
                                <i class='bx bxs-cloud-upload'></i>
                                <h3>Drop your file here</h3>
                                <p>or <span class="browse-link" onclick="document.getElementById('excel_file').click()">browse</span> to choose a file</p>
                                <small>Supports .csv, .xlsx, .xls &nbsp;•&nbsp; Max <strong>40MB</strong></small>
                            </div>
                            <div class="file-info" id="fileInfo" style="display:none;">
                                <i class='bx bxs-file'></i>
                                <div class="file-details">
                                    <span class="file-name"></span>
                                    <span class="file-size"></span>
                                </div>
                                <button type="button" class="remove-file-btn" onclick="clearExcelFile()">
                                    <i class='bx bx-x'></i>
                                </button>
                            </div>
                        </div>

                        <div class="upload-actions">
                            <button type="button" class="btn btn-secondary" onclick="clearExcelFile()">
                                <i class='bx bx-trash'></i>
                                <span>Clear</span>
                            </button>
                            <button type="submit" class="btn btn-primary" id="uploadBtn">
                                <i class='bx bx-upload'></i>
                                <span>Upload & Process</span>
                            </button>
                        </div>
                    </form>

                    <!-- Progress bar -->
                    <div id="uploadProgress" style="display:none; margin-top:1.5rem;">
                        <div class="progress-content">
                            <div class="progress-bar-container">
                                <div class="progress-bar-fill" id="progressFill"></div>
                            </div>
                            <p class="progress-text">Processing your file, please wait...</p>
                        </div>
                    </div>

                    <!-- Results (preview table + summary) -->
                    <div id="uploadResults" style="display:none; margin-top:1rem;"></div>

                    <!-- Action section: either blocked or confirm -->
                    <div id="confirmSection" style="display:none; margin-top:1rem;"></div>


                </div><!-- /content-card -->
            </div><!-- /excel-panel -->
            <div class="tab-panel" id="history-panel">
                  <!-- Header -->
    <div style="display:flex;align-items:center;justify-content:space-between;
                flex-wrap:wrap;gap:0.75rem;margin-bottom:1.25rem;">
        <div style="display:flex;align-items:center;gap:0.6rem;flex-wrap:wrap;">
            <i class='bx bx-history' style="font-size:1.4rem;color:#3b82f6;"></i>
            <h3 style="margin:0;font-size:1.05rem;font-weight:600;color:#1e293b;">Asset Add History</h3>
            <span style="background:#eff6ff;color:#2563eb;border-radius:20px;
                padding:2px 10px;font-size:0.75rem;font-weight:600;">Manual + Excel</span>
        </div>
        <button onclick="loadHistory(1)" class="btn btn-outline" style="font-size:0.82rem;padding:0.4rem 0.9rem;">
            <i class='bx bx-refresh'></i> Refresh
        </button>
    </div>
 
    <!-- Filters -->
    <div style="display:flex;flex-wrap:wrap;gap:0.75rem;margin-bottom:1.25rem;
                padding:1rem;background:#f8fafc;border-radius:10px;border:1px solid #e2e8f0;">
 
        <div style="display:flex;flex-direction:column;gap:0.3rem;flex:1;min-width:140px;">
            <label style="font-size:0.78rem;font-weight:600;color:#64748b;">Date From</label>
            <input type="date" id="hFilterFrom"
                style="border:1px solid #e2e8f0;border-radius:7px;padding:0.4rem 0.6rem;font-size:0.85rem;font-family:inherit;">
        </div>
 
        <div style="display:flex;flex-direction:column;gap:0.3rem;flex:1;min-width:140px;">
            <label style="font-size:0.78rem;font-weight:600;color:#64748b;">Date To</label>
            <input type="date" id="hFilterTo"
                style="border:1px solid #e2e8f0;border-radius:7px;padding:0.4rem 0.6rem;font-size:0.85rem;font-family:inherit;">
        </div>
 
        <div style="display:flex;flex-direction:column;gap:0.3rem;flex:1;min-width:140px;">
            <label style="font-size:0.78rem;font-weight:600;color:#64748b;">Source</label>
            <select id="hFilterSource"
                style="border:1px solid #e2e8f0;border-radius:7px;padding:0.4rem 0.6rem;font-size:0.85rem;font-family:inherit;background:#fff;">
                <option value="">All Sources</option>
                <option value="MANUAL">Manual Entry</option>
                <option value="EXCEL">Excel Upload</option>
            </select>
        </div>
 
 <?php if ($_SESSION['role'] === 'ADMIN'): ?>
<div style="display:flex;flex-direction:column;gap:0.3rem;flex:1;min-width:150px;">
    <label style="font-size:0.78rem;font-weight:600;color:#64748b;">Uploaded By</label>
    <select id="hFilterUser" style="
       padding:0.4rem 0.6rem;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 0.875rem;
        color: #374151;
        background: white;
        cursor: pointer;
        outline: none;
        width: 100%;
    ">
        <option value="">All Users</option>
    </select>
</div>
<?php else: ?>
<select id="hFilterUser" style="display:none;">
    <option value="">All Users</option>
</select>
<?php endif; ?>
 
        <div style="display:flex;flex-direction:column;gap:0.3rem;flex:2;min-width:180px;">
            <label style="font-size:0.78rem;font-weight:600;color:#64748b;">Search</label>
            <input type="text" id="hFilterSearch" placeholder="Brand, model, filename..."
                style="border:1px solid #e2e8f0;border-radius:7px;padding:0.4rem 0.6rem;font-size:0.85rem;font-family:inherit;">
        </div>
 
        <div style="display:flex;align-items:flex-end;gap:0.5rem;">
            <button onclick="loadHistory(1)" class="btn btn-primary" style="font-size:0.83rem;padding:0.45rem 1.1rem;">
                <i class='bx bx-search'></i> Search
            </button>
            <button onclick="clearHistoryFilters()" class="btn btn-secondary" style="font-size:0.83rem;padding:0.45rem 0.9rem;">
                <i class='bx bx-x'></i> Clear
            </button>
        </div>
    </div>
 
    <!-- Table -->
    <div style="overflow-x:auto;border-radius:10px;border:1px solid #e5e7eb;">
        <table style="width:100%;border-collapse:collapse;font-size:0.83rem;">
            <thead>
                <tr style="background:#f3f4f6;">
                    <th style="padding:0.65rem 0.85rem;text-align:left;font-weight:600;color:#374151;border-bottom:1px solid #e5e7eb;white-space:nowrap;">#</th>
                    <th style="padding:0.65rem 0.85rem;text-align:center;font-weight:600;color:#374151;border-bottom:1px solid #e5e7eb;white-space:nowrap;">Source</th>
                    <th style="padding:0.65rem 0.85rem;text-align:left;font-weight:600;color:#374151;border-bottom:1px solid #e5e7eb;white-space:nowrap;">Description / Filename</th>
                    <th style="padding:0.65rem 0.85rem;text-align:left;font-weight:600;color:#374151;border-bottom:1px solid #e5e7eb;white-space:nowrap;">Added By</th>
                    <th style="padding:0.65rem 0.85rem;text-align:center;font-weight:600;color:#374151;border-bottom:1px solid #e5e7eb;white-space:nowrap;">Total</th>
                    <th style="padding:0.65rem 0.85rem;text-align:center;font-weight:600;color:#374151;border-bottom:1px solid #e5e7eb;white-space:nowrap;">Valid</th>
                    <th style="padding:0.65rem 0.85rem;text-align:center;font-weight:600;color:#374151;border-bottom:1px solid #e5e7eb;white-space:nowrap;">Invalid</th>
                    <th style="padding:0.65rem 0.85rem;text-align:center;font-weight:600;color:#374151;border-bottom:1px solid #e5e7eb;white-space:nowrap;">Status</th>
                    <th style="padding:0.65rem 0.85rem;text-align:left;font-weight:600;color:#374151;border-bottom:1px solid #e5e7eb;white-space:nowrap;">Date & Time</th>
                    <th style="padding:0.65rem 0.85rem;text-align:center;font-weight:600;color:#374151;border-bottom:1px solid #e5e7eb;white-space:nowrap;">Action</th>
                </tr>
            </thead>
            <tbody id="historyTableBody">
                <tr>
                    <td colspan="10" style="padding:2rem;text-align:center;color:#9ca3af;">
                        <i class='bx bx-loader-alt bx-spin' style="font-size:1.5rem;"></i>
                        <p style="margin:0.5rem 0 0;">Loading history...</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
 
    <!-- Pagination -->
    <div id="historyPagination"
        style="display:flex;align-items:center;justify-content:space-between;
               flex-wrap:wrap;gap:0.75rem;margin-top:1rem;font-size:0.83rem;color:#6b7280;">
    </div>
 
</div><!-- /historyCard -->
 
 
<!-- ══════════════════════════════════════════════════════════════════════════
     BATCH / ASSET DETAIL MODAL
     ══════════════════════════════════════════════════════════════════════════ -->
<div id="batchModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);
     z-index:9000;overflow-y:auto;padding:clamp(0.5rem,3vw,2rem) clamp(0.5rem,2vw,1rem);">
    <div style="background:#fff;border-radius:14px;max-width:1100px;margin:0 auto;
                box-shadow:0 20px 60px rgba(0,0,0,0.25);overflow:hidden;width:100%;">
 
        <div style="display:flex;align-items:center;justify-content:space-between;
                    padding:1.1rem 1.5rem;border-bottom:1px solid #e5e7eb;background:#f8fafc;">
            <div style="display:flex;align-items:center;gap:0.6rem;">
                <i class='bx bx-file-blank' style="font-size:1.3rem;color:#3b82f6;"></i>
                <div>
                    <h3 id="modalTitle" style="margin:0;font-size:1rem;font-weight:700;color:#1e293b;"></h3>
                    <p id="modalSubtitle" style="margin:0;font-size:0.78rem;color:#6b7280;"></p>
                </div>
            </div>
            <button onclick="closeBatchModal()"
                style="background:none;border:none;font-size:1.4rem;color:#64748b;cursor:pointer;padding:0.25rem;">
                <i class='bx bx-x'></i>
            </button>
        </div>
 
        <div style="padding:1.25rem 1.5rem;max-height:min(75vh, 600px);overflow-y:auto;" id="modalBody">
            <div style="text-align:center;padding:2rem;color:#9ca3af;">
                <i class='bx bx-loader-alt bx-spin' style="font-size:2rem;"></i>
                <p style="margin:0.5rem 0 0;">Loading...</p>
            </div>
        </div>
    </div>
</div>

        </div><!-- /page-container -->
    </section>

    <script src="js/upload-asses.js"></script>
    <script>
        // ── Sidebar collapse responsive sync ─────────────────────────────────
        (function syncSidebarState() {
            const isMobile = window.innerWidth <= 768;
            const homeEl   = document.querySelector('.home');
            if (!homeEl) return;

            if (isMobile) {
                homeEl.style.left  = '0';
                homeEl.style.width = '100%';
            }

            // Watch for sidebar toggle class changes (if sidebar.php uses body class or sidebar class)
            const observer = new MutationObserver(() => {
                if (window.innerWidth <= 768) return; // skip on mobile; CSS handles it
                const sidebar = document.querySelector('.sidebar');
                if (!sidebar) return;
                const isCollapsed = sidebar.classList.contains('close') || sidebar.classList.contains('collapsed');
                homeEl.style.left  = isCollapsed ? '78px' : '260px';
                homeEl.style.width = isCollapsed ? 'calc(100% - 78px)' : 'calc(100% - 260px)';
            });

            const sidebar = document.querySelector('.sidebar');
            if (sidebar) observer.observe(sidebar, { attributes: true, attributeFilter: ['class'] });

            // Also handle window resize
            window.addEventListener('resize', () => {
                if (window.innerWidth <= 768) {
                    homeEl.style.left  = '0';
                    homeEl.style.width = '100%';
                } else {
                    const sb = document.querySelector('.sidebar');
                    const collapsed = sb && (sb.classList.contains('close') || sb.classList.contains('collapsed'));
                    homeEl.style.left  = collapsed ? '78px' : '260px';
                    homeEl.style.width = collapsed ? 'calc(100% - 78px)' : 'calc(100% - 260px)';
                }
            });
        })();
        // ── Hidden tracking type ──────────────────────────────────────────────
        document.querySelectorAll('input[name="tracking_type"]').forEach(el => el.removeAttribute('required'));
        const trackingInput = document.createElement('input');
        trackingInput.type  = 'hidden';
        trackingInput.name  = 'tracking_type';
        trackingInput.value = 'BULK';
        document.getElementById('manualAssetForm').appendChild(trackingInput);

        // ── Manual form validation ────────────────────────────────────────────
        const manualForm   = document.getElementById('manualAssetForm');
        const invalidStyle = `border:2px solid #d63031!important;background:#fff5f5!important;box-shadow:0 0 0 3px rgba(214,48,49,0.15)!important;`;

        function setInvalid(el) { el.setAttribute('style', invalidStyle); el.classList.add('field-invalid'); }
        function setValid(el)   { el.removeAttribute('style'); el.classList.remove('field-invalid'); }

        manualForm.querySelectorAll('input,select,textarea').forEach(el => {
            el.addEventListener('input',  () => setValid(el));
            el.addEventListener('change', () => setValid(el));
        });

        manualForm.addEventListener('submit', function(e) {
            const fields = Array.from(manualForm.querySelectorAll('[required]'))
                .filter(el => el.type !== 'hidden' && el.name !== 'tracking_type');
            let hasError = false;
            fields.forEach(el => {
                if (!el.value.trim()) { e.preventDefault(); setInvalid(el); hasError = true; }
                else setValid(el);
            });
            if (hasError) {
                const first = manualForm.querySelector('.field-invalid');
                if (first) first.scrollIntoView({ behavior:'smooth', block:'center' });
                const t = document.createElement('div');
                t.style.cssText = 'position:fixed;top:20px;right:20px;padding:1rem 1.5rem;background:#d63031;color:white;border-radius:10px;font-weight:500;box-shadow:0 4px 16px rgba(0,0,0,0.2);z-index:10000;font-family:inherit;';
                t.textContent = '⚠️ Please fill in all required fields.';
                document.body.appendChild(t);
                setTimeout(() => t.remove(), 3500);
            }
        });

        // ════════════════════════════════════════════════════════════════════════
        // EXCEL UPLOAD
        // ════════════════════════════════════════════════════════════════════════
        let stagingToken = null;

        document.getElementById('excel_file').addEventListener('change', function () {
            const file = this.files[0];
            if (!file) return;
            document.querySelector('.drop-zone-content').style.display = 'none';
            const info = document.getElementById('fileInfo');
            info.style.display = 'flex';
            info.querySelector('.file-name').textContent = file.name;
            info.querySelector('.file-size').textContent = (file.size / 1024 / 1024).toFixed(2) + ' MB';
            document.getElementById('uploadResults').style.display  = 'none';
            document.getElementById('confirmSection').style.display = 'none';
            stagingToken = null;
        });

        function clearExcelFile() {
            document.getElementById('excel_file').value = '';
            document.getElementById('fileInfo').style.display          = 'none';
            document.querySelector('.drop-zone-content').style.display = '';
            document.getElementById('uploadResults').style.display     = 'none';
            document.getElementById('confirmSection').style.display    = 'none';
            stagingToken = null;
        }

        function escHtml(str) {
            if (str === null || str === undefined) return '—';
            return String(str)
                .replace(/&/g,'&amp;').replace(/</g,'&lt;')
                .replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
        }

        // ── STEP 1: Upload & Validate ─────────────────────────────────────────
        document.getElementById('excelUploadForm').addEventListener('submit', async function (e) {
            e.preventDefault();
            const file = document.getElementById('excel_file').files[0];
            if (!file) { alert('Please select a file first.'); return; }

            const progressEl = document.getElementById('uploadProgress');
            const resultsDiv = document.getElementById('uploadResults');
            const confirmSec = document.getElementById('confirmSection');
            const fill       = document.getElementById('progressFill');
            const uploadBtn  = document.getElementById('uploadBtn');

            progressEl.style.display = 'block';
            resultsDiv.style.display = 'none';
            confirmSec.style.display = 'none';
            uploadBtn.disabled       = true;
            stagingToken             = null;

            let pct   = 0;
            const timer = setInterval(() => {
                pct = Math.min(pct + Math.random() * 8, 90);
                fill.style.width = pct + '%';
            }, 300);

            const formData = new FormData();
            formData.append('excel_file', file);

            try {
                const res    = await fetch('upload-excel.php', { method: 'POST', body: formData });
                const result = await res.json();

                clearInterval(timer);
                fill.style.width = '100%';
                setTimeout(() => { progressEl.style.display = 'none'; fill.style.width = '0'; }, 500);

                resultsDiv.style.display = 'block';

                if (!result.success) {
                    resultsDiv.innerHTML = `<div class="alert alert-danger">❌ <strong>Error:</strong> ${escHtml(result.error)}</div>`;
                    uploadBtn.disabled   = false;
                    return;
                }

                stagingToken = result.token;
                const hasErrors = result.invalid_rows > 0;
                const allValid  = !hasErrors && result.valid_rows > 0;

                // ── Summary bar ───────────────────────────────────────────────
                let html = `
                <div class="preview-summary">
                    <div class="summary-stat ok"><i class='bx bx-check-circle'></i> <strong>${result.valid_rows}</strong> valid rows</div>
                    <div class="summary-stat ${hasErrors ? 'err' : 'ok'}">
                        <i class='bx bx-${hasErrors ? 'error' : 'check-circle'}'></i>
                        <strong>${result.invalid_rows}</strong> rows with errors
                    </div>
                    <div class="summary-stat info"><i class='bx bx-table'></i> <strong>${result.total_rows}</strong> total rows</div>
                </div>`;

                // ── Preview table ─────────────────────────────────────────────
                if (result.preview && result.preview.length > 0) {
                    html += `
                    <div class="preview-table-wrap">
                      <table class="preview-table">
                        <thead><tr>
                          <th>Row</th><th>Category</th><th>Sub-Category</th>
                          <th>Brand</th><th>Model</th><th>Serial</th>
                          <th>Qty</th><th>Condition</th><th>Status</th>
                          <th>Location</th><th>Sub-Location</th>
                          <th>Photo 1</th><th>Photo 2</th><th>Photo 3</th><th>Issues</th>
                        </tr></thead><tbody>`;

                    result.preview.forEach(r => {
                        const rowClass = r.is_valid ? 'row-valid' : 'row-invalid';
                        function makePhotoLink(url, n) {
                            if (!url) return '—';
                            return `<a href="${escHtml(url)}" target="_blank" rel="noopener noreferrer"
                                style="display:inline-flex;align-items:center;gap:4px;color:#1a73e8;font-weight:500;
                                text-decoration:none;background:#e8f0fe;padding:3px 8px;border-radius:6px;
                                font-size:0.78rem;white-space:nowrap;">
                                <i class='bx bxl-google' style="font-size:1rem;"></i> Photo ${n}</a>`;
                        }
                        const issues = r.is_valid
                            ? '<span style="color:#28a745;font-weight:600;">✓ OK</span>'
                            : r.errors.map(err => `<span class="error-tag">${escHtml(err)}</span>`).join('');

                        html += `<tr class="${rowClass}">
                            <td>${r.row}</td>
                            <td>${escHtml(r.category)}</td><td>${escHtml(r.sub_category)}</td>
                            <td>${escHtml(r.brand)}</td><td>${escHtml(r.model)}</td>
                            <td>${escHtml(r.serial)}</td><td>${r.qty}</td>
                            <td>${escHtml(r.condition)}</td><td>${escHtml(r.status)}</td>
                            <td>${escHtml(r.location)}</td><td>${escHtml(r.sub_location)}</td>
                            <td>${makePhotoLink(r.photo_drive_url_1, 1)}</td>
                            <td>${makePhotoLink(r.photo_drive_url_2, 2)}</td>
                            <td>${makePhotoLink(r.photo_drive_url_3, 3)}</td>
                            <td>${issues}</td>
                        </tr>`;
                    });

                    html += `</tbody></table></div>`;
                }

                resultsDiv.innerHTML = html;

                // ── STEP 2 action section ─────────────────────────────────────
                confirmSec.style.display = 'block';

                if (hasErrors) {
                    // ❌ BLOCKED — must fix errors first
                    confirmSec.innerHTML = `
                    <div class="blocked-box">
                        <i class='bx bx-block'></i>
                        <div>
                            <h4>Import Blocked — Please Fix All Errors First</h4>
                            <p>
                                <strong>${result.invalid_rows} row(s)</strong> have errors highlighted in red above.
                                All rows must be valid before you can import.<br>
                                Please correct your Excel file and re-upload.
                            </p>
                        </div>
                    </div>`;
                    stagingToken = null; // prevent any accidental import

                } else if (allValid) {
                    // ✅ ALL VALID — show confirm button
                    confirmSec.innerHTML = `
                    <div class="confirm-box">
                        <div class="confirm-info">
                            <i class='bx bx-import' style="font-size:2rem; color:#3b82f6;"></i>
                            <div>
                                <strong>All ${result.valid_rows} rows are valid and ready to import.</strong>
                                <div style="color:#15803d;font-size:0.875rem;margin-top:0.25rem;">
                                    ✅ No errors found — you can safely proceed.
                                </div>
                            </div>
                        </div>
                        <div class="confirm-actions">
                            <button type="button" class="btn btn-secondary" onclick="clearExcelFile()">
                                <i class='bx bx-x'></i> Cancel
                            </button>
                            <button type="button" id="confirmImportBtn" class="btn btn-primary">
                                <i class='bx bx-check-circle'></i> <span>Confirm Import</span>
                            </button>
                        </div>
                    </div>`;

                    // Attach confirm handler
                    document.getElementById('confirmImportBtn').addEventListener('click', confirmImport);

                } else {
                    // No rows at all
                    confirmSec.innerHTML = `<div class="alert alert-danger">❌ No valid rows found to import.</div>`;
                    stagingToken = null;
                }

            } catch (err) {
                clearInterval(timer);
                progressEl.style.display = 'none';
                resultsDiv.innerHTML     = `<div class="alert alert-danger">❌ Upload failed: ${escHtml(err.message)}</div>`;
                resultsDiv.style.display = 'block';
            }

            uploadBtn.disabled = false;
        });

        // ── STEP 2: Confirm & Import ──────────────────────────────────────────
        async function confirmImport() {
            if (!stagingToken) { alert('No staged data found. Please re-upload.'); return; }

            const btn = document.getElementById('confirmImportBtn');
            btn.disabled  = true;
            btn.innerHTML = `<i class='bx bx-loader-alt bx-spin'></i> <span>Importing...</span>`;

            try {
                const res    = await fetch('confirm-import.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ token: stagingToken })
                });
                const result = await res.json();

                const confirmSec = document.getElementById('confirmSection');
                const resultsDiv = document.getElementById('uploadResults');

                if (result.success) {
                    confirmSec.innerHTML = `
                    <div class="alert alert-success">
                        ✅ <strong>Import Successful!</strong> ${escHtml(result.message)}
                    </div>`;

                    const toast = document.createElement('div');
                    toast.style.cssText = 'position:fixed;top:20px;right:20px;padding:1rem 1.5rem;background:#16a34a;color:white;border-radius:10px;font-weight:500;box-shadow:0 4px 16px rgba(0,0,0,0.2);z-index:10000;display:flex;align-items:center;gap:0.5rem;';
                    toast.innerHTML = `<i class='bx bx-check-circle' style="font-size:1.25rem;"></i> Assets imported successfully!`;
                    document.body.appendChild(toast);
                    setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 400); }, 4000);

                    stagingToken = null;
                    setTimeout(() => clearExcelFile(), 3000);
                } else {
                    confirmSec.insertAdjacentHTML('afterbegin',
                        `<div class="alert alert-danger" style="margin-bottom:1rem;">❌ <strong>Import failed:</strong> ${escHtml(result.error)}</div>`);
                    btn.disabled  = false;
                    btn.innerHTML = `<i class='bx bx-check-circle'></i> <span>Confirm Import</span>`;
                }
            } catch (err) {
                document.getElementById('confirmSection').insertAdjacentHTML('afterbegin',
                    `<div class="alert alert-danger" style="margin-bottom:1rem;">❌ Confirm failed: ${escHtml(err.message)}</div>`);
                btn.disabled  = false;
                btn.innerHTML = `<i class='bx bx-check-circle'></i> <span>Confirm Import</span>`;
            }
        }
    </script>
    <script>
// ─── Load user filter dropdown ────────────────────────────────────────────────
(async function loadHistoryUsers() {
    try {
        const res  = await fetch('get-upload-history.php?mode=users');
        const data = await res.json();
        if (!data.success) return;
        const sel = document.getElementById('hFilterUser');
        data.users.forEach(u => {
            const opt       = document.createElement('option');
            opt.value       = u.uploaded_by;
            opt.textContent = u.uploaded_by_name;
            sel.appendChild(opt);
        });
    } catch (_) {}
})();
 
// ─── Source badge helper ──────────────────────────────────────────────────────
function sourceBadge(type) {
    if (type === 'MANUAL') {
        return `<span style="display:inline-flex;align-items:center;gap:3px;
            background:#f0fdf4;color:#15803d;border-radius:6px;
            padding:3px 8px;font-size:0.73rem;font-weight:600;white-space:nowrap;">
            <i class='bx bx-edit-alt'></i>Manual</span>`;
    }
    return `<span style="display:inline-flex;align-items:center;gap:3px;
        background:#eff6ff;color:#2563eb;border-radius:6px;
        padding:3px 8px;font-size:0.73rem;font-weight:600;white-space:nowrap;">
        <i class='bx bxs-file-import'></i>Excel</span>`;
}
 
function statusBadge(s) {
    const map = {
        SUCCESS: ['#dcfce7','#15803d','bx-check-circle'],
        FAILED:  ['#fee2e2','#b91c1c','bx-error'],
        BLOCKED: ['#fff7ed','#c2410c','bx-block'],
    };
    const [bg, color, icon] = map[s] || ['#f3f4f6','#6b7280','bx-minus'];
    return `<span style="display:inline-flex;align-items:center;gap:4px;
        background:${bg};color:${color};border-radius:6px;
        padding:3px 8px;font-size:0.75rem;font-weight:600;white-space:nowrap;">
        <i class='bx ${icon}'></i>${s}</span>`;
}
 
function fmtDate(d) {
    const dt = new Date(d);
    return dt.toLocaleDateString('en-PH', { month:'short', day:'numeric', year:'numeric' })
        + ' ' + dt.toLocaleTimeString('en-PH', { hour:'2-digit', minute:'2-digit' });
}
 
// ─── Load history list ────────────────────────────────────────────────────────
async function loadHistory(page = 1) {
    const tbody = document.getElementById('historyTableBody');
    tbody.innerHTML = `<tr><td colspan="10" style="padding:2rem;text-align:center;color:#9ca3af;">
        <i class='bx bx-loader-alt bx-spin' style="font-size:1.5rem;"></i>
        <p style="margin:0.5rem 0 0;">Loading...</p></td></tr>`;
 
    const params = new URLSearchParams({
        page,
        per_page:    10,
        date_from:   document.getElementById('hFilterFrom').value,
        date_to:     document.getElementById('hFilterTo').value,
        source_type: document.getElementById('hFilterSource').value,
        uploaded_by: document.getElementById('hFilterUser').value,
        search:      document.getElementById('hFilterSearch').value,
    });
 
    try {
        const res  = await fetch('get-upload-history.php?' + params.toString());
        const data = await res.json();
 
        if (!data.success) {
            tbody.innerHTML = `<tr><td colspan="10" style="padding:2rem;text-align:center;color:#ef4444;">
                Failed to load history.</td></tr>`;
            return;
        }
 
        if (!data.data.length) {
            tbody.innerHTML = `<tr><td colspan="10" style="padding:2.5rem;text-align:center;color:#9ca3af;">
                <i class='bx bx-inbox' style="font-size:2rem;display:block;margin-bottom:0.5rem;"></i>
                No history found.</td></tr>`;
            renderHistoryPagination(data);
            return;
        }
 
        let rows = '';
        data.data.forEach((h, i) => {
            const rowNum = (page - 1) * 10 + i + 1;
            rows += `
            <tr style="border-bottom:1px solid #f3f4f6;"
                onmouseover="this.style.background='#f8fafc'"
                onmouseout="this.style.background=''">
                <td style="padding:0.6rem 0.85rem;color:#94a3b8;font-weight:600;">${rowNum}</td>
                <td style="padding:0.6rem 0.85rem;text-align:center;">${sourceBadge(h.source_type)}</td>
                <td style="padding:0.6rem 0.85rem;">
                    <div style="display:flex;align-items:center;gap:0.45rem;">
                        <i class='bx ${h.source_type === 'MANUAL' ? 'bx-edit-alt' : 'bxs-file'}'
                            style="color:#64748b;font-size:1rem;flex-shrink:0;"></i>
                        <span style="font-weight:500;color:#1e293b;word-break:break-all;">
                            ${escHtml(h.filename)}
                        </span>
                    </div>
                </td>
                <td style="padding:0.6rem 0.85rem;white-space:nowrap;">
                    <div style="display:flex;align-items:center;gap:0.4rem;">
                        <i class='bx bx-user' style="color:#94a3b8;"></i>
                        ${escHtml(h.uploaded_by_name)}
                    </div>
                </td>
                <td style="padding:0.6rem 0.85rem;text-align:center;font-weight:600;">${h.total_rows}</td>
                <td style="padding:0.6rem 0.85rem;text-align:center;">
                    <span style="color:#15803d;font-weight:600;">${h.valid_rows}</span>
                </td>
                <td style="padding:0.6rem 0.85rem;text-align:center;">
                    <span style="color:${h.invalid_rows > 0 ? '#b91c1c' : '#9ca3af'};font-weight:600;">
                        ${h.invalid_rows}
                    </span>
                </td>
                <td style="padding:0.6rem 0.85rem;text-align:center;">${statusBadge(h.status)}</td>
                <td style="padding:0.6rem 0.85rem;white-space:nowrap;color:#64748b;">${fmtDate(h.imported_at)}</td>
               <td style="padding:0.6rem 0.85rem;text-align:center;">
    <button
        data-token="${escHtml(h.batch_token)}"
        data-filename="${escHtml(h.filename)}"
        data-date="${escHtml(fmtDate(h.imported_at))}"
        data-uploader="${escHtml(h.uploaded_by_name)}"
        data-source="${escHtml(h.source_type)}"
        onclick="openBatchModal(
            this.dataset.token,
            this.dataset.filename,
            this.dataset.date,
            this.dataset.uploader,
            this.dataset.source
        )"
        style="display:inline-flex;align-items:center;gap:4px;
            background:#eff6ff;color:#2563eb;border:none;border-radius:7px;
            padding:5px 12px;font-size:0.8rem;font-weight:600;
            cursor:pointer;white-space:nowrap;font-family:inherit;">
        <i class='bx bx-show'></i> View
    </button>
</td>
            </tr>`;
        });
 
        tbody.innerHTML = rows;
        renderHistoryPagination(data);
 
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="10" style="padding:2rem;text-align:center;color:#ef4444;">
            Error: ${escHtml(err.message)}</td></tr>`;
    }
}
 
// ─── Pagination ───────────────────────────────────────────────────────────────
function renderHistoryPagination(data) {
    const el = document.getElementById('historyPagination');
    if (!data.total) { el.innerHTML = ''; return; }
 
    const start = (data.page - 1) * data.per_page + 1;
    const end   = Math.min(data.page * data.per_page, data.total);
 
    const btnStyle = (disabled, active) => `style="border:1px solid ${active ? '#3b82f6' : '#e5e7eb'};
        background:${active ? '#3b82f6' : '#fff'};
        color:${active ? '#fff' : disabled ? '#d1d5db' : '#374151'};
        border-radius:6px;padding:4px 10px;font-size:0.8rem;
        cursor:${disabled ? 'not-allowed' : 'pointer'};font-family:inherit;"`;
 
    let pages = '';
    for (let p = 1; p <= data.total_pages; p++) {
        if (data.total_pages > 7 && Math.abs(p - data.page) > 2 && p !== 1 && p !== data.total_pages) {
            if (p === 2 || p === data.total_pages - 1) pages += `<span style="padding:0 4px;">…</span>`;
            continue;
        }
        pages += `<button onclick="loadHistory(${p})" ${btnStyle(false, p === data.page)}>${p}</button>`;
    }
 
    el.innerHTML = `
        <span>Showing <strong>${start}–${end}</strong> of <strong>${data.total}</strong> entries</span>
        <div style="display:flex;align-items:center;gap:0.3rem;flex-wrap:wrap;">
            <button onclick="loadHistory(${data.page - 1})" ${data.page <= 1 ? 'disabled' : ''}
                ${btnStyle(data.page <= 1, false)}>‹ Prev</button>
            ${pages}
            <button onclick="loadHistory(${data.page + 1})" ${data.page >= data.total_pages ? 'disabled' : ''}
                ${btnStyle(data.page >= data.total_pages, false)}>Next ›</button>
        </div>`;
}
 
// ─── Clear filters ────────────────────────────────────────────────────────────
function clearHistoryFilters() {
    ['hFilterFrom','hFilterTo','hFilterSource','hFilterUser','hFilterSearch']
        .forEach(id => document.getElementById(id).value = '');
    loadHistory(1);
}
 
// ─── Open detail modal ────────────────────────────────────────────────────────
async function openBatchModal(token, filename, date, uploader, sourceType) {
    document.getElementById('batchModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
 
    const typeLabel = sourceType === 'MANUAL' ? '🖊 Manual Entry' : '📋 Excel Upload';
    document.getElementById('modalTitle').textContent    = filename;
    document.getElementById('modalSubtitle').textContent =
        `${typeLabel} · Added by ${uploader} · ${date}`;
 
    document.getElementById('modalBody').innerHTML = `
        <div style="text-align:center;padding:2.5rem;color:#9ca3af;">
            <i class='bx bx-loader-alt bx-spin' style="font-size:2rem;"></i>
            <p style="margin:0.5rem 0 0;">Loading details...</p>
        </div>`;
 
    try {
        const res  = await fetch(`get-upload-history.php?mode=batch_detail&token=${encodeURIComponent(token)}`);
        const data = await res.json();
 
        if (!data.success || !data.rows.length) {
            document.getElementById('modalBody').innerHTML =
                `<p style="text-align:center;color:#9ca3af;padding:2rem;">No data found.</p>`;
            return;
        }
 
        const condBadge = c => {
            const map = { NEW: ['#dcfce7','#15803d'], USED: ['#fef9c3','#854d0e'] };
            const [bg, color] = map[c] || ['#f3f4f6','#374151'];
            return `<span style="background:${bg};color:${color};border-radius:5px;padding:2px 7px;font-size:0.75rem;font-weight:600;">${c||'—'}</span>`;
        };
        const statBadge = s => {
            const map = { WORKING:['#dcfce7','#15803d'], 'FOR CHECKING':['#fef9c3','#854d0e'], 'NOT WORKING':['#fee2e2','#b91c1c'] };
            const [bg, color] = map[s] || ['#f3f4f6','#374151'];
            return `<span style="background:${bg};color:${color};border-radius:5px;padding:2px 7px;font-size:0.75rem;font-weight:600;">${s||'—'}</span>`;
        };
        const photoLink = (url, n) => {
            if (!url) return '';
            // Handle local file paths (manual uploads) vs drive URLs
            const isLocal = !url.startsWith('http');
            if (isLocal) {
                return `<a href="../../${url}" target="_blank" rel="noopener noreferrer"
                    style="display:inline-flex;align-items:center;gap:3px;color:#374151;
                    background:#f3f4f6;padding:3px 8px;border-radius:5px;font-size:0.75rem;
                    font-weight:500;text-decoration:none;margin:2px;">
                    <i class='bx bx-image'></i>Photo ${n}</a>`;
            }
            return `<a href="${escHtml(url)}" target="_blank" rel="noopener noreferrer"
                style="display:inline-flex;align-items:center;gap:3px;color:#1a73e8;
                background:#e8f0fe;padding:3px 8px;border-radius:5px;font-size:0.75rem;
                font-weight:500;text-decoration:none;margin:2px;">
                <i class='bx bxl-google'></i>Photo ${n}</a>`;
        };
 
        // Summary strip
        const totalRows = data.rows.length;
        const validRows = data.rows.filter(r => r.is_valid == 1).length;
        const invRows   = totalRows - validRows;
 
        let html = `
        <div style="display:flex;gap:0.75rem;flex-wrap:wrap;margin-bottom:1.25rem;">
            ${sourceBadge(data.source_type)}
            <div style="background:#eff6ff;color:#1d4ed8;border-radius:8px;padding:0.4rem 1rem;
        font-size:0.85rem;font-weight:600;display:flex;align-items:center;gap:0.4rem;">
        <i class='bx bx-table'></i> ${totalRows} total
    </div>
        </div>
        <div style="overflow-x:auto;border-radius:10px;border:1px solid #e5e7eb;">
        <table style="width:100%;border-collapse:collapse;font-size:0.8rem;">
            <thead><tr style="background:#f3f4f6;">
                <th style="padding:0.55rem 0.7rem;text-align:left;border-bottom:1px solid #e5e7eb;font-weight:600;color:#374151;white-space:nowrap;">Row</th>
                <th style="padding:0.55rem 0.7rem;text-align:left;border-bottom:1px solid #e5e7eb;font-weight:600;color:#374151;white-space:nowrap;">Category</th>
                <th style="padding:0.55rem 0.7rem;text-align:left;border-bottom:1px solid #e5e7eb;font-weight:600;color:#374151;white-space:nowrap;">Sub-Category</th>
                <th style="padding:0.55rem 0.7rem;text-align:left;border-bottom:1px solid #e5e7eb;font-weight:600;color:#374151;white-space:nowrap;">Brand</th>
                <th style="padding:0.55rem 0.7rem;text-align:left;border-bottom:1px solid #e5e7eb;font-weight:600;color:#374151;white-space:nowrap;">Model</th>
                <th style="padding:0.55rem 0.7rem;text-align:left;border-bottom:1px solid #e5e7eb;font-weight:600;color:#374151;white-space:nowrap;">Serial No.</th>
                <th style="padding:0.55rem 0.7rem;text-align:center;border-bottom:1px solid #e5e7eb;font-weight:600;color:#374151;white-space:nowrap;">Qty</th>
                <th style="padding:0.55rem 0.7rem;text-align:center;border-bottom:1px solid #e5e7eb;font-weight:600;color:#374151;white-space:nowrap;">Cond.</th>
                <th style="padding:0.55rem 0.7rem;text-align:center;border-bottom:1px solid #e5e7eb;font-weight:600;color:#374151;white-space:nowrap;">Status</th>
                <th style="padding:0.55rem 0.7rem;text-align:left;border-bottom:1px solid #e5e7eb;font-weight:600;color:#374151;white-space:nowrap;">Location</th>
                <th style="padding:0.55rem 0.7rem;text-align:left;border-bottom:1px solid #e5e7eb;font-weight:600;color:#374151;white-space:nowrap;">Sub-Loc</th>
                <th style="padding:0.55rem 0.7rem;text-align:left;border-bottom:1px solid #e5e7eb;font-weight:600;color:#374151;white-space:nowrap;">Photos</th>
            
            </tr></thead>
            <tbody>`;
 
        data.rows.forEach(r => {
            
            const photos = [
                photoLink(r.photo_drive_url_1, 1),
                photoLink(r.photo_drive_url_2, 2),
                photoLink(r.photo_drive_url_3, 3),
            ].filter(Boolean).join('') || '<span style="color:#d1d5db;">—</span>';
            const notes  = r.is_valid == 1
                ? '<span style="color:#15803d;font-weight:600;">✓ OK</span>'
                : `<span style="color:#b91c1c;font-size:0.75rem;">${escHtml(r.error_notes||'')}</span>`;
 
            
html += `<tr style="border-bottom:1px solid #f3f4f6;"
                onmouseover="this.style.filter='brightness(0.97)'"
                onmouseout="this.style.filter=''">
                <td style="padding:0.5rem 0.7rem;color:#94a3b8;">${r.row_num}</td>
                <td style="padding:0.5rem 0.7rem;">${escHtml(r.category_name)||'—'}</td>
                <td style="padding:0.5rem 0.7rem;">${escHtml(r.sub_category_name)||'—'}</td>
                <td style="padding:0.5rem 0.7rem;">${escHtml(r.brand)||'—'}</td>
                <td style="padding:0.5rem 0.7rem;">${escHtml(r.model)||'—'}</td>
                <td style="padding:0.5rem 0.7rem;font-family:monospace;font-size:0.78rem;">${escHtml(r.serial_number)||'—'}</td>
                <td style="padding:0.5rem 0.7rem;text-align:center;font-weight:600;">${r.qty}</td>
                <td style="padding:0.5rem 0.7rem;text-align:center;">${condBadge(r.condition)}</td>
                <td style="padding:0.5rem 0.7rem;text-align:center;">${statBadge(r.status)}</td>
                <td style="padding:0.5rem 0.7rem;white-space:nowrap;">${escHtml(r.location)||'—'}</td>
                <td style="padding:0.5rem 0.7rem;white-space:nowrap;">${escHtml(r.sub_location)||'—'}</td>
                <td style="padding:0.5rem 0.7rem;">${photos}</td>
                
            </tr>`;
        });
 
        html += `</tbody></table></div>`;
        document.getElementById('modalBody').innerHTML = html;
 
    } catch (err) {
        document.getElementById('modalBody').innerHTML =
            `<p style="text-align:center;color:#ef4444;padding:2rem;">Error: ${escHtml(err.message)}</p>`;
    }
}
 
function closeBatchModal() {
    document.getElementById('batchModal').style.display = 'none';
    document.body.style.overflow = '';
}
 
document.getElementById('batchModal').addEventListener('click', function(e) {
    if (e.target === this) closeBatchModal();
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeBatchModal(); });
 
// ─── Auto-load when Excel tab is clicked ─────────────────────────────────────
document.querySelectorAll('.tab-button').forEach(btn => {
    btn.addEventListener('click', function() {
       if (this.dataset.tab === 'history') setTimeout(() => loadHistory(1), 100);
    });
});
if (document.querySelector('.tab-button[data-tab="history"]')?.classList.contains('active')) {
    loadHistory(1);
}
</script>

</body>
</html>