<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: landing.php");
    exit();
}

// Path to the pre-built template file (inside templates/ folder)
$templatePath = __DIR__ . '/templates/asset_upload_template.xlsx';

if (!file_exists($templatePath)) {
    http_response_code(404);
    echo '
    <!DOCTYPE html>
    <html><head>
        <title>Template Not Found</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
        <style>
            body { font-family: Inter, sans-serif; display:flex; align-items:center;
                   justify-content:center; min-height:100vh; margin:0; background:#f9fafb; }
            .box { text-align:center; background:white; padding:2.5rem 3rem;
                   border-radius:16px; box-shadow:0 4px 24px rgba(0,0,0,0.08); max-width:420px; }
            h2  { color:#dc2626; margin-bottom:0.5rem; }
            p   { color:#6b7280; margin-bottom:1.5rem; }
            a   { display:inline-block; background:#1e3a5f; color:white; padding:0.6rem 1.4rem;
                  border-radius:8px; text-decoration:none; font-weight:600; }
        </style>
    </head><body>
        <div class="box">
            <h2>⚠️ Template Not Found</h2>
            <p>The file <code>asset_upload_template.xlsx</code> is missing from the server.<br>
               Please ask your administrator to upload it to the project root.</p>
            <a href="upload-asset.php">← Go Back</a>
        </div>
    </body></html>';
    exit();
}

// Stream the file as a download
$filename = 'asset_upload_template.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($templatePath));
header('Cache-Control: max-age=0');
header('Pragma: public');
header('Expires: 0');

readfile($templatePath);
exit();

// ── OLD Python-based generator (removed — not compatible with Windows) ─────
$tmpFile = tempnam(sys_get_temp_dir(), 'inv_template_') . '.xlsx';

$script = <<<'PYEOF'
import sys
from openpyxl import Workbook
from openpyxl.styles import Font, PatternFill, Alignment, Border, Side
from openpyxl.utils import get_column_letter
from openpyxl.worksheet.datavalidation import DataValidation

output_path = sys.argv[1]

wb = Workbook()

# ═══════════════════════════════════════════════════════════
# Sheet 1: Asset Upload
# ═══════════════════════════════════════════════════════════
ws = wb.active
ws.title = "Asset Upload"

HEADER_BG  = "1E3A5F"
HEADER_FG  = "FFFFFF"
REQ_BG     = "FFF3CD"
OPT_BG     = "E8F4FD"
PHOTO_BG   = "E8F5E9"
EXAMPLE_BG = "F8F9FA"
BORDER_COL = "CCCCCC"

thin   = Side(style='thin', color=BORDER_COL)
border = Border(left=thin, right=thin, top=thin, bottom=thin)

columns = [
    ("category",         22, True,  "e.g. IT Equipment"),
    ("sub_category",     26, True,  "e.g. Laptop"),
    ("brand",            18, False, "e.g. Dell"),
    ("model",            22, False, "e.g. Latitude 5420"),
    ("serial_number",    22, False, "e.g. SN123456"),
    ("quantity",         12, True,  "Whole number >= 1"),
    ("condition",        14, True,  "NEW or USED"),
    ("status",           20, True,  "WORKING / FOR CHECKING / NOT WORKING"),
    ("location",         22, True,  "e.g. QC WareHouse"),
    ("sub_location",     20, False, "e.g. Rack A"),
    ("description",      30, False, "Any notes/specs"),
    ("photo_drive_url",  42, False, "Google Drive shared folder URL for photos"),
]

# Header row
for col_idx, (col_name, width, required, _) in enumerate(columns, 1):
    cell = ws.cell(row=1, column=col_idx, value=col_name)
    cell.font      = Font(name="Arial", bold=True, color=HEADER_FG, size=10)
    cell.fill      = PatternFill("solid", start_color=HEADER_BG)
    cell.alignment = Alignment(horizontal="center", vertical="center", wrap_text=True)
    cell.border    = border
    ws.column_dimensions[get_column_letter(col_idx)].width = width

ws.row_dimensions[1].height = 32

# Sub-header row (required/optional hints)
for col_idx, (_, _, required, note) in enumerate(columns, 1):
    label = ("* Required" if required else "o Optional") + " - " + note
    cell  = ws.cell(row=2, column=col_idx, value=label)
    if col_idx == 12:
        bg = PHOTO_BG
        fc = "1A5226"
    elif required:
        bg = REQ_BG
        fc = "7B3F00"
    else:
        bg = OPT_BG
        fc = "1A5276"
    cell.fill      = PatternFill("solid", start_color=bg)
    cell.font      = Font(name="Arial", italic=True, size=8, color=fc)
    cell.alignment = Alignment(horizontal="center", vertical="center", wrap_text=True)
    cell.border    = border

ws.row_dimensions[2].height = 28

# Sample rows
samples = [
    ["IT Equipment",      "Laptop",   "Dell",    "Latitude 5420", "SN-DELL-001", 1, "NEW",  "WORKING",      "QC WareHouse",      "Rack A",   "Core i5, 16GB RAM",  "https://drive.google.com/drive/folders/EXAMPLE_1"],
    ["IT Equipment",      "Monitor",  "Samsung", "27\" FHD",      "",            2, "USED", "FOR CHECKING", "Pulilan WareHouse", "Shelf B",  "",                   "https://drive.google.com/drive/folders/EXAMPLE_2"],
    ["Network Equipment", "Switch",   "Cisco",   "SG350-28",      "SN-CSC-099",  1, "NEW",  "WORKING",      "QC WareHouse",      "",         "28-port managed",    ""],
]
for r_idx, row_data in enumerate(samples, 3):
    for c_idx, val in enumerate(row_data, 1):
        cell = ws.cell(row=r_idx, column=c_idx, value=val)
        cell.fill      = PatternFill("solid", start_color=EXAMPLE_BG)
        cell.font      = Font(name="Arial", size=9, color="888888", italic=True)
        cell.alignment = Alignment(vertical="center")
        cell.border    = border
    ws.row_dimensions[r_idx].height = 20

# Blank data rows
for r_idx in range(6, 506):
    for c_idx in range(1, 13):
        cell = ws.cell(row=r_idx, column=c_idx, value="")
        cell.font      = Font(name="Arial", size=9)
        cell.alignment = Alignment(vertical="center")
        cell.border    = border
    ws.row_dimensions[r_idx].height = 18

# Dropdowns
dv_condition = DataValidation(type="list", formula1='"NEW,USED"', allow_blank=True)
dv_status    = DataValidation(type="list", formula1='"WORKING,FOR CHECKING,NOT WORKING"', allow_blank=True)
dv_condition.sqref = "G6:G505"
dv_status.sqref    = "H6:H505"
ws.add_data_validation(dv_condition)
ws.add_data_validation(dv_status)

ws.freeze_panes = "A3"

# ═══════════════════════════════════════════════════════════
# Sheet 2: Instructions
# ═══════════════════════════════════════════════════════════
wi = wb.create_sheet("Instructions")
wi.sheet_view.showGridLines = False
wi.column_dimensions["A"].width = 2
wi.column_dimensions["B"].width = 26
wi.column_dimensions["C"].width = 72

instructions = [
    ("", ""),
    ("COLUMN", "DESCRIPTION / RULES"),
    ("category",
     "Required. Must match exactly one of the categories in your system (case-insensitive). "
     "E.g. 'IT Equipment', 'Network Equipment', 'Security & Access Control'."),
    ("sub_category",
     "Required. Must match a sub-category that belongs to the chosen category. "
     "E.g. 'Laptop', 'Switch', 'CCTV'."),
    ("brand",    "Optional. Brand or manufacturer name. E.g. Dell, HP, Cisco, Hikvision."),
    ("model",    "Optional. Specific model name or number. E.g. Latitude 5420, SG350-28."),
    ("serial_number",
     "Optional. Must be unique — duplicates within the file or vs. existing records will be flagged."),
    ("quantity", "Required. Must be a whole number >= 1. Defaults to 1 if left blank."),
    ("condition","Required. Use the dropdown: NEW or USED."),
    ("status",   "Required. Use the dropdown: WORKING, FOR CHECKING, or NOT WORKING."),
    ("location", "Required. Main storage location. E.g. QC WareHouse, Pulilan WareHouse, CFE."),
    ("sub_location", "Optional. Specific spot within the location. E.g. Rack A, Shelf 3."),
    ("description",  "Optional. Any notes, specs, or remarks about this asset."),
    ("photo_drive_url",
     "Optional. Paste the Google Drive shared FOLDER link for this asset's photos.\n\n"
     "HOW TO GET THE LINK:\n"
     "  1. Upload your photos into a Google Drive folder.\n"
     "  2. Right-click the folder > Share > set to 'Anyone with the link can view' > Copy link.\n"
     "  3. Paste the full URL in this column.\n\n"
     "EXAMPLE:\n"
     "  https://drive.google.com/drive/folders/1AbCdEfGhIjKlMnOpQrStUvWxYz\n\n"
     "NOTE: Leave blank if no photos are available. Invalid URLs will be flagged as errors."),
]

for r_idx, (col, desc) in enumerate(instructions, 1):
    if r_idx == 2:
        for c_idx, val in [(2, col), (3, desc)]:
            cell = wi.cell(row=r_idx, column=c_idx, value=val)
            cell.font      = Font(name="Arial", bold=True, color="FFFFFF", size=10)
            cell.fill      = PatternFill("solid", start_color=HEADER_BG)
            cell.alignment = Alignment(horizontal="center", vertical="center")
            cell.border    = border
        wi.row_dimensions[r_idx].height = 24
        continue

    b = wi.cell(row=r_idx, column=2, value=col)
    c = wi.cell(row=r_idx, column=3, value=desc)

    is_photo = (col == "photo_drive_url")
    b.font = Font(name="Arial", bold=(col not in ("",)), size=9,
                  color=("1A5226" if is_photo else "000000"))
    c.font = Font(name="Arial", size=9)
    c.alignment = Alignment(wrap_text=True, vertical="top")

    if is_photo:
        b.fill = PatternFill("solid", start_color=PHOTO_BG)
        c.fill = PatternFill("solid", start_color=PHOTO_BG)
        wi.row_dimensions[r_idx].height = 110
    else:
        wi.row_dimensions[r_idx].height = 40

wb.save(output_path)
print("ok")
PYEOF;

$scriptFile = tempnam(sys_get_temp_dir(), 'tpl_script_') . '.py';
file_put_contents($scriptFile, $script);

$output = shell_exec("python3 " . escapeshellarg($scriptFile) . " " . escapeshellarg($tmpFile) . " 2>&1");
unlink($scriptFile);

if (!file_exists($tmpFile) || trim($output) !== 'ok') {
    http_response_code(500);
    echo "Failed to generate template. Detail: " . htmlspecialchars($output);
    exit();
}

// ── Stream the file to the browser ───────────────────────────────────────
$filename = 'asset_upload_template.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmpFile));
header('Cache-Control: max-age=0');
header('Pragma: public');

readfile($tmpFile);
unlink($tmpFile);
exit();