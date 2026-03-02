<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'EMPLOYEE') {
    header("Location: ../landing.php");
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        :root {
            --color-bg: #f8f9fa;
            --color-surface: #ffffff;
            --color-border: #e1e4e8;
            --color-text-primary: #1a1f36;
            --color-text-secondary: #5e6778;
            --color-text-tertiary: #8a94a6;
            --color-accent: #2c5aa0;
            --color-accent-hover: #1e4277;
            --color-success: #0ca678;
            --color-warning: #f59e0b;
            --color-danger: #dc2626;
            --color-info: #3b82f6;
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.04);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.08), 0 2px 4px -1px rgba(0, 0, 0, 0.04);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -2px rgba(0, 0, 0, 0.04);
            --radius: 8px;
            --radius-sm: 6px;
            --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'IBM Plex Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--color-bg);
            color: var(--color-text-primary);
            line-height: 1.6;
            overflow-x: hidden;
        }

        .dashboard {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            transition: margin-right 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .main-content.panel-open {
            margin-right: 420px;
        }

        header {
            background: var(--color-surface);
            border-bottom: 1px solid var(--color-border);
            padding: 1.5rem 2rem;
            box-shadow: var(--shadow-sm);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo {
            height: 50px;
            width: auto;
            object-fit: contain;
        }

    
           .logo1{
            height: 50px;
            width: auto;
            object-fit: contain;
        }


        h1 {
            font-size: 1.75rem;
            font-weight: 700;
            letter-spacing: -0.025em;
            color: var(--color-text-primary);
        }

        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            padding: 0.625rem 1rem 0.625rem 2.75rem;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-sm);
            font-size: 0.9375rem;
            width: 320px;
            transition: var(--transition);
            font-family: inherit;
            background: var(--color-bg);
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--color-accent);
            box-shadow: 0 0 0 3px rgba(44, 90, 160, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--color-text-tertiary);
        }

        .btn {
            padding: 0.625rem 1.25rem;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 0.9375rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--color-accent);
            color: white;
        }

        .btn-primary:hover {
            background: var(--color-accent-hover);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary {
            background: transparent;
            border: 1px solid var(--color-border);
            color: var(--color-text-primary);
        }

        .btn-secondary:hover {
            background: var(--color-bg);
        }

        .btn-danger {
            background: var(--color-danger);
            color: white;
        }

        .btn-danger:hover {
            background: #b91c1c;
        }

        .btn-transfer {
            background: var(--color-warning);
            color: white;
        }

        .btn-transfer:hover {
            background: #d97706;
        }

        .container {
            padding: 2rem;
            flex: 1;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--color-surface);
            padding: 1.5rem;
            border-radius: var(--radius);
            border: 1px solid var(--color-border);
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }

        .stat-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--color-text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            font-family: 'JetBrains Mono', monospace;
            letter-spacing: -0.02em;
        }

        .table-container {
            background: var(--color-surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--color-border);
            overflow: hidden;
        }

        .table-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--color-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .table-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--color-text-primary);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: var(--color-bg);
            border-bottom: 2px solid var(--color-border);
        }

        th {
            padding: 1rem 1.25rem;
            text-align: left;
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--color-text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
            cursor: pointer;
            user-select: none;
            position: relative;
            transition: var(--transition);
        }

        th:hover {
            color: var(--color-text-primary);
            background: rgba(0, 0, 0, 0.02);
        }

        th.sortable::after {
            content: '⇅';
            margin-left: 0.5rem;
            opacity: 0.3;
            font-size: 0.75rem;
        }

        th.sort-asc::after {
            content: '↑';
            opacity: 1;
            color: var(--color-accent);
        }

        th.sort-desc::after {
            content: '↓';
            opacity: 1;
            color: var(--color-accent);
        }

        td {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--color-border);
            font-size: 0.9375rem;
        }

        tbody tr {
            transition: var(--transition);
            cursor: pointer;
        }

        tbody tr:hover {
            background: var(--color-bg);
        }

        tbody tr.selected {
            background: rgba(44, 90, 160, 0.05);
        }

        .item-image {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-sm);
            object-fit: cover;
            border: 1px solid var(--color-border);
        }

            /* .item-image {
    width: 48px;
    height: 48px;
    object-fit: cover;
    display: block;
}

#inventoryBody tr {
    height: 56px;
} */

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.375rem 0.75rem;
            border-radius: 999px;
            font-size: 0.8125rem;
            font-weight: 500;
            gap: 0.375rem;
        }

        .status-badge::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
        }

        .status-active {
            background: rgba(12, 166, 120, 0.1);
            color: var(--color-success);
        }

        .status-active::before {
            background: var(--color-success);
        }

        .status-maintenance {
            background: rgba(245, 158, 11, 0.1);
            color: var(--color-warning);
        }

        .status-maintenance::before {
            background: var(--color-warning);
        }

        .status-retired {
            background: rgba(138, 148, 166, 0.1);
            color: var(--color-text-tertiary);
        }

        .status-retired::before {
            background: var(--color-text-tertiary);
        }

        .condition-badge {
            padding: 0.25rem 0.625rem;
            border-radius: var(--radius-sm);
            font-size: 0.8125rem;
            font-weight: 500;
            display: inline-block;
        }

        .condition-excellent {
            background: rgba(12, 166, 120, 0.1);
            color: var(--color-success);
        }

        .condition-good {
            background: rgba(59, 130, 246, 0.1);
            color: var(--color-info);
        }

        .condition-fair {
            background: rgba(245, 158, 11, 0.1);
            color: var(--color-warning);
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-icon {
            padding: 0.5rem;
            border: 1px solid var(--color-border);
            background: transparent;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: var(--transition);
            color: var(--color-text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-icon:hover {
            background: var(--color-bg);
            color: var(--color-text-primary);
        }

        .btn-icon.delete:hover {
            background: rgba(220, 38, 38, 0.1);
            color: var(--color-danger);
            border-color: var(--color-danger);
        }

        .side-panel {
            position: fixed;
            right: 0;
            top: 0;
            width: 420px;
            height: 100vh;
            background: var(--color-surface);
            border-left: 1px solid var(--color-border);
            box-shadow: -4px 0 12px rgba(0, 0, 0, 0.08);
            transform: translateX(100%);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-y: auto;
            z-index: 1000;
        }

        .side-panel.open {
            transform: translateX(0);
        }

        .panel-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--color-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: var(--color-surface);
            z-index: 10;
        }

        .panel-title {
            font-size: 1.125rem;
            font-weight: 600;
        }

        .close-panel {
            background: transparent;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--color-text-secondary);
            padding: 0.25rem;
            line-height: 1;
            transition: var(--transition);
        }

        .close-panel:hover {
            color: var(--color-text-primary);
        }

        .panel-content {
            padding: 1.5rem;
        }

        .large-image-container {
            width: 100%;
            height: 280px;
            border-radius: var(--radius);
            overflow: hidden;
            background: var(--color-bg);
            border: 1px solid var(--color-border);
            margin-bottom: 1.5rem;
        }

        .large-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .detail-section {
            margin-bottom: 1.5rem;
        }

        .detail-section-title {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--color-text-secondary);
            margin-bottom: 1rem;
        }

        .detail-grid {
            display: grid;
            gap: 1rem;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .detail-label {
            font-size: 0.8125rem;
            color: var(--color-text-secondary);
            font-weight: 500;
        }

        .detail-value {
            font-size: 0.9375rem;
            color: var(--color-text-primary);
            font-weight: 500;
        }

        .qr-code-container {
            background: var(--color-bg);
            padding: 1.5rem;
            border-radius: var(--radius);
            text-align: center;
            border: 1px solid var(--color-border);
        }

        #qrcode {
            display: inline-block;
            margin-bottom: 1rem;
        }

        .qr-label {
            font-size: 0.875rem;
            color: var(--color-text-secondary);
            font-family: 'JetBrains Mono', monospace;
        }

        .panel-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }

        .btn-block {
            flex: 1;
        }

        svg {
            width: 20px;
            height: 20px;
        }

        @media (max-width: 1024px) {
            .main-content.panel-open {
                margin-right: 0;
            }

            .side-panel {
                width: 100%;
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stat-card,
        tbody tr {
            animation: fadeIn 0.4s ease-out;
        }

        .stat-card:nth-child(1) { animation-delay: 0.05s; }
        .stat-card:nth-child(2) { animation-delay: 0.1s; }
        .stat-card:nth-child(3) { animation-delay: 0.15s; }
        .stat-card:nth-child(4) { animation-delay: 0.2s; }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: var(--color-surface);
            border-radius: var(--radius);
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-lg);
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--color-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .form-grid {
            display: grid;
            gap: 1.25rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--color-text-primary);
        }

        .form-label.required::after {
            content: '*';
            color: var(--color-danger);
            margin-left: 0.25rem;
        }

        .form-input,
        .form-select {
            padding: 0.75rem;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-sm);
            font-size: 0.9375rem;
            font-family: inherit;
            transition: var(--transition);
            background: var(--color-surface);
        }

        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: var(--color-accent);
            box-shadow: 0 0 0 3px rgba(44, 90, 160, 0.1);
        }

        .form-input::placeholder {
            color: var(--color-text-tertiary);
        }

        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--color-border);
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }

        .image-preview {
            width: 100%;
            max-height: 200px;
            object-fit: cover;
            border-radius: var(--radius-sm);
            border: 1px solid var(--color-border);
            margin-top: 0.5rem;
            display: none;
        }

        .image-preview.active {
            display: block;
        }

        .file-input-wrapper {
            position: relative;
        }

        .file-input-wrapper input[type=file] {
            display: none;
        }

        .file-input-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem;
            background: var(--color-bg);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.9375rem;
        }

        .file-input-label:hover {
            background: var(--color-surface);
            border-color: var(--color-accent);
        }

        .timeline {
            position: relative;
            padding-left: 2rem;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 0.5rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--color-border);
        }

        .timeline-item {
            position: relative;
            padding-bottom: 1.5rem;
        }

        .timeline-item:last-child {
            padding-bottom: 0;
        }

        .timeline-dot {
            position: absolute;
            left: -1.5rem;
            top: 0.25rem;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--color-accent);
            border: 2px solid var(--color-surface);
            box-shadow: 0 0 0 2px var(--color-border);
        }

        .timeline-dot.current {
            background: var(--color-success);
            box-shadow: 0 0 0 2px var(--color-success), 0 0 0 4px rgba(12, 166, 120, 0.2);
        }

        .timeline-content {
            background: var(--color-bg);
            padding: 1rem;
            border-radius: var(--radius-sm);
            border: 1px solid var(--color-border);
        }

        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.5rem;
        }

        .timeline-location {
            font-weight: 600;
            color: var(--color-text-primary);
            font-size: 0.9375rem;
        }

        .timeline-date {
            font-size: 0.8125rem;
            color: var(--color-text-tertiary);
            font-family: 'JetBrains Mono', monospace;
        }

        .timeline-action {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.625rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .timeline-action.assigned {
            background: rgba(59, 130, 246, 0.1);
            color: var(--color-info);
        }

        .timeline-action.transferred {
            background: rgba(245, 158, 11, 0.1);
            color: var(--color-warning);
        }

        .timeline-action.maintenance {
            background: rgba(220, 38, 38, 0.1);
            color: var(--color-danger);
        }

        .timeline-action.received {
            background: rgba(12, 166, 120, 0.1);
            color: var(--color-success);
        }

        .timeline-action.retired, .timeline-action.returned {
            background: rgba(138, 148, 166, 0.1);
            color: var(--color-text-tertiary);
        }

        .timeline-action.deployed {
            background: rgba(59, 130, 246, 0.1);
            color: var(--color-info);
        }

        .timeline-notes {
            font-size: 0.875rem;
            color: var(--color-text-secondary);
        }

        .transfer-form {
            display: grid;
            gap: 1rem;
        }

        /* ===============================
   UI POLISH & DESIGN ENHANCEMENTS
   =============================== */

/* Global smoothing */
body {
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

/* Scrollbar styling */
::-webkit-scrollbar {
    width: 10px;
    height: 10px;
}

::-webkit-scrollbar-track {
    background: transparent;
}

::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.15);
    border-radius: 999px;
}

::-webkit-scrollbar-thumb:hover {
    background: rgba(0, 0, 0, 0.25);
}

/* Header enhancement */
header {
    backdrop-filter: saturate(180%) blur(8px);
    background: linear-gradient(
        180deg,
        var(--color-surface),
        rgba(255, 255, 255, 0.96)
    );
}

/* Buttons: richer depth */
.btn {
    box-shadow: 0 1px 0 rgba(255,255,255,0.6) inset;
}

.btn:active {
    transform: translateY(0);
    box-shadow: none;
}

/* Primary button glow */
.btn-primary {
    box-shadow:
        0 8px 20px rgba(44, 90, 160, 0.25),
        inset 0 -1px 0 rgba(0,0,0,0.2);
}

.btn-primary:hover {
    box-shadow:
        0 12px 28px rgba(44, 90, 160, 0.35),
        inset 0 -1px 0 rgba(0,0,0,0.25);
}

/* Stat cards: premium lift */
.stat-card {
    background:
        linear-gradient(
            180deg,
            #ffffff,
            #fbfcfe
        );
}

.stat-card::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    pointer-events: none;
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.7);
}

/* Table container depth */
.table-container {
    background: linear-gradient(
        180deg,
        #ffffff,
        #fafbfc
    );
}

/* Table row hover highlight */
tbody tr:hover {
    box-shadow:
        inset 4px 0 0 var(--color-accent),
        0 4px 10px rgba(0,0,0,0.04);
}

/* Selected row clarity */
tbody tr.selected {
    box-shadow:
        inset 4px 0 0 var(--color-accent),
        inset 0 0 0 1px rgba(44,90,160,0.15);
}

/* Badges: subtle shine */
.status-badge,
.condition-badge,
.timeline-action {
    box-shadow:
        inset 0 1px 0 rgba(255,255,255,0.6);
}

/* Side panel glass effect */
.side-panel {
    backdrop-filter: blur(10px) saturate(180%);
    background:
        linear-gradient(
            180deg,
            rgba(255,255,255,0.98),
            rgba(250,251,252,0.98)
        );
}

/* Side panel slide smoothing */
.side-panel.open {
    animation: panelReveal 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes panelReveal {
    from {
        transform: translateX(100%);
        opacity: 0.6;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* Large image polish */
.large-image-container {
    box-shadow:
        inset 0 0 0 1px rgba(0,0,0,0.04),
        0 12px 30px rgba(0,0,0,0.08);
}

/* Modal elevation */
.modal-content {
    background:
        linear-gradient(
            180deg,
            #ffffff,
            #fafbfc
        );
}

/* Modal header separation */
.modal-header {
    background: linear-gradient(
        180deg,
        #ffffff,
        #f9fafb
    );
}

/* Inputs: focus glow */
.form-input:focus,
.form-select:focus {
    box-shadow:
        0 0 0 3px rgba(44, 90, 160, 0.15),
        inset 0 1px 2px rgba(0,0,0,0.05);
}

/* File input polish */
.file-input-label {
    background:
        linear-gradient(
            180deg,
            #f8f9fb,
            #f1f3f6
        );
}

/* Timeline refinement */
.timeline-content {
    background:
        linear-gradient(
            180deg,
            #ffffff,
            #f9fafb
        );
}

/* QR code container depth */
.qr-code-container {
    background:
        linear-gradient(
            180deg,
            #f9fafb,
            #f1f3f6
        );
}

/* Icon buttons hover feedback */
.btn-icon:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

/* Subtle page background texture */
body::before {
    content: '';
    position: fixed;
    inset: 0;
    background:
        radial-gradient(
            circle at top left,
            rgba(44,90,160,0.03),
            transparent 60%
        );
    pointer-events: none;
    z-index: -1;
}

/* ==========================================
   SIDEBAR MENU STYLES
   ========================================== */

/* Hamburger Menu Button */
.hamburger-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    background: transparent;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: var(--transition);
    padding: 0;
}

.hamburger-btn:hover {
    background: var(--color-bg);
    border-color: var(--color-accent);
}

.hamburger-icon {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.hamburger-icon span {
    width: 20px;
    height: 2px;
    background: var(--color-text-primary);
    border-radius: 2px;
    transition: var(--transition);
}

/* Sidebar */
.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    width: 260px;
    height: 100vh;
    background: var(--color-surface);
    border-right: 1px solid var(--color-border);
    box-shadow: var(--shadow-md);
    transform: translateX(-100%);
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 2000;
    overflow-y: auto;
}

.sidebar.open {
    transform: translateX(0);
}

.sidebar-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--color-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.sidebar-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--color-text-primary);
}

.sidebar-close {
    background: transparent;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--color-text-secondary);
    padding: 0.25rem;
    line-height: 1;
    transition: var(--transition);
}

.sidebar-close:hover {
    color: var(--color-text-primary);
}

.sidebar-menu {
    padding: 1rem 0;
}

.menu-section {
    margin-bottom: 0.5rem;
}

.menu-divider {
    height: 1px;
    background: var(--color-border);
    margin: 0.5rem 1rem;
}

.menu-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1.5rem;
    color: var(--color-text-primary);
    text-decoration: none;
    transition: var(--transition);
    cursor: pointer;
    border-left: 3px solid transparent;
}

.menu-item:hover {
    background: var(--color-bg);
    border-left-color: var(--color-accent);
}

.menu-item.active {
    background: rgba(44, 90, 160, 0.05);
    border-left-color: var(--color-accent);
    color: var(--color-accent);
    font-weight: 500;
}

.menu-item .icon {
    font-size: 1.25rem;
    width: 24px;
    text-align: center;
}

.menu-item .label {
    font-size: 0.9375rem;
}

/* Sidebar Overlay */
.sidebar-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(2px);
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s, visibility 0.3s;
    z-index: 1999;
}

.sidebar-overlay.active {
    opacity: 1;
    visibility: visible;
}

@media (max-width: 768px) {
    .sidebar {
        width: 100%;
        max-width: 300px;
    }
}

.menu-profile {
  text-align: center;
  padding: 16px;
}

.avatar {
  width: 100px;
  height: 100px;
  border-radius: 70%;
}

.name {
  margin-top: 8px;
  font-weight: 600;
}
.name {
  color: #0d6efd; /* same as button color */
  font-weight: 600;
}


    </style>
</head>
<body>
       <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar Menu -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-title">Menu</div>
            <button class="sidebar-close" onclick="toggleSidebar()">&times;</button>
        </div>
        <nav class="sidebar-menu">
            <div class="menu-profile">
  <img src="assets/pic.jpg" class="avatar">
  <p class="name">Charles</p>
</div>

            <div class="menu-section">
                <a href="#" class="menu-item active" onclick="navigateTo('dashboard')">
                    <span class="icon"> <img src="assets/dashboard.png"  onerror="this.style.display='none'"></span>
                    <span class="label">Dashboard</span>
                </a>
                <a href="#" class="menu-item" onclick="navigateTo('notifications')">
                       <span class="icon"> <img src="assets/notification.png"  onerror="this.style.display='none'"></span>
                    <span class="label">Notifications</span>
                </a>
                <a href="#" class="menu-item" onclick="navigateTo('upload')">
                    <span class="icon"><img src="assets/upload.png"  onerror="this.style.display='none'"></span>
                    <span class="label">Upload Excel</span>
                </a>
                <a href="#" class="menu-item" onclick="navigateTo('reports')">
                     <span class="icon"><img src="assets/reports.png"  onerror="this.style.display='none'"></span>
                    <span class="label">Reports</span>
                </a>
            </div>
            
            <div class="menu-divider"></div>
            
            <div class="menu-section">
                <a href="#" class="menu-item" onclick="navigateTo('profile')">
                     <span class="icon"><img src="assets/profile.png"  onerror="this.style.display='none'"></span>
                    <span class="label">Profile</span>
                </a>
                <a href="#" class="menu-item" onclick="navigateTo('settings')">
                     <span class="icon"><img src="assets/settings.png"  onerror="this.style.display='none'"></span>
                    <span class="label">Settings</span>
                </a>
            </div>
            
            <div class="menu-divider"></div>
            
            <div class="menu-section">
                <a href="#" class="menu-item" onclick="logout()">
                       <span class="icon"> <img src="assets/logout.png"  onerror="this.style.display='none'"></span>
                    <span class="label">Logout</span>
                </a>
            </div>
        </nav>
    </aside>
    <div class="dashboard">
        <div class="main-content" id="mainContent">
            <header>
                <div class="header-content">
                  <div class="header-left">
    <button class="hamburger-btn" onclick="toggleSidebar()" title="Menu">
        <div class="hamburger-icon">
            <span></span>
            <span></span>
            <span></span>
        </div>
    </button>
    <img src="assets/teamlogo.png" alt="Logo" class="logo1" onerror="this.style.display='none'">
    <h1>Inventory Management</h1>
</div>
                    <div class="header-actions">
                        <div class="search-box">
                            <svg class="search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                            <input type="text" placeholder="Search inventory..." id="searchInput">
                        </div>
                        <button class="btn btn-primary" onclick="addNewItem()">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Add Asset
                        </button>
                    </div>
                </div>
            </header>

            <div class="container">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-label">Total Assets</div>
                        <div class="stat-value" id="statTotal">0</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Active</div>
                        <div class="stat-value" style="color: var(--color-success);" id="statActive">0</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Maintenance</div>
                        <div class="stat-value" style="color: var(--color-warning);" id="statMaintenance">0</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Retired</div>
                        <div class="stat-value" style="color: var(--color-text-tertiary);" id="statRetired">0</div>
                    </div>
                </div>

                <div class="table-container">
                    <div class="table-header">
                        <div class="table-title">Asset Inventory</div>
                        <div style="display: flex; gap: 0.75rem; align-items: flex-end; flex-wrap: wrap;">
                            <div class="form-group" style="margin: 0; min-width: 150px;">
                                <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;">Status</label>
                                <select id="statusFilter" class="form-select" style="padding: 0.5rem 0.75rem; font-size: 0.875rem;" onchange="applyFilters()">
                                    <option value="all">All Statuses</option>
                                    <option value="active">Active</option>
                                    <option value="maintenance">Maintenance</option>
                                    <option value="retired">Retired</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin: 0; min-width: 150px;">
                                <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;">Asset Type</label>
                                <select id="assetTypeFilter" class="form-select" style="padding: 0.5rem 0.75rem; font-size: 0.875rem;" onchange="applyFilters()">
                                    <option value="all">All Types</option>
                                    <option value="Laptop">Laptop</option>
                                    <option value="Desktop">Desktop</option>
                                    <option value="Monitor">Monitor</option>
                                    <option value="Printer">Printer</option>
                                    <option value="Mouse">Mouse</option>
                                    <option value="Keyboard">Keyboard</option>
                                    <option value="Phone">Phone</option>
                                    <option value="Headset">Headset</option>
                                    <option value="Tablet">Tablet</option>
                                    <option value="Router">Router</option>
                                    <option value="Switch">Switch</option>
                                    <option value="Projector">Projector</option>
                                    <option value="Scanner">Scanner</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin: 0; min-width: 150px;">
                                <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;">From Date</label>
                                <input type="date" id="dateFromFilter" class="form-input" style="padding: 0.5rem 0.75rem; font-size: 0.875rem;" onchange="applyFilters()">
                            </div>
                            <div class="form-group" style="margin: 0; min-width: 150px;">
                                <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;">To Date</label>
                                <input type="date" id="dateToFilter" class="form-input" style="padding: 0.5rem 0.75rem; font-size: 0.875rem;" onchange="applyFilters()">
                            </div>
                            <button class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.875rem; height: 38px;" onclick="clearFilters()">Clear Filters</button>
                        </div>
                    </div>
                    <table id="inventoryTable">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th class="sortable" data-sort="asset_type">Asset Type</th>
                                <th class="sortable" data-sort="brand">Brand</th>
                                <th class="sortable" data-sort="serial_number">Serial Number</th>
                                <th class="sortable" data-sort="model">Model</th>
                                <th class="sortable" data-sort="status">Status</th>
                                <th class="sortable" data-sort="condition">Condition</th>
                                <th class="sortable" data-sort="place">Place</th>
                                <th class="sortable" data-sort="box">Box</th>
                                <th class="sortable" data-sort="date_added">Date Added</th>
                                <th>QR Code</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="inventoryBody">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="side-panel" id="sidePanel">
            <div class="panel-header">
                <div class="panel-title">Asset Details</div>
                <button class="close-panel" onclick="closeSidePanel()">&times;</button>
            </div>
            <div class="panel-content" id="panelContent">
            </div>
        </div>
    </div>

    <!-- Add/Edit Item Modal -->
    <div class="modal" id="itemModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title" id="modalTitle">Add New Asset</div>
                <button class="close-panel" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="itemForm" class="form-grid" onsubmit="saveAsset(event)">
                    <div class="form-group">
                        <label class="form-label required" for="imageFile">Asset Image</label>
                        <div class="file-input-wrapper">
                            <input type="file" id="imageFile" name="image" accept="image/*" onchange="previewImage(this)">
                            <label for="imageFile" class="file-input-label">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                <span id="fileLabel">Choose Image File</span>
                            </label>
                        </div>
                        <img id="imagePreview" class="image-preview" alt="Preview">
                    </div>
                    <div class="form-group">
                        <label class="form-label required" for="assetType">Asset Type</label>
                        <select id="assetType" name="assetType" class="form-select" required>
                            <option value="">Select asset type...</option>
                            <option value="Laptop">Laptop</option>
                            <option value="Desktop">Desktop</option>
                            <option value="Monitor">Monitor</option>
                            <option value="Printer">Printer</option>
                            <option value="Mouse">Mouse</option>
                            <option value="Keyboard">Keyboard</option>
                            <option value="Phone">Phone</option>
                            <option value="Headset">Headset</option>
                            <option value="Tablet">Tablet</option>
                            <option value="Router">Router</option>
                            <option value="Switch">Switch</option>
                            <option value="Projector">Projector</option>
                            <option value="Scanner">Scanner</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label required" for="brand">Brand</label>
                        <input type="text" id="brand" name="brand" class="form-input" placeholder="e.g., Dell, HP, Apple" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label required" for="serialNumber">Serial Number</label>
                        <input type="text" id="serialNumber" name="serialNumber" class="form-input" placeholder="e.g., DL-8392-XK91" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label required" for="model">Model</label>
                        <input type="text" id="model" name="model" class="form-input" placeholder="e.g., Latitude 5520" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label required" for="status">Status</label>
                        <select id="status" name="status" class="form-select" required>
                            <option value="">Select status...</option>
                            <option value="active">Active</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="retired">Retired</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label required" for="condition">Condition</label>
                        <select id="condition" name="condition" class="form-select" required>
                            <option value="">Select condition...</option>
                            <option value="excellent">Excellent</option>
                            <option value="good">Good</option>
                            <option value="fair">Fair</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label required" for="place">Place</label>
                        <input type="text" id="place" name="place" class="form-input" placeholder="e.g., Office A - Desk 12" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label required" for="box">Storage Box</label>
                        <input type="text" id="box" name="box" class="form-input" placeholder="e.g., IT-Box-047" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" form="itemForm" class="btn btn-primary" id="submitBtn">Add Asset</button>
            </div>
        </div>
    </div>

    <!-- Transfer Location Modal -->
    <div class="modal" id="transferModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Transfer Asset</div>
                <button class="close-panel" onclick="closeTransferModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="transferForm" class="transfer-form" onsubmit="transferAsset(event)">
                    <div class="form-group">
                        <label class="form-label">Current Location</label>
                        <div class="detail-value" id="currentLocation" style="padding: 0.75rem; background: var(--color-bg); border-radius: var(--radius-sm); border: 1px solid var(--color-border);"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label required" for="newLocation">New Location</label>
                        <input type="text" id="newLocation" name="newLocation" class="form-input" placeholder="e.g., QC Bagumbayan, Pulilan Bulacan, Manila Office" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label required" for="transferAction">Action Type</label>
                        <select id="transferAction" name="transferAction" class="form-select" required>
                            <option value="">Select action...</option>
                            <option value="transferred">Transferred</option>
                            <option value="assigned">Assigned</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="received">Received</option>
                            <option value="deployed">Deployed</option>
                            <option value="returned">Returned</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label required" for="receivedBy">Received By</label>
                        <input type="text" id="receivedBy" name="receivedBy" class="form-input" placeholder="Name of person receiving the asset" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="transferNotes">Notes</label>
                        <textarea id="transferNotes" name="transferNotes" class="form-input" rows="3" placeholder="Add notes about this transfer..."></textarea>
                    </div>
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="checkbox" id="stillAtLocation" name="stillAtLocation" style="width: 18px; height: 18px; cursor: pointer;" checked>
                            <span class="form-label" style="margin: 0;">Asset is still at this location</span>
                        </label>
                        <small style="color: var(--color-text-tertiary); font-size: 0.8125rem; display: block; margin-top: 0.25rem;">
                            Check this if the asset is currently at the new location. Uncheck if it's in transit or has been pulled out.
                        </small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeTransferModal()">Cancel</button>
                <button type="submit" form="transferForm" class="btn btn-transfer">Transfer Asset</button>
            </div>
        </div>
    </div>

    <script>
    </script>
    <script src="app.js"></script>

    <script>
// Sidebar Toggle Functions
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    sidebar.classList.toggle('open');
    overlay.classList.toggle('active');
}

// Navigation Function
function navigateTo(page) {
    // Remove active class from all menu items
    document.querySelectorAll('.menu-item').forEach(item => {
        item.classList.remove('active');
    });
    
    // Add active class to clicked item
    event.currentTarget.classList.add('active');
    
    // Close sidebar after navigation
    toggleSidebar();
    
   
   // Handle navigation based on page
    switch(page) {
        case 'dashboard':
            console.log('Navigate to Dashboard');
            window.location.href = 'index.php';
            break;
        case 'notifications':
            console.log('Navigate to Notifications');
            // window.location.href = 'notifications.php';
            alert('Notifications page - Coming soon!');
            break;
       case 'upload':
             window.location.href = 'upload_excel_page_with_images.php';
            break;
        case 'reports':
            console.log('Navigate to Reports');
            window.location.href = 'reports.php';
            // alert('Reports page - Coming soon!');
            break;
        case 'profile':
            console.log('Navigate to Profile');
            window.location.href = 'profile.php';
            // alert('Profile page - Coming soon!');
            break;
        case 'settings':
            console.log('Navigate to Settings');
            // window.location.href = 'settings.php';
            alert('Settings page - Coming soon!');
            break;
    }
}

// Logout Function
function logout() {
    if (confirm('Are you sure you want to logout?')) {
        console.log('Logging out...');
        window.location.href = 'pages/logout.php';
        // Or use: window.location.href = 'login.php';
    }
}

// Close sidebar when clicking outside
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    const hamburger = document.querySelector('.hamburger-btn');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (sidebar && sidebar.classList.contains('open')) {
        if (!sidebar.contains(event.target) && !hamburger?.contains(event.target)) {
            if (overlay && overlay.classList.contains('active')) {
                toggleSidebar();
            }
        }
    }
});
</script>
</body>
</html>