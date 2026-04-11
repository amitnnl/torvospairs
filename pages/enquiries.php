<?php
define('BASE_PATH', dirname(__DIR__));
$pageTitle      = 'Customer Enquiries';
$pageIcon       = 'fas fa-envelope-open';
$activePage     = 'enquiries';
$pageBreadcrumb = 'Customer Enquiries';
include BASE_PATH . '/includes/header.php';
requireAdmin();

$db = getDB();

// Ensure enquiries table
$db->exec("CREATE TABLE IF NOT EXISTS `enquiries` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `company` VARCHAR(150),
    `email` VARCHAR(150) NOT NULL,
    `phone` VARCHAR(20),
    `subject` VARCHAR(200),
    `message` TEXT NOT NULL,
    `reply` TEXT,
    `status` ENUM('new','replied','closed') DEFAULT 'new',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $eid    = (int)($_POST['enquiry_id'] ?? 0);

    if ($action === 'reply' && $eid) {
        $reply  = sanitize($_POST['reply'] ?? '');
        $status = sanitize($_POST['status'] ?? 'replied');
        $db->prepare("UPDATE enquiries SET reply=?, status=? WHERE id=?")->execute([$reply, $status, $eid]);
        setFlash('success', 'Enquiry updated.');
    }
    if ($action === 'delete' && $eid) {
        $db->prepare("DELETE FROM enquiries WHERE id=?")->execute([$eid]);
        setFlash('success', 'Enquiry deleted.');
    }
    header('Location: enquiries.php'); exit;
}

$statusFilter = sanitize($_GET['status'] ?? '');
$sql    = "SELECT * FROM enquiries";
$params = [];
if ($statusFilter) { $sql .= " WHERE status=?"; $params[] = $statusFilter; }
$sql .= " ORDER BY created_at DESC";
$stmt  = $db->prepare($sql);
$stmt->execute($params);
$enquiries = $stmt->fetchAll();

$newCount      = $db->query("SELECT COUNT(*) FROM enquiries WHERE status='new'")->fetchColumn();
$repliedCount  = $db->query("SELECT COUNT(*) FROM enquiries WHERE status='replied'")->fetchColumn();
$closedCount   = $db->query("SELECT COUNT(*) FROM enquiries WHERE status='closed'")->fetchColumn();
?>

<div class="page-body">

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem;">
    <?php foreach ([
        ['All',     count($enquiries), 'fas fa-inbox',         'var(--primary)',  ''],
        ['New',     $newCount,         'fas fa-envelope',      'var(--danger)',   'new'],
        ['Replied', $repliedCount,     'fas fa-reply',         'var(--success)',  'replied'],
        ['Closed',  $closedCount,      'fas fa-check-double',  'var(--text-muted)','closed'],
    ] as [$label, $count, $icon, $color, $filter]): ?>
    <a href="?status=<?= $filter ?>" style="text-decoration:none;">
        <div class="card" style="padding:1rem;display:flex;align-items:center;gap:0.85rem;border-left:3px solid <?= $color ?>;transition:all 0.2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform=''">
            <div style="width:40px;height:40px;border-radius:10px;background:<?= $color ?>22;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="<?= $icon ?>" style="color:<?= $color ?>;"></i>
            </div>
            <div>
                <div style="font-size:1.4rem;font-weight:800;color:var(--text-primary);"><?= $count ?></div>
                <div style="font-size:0.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;"><?= $label ?></div>
            </div>
        </div>
    </a>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-envelope-open"></i> Enquiries
            <?php if ($newCount > 0): ?>
            <span style="background:var(--danger);color:#fff;font-size:0.65rem;padding:2px 7px;border-radius:20px;"><?= $newCount ?> New</span>
            <?php endif; ?>
        </div>
        <a href="?status=" class="btn btn-outline btn-sm"><i class="fas fa-sync"></i> All</a>
    </div>

    <?php if (empty($enquiries)): ?>
    <div class="empty-state"><i class="fas fa-inbox"></i><h3>No enquiries yet</h3><p>Customer enquiries from the portal contact form will appear here.</p></div>
    <?php else: ?>
    <div style="display:flex;flex-direction:column;">
    <?php foreach ($enquiries as $e):
        $statusColors = ['new'=>'badge-danger','replied'=>'badge-success','closed'=>'badge-gray'];
    ?>
    <div style="padding:1.1rem 1.5rem;border-bottom:1px solid var(--border-color);">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;">
            <div style="flex:1;min-width:0;">
                <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.3rem;flex-wrap:wrap;">
                    <span style="font-weight:700;font-size:0.9rem;"><?= htmlspecialchars($e['name']) ?></span>
                    <?php if ($e['company']): ?>
                    <span style="font-size:0.75rem;color:var(--text-muted);">· <?= htmlspecialchars($e['company']) ?></span>
                    <?php endif; ?>
                    <span class="badge-pill <?= $statusColors[$e['status']] ?? 'badge-gray' ?>"><?= ucfirst($e['status']) ?></span>
                </div>
                <div style="font-size:0.78rem;color:var(--text-muted);margin-bottom:0.5rem;display:flex;gap:1rem;flex-wrap:wrap;">
                    <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($e['email']) ?></span>
                    <?php if ($e['phone']): ?><span><i class="fas fa-phone"></i> <?= htmlspecialchars($e['phone']) ?></span><?php endif; ?>
                    <?php if ($e['subject']): ?><span><i class="fas fa-tag"></i> <?= htmlspecialchars($e['subject']) ?></span><?php endif; ?>
                    <span><i class="fas fa-clock"></i> <?= date('d M Y, h:ia', strtotime($e['created_at'])) ?></span>
                </div>
                <div style="font-size:0.85rem;color:var(--text-medium);background:var(--bg-card2);padding:0.65rem 0.85rem;border-radius:8px;border-left:3px solid var(--primary);">
                    <?= nl2br(htmlspecialchars($e['message'])) ?>
                </div>
                <?php if ($e['reply']): ?>
                <div style="margin-top:0.6rem;font-size:0.82rem;color:var(--success);background:rgba(22,163,74,0.07);padding:0.6rem 0.85rem;border-radius:8px;border-left:3px solid var(--success);">
                    <i class="fas fa-reply"></i> <strong>Your reply:</strong> <?= nl2br(htmlspecialchars($e['reply'])) ?>
                </div>
                <?php endif; ?>
            </div>
            <div style="display:flex;gap:0.35rem;flex-shrink:0;padding-top:0.2rem;">
                <button class="btn btn-primary btn-sm btn-icon" onclick='replyEnquiry(<?= json_encode($e) ?>)' data-tooltip="Reply">
                    <i class="fas fa-reply"></i>
                </button>
                <a href="mailto:<?= htmlspecialchars($e['email']) ?>?subject=Re: <?= urlencode($e['subject'] ?: 'Your enquiry') ?>" class="btn btn-outline btn-sm btn-icon" data-tooltip="Email">
                    <i class="fas fa-envelope"></i>
                </a>
                <?php if ($e['phone']): ?>
                <a href="https://api.whatsapp.com/send?phone=<?= preg_replace('/[^0-9]/', '', $e['phone']) ?>&text=Hi <?= urlencode($e['name']) ?>! Thank you for enquiring with TORVO SPAIR." target="_blank" class="btn btn-outline btn-sm btn-icon" data-tooltip="WhatsApp" style="color:#25d366;border-color:#25d366;">
                    <i class="fab fa-whatsapp"></i>
                </a>
                <?php endif; ?>
                <form method="POST" style="display:inline;" onsubmit="return confirmDelete('Delete this enquiry?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="enquiry_id" value="<?= $e['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm btn-icon" data-tooltip="Delete"><i class="fas fa-trash"></i></button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
</div>

<!-- Reply Modal -->
<div class="modal-overlay" id="replyModal">
    <div class="modal-box" style="max-width:540px;">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-reply" style="color:var(--primary);"></i> Reply to Enquiry</div>
            <button class="modal-close" onclick="closeModal('replyModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="reply">
            <input type="hidden" name="enquiry_id" id="replyEid">
            <div class="modal-body">
                <div id="replyMsgPreview" style="background:var(--bg-card2);border-radius:8px;padding:0.85rem;font-size:0.82rem;margin-bottom:1rem;border-left:3px solid var(--primary);"></div>
                <div class="form-group">
                    <label class="form-label">Your Reply / Notes</label>
                    <textarea name="reply" id="replyText" class="form-control" rows="4" placeholder="Write your reply or internal note here..."></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Update Status</label>
                    <select name="status" id="replyStatus" class="form-control">
                        <option value="new">New</option>
                        <option value="replied" selected>Replied</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('replyModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Reply</button>
            </div>
        </form>
    </div>
</div>

<script>
function replyEnquiry(e) {
    document.getElementById('replyEid').value = e.id;
    document.getElementById('replyText').value = e.reply || '';
    document.getElementById('replyStatus').value = e.status;
    document.getElementById('replyMsgPreview').textContent = e.message;
    openModal('replyModal');
}
</script>

<?php include BASE_PATH . '/includes/footer.php'; ?>
