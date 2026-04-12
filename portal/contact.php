<?php
require_once __DIR__ . '/config/auth.php';


$db = portalDB();
$db->exec("CREATE TABLE IF NOT EXISTS `enquiries` (`id` INT AUTO_INCREMENT PRIMARY KEY, `name` VARCHAR(100) NOT NULL, `company` VARCHAR(150), `email` VARCHAR(150) NOT NULL, `phone` VARCHAR(20), `subject` VARCHAR(200), `message` TEXT NOT NULL, `status` ENUM('new','replied','closed') DEFAULT 'new', `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$success = false;
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = sanitize($_POST['name']    ?? '');
    $company = sanitize($_POST['company'] ?? '');
    $email   = sanitize($_POST['email']   ?? '');
    $phone   = sanitize($_POST['phone']   ?? '');
    $subject = sanitize($_POST['subject'] ?? '');
    $message = sanitize($_POST['message'] ?? '');

    if (empty($name))    $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (strlen($message) < 10) $errors[] = 'Message must be at least 10 characters.';

    if (empty($errors)) {
        $db->prepare("INSERT INTO enquiries (name,company,email,phone,subject,message) VALUES (?,?,?,?,?,?)")
           ->execute([$name, $company, $email, $phone, $subject, $message]);
        $success = true;
    }
}

$pageTitle  = 'Contact Us';
$activePage = 'contact';
include __DIR__ . '/includes/header.php';
?>

<!-- Page header -->
<div style="background:linear-gradient(135deg,#1e3a8a,#1d4ed8);padding:3rem 1.5rem;text-align:center;">
    <h1 style="font-size:2rem;font-weight:900;color:#fff;margin-bottom:0.5rem;">Get In Touch</h1>
    <p style="color:rgba(255,255,255,0.6);">Talk to our sales team — we respond within 24 business hours</p>
</div>

<div class="section container" style="max-width:1100px;">
    <div style="display:grid;grid-template-columns:1fr 1.5fr;gap:2rem;align-items:start;">

        <!-- Contact Info -->
        <div>
            <div class="card" style="margin-bottom:1rem;">
                <div class="card-body">
                    <h3 style="font-size:1rem;font-weight:800;color:var(--text-dark);margin-bottom:1.25rem;">Contact Information</h3>
                    <?php foreach ([
                        ['fas fa-phone',         '+91 98000 00000',    'tel:+919800000000',        '#16a34a'],
                        ['fas fa-envelope',      'sales@torvo.com',    'mailto:sales@torvo.com',   '#2563eb'],
                        ['fab fa-whatsapp',      'WhatsApp Us',        'https://api.whatsapp.com/send?phone=919800000000','#25d366'],
                        ['fas fa-map-marker-alt','Mumbai, Maharashtra','#',                         '#f97316'],
                        ['fas fa-clock',         'Mon–Sat, 9am–6pm',  '#',                         '#6366f1'],
                    ] as [$icon, $text, $href, $color]): ?>
                    <a href="<?= $href ?>" target="<?= str_starts_with($href,'http')?'_blank':'_self' ?>" style="display:flex;align-items:center;gap:0.85rem;padding:0.75rem 0;border-bottom:1px solid var(--border);text-decoration:none;color:var(--text-medium);" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--text-medium)'">
                        <div style="width:36px;height:36px;border-radius:9px;background:<?= $color ?>15;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="<?= $icon ?>" style="color:<?= $color ?>;font-size:0.9rem;"></i>
                        </div>
                        <span style="font-size:0.875rem;font-weight:500;"><?= $text ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- WhatsApp CTA card -->
            <div style="background:linear-gradient(135deg,#25d366,#128c7e);border-radius:14px;padding:1.5rem;text-align:center;">
                <i class="fab fa-whatsapp" style="font-size:2rem;color:#fff;margin-bottom:0.75rem;display:block;"></i>
                <div style="font-weight:800;color:#fff;margin-bottom:0.3rem;">Quick Response</div>
                <div style="font-size:0.8rem;color:rgba(255,255,255,0.75);margin-bottom:1rem;">Get answers faster via WhatsApp</div>
                <a href="https://api.whatsapp.com/send?phone=919800000000&text=Hello! I have an enquiry about TORVO SPAIR spare parts." target="_blank" style="background:#fff;color:#128c7e;font-weight:800;padding:0.6rem 1.5rem;border-radius:8px;font-size:0.875rem;text-decoration:none;display:inline-flex;align-items:center;gap:0.5rem;">
                    <i class="fab fa-whatsapp"></i> Start Chat
                </a>
            </div>
        </div>

        <!-- Contact Form -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-paper-plane"></i> Send Us a Message</div>
            </div>
            <div class="card-body">
                <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <strong>Message sent successfully!</strong> Our team will respond within 24 business hours.
                </div>
                <?php elseif (!empty($errors)): ?>
                <div class="alert alert-error"><i class="fas fa-times-circle"></i> <?= implode(' ', array_map('htmlspecialchars', $errors)) ?></div>
                <?php endif; ?>

                <?php if (!$success): ?>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Your Name *</label>
                            <input type="text" name="name" class="form-control" required placeholder="Full name" value="<?= htmlspecialchars($_POST['name']??'') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Company Name</label>
                            <input type="text" name="company" class="form-control" placeholder="Business name" value="<?= htmlspecialchars($_POST['company']??'') ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" required placeholder="you@email.com" value="<?= htmlspecialchars($_POST['email']??'') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone</label>
                            <input type="tel" name="phone" class="form-control" placeholder="+91 98000 00000" value="<?= htmlspecialchars($_POST['phone']??'') ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Subject</label>
                        <select name="subject" class="form-control">
                            <option value="">— Select a topic —</option>
                            <option>Product Enquiry</option>
                            <option>Partnership / Dealership</option>
                            <option>Bulk Order / RFQ</option>
                            <option>Order Status</option>
                            <option>Technical Support</option>
                            <option>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Message *</label>
                        <textarea name="message" class="form-control" rows="5" required placeholder="Describe what you're looking for — specific parts, tool models, quantities, delivery location..."><?= htmlspecialchars($_POST['message']??'') ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-full btn-lg">
                        <i class="fas fa-paper-plane"></i> Send Message
                    </button>
                </form>
                <?php else: ?>
                <div style="text-align:center;padding:1rem 0;">
                    <a href="catalogue.php" class="btn btn-primary"><i class="fas fa-th-large"></i> Browse Catalogue</a>
                    <a href="contact.php" class="btn btn-outline" style="margin-left:0.75rem;"><i class="fas fa-plus"></i> New Message</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
