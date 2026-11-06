<?php
require_once __DIR__ . '/../app/bootstrap.php';
$pageTitle = 'Contact — FitFlow Pro';
$activePage = 'contact';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $form = $_POST['form'] ?? 'contact';
    if ($form === 'newsletter') {
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        if ($email) {
            try {
                notify(null, null, 'Newsletter signup', 'New newsletter subscriber: ' . $email, 'info');
            } catch (Throwable $ignored) {
            }
            flash('success', 'Thanks for subscribing. One useful email a month, no spam.');
        } else {
            flash('error', 'Please enter a valid email address.');
        }
        redirect(url('public/index.php'));
    }
    $name = trim($_POST['name'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $subject = trim($_POST['subject'] ?? 'Website enquiry');
    $message = trim($_POST['message'] ?? '');
    if ($name !== '' && $email && $message !== '') {
        try {
            Database::insert('support_tickets', [
                'tenant_id' => null,
                'user_id' => null,
                'subject' => $subject . ' — from ' . $name . ' <' . $email . '>',
                'message' => $message,
                'priority' => 'medium',
                'status' => 'open',
            ]);
            flash('success', 'Message sent. Our team replies within one business day.');
        } catch (Throwable $e) {
            flash('error', 'Could not send right now. Please email hello@fitflowpro.example instead.');
        }
    } else {
        flash('error', 'Please complete your name, a valid email and a message.');
    }
    redirect(url('public/contact.php'));
}

require __DIR__ . '/../views/website/header.php';
?>
<section class="section-pad">
  <div class="container">
    <div class="text-center mb-5"><span class="section-tag">Contact</span>
      <h1 class="display-title">Talk to<br><span class="accent">a human</span></h1></div>
    <?= render_flash() ?>
    <div class="row g-4">
      <div class="col-lg-5">
        <div class="panel h-100">
          <h5 class="fw-bold mb-3">Contact details</h5>
          <p><i class="bi bi-geo-alt text-orange me-2"></i>1200 Iron Street, Austin, TX</p>
          <p><i class="bi bi-telephone text-orange me-2"></i>+1 555 010 2030</p>
          <p><i class="bi bi-envelope text-orange me-2"></i>hello@fitflowpro.example</p>
          <p><i class="bi bi-clock text-orange me-2"></i>Mon–Sat: 5:00 AM – 11:00 PM<br>Sun: 7:00 AM – 9:00 PM</p>
          <hr style="border-color:var(--ff-border)">
          <p class="small mb-0" style="color:var(--ff-muted)">Sales enquiries from gym owners are answered by the platform team; member questions go straight to your gym's front desk.</p>
        </div>
      </div>
      <div class="col-lg-7">
        <div class="panel">
          <h5 class="fw-bold mb-3">Send a message</h5>
          <form method="post" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="form" value="contact">
            <div class="row g-3">
              <div class="col-md-6"><label class="form-label">Your name</label><input class="form-control" name="name" required></div>
              <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" name="email" required></div>
              <div class="col-12"><label class="form-label">Subject</label><input class="form-control" name="subject" placeholder="e.g. Demo request for my gym"></div>
              <div class="col-12"><label class="form-label">Message</label><textarea class="form-control" name="message" rows="5" required></textarea></div>
            </div>
            <button class="btn btn-ff mt-3" type="submit">Send message</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../views/website/footer.php'; ?>
