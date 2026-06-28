<?php
require_once 'includes/db.php';

// Fetch stats safely with fallback/coalesce
$total_items = $pdo->query("SELECT COUNT(*) FROM items")->fetchColumn();
$total_qty = $pdo->query("SELECT COALESCE(SUM(quantity), 0) FROM items")->fetchColumn();
$total_value = $pdo->query("SELECT COALESCE(SUM(quantity * unit_price), 0) FROM items")->fetchColumn();
$total_reqs = $pdo->query("SELECT COUNT(*) FROM requisitions")->fetchColumn();
$pending_reqs = $pdo->query("SELECT COUNT(*) FROM requisitions WHERE status = 'Pending'")->fetchColumn();
$approved_reqs = $pdo->query("SELECT COUNT(*) FROM requisitions WHERE status = 'Approved'")->fetchColumn();

// Fetch recent audit logs (latest 5)
$audit_logs = $pdo->query("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 5")->fetchAll();

include 'includes/header.php';
?>

<style>
    .dashboard-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 1.5rem;
    }
    @media (max-width: 1024px) {
        .dashboard-grid {
            grid-template-columns: 1fr;
        }
    }
    .panel-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 1rem;
        padding: 1.5rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }
    .panel-title {
        font-size: 1.1rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 8px;
        color: var(--text);
        margin-bottom: 0.5rem;
    }
    .activity-list {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    .activity-item {
        display: flex;
        gap: 12px;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--border-subtle);
    }
    .activity-item:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }
    .activity-icon {
        flex-shrink: 0;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--bg-hover);
        color: var(--text-muted);
    }
    .activity-icon.insert { background: #dcfce7; color: #15803d; }
    .activity-icon.update { background: #fffbeb; color: #d97706; }
    .activity-icon.delete { background: #fef2f2; color: #dc2626; }
    .activity-icon.approved { background: #dcfce7; color: #15803d; }
    .activity-icon.rejected { background: #fef2f2; color: #dc2626; }
    
    [data-theme="dark"] .activity-icon.insert { background: rgba(34, 197, 94, 0.15); color: #4ade80; }
    [data-theme="dark"] .activity-icon.update { background: rgba(245, 158, 11, 0.15); color: #fbbf24; }
    [data-theme="dark"] .activity-icon.delete { background: rgba(239, 68, 68, 0.15); color: #f87171; }
    [data-theme="dark"] .activity-icon.approved { background: rgba(34, 197, 94, 0.15); color: #4ade80; }
    [data-theme="dark"] .activity-icon.rejected { background: rgba(239, 68, 68, 0.15); color: #f87171; }

    .activity-content {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .activity-text {
        font-size: 0.875rem;
        color: var(--text);
        line-height: 1.4;
    }
    .activity-time {
        font-size: 0.75rem;
        color: var(--text-muted);
    }
    .quick-actions-list {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }
    .quick-action-card {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 1rem;
        background: var(--bg-subtle);
        border: 1px solid var(--border-subtle);
        border-radius: 0.75rem;
        text-decoration: none;
        color: var(--text);
        transition: all 0.2s ease;
    }
    .quick-action-card:hover {
        background: var(--bg-hover);
        border-color: var(--primary);
        transform: translateY(-2px);
    }
    .quick-action-icon {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--primary);
        color: white;
    }
    .quick-action-info {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .quick-action-name {
        font-weight: 600;
        font-size: 0.9rem;
    }
    .quick-action-desc {
        font-size: 0.75rem;
        color: var(--text-muted);
    }
    .stat-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.5rem;
    }
    .stat-icon-wrapper {
        color: var(--primary);
        background: var(--bg-hover);
        padding: 8px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .stat-value {
        font-size: 2rem;
        font-weight: 700;
        color: var(--text);
    }
</style>

<div class="page-header" style="margin-bottom: 2rem;">
    <h1 class="page-title">แผงควบคุมระบบ (Dashboard)</h1>
    <p class="page-subtitle">ภาพรวมของระบบบริหารจัดการพัสดุ มหาวิทยาลัยสวนดุสิต</p>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <!-- Card 1: Distinct Items -->
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">รายการครุภัณฑ์ทั้งหมด</span>
            <div class="stat-icon-wrapper">
                <i data-lucide="box" style="width: 20px; height: 20px;"></i>
            </div>
        </div>
        <div class="stat-value"><?php echo number_format($total_items); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">ประเภทชนิดที่ไม่ซ้ำกันในระบบ</div>
    </div>

    <!-- Card 2: Total Quantity -->
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">จำนวนพัสดุในคลังทั้งหมด</span>
            <div class="stat-icon-wrapper">
                <i data-lucide="layers" style="width: 20px; height: 20px;"></i>
            </div>
        </div>
        <div class="stat-value"><?php echo number_format($total_qty); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">จำนวนหน่วยพัสดุทั้งหมดรวมกัน</div>
    </div>

    <!-- Card 3: Total Asset Value -->
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">มูลค่าครุภัณฑ์รวมทั้งหมด</span>
            <div class="stat-icon-wrapper">
                <i data-lucide="wallet" style="width: 20px; height: 20px;"></i>
            </div>
        </div>
        <div class="stat-value" style="font-size: 1.6rem; line-height: 2.2rem; font-family: monospace; font-weight: 700;">฿<?php echo number_format($total_value, 2); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">คิดตามราคาต่อหน่วยและจำนวนในคลัง</div>
    </div>

    <!-- Card 4: Total Requisitions -->
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">รายการเบิกจ่ายรวม</span>
            <div class="stat-icon-wrapper">
                <i data-lucide="file-text" style="width: 20px; height: 20px;"></i>
            </div>
        </div>
        <div class="stat-value"><?php echo number_format($total_reqs); ?></div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">
            <span style="color: var(--success); font-weight: 600;">อนุมัติ <?php echo $approved_reqs; ?></span> | 
            <span style="color: var(--warning); font-weight: 600;">รออนุมัติ <?php echo $pending_reqs; ?></span>
        </div>
    </div>
</div>

<!-- Main Sections -->
<div class="dashboard-grid">
    <!-- Section 1: Recent Activity Logs -->
    <div class="panel-card">
        <div class="panel-title">
            <i data-lucide="activity" style="width: 20px; height: 20px; color: var(--primary);"></i>
            <span>ประวัติการทำรายการล่าสุด</span>
        </div>
        
        <div class="activity-list">
            <?php if (empty($audit_logs)): ?>
                <div style="text-align: center; color: var(--text-muted); padding: 2rem; font-style: italic; font-size: 0.9rem;">
                    ยังไม่มีข้อมูลประวัติการแก้ไขในระบบ
                </div>
            <?php else: ?>
                <?php foreach ($audit_logs as $log): ?>
                    <?php
                    $action_class = strtolower($log['action_type']);
                    $action_icon = 'info';
                    $action_text = $log['action_type'];
                    
                    if ($action_class == 'insert') {
                        $action_icon = 'plus';
                        $action_text = 'เพิ่ม';
                    } elseif ($action_class == 'update') {
                        $action_icon = 'edit-3';
                        $action_text = 'แก้ไข';
                    } elseif ($action_class == 'delete') {
                        $action_icon = 'trash-2';
                        $action_text = 'ลบ';
                    } elseif ($action_class == 'approved') {
                        $action_icon = 'check-circle';
                        $action_text = 'อนุมัติ';
                    } elseif ($action_class == 'rejected') {
                        $action_icon = 'x-circle';
                        $action_text = 'ปฏิเสธ';
                    }
                    ?>
                    <div class="activity-item">
                        <div class="activity-icon <?php echo $action_class; ?>" title="<?php echo $action_text; ?>">
                            <i data-lucide="<?php echo $action_icon; ?>" style="width: 16px; height: 16px;"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-text">
                                <?php echo htmlspecialchars($log['details']); ?>
                            </div>
                            <div class="activity-time">
                                <i data-lucide="clock" style="width: 10px; height: 10px; display: inline-block; vertical-align: middle; margin-right: 2px;"></i>
                                <span style="vertical-align: middle;"><?php echo date('d/m/Y H:i น.', strtotime($log['created_at'])); ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Section 2: Quick Links -->
    <div class="panel-card">
        <div class="panel-title">
            <i data-lucide="zap" style="width: 20px; height: 20px; color: var(--warning);"></i>
            <span>เมนูลัดด่วน</span>
        </div>

        <div class="quick-actions-list">
            <a href="items.php" class="quick-action-card">
                <div class="quick-action-icon" style="background: #3b82f6;">
                    <i data-lucide="plus" style="width: 20px; height: 20px;"></i>
                </div>
                <div class="quick-action-info">
                    <span class="quick-action-name">จัดการครุภัณฑ์</span>
                    <span class="quick-action-desc">เพิ่ม, ลบ, แก้ไข หรือพิมพ์ Barcode รายตัว</span>
                </div>
            </a>

            <a href="requisitions.php" class="quick-action-card">
                <div class="quick-action-icon" style="background: #10b981;">
                    <i data-lucide="file-plus" style="width: 20px; height: 20px;"></i>
                </div>
                <div class="quick-action-info">
                    <span class="quick-action-name">รายการเบิกพัสดุ</span>
                    <span class="quick-action-desc">จัดการขอเบิก ปรับปรุงสถานะใบเบิกพัสดุ</span>
                </div>
            </a>

            <a href="print_barcode_range.php" class="quick-action-card">
                <div class="quick-action-icon" style="background: #8b5cf6;">
                    <i data-lucide="printer" style="width: 20px; height: 20px;"></i>
                </div>
                <div class="quick-action-info">
                    <span class="quick-action-name">พิมพ์ Barcode ต่อเนื่อง</span>
                    <span class="quick-action-desc">พิมพ์สติกเกอร์รหัสบาร์โค้ดแบบจัดกลุ่มต่อเนื่อง</span>
                </div>
            </a>

            <a href="api_backup.php" class="quick-action-card">
                <div class="quick-action-icon" style="background: #15803d;">
                    <i data-lucide="download" style="width: 20px; height: 20px;"></i>
                </div>
                <div class="quick-action-info">
                    <span class="quick-action-name">สำรองข้อมูลระบบ</span>
                    <span class="quick-action-desc">ดาวน์โหลดประวัติและโครงสร้างสำรอง (SQLite)</span>
                </div>
            </a>
        </div>
    </div>
</div>

<script>
    if (window.lucide) {
        lucide.createIcons();
    }
</script>

<?php
include 'includes/footer.php';
?>
