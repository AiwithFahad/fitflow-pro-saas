<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../views/website/data.php';
$pageTitle = 'About — FitFlow Pro';
$activePage = 'about';
require __DIR__ . '/../views/website/header.php';
?>
<section class="section-pad">
  <div class="container">
    <div class="text-center mb-5"><span class="section-tag">About us</span>
      <h1 class="display-title">We build software<br><span class="accent">for serious gyms</span></h1></div>
    <div class="row align-items-center g-5">
      <div class="col-lg-6"><img class="img-fluid rounded-4" src="https://images.unsplash.com/photo-1571902943202-507ec2618e8f?w=1000&q=80&auto=format&fit=crop" alt="Athlete training in a dark gym"></div>
      <div class="col-lg-6">
        <h3 class="fw-bold">Our story</h3>
        <p style="color:var(--ff-muted)">FitFlow Pro started inside a real gym: spreadsheets for members, a paper notebook at the front desk, and renewals slipping through the cracks. We built the system we wished existed — one where attendance, coaching, payments and subscriptions live together, and where every gym's data is private, isolated and exportable.</p>
        <p style="color:var(--ff-muted)">Today, independent gyms and multi-branch chains run their entire operation on FitFlow Pro: QR check-ins in the morning rush, coach-built programs during the day, and renewal reminders that quietly protect revenue every night.</p>
        <div class="row g-3 mt-3">
          <div class="col-4"><div class="panel text-center"><div class="fs-3 fw-black" style="font-weight:900">240+</div><small style="color:var(--ff-muted)">Gyms</small></div></div>
          <div class="col-4"><div class="panel text-center"><div class="fs-3 fw-black" style="font-weight:900">58k+</div><small style="color:var(--ff-muted)">Members</small></div></div>
          <div class="col-4"><div class="panel text-center"><div class="fs-3 fw-black" style="font-weight:900">99.9%</div><small style="color:var(--ff-muted)">Uptime</small></div></div>
        </div>
      </div>
    </div>
    <div class="row g-4 mt-5">
      <?php foreach ([['bi-shield-lock', 'Privacy by design', 'Tenant data is isolated at the database level. Members only ever see their own records.'], ['bi-database-check', 'Honest numbers', 'Every dashboard statistic is calculated from real records — never hard-coded demo numbers.'], ['bi-people', 'Coach-first', 'Trainers get schedules, assigned members and program tools, not just an admin login.']] as [$i, $t, $d]): ?>
      <div class="col-md-4"><div class="ff-card p-4 h-100"><div class="icon-wrap mb-3"><i class="bi <?= $i ?>"></i></div><h5 class="fw-bold"><?= e($t) ?></h5><p class="mb-0" style="color:var(--ff-muted)"><?= e($d) ?></p></div></div>
      <?php endforeach; ?>
    </div>
    <div class="text-center mt-5"><a href="<?= e(url('public/register.php')) ?>" class="btn btn-ff btn-lg">Start your free trial</a></div>
  </div>
</section>
<?php require __DIR__ . '/../views/website/footer.php'; ?>
