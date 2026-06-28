<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบบริหารจัดการพัสดุ มหาวิทยาลัยสวนดุสิต</title>
    <script>
        // Prevent white flash / theme flicker
        (function() {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css?v=<?php echo time(); ?>">
    <link rel="icon" type="image/png" href="assets/sdu_logo.png">
    <script src="assets/libs/lucide.min.js"></script>
    <link href="assets/libs/tom-select.css" rel="stylesheet">
    <script src="assets/libs/tom-select.complete.min.js"></script>
</head>
<body>
    <aside class="sidebar" id="sidebar">
        <div class="toggle-btn" id="toggleSidebar">
            <i data-lucide="chevron-left" id="toggleIcon" style="width: 14px; height: 14px;"></i>
        </div>
        <div class="logo" style="padding: 1.5rem 1rem; border-bottom: 1px solid var(--border); margin-bottom: 1rem; display: flex; align-items: center;">
            <img src="assets/sdu_logo.png" alt="SDU Logo" style="width: 45px; height: auto; flex-shrink: 0;">
            <div style="margin-left: 10px; display: flex; flex-direction: column;">
                <span style="font-weight: 700; color: var(--primary); font-size: 13px; line-height: 1.3;">ระบบบริหารจัดการพัสดุ</span>
                <span style="font-weight: 500; color: var(--text-muted); font-size: 11px; margin-top: 2px;">มหาวิทยาลัยสวนดุสิต</span>
            </div>
        </div>
        <nav>
            <ul class="nav-links">
                <li>
                    <a href="index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                        <i data-lucide="layout-dashboard" style="width: 20px; height: 20px;"></i>
                        <span>หน้าแรก</span>
                    </a>
                </li>
                <li>
                    <a href="items.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'items.php' ? 'active' : ''; ?>">
                        <i data-lucide="box" style="width: 20px; height: 20px;"></i>
                        <span>รายการครุภัณฑ์</span>
                    </a>
                </li>
                <li>
                    <a href="requisitions.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'requisitions.php' ? 'active' : ''; ?>">
                        <i data-lucide="file-text" style="width: 20px; height: 20px;"></i>
                        <span>หน้ารายการเบิกพัสดุ</span>
                    </a>
                </li>
                <li>
                    <a href="print_barcode_range.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'print_barcode_range.php' ? 'active' : ''; ?>">
                        <i data-lucide="printer" style="width: 20px; height: 20px;"></i>
                        <span>พิมพ์ Barcode ต่อเนื่อง</span>
                    </a>
                </li>
                <li>
                    <a href="audit_logs.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'audit_logs.php' ? 'active' : ''; ?>">
                        <i data-lucide="history" style="width: 20px; height: 20px;"></i>
                        <span>ประวัติการแก้ไข</span>
                    </a>
                </li>
            </ul>
        </nav>
        <div style="margin-top: auto; padding-top: 1rem; border-top: 1px solid var(--border); display: flex; flex-direction: column; gap: 0.25rem;">
            <a href="api_backup.php" class="nav-link" style="color: var(--success) !important;">
                <i data-lucide="download" style="width: 20px; height: 20px;"></i>
                <span>สำรองฐานข้อมูล</span>
            </a>
            <a href="#" id="themeToggleBtn" class="nav-link">
                <i data-lucide="moon" id="themeIcon" style="width: 20px; height: 20px;"></i>
                <span id="themeText">โหมดกลางคืน</span>
            </a>
        </div>
    </aside>

    <script>
        // Theme initialization
        const themeToggleBtn = document.getElementById('themeToggleBtn');
        const themeIcon = document.getElementById('themeIcon');
        const themeText = document.getElementById('themeText');
        
        function setTheme(isDark) {
            if (isDark) {
                document.body.setAttribute('data-theme', 'dark');
                document.documentElement.setAttribute('data-theme', 'dark');
                themeIcon.setAttribute('data-lucide', 'sun');
                themeText.textContent = 'โหมดกลางวัน';
                localStorage.setItem('theme', 'dark');
            } else {
                document.body.removeAttribute('data-theme');
                document.documentElement.removeAttribute('data-theme');
                themeIcon.setAttribute('data-lucide', 'moon');
                themeText.textContent = 'โหมดกลางคืน';
                localStorage.setItem('theme', 'light');
            }
            if (window.lucide) {
                lucide.createIcons();
            }
        }

        // Check for saved theme
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            setTheme(true);
        } else {
            setTheme(false);
        }

        themeToggleBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const isDark = document.documentElement.getAttribute('data-theme') !== 'dark';
            setTheme(isDark);
        });
        const sidebar = document.getElementById('sidebar');
        const toggleBtn = document.getElementById('toggleSidebar');
        const toggleIcon = document.getElementById('toggleIcon');

        // Check for saved state
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            sidebar.classList.add('collapsed');
            toggleIcon.setAttribute('data-lucide', 'chevron-right');
        }

        toggleBtn.addEventListener('click', () => {
            const isCollapsed = sidebar.classList.toggle('collapsed');
            localStorage.setItem('sidebarCollapsed', isCollapsed);
            
            // Update icon
            if (isCollapsed) {
                toggleIcon.setAttribute('data-lucide', 'chevron-right');
            } else {
                toggleIcon.setAttribute('data-lucide', 'chevron-left');
            }
            lucide.createIcons();
        });

        // ==========================================
        // Global Barcode Scanner Listener
        // ==========================================
        let globalBarcodeBuffer = '';
        let globalBarcodeTimeout;

        document.addEventListener('keydown', function(e) {
            // If user is typing in an input field, let them type (unless it's the search box)
            const isInput = e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA';
            if (isInput && e.target.id !== 'searchInput') return;
            
            // Ignore if a scan modal is already open
            const scanModal = document.getElementById('scanResultModal');
            if (scanModal && scanModal.style.display === 'flex') return;

            if (e.key === 'Enter') {
                if (globalBarcodeBuffer.length > 3) {
                    handleGlobalBarcodeScan(globalBarcodeBuffer);
                    globalBarcodeBuffer = '';
                    e.preventDefault();
                }
            } else if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
                globalBarcodeBuffer += e.key;
                clearTimeout(globalBarcodeTimeout);
                // Set timeout to 100ms to accommodate slightly slower USB scanners
                globalBarcodeTimeout = setTimeout(() => {
                    globalBarcodeBuffer = '';
                }, 100);
            }
        });

        function handleGlobalBarcodeScan(barcode) {
            let cleanBarcode = barcode.trim();
            
            // If the barcode is actually a URL (e.g. scanned from a mobile phone link), extract the 'scan' parameter
            if (cleanBarcode.startsWith('http://') || cleanBarcode.startsWith('https://')) {
                try {
                    const url = new URL(cleanBarcode);
                    const scanParam = url.searchParams.get('scan');
                    if (scanParam) {
                        cleanBarcode = scanParam;
                    }
                } catch(e) {}
            }
            
            // If we are already on items.php, use the existing handler if available
            if (window.location.pathname.includes('items.php') && typeof handleBarcodeScan === 'function') {
                handleBarcodeScan(cleanBarcode);
            } else {
                // Redirect to items.php with the scan parameter
                window.location.href = 'items.php?scan=' + encodeURIComponent(cleanBarcode);
            }
        }
    </script>
    <main class="main-content">
