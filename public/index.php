<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../views/website/data.php';

$pageTitle = 'FitFlow Pro — Men\'s Gym Management SaaS';
$activePage = 'home';

// Live platform counters (fall back to marketing defaults without a DB).
$stats = ['gyms' => 240, 'members' => 58900, 'trainers' => 2100, 'workouts' => 480000];
try {
    $stats['gyms'] = max($stats['gyms'], (int) Database::scalar('SELECT COUNT(*) FROM tenants'));
    $stats['members'] = max($stats['members'], (int) Database::scalar('SELECT COUNT(*) FROM members'));
    $stats['trainers'] = max($stats['trainers'], (int) Database::scalar('SELECT COUNT(*) FROM trainers'));
} catch (Throwable $ignored) {
}

// SaaS plans (DB with fallback).
$saasPlans = [];
try {
    $saasPlans = SubscriptionPlan::active();
} catch (Throwable $ignored) {
}
if (!$saasPlans) {
    $saasPlans = [
        ['name' => 'Starter', 'price_monthly' => 29, 'price_yearly' => 290, 'description' => 'For small independent gyms', 'max_members' => 150, 'max_trainers' => 5, 'max_branches' => 1],
        ['name' => 'Professional', 'price_monthly' => 59, 'price_yearly' => 590, 'description' => 'For growing gyms', 'max_members' => 500, 'max_trainers' => 15, 'max_branches' => 3],
        ['name' => 'Business', 'price_monthly' => 119, 'price_yearly' => 1190, 'description' => 'For multi-branch gyms', 'max_members' => 2000, 'max_trainers' => 40, 'max_branches' => 8],
    ];
}

// Demo gym membership plans (DB with fallback).
$memberPlans = [];
try {
    $memberPlans = MembershipPlan::active(1);
} catch (Throwable $ignored) {
}
if (!$memberPlans) {
    $memberPlans = [
        ['name' => 'Monthly', 'price' => 29, 'duration_days' => 30, 'features' => 'Full gym access, 2 group classes / week, Locker room'],
        ['name' => 'Quarterly', 'price' => 79, 'duration_days' => 90, 'features' => 'Full gym access, Unlimited classes, 1 PT session / month'],
        ['name' => 'Annual', 'price' => 290, 'duration_days' => 365, 'features' => 'Full gym access, Unlimited classes, 2 PT sessions / month, Diet plan included'],
    ];
}
$trainers = demo_trainers();
$programs = demo_programs();
$posts = demo_posts();

require __DIR__ . '/../views/website/header.php';
?>

<!-- 1. HERO -->
<header class="hero">
  <div class="container">
    <div class="row align-items-center">
      <div class="col-lg-7">
        <span class="section-tag">Men's Gym Management SaaS</span>
        <h1 class="display-title text-white">Forge Your <span class="accent">Strength.</span><br>Break Your <span class="accent">Limits.</span></h1>
        <p class="lead mt-3">FitFlow Pro runs your entire gym — members, memberships, trainers, QR check-ins, classes, payments and subscriptions — in one powerful multi-tenant platform.</p>
        <div class="d-flex flex-wrap gap-3 mt-4">
          <a href="<?= e(url('public/register.php')) ?>" class="btn btn-ff btn-lg">Join Now</a>
          <a href="<?= e(url('public/pricing.php')) ?>" class="btn btn-ff-outline btn-lg">See Pricing</a>
        </div>
        <div class="d-flex gap-4 mt-4 small" style="color:var(--ff-muted)">
          <span><i class="bi bi-check-circle-fill text-orange me-1"></i>14-day free trial</span>
          <span><i class="bi bi-check-circle-fill text-orange me-1"></i>No card required</span>
          <span><i class="bi bi-check-circle-fill text-orange me-1"></i>Cancel anytime</span>
        </div>
      </div>
    </div>
  </div>
</header>

<!-- 2. GYM STATISTICS -->
<section class="stat-strip py-4">
  <div class="container">
    <div class="row text-center g-3">
      <div class="col-6 col-md-3"><div class="num"><?= number_format($stats['gyms']) ?>+</div><div class="lbl">Gyms Powered</div></div>
      <div class="col-6 col-md-3"><div class="num"><?= number_format($stats['members']) ?>+</div><div class="lbl">Active Members</div></div>
      <div class="col-6 col-md-3"><div class="num"><?= number_format($stats['trainers']) ?>+</div><div class="lbl">Expert Trainers</div></div>
      <div class="col-6 col-md-3"><div class="num"><?= number_format($stats['workouts']) ?>+</div><div class="lbl">Workouts Logged</div></div>
    </div>
  </div>
</section>

<!-- 3. ABOUT -->
<section class="section-pad">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-6">
        <img class="img-fluid rounded-4" alt="Inside a FitFlow Pro powered gym" src="https://images.unsplash.com/photo-1534438327276-14e5300c3a48?w=1000&q=80&auto=format&fit=crop">
      </div>
      <div class="col-lg-6">
        <span class="section-tag">About the platform</span>
        <h2 class="display-title">Built by lifters.<br><span class="accent">Engineered for gyms.</span></h2>
        <p class="mt-3" style="color:var(--ff-muted)">Most gym software is a billing tool with a logo. FitFlow Pro is a complete operating system for men's fitness businesses: front-desk QR check-ins, coach-built workout and diet programs, class bookings, equipment maintenance and full subscription billing — with every gym's data completely isolated.</p>
        <div class="row g-3 mt-2">
          <div class="col-6"><div class="panel h-100"><h5><i class="bi bi-qr-code-scan text-orange me-2"></i>QR Check-ins</h5><p class="small mb-0" style="color:var(--ff-muted)">Members scan in seconds. Attendance tracks itself.</p></div></div>
          <div class="col-6"><div class="panel h-100"><h5><i class="bi bi-phone text-orange me-2"></i>Member App Portal</h5><p class="small mb-0" style="color:var(--ff-muted)">Workouts, diet plans, goals and payments in every member's pocket.</p></div></div>
        </div>
        <a href="<?= e(url('public/about.php')) ?>" class="btn btn-ff mt-4">More About Us</a>
      </div>
    </div>
  </div>
</section>

<!-- 4. FEATURES -->
<section class="section-pad" style="background:var(--ff-charcoal)">
  <div class="container">
    <div class="text-center mb-5"><span class="section-tag">Platform features</span>
      <h2 class="display-title">Everything your gym needs. <span class="accent">Nothing it doesn't.</span></h2></div>
    <div class="row g-4">
      <?php
      $features = [
          ['bi-people', 'Member Management', 'Profiles, BMI, progress photos and full membership history per member.'],
          ['bi-qr-code-scan', 'QR Attendance', 'Unique QR token per member with instant check-in and check-out.'],
          ['bi-arrow-repeat', 'Renewal Automation', 'Expiry alerts and renewal reminders before memberships lapse.'],
          ['bi-person-badge', 'Trainer Management', 'Schedules, specialties and assigned-member tracking.'],
          ['bi-activity', 'Workouts & Diet', 'Program builder, exercise library and macro-based diet plans.'],
          ['bi-credit-card', 'Payments & Invoices', 'Record payments, generate invoices and receipts, track dues.'],
          ['bi-collection', 'Classes & Bookings', 'Capacity-checked class bookings with attendance states.'],
          ['bi-gem', 'Subscriptions', 'Plans, trials, coupons, upgrades and grace periods built in.'],
          ['bi-graph-up', 'Real Analytics', 'Revenue, attendance and growth charts from your actual data.'],
      ];
      foreach ($features as [$icon, $title, $text]): ?>
      <div class="col-md-6 col-lg-4">
        <div class="ff-card p-4 h-100"><div class="icon-wrap mb-3"><i class="bi <?= $icon ?>"></i></div>
          <h5 class="fw-bold"><?= e($title) ?></h5><p class="mb-0" style="color:var(--ff-muted)"><?= e($text) ?></p></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- 5. MEMBERSHIP PRICING CAROUSEL -->
<section class="section-pad">
  <div class="container">
    <div class="text-center mb-5"><span class="section-tag">Membership</span>
      <h2 class="display-title">Pick your <span class="accent">battle plan</span></h2>
      <p style="color:var(--ff-muted)">Live plans from our demo gym, Iron Paradise. Use coupon <strong class="text-orange">WELCOME10</strong> for 10% off your first plan.</p></div>
    <div id="memberPlanCarousel" class="carousel slide" data-bs-ride="carousel">
      <div class="carousel-inner">
        <?php foreach (array_chunk($memberPlans, 3) as $i => $chunk): ?>
        <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
          <div class="row g-4 justify-content-center">
            <?php foreach ($chunk as $idx => $p): ?>
            <div class="col-md-6 col-lg-4">
              <div class="price-card h-100 <?= $idx === 1 ? 'featured' : '' ?>">
                <?php if ($idx === 1): ?><span class="badge badge-ff mb-2">Most Popular</span><?php endif; ?>
                <h4 class="fw-bold"><?= e($p['name']) ?></h4>
                <div class="amount text-orange">$<?= number_format((float) $p['price']) ?><span class="fs-6" style="color:var(--ff-muted)"> / <?= (int) $p['duration_days'] ?> days</span></div>
                <ul class="list-unstyled mt-3">
                  <?php foreach (array_map('trim', explode(',', (string) ($p['features'] ?? ''))) as $f): if ($f === '') continue; ?>
                  <li class="mb-2"><i class="bi bi-check-circle-fill text-orange me-2"></i><?= e($f) ?></li>
                  <?php endforeach; ?>
                </ul>
                <a href="<?= e(url('public/register.php')) ?>" class="btn btn-ff w-100 mt-2">Join with <?= e($p['name']) ?></a>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php if (count($memberPlans) > 3): ?>
      <button class="carousel-control-prev" type="button" data-bs-target="#memberPlanCarousel" data-bs-slide="prev"><span class="carousel-control-prev-icon"></span></button>
      <button class="carousel-control-next" type="button" data-bs-target="#memberPlanCarousel" data-bs-slide="next"><span class="carousel-control-next-icon"></span></button>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- 6. TRAINER CAROUSEL -->
<section class="section-pad" style="background:var(--ff-charcoal)">
  <div class="container">
    <div class="text-center mb-5"><span class="section-tag">Coaches</span>
      <h2 class="display-title">Train with <span class="accent">the best</span></h2></div>
    <div id="trainerCarousel" class="carousel slide" data-bs-ride="carousel">
      <div class="carousel-inner">
        <?php foreach (array_chunk($trainers, 2) as $i => $pair): ?>
        <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
          <div class="row g-4 justify-content-center">
            <?php foreach ($pair as $t): ?>
            <div class="col-md-6 col-lg-5">
              <div class="ff-card trainer-card h-100">
                <img src="<?= e($t['photo']) ?>" alt="<?= e($t['name']) ?>">
                <div class="p-4">
                  <h5 class="fw-bold mb-1"><?= e($t['name']) ?></h5>
                  <div class="text-orange fw-bold small"><?= e($t['specialty']) ?> · <?= (int) $t['exp'] ?> yrs experience</div>
                  <p class="mb-0 mt-2" style="color:var(--ff-muted)"><?= e($t['bio']) ?></p>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <button class="carousel-control-prev" type="button" data-bs-target="#trainerCarousel" data-bs-slide="prev"><span class="carousel-control-prev-icon"></span></button>
      <button class="carousel-control-next" type="button" data-bs-target="#trainerCarousel" data-bs-slide="next"><span class="carousel-control-next-icon"></span></button>
    </div>
    <div class="text-center mt-4"><a class="btn btn-ff-outline" href="<?= e(url('public/trainers.php')) ?>">Meet all trainers</a></div>
  </div>
</section>

<!-- 7. PROGRAMS -->
<section class="section-pad">
  <div class="container">
    <div class="text-center mb-5"><span class="section-tag">Programs</span>
      <h2 class="display-title">Programs that <span class="accent">deliver results</span></h2></div>
    <div class="row g-4">
      <?php foreach ($programs as $p): ?>
      <div class="col-md-6 col-lg-4">
        <div class="ff-card h-100">
          <img class="card-photo" src="<?= e($p['img']) ?>" alt="<?= e($p['name']) ?>">
          <div class="p-4">
            <h5 class="fw-bold"><i class="bi <?= $p['icon'] ?> text-orange me-2"></i><?= e($p['name']) ?></h5>
            <p style="color:var(--ff-muted)"><?= e($p['text']) ?></p>
            <ul class="list-unstyled small">
              <?php foreach ($p['points'] as $pt): ?><li class="mb-1"><i class="bi bi-check-lg text-orange me-1"></i><?= e($pt) ?></li><?php endforeach; ?>
            </ul>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- 8+9. EQUIPMENT GALLERY + TRANSFORMATIONS -->
<section class="section-pad" style="background:var(--ff-charcoal)">
  <div class="container">
    <div class="text-center mb-5"><span class="section-tag">Inside the gym</span>
      <h2 class="display-title">Pro-grade <span class="accent">equipment</span></h2></div>
    <div class="row g-3">
      <?php
      $shots = [
          ['https://images.unsplash.com/photo-1534438327276-14e5300c3a48?w=800&q=80&auto=format&fit=crop', 'Dumbbell rack'],
          ['https://images.unsplash.com/photo-1571902943202-507ec2618e8f?w=800&q=80&auto=format&fit=crop', 'Strength zone'],
          ['https://images.unsplash.com/photo-1599058917212-d750089bc07e?w=800&q=80&auto=format&fit=crop', 'Free weights area'],
          ['https://images.unsplash.com/photo-1517836357463-d25dfeac3438?w=800&q=80&auto=format&fit=crop', 'Functional training'],
      ];
      foreach ($shots as [$src, $alt]): ?>
      <div class="col-6 col-lg-3"><img class="gallery-img" src="<?= e($src) ?>" alt="<?= e($alt) ?>" loading="lazy"></div>
      <?php endforeach; ?>
    </div>
    <div class="row g-4 mt-4 align-items-center">
      <div class="col-lg-5">
        <h3 class="fw-black" style="font-weight:900">REAL PEOPLE. <span class="text-orange">REAL TRANSFORMATIONS.</span></h3>
        <p style="color:var(--ff-muted)">Structured programs, tracked nutrition and coaches who see every check-in. Our members average measurable progress within the first 12 weeks — because everything is measured.</p>
        <a href="<?= e(url('public/gallery.php')) ?>" class="btn btn-ff">See the gallery</a>
      </div>
      <div class="col-lg-7">
        <div class="row g-3">
          <div class="col-6"><img class="gallery-img" style="height:280px" src="https://images.unsplash.com/photo-1550345332-09e3ac987658?w=700&q=80&auto=format&fit=crop" alt="Member transformation — after" loading="lazy"></div>
          <div class="col-6"><img class="gallery-img" style="height:280px" src="https://images.unsplash.com/photo-1583864697784-a0efc8379f70?w=700&q=80&auto=format&fit=crop" alt="Member transformation — training" loading="lazy"></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 10. TESTIMONIALS -->
<section class="section-pad">
  <div class="container">
    <div class="text-center mb-5"><span class="section-tag">Testimonials</span>
      <h2 class="display-title">Members & owners <span class="accent">talk</span></h2></div>
    <div class="row g-4">
      <?php foreach (demo_testimonials() as $t): ?>
      <div class="col-md-6 col-lg-4">
        <div class="testi h-100">
          <div class="text-orange mb-2"><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i></div>
          <p>"<?= e($t['text']) ?>"</p>
          <div class="d-flex align-items-center gap-3 mt-3">
            <span class="avatar-initials"><?= e($t['initials']) ?></span>
            <span><strong class="d-block"><?= e($t['name']) ?></strong><small style="color:var(--ff-muted)"><?= e($t['role']) ?></small></span>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- 11. FACILITIES -->
<section class="section-pad" style="background:var(--ff-charcoal)">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-6">
        <span class="section-tag">Facilities</span>
        <h2 class="display-title">A facility built<br><span class="accent">for war.</span></h2>
        <div class="row g-3 mt-3">
          <?php
          $fac = [
              ['bi-lightning-charge', 'Strength Zone', '8 power racks, competition benches and calibrated plates.'],
              ['bi-heart-pulse', 'Cardio Deck', 'Treadmills, air bikes and rowers with performance tracking.'],
              ['bi-collection', 'Class Studio', 'Daily HIIT, mobility and conditioning classes.'],
              ['bi-droplet', 'Recovery Area', 'Sauna, cold plunge and mobility corner.'],
          ];
          foreach ($fac as [$icon, $name, $text]): ?>
          <div class="col-md-6"><div class="panel h-100"><h6 class="fw-bold"><i class="bi <?= $icon ?> text-orange me-2"></i><?= e($name) ?></h6><p class="small mb-0" style="color:var(--ff-muted)"><?= e($text) ?></p></div></div>
          <?php endforeach; ?>
        </div>
        <a href="<?= e(url('public/facilities.php')) ?>" class="btn btn-ff-outline mt-4">Tour the facilities</a>
      </div>
      <div class="col-lg-6">
        <div id="facilityCarousel" class="carousel slide" data-bs-ride="carousel">
          <div class="carousel-inner rounded-4">
            <div class="carousel-item active"><img class="d-block w-100" style="height:380px;object-fit:cover" src="https://images.unsplash.com/photo-1534438327276-14e5300c3a48?w=1000&q=80&auto=format&fit=crop" alt="Strength zone"></div>
            <div class="carousel-item"><img class="d-block w-100" style="height:380px;object-fit:cover" src="https://images.unsplash.com/photo-1571902943202-507ec2618e8f?w=1000&q=80&auto=format&fit=crop" alt="Training floor"></div>
            <div class="carousel-item"><img class="d-block w-100" style="height:380px;object-fit:cover" src="https://images.unsplash.com/photo-1599058917212-d750089bc07e?w=1000&q=80&auto=format&fit=crop" alt="Free weights"></div>
          </div>
          <button class="carousel-control-prev" type="button" data-bs-target="#facilityCarousel" data-bs-slide="prev"><span class="carousel-control-prev-icon"></span></button>
          <button class="carousel-control-next" type="button" data-bs-target="#facilityCarousel" data-bs-slide="next"><span class="carousel-control-next-icon"></span></button>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 12+13. BMI CALCULATOR + FITNESS GOALS -->
<section class="section-pad">
  <div class="container">
    <div class="row g-4">
      <div class="col-lg-6">
        <div class="panel h-100">
          <span class="section-tag">Free tool</span>
          <h3 class="fw-bold">BMI Calculator</h3>
          <p style="color:var(--ff-muted)">Know your starting point. Members track BMI automatically at every weigh-in.</p>
          <div class="row g-3">
            <div class="col-6"><label class="form-label">Weight (kg)</label><input type="number" id="bmiWeight" class="form-control" placeholder="86" min="1"></div>
            <div class="col-6"><label class="form-label">Height (cm)</label><input type="number" id="bmiHeight" class="form-control" placeholder="180" min="1"></div>
          </div>
          <button type="button" class="btn btn-ff mt-3" onclick="ffCalculateBMI('bmiWeight','bmiHeight','bmiResult')">Calculate my BMI</button>
          <div id="bmiResult" class="mt-3 fs-5"></div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="panel h-100">
          <span class="section-tag">Fitness goals</span>
          <h3 class="fw-bold">Set a goal. We track the war.</h3>
          <p style="color:var(--ff-muted)">Squat targets, bodyweight goals, fat-loss deadlines — every goal lives in the member portal with a live progress bar.</p>
          <?php
          $goalDemo = [['Squat 140 kg', 50], ['Reach 90 kg bodyweight', 62], ['Lose 10 kg body fat', 60]];
          foreach ($goalDemo as [$g, $pct]): ?>
          <div class="mb-3"><div class="d-flex justify-content-between small fw-bold"><span><?= e($g) ?></span><span class="text-orange"><?= $pct ?>%</span></div>
            <div class="progress mt-1"><div class="progress-bar" style="width:<?= $pct ?>%"></div></div></div>
          <?php endforeach; ?>
          <a href="<?= e(url('public/register.php')) ?>" class="btn btn-ff-outline mt-2">Start your first goal</a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 14. DASHBOARD PREVIEW -->
<section class="section-pad" style="background:var(--ff-charcoal)">
  <div class="container">
    <div class="text-center mb-5"><span class="section-tag">SaaS dashboard preview</span>
      <h2 class="display-title">Command your gym<br><span class="accent">from one dashboard</span></h2></div>
    <div class="panel p-0 overflow-hidden">
      <div class="d-flex" style="background:#101012;border-bottom:1px solid var(--ff-border)">
        <div class="p-3 d-none d-md-block" style="width:210px;border-right:1px solid var(--ff-border)">
          <div class="brand-mark fs-6"><span class="bm-icon" style="width:28px;height:28px"><i class="bi bi-lightning-charge-fill"></i></span>FitFlow Pro</div>
          <div class="mt-3 small" style="color:var(--ff-muted)">
            <div class="py-1 text-white"><i class="bi bi-speedometer2 me-2 text-orange"></i>Overview</div>
            <div class="py-1"><i class="bi bi-people me-2"></i>Members</div>
            <div class="py-1"><i class="bi bi-qr-code-scan me-2"></i>QR Check-in</div>
            <div class="py-1"><i class="bi bi-credit-card me-2"></i>Payments</div>
            <div class="py-1"><i class="bi bi-graph-up me-2"></i>Reports</div>
          </div>
        </div>
        <div class="flex-grow-1 p-4">
          <div class="row g-3">
            <div class="col-6 col-lg-3"><div class="kpi"><span class="k-icon"><i class="bi bi-people"></i></span><div class="k-label">Members</div><div class="k-value">5,890</div><small style="color:#4ade80">+12.4% this month</small></div></div>
            <div class="col-6 col-lg-3"><div class="kpi"><span class="k-icon"><i class="bi bi-person-plus"></i></span><div class="k-label">New members</div><div class="k-value">2,000</div><small style="color:#4ade80">+8.1%</small></div></div>
            <div class="col-6 col-lg-3"><div class="kpi"><span class="k-icon"><i class="bi bi-calendar-check"></i></span><div class="k-label">Check-ins today</div><div class="k-value">500</div><small style="color:var(--ff-muted)">Peak 7–8 AM</small></div></div>
            <div class="col-6 col-lg-3"><div class="kpi"><span class="k-icon"><i class="bi bi-cash-stack"></i></span><div class="k-label">Revenue (mo)</div><div class="k-value">$48.2k</div><small style="color:#4ade80">+15.9%</small></div></div>
          </div>
          <div class="panel mt-3">
            <div class="d-flex justify-content-between"><h6 class="fw-bold mb-0">Membership target — November</h6><span class="text-orange fw-bold">75.55%</span></div>
            <div class="progress mt-2" style="height:.8rem"><div class="progress-bar" style="width:75.55%"></div></div>
            <div class="row text-center mt-3 small" style="color:var(--ff-muted)">
              <div class="col-4">Weekly attendance trending up <span class="text-white fw-bold">+18%</span></div>
              <div class="col-4">Renewals due this week <span class="text-white fw-bold">23</span></div>
              <div class="col-4">Classes booked today <span class="text-white fw-bold">141</span></div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="text-center mt-4"><a href="<?= e(url('public/login.php')) ?>" class="btn btn-ff">Open the live demo dashboard</a>
      <div class="small mt-2" style="color:var(--ff-muted)">Demo logins — admin@demo.com / member@demo.com · password: password</div></div>
  </div>
</section>

<!-- 15. SAAS PRICING + HOW REGISTRATION WORKS -->
<section class="section-pad">
  <div class="container">
    <div class="text-center mb-4"><span class="section-tag">For gym owners</span>
      <h2 class="display-title">SaaS pricing that <span class="accent">scales with you</span></h2>
      <div class="form-check form-switch d-inline-flex align-items-center gap-2 mt-3">
        <label class="form-check-label fw-bold" for="billingToggle">Monthly</label>
        <input class="form-check-input" type="checkbox" id="billingToggle" style="width:3rem;height:1.5rem">
        <label class="form-check-label fw-bold" for="billingToggle">Yearly <span class="text-orange">(2 months free)</span></label>
      </div>
    </div>
    <div class="row g-4 justify-content-center">
      <?php foreach ($saasPlans as $i => $p): ?>
      <div class="col-md-6 col-lg-4">
        <div class="price-card h-100 <?= $i === 1 ? 'featured' : '' ?>">
          <?php if ($i === 1): ?><span class="badge badge-ff mb-2">Most Popular</span><?php endif; ?>
          <h4 class="fw-bold"><?= e($p['name']) ?></h4>
          <p class="small" style="color:var(--ff-muted)"><?= e($p['description'] ?? '') ?></p>
          <div class="amount text-orange">$<span data-price-monthly="<?= number_format((float) $p['price_monthly']) ?>" data-price-yearly="<?= number_format((float) $p['price_yearly']) ?>"><?= number_format((float) $p['price_monthly']) ?></span><span class="fs-6 cycle-label" style="color:var(--ff-muted)">/month</span></div>
          <ul class="list-unstyled mt-3 small">
            <li class="mb-2"><i class="bi bi-check-circle-fill text-orange me-2"></i>Up to <?= (int) $p['max_members'] < 0 ? 'Unlimited' : number_format((int) $p['max_members']) ?> members</li>
            <li class="mb-2"><i class="bi bi-check-circle-fill text-orange me-2"></i><?= (int) $p['max_trainers'] < 0 ? 'Unlimited' : (int) $p['max_trainers'] ?> trainers</li>
            <li class="mb-2"><i class="bi bi-check-circle-fill text-orange me-2"></i><?= (int) $p['max_branches'] < 0 ? 'Unlimited' : (int) $p['max_branches'] ?> branch<?= (int) $p['max_branches'] === 1 ? '' : 'es' ?></li>
            <li class="mb-2"><i class="bi bi-check-circle-fill text-orange me-2"></i>QR check-ins, classes &amp; reports</li>
          </ul>
          <a href="<?= e(url('public/register.php')) ?>" class="btn btn-ff w-100">Start free trial</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="row g-4 mt-5 text-center">
      <h3 class="fw-bold">Membership registration in 4 steps</h3>
      <?php
      $steps = [
          ['1', 'Create your gym account', 'Register your gym and start a 14-day free trial.'],
          ['2', 'Set up plans & branches', 'Add your membership plans, trainers and branches.'],
          ['3', 'Register members', 'Enrol members, assign trainers, issue QR tokens.'],
          ['4', 'Train & grow', 'Track attendance, renewals and revenue in real time.'],
      ];
      foreach ($steps as [$n, $t, $d]): ?>
      <div class="col-6 col-lg-3"><div class="panel h-100"><div class="display-6 fw-black text-orange" style="font-weight:900"><?= $n ?></div><h6 class="fw-bold mt-2"><?= e($t) ?></h6><p class="small mb-0" style="color:var(--ff-muted)"><?= e($d) ?></p></div></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- 16. FAQ -->
<section class="section-pad" style="background:var(--ff-charcoal)">
  <div class="container" style="max-width:820px">
    <div class="text-center mb-5"><span class="section-tag">FAQ</span>
      <h2 class="display-title">Questions, <span class="accent">answered</span></h2></div>
    <div class="accordion" id="faqAcc">
      <?php
      $faqs = [
          ['How does the free trial work?', 'Every new gym gets a full-feature 14-day Free Trial plan. No card required. When the trial ends you can pick a paid plan; your members, trainers and data stay exactly where you left them.'],
          ['Can I run multiple branches?', 'Yes. Professional plans include up to 3 branches, Business up to 8, and Enterprise is unlimited. Members, equipment and attendance are tracked per branch while billing stays central.'],
          ['How does QR check-in work?', 'Every member gets a unique QR token on their card and in the member portal. Front-desk scans toggle check-in/check-out instantly, and attendance history builds itself.'],
          ['Are payments marked paid automatically?', 'No — and that is deliberate. Manually recorded payments stay "pending" until they are verified, so your books never show money you have not actually confirmed.'],
          ['Can members see their own progress?', 'Yes. The member portal shows their membership, payments, attendance, assigned workouts, diet plan and goal/BMI progress.'],
          ['Can I use a coupon code?', 'Members can apply codes like WELCOME10 at enrolment for 10% off, and owners can apply platform coupons to subscription upgrades.'],
      ];
      foreach ($faqs as $i => [$q, $a]): ?>
      <div class="accordion-item">
        <h2 class="accordion-header"><button class="accordion-button <?= $i > 0 ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#faq<?= $i ?>"><?= e($q) ?></button></h2>
        <div id="faq<?= $i ?>" class="accordion-collapse collapse <?= $i === 0 ? 'show' : '' ?>" data-bs-parent="#faqAcc"><div class="accordion-body"><?= e($a) ?></div></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- 17. BLOG -->
<section class="section-pad">
  <div class="container">
    <div class="text-center mb-5"><span class="section-tag">From the blog</span>
      <h2 class="display-title">Train smarter, <span class="accent">read faster</span></h2></div>
    <div class="row g-4">
      <?php foreach ($posts as $post): ?>
      <div class="col-md-6 col-lg-4">
        <div class="ff-card h-100">
          <img class="card-photo" src="<?= e($post['img']) ?>" alt="<?= e($post['title']) ?>" loading="lazy">
          <div class="p-4">
            <span class="badge badge-ff"><?= e($post['category']) ?></span>
            <small class="ms-2" style="color:var(--ff-muted)"><?= e($post['date']) ?></small>
            <h5 class="fw-bold mt-2"><?= e($post['title']) ?></h5>
            <p style="color:var(--ff-muted)"><?= e($post['excerpt']) ?></p>
            <a href="<?= e(url('public/blog-details.php')) ?>?post=<?= e($post['slug']) ?>" class="fw-bold">Read article <i class="bi bi-arrow-right"></i></a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- 18. CTA -->
<section class="section-pad" style="background:linear-gradient(120deg,#1a0e04,#0a0a0b)">
  <div class="container text-center">
    <h2 class="display-title">Your first workout is <span class="accent">on us.</span></h2>
    <p class="lead" style="color:var(--ff-muted)">Start your 14-day free trial today — as a gym owner or a member.</p>
    <div class="d-flex justify-content-center gap-3 mt-4 flex-wrap">
      <a href="<?= e(url('public/register.php')) ?>" class="btn btn-ff btn-lg">Start Free Trial</a>
      <a href="<?= e(url('public/contact.php')) ?>" class="btn btn-ff-outline btn-lg">Talk to Sales</a>
    </div>
  </div>
</section>

<?php require __DIR__ . '/../views/website/footer.php'; ?>
