<?php
/**
 * audit_logs.php — หน้าต่างแสดงประวัติการแก้ไขและบันทึกการกระทำในระบบ
 */

require_once 'includes/db.php';
include 'includes/header.php';

// ล้างประวัติ (ถ้าผู้ใช้สั่งล้าง)
if (isset($_POST['action']) && $_POST['action'] === 'clear_logs') {
    try {
        $pdo->exec("DELETE FROM audit_logs");
        $success_msg = "ล้างประวัติการแก้ไขทั้งหมดเรียบร้อยแล้ว";
    } catch (Exception $e) {
        $error_msg = "เกิดข้อผิดพลาดในการล้างประวัติ: " . $e->getMessage();
    }
}

// ค้นหาและดึงข้อมูลบันทึกประวัติล่าสุด
$logs = $pdo->query("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 100")->fetchAll();
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <div>
        <h1 class="page-title">ประวัติการแก้ไขระบบ (Audit Logs)</h1>
        <p class="page-subtitle">แสดงรายการแก้ไขข้อมูลครุภัณฑ์ และการทำรายการใบเบิกพัสดุ 100 รายการล่าสุด</p>
    </div>
    <div>
        <?php if (!empty($logs)): ?>
            <form method="POST" onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบประวัติการแก้ไขทั้งหมด? การดำเนินการนี้ไม่สามารถย้อนกลับได้');">
                <input type="hidden" name="action" value="clear_logs">
                <button type="submit" class="btn btn-danger" style="display: flex; align-items: center; gap: 8px;">
                    <i data-lucide="trash-2" style="width: 18px; height: 18px;"></i>
                    ล้างประวัติทั้งหมด
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($success_msg)): ?>
    <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid var(--success); color: var(--success); padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px;">
        <i data-lucide="check-circle" style="width: 20px; height: 20px;"></i>
        <span><?php echo $success_msg; ?></span>
    </div>
<?php endif; ?>

<?php if (isset($error_msg)): ?>
    <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger); color: var(--danger); padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px;">
        <i data-lucide="alert-triangle" style="width: 20px; height: 20px;"></i>
        <span><?php echo $error_msg; ?></span>
    </div>
<?php endif; ?>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th style="width: 180px;">วันที่ / เวลา</th>
                <th style="width: 120px; text-align: center;">การทำงาน</th>
                <th style="width: 120px;">ตาราง</th>
                <th>รายละเอียดการแก้ไข</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 4rem;">
                        <i data-lucide="info" style="width: 32px; height: 32px; margin-bottom: 0.5rem; opacity: 0.5;"></i>
                        <p style="margin: 0;">ยังไม่มีบันทึกประวัติการแก้ไขในระบบ</p>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($logs as $row): 
                    // กำหนดสีของ Badge ตามประเภทของการกระทำ
                    $badgeClass = 'badge-warning';
                    if ($row['action_type'] === 'INSERT') $badgeClass = 'badge-success';
                    if ($row['action_type'] === 'DELETE') $badgeClass = 'badge-danger';
                    if ($row['action_type'] === 'APPROVE') $badgeClass = 'badge-success';
                    if ($row['action_type'] === 'REJECT') $badgeClass = 'badge-danger';
                ?>
                    <tr>
                        <td style="color: var(--text-muted); font-size: 0.9rem;">
                            <?php echo date('d/m/Y H:i:s', strtotime($row['created_at'])); ?>
                        </td>
                        <td style="text-align: center;">
                            <span class="badge <?php echo $badgeClass; ?>">
                                <?php echo htmlspecialchars($row['action_type']); ?>
                            </span>
                        </td>
                        <td style="font-weight: 600; color: var(--text-muted); font-size: 0.9rem;">
                            <?php echo htmlspecialchars($row['table_name']); ?>
                        </td>
                        <td style="font-size: 0.95rem; color: var(--text);">
                            <?php echo htmlspecialchars($row['details']); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include 'includes/footer.php'; ?>
