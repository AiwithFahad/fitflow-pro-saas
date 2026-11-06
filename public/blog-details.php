<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../views/website/data.php';
$slug = $_GET['post'] ?? '';
$post = null;
foreach (demo_posts() as $p) {
    if ($p['slug'] === $slug) {
        $post = $p;
        break;
    }
}
$post = $post ?? demo_posts()[0];
$pageTitle = $post['title'] . ' — FitFlow Pro Blog';
$activePage = 'blog';
require __DIR__ . '/../views/website/header.php';
?>
<section class="section-pad">
  <div class="container" style="max-width:820px">
    <a href="<?= e(url('public/blog.php')) ?>" class="fw-bold"><i class="bi bi-arrow-left me-1"></i>All articles</a>
    <div class="mt-3"><span class="badge badge-ff"><?= e($post['category']) ?></span><small class="ms-2" style="color:var(--ff-muted)"><?= e($post['date']) ?> · FitFlow Pro Coaching Team</small></div>
    <h1 class="fw-bold mt-3" style="font-weight:900"><?= e($post['title']) ?></h1>
    <img class="img-fluid rounded-4 my-4 w-100" style="max-height:420px;object-fit:cover" src="<?= e($post['img']) ?>" alt="<?= e($post['title']) ?>">
    <p class="lead" style="color:#d4d4d8"><?= e($post['excerpt']) ?></p>
    <p style="color:var(--ff-muted)">The principle behind every program we write at FitFlow Pro gyms is simple: measurable inputs create measurable outcomes. That means logging your working sets, your bodyweight, your sleep and your food — not guessing. Members who track consistently in the portal progress roughly twice as fast as those who train by feel alone, because their coach can see exactly what happened between sessions.</p>
    <h4 class="fw-bold mt-4">Start with the minimum effective dose</h4>
    <p style="color:var(--ff-muted)">Pick the smallest change you can repeat for four weeks: two extra reps, 2.5 kg on the bar, one more weekly session, or 20 grams more protein a day. Small hinges swing big doors. Your trainer will review the data at your next check-in and progress the plan from there.</p>
    <h4 class="fw-bold mt-4">Track it or it didn't happen</h4>
    <p style="color:var(--ff-muted)">Every FitFlow Pro membership includes the member portal: workouts assigned by your coach, your diet plan, attendance history and goal progress. Check in with your QR token, log your weigh-ins, and let the trend line — not a single bad day — tell the story.</p>
    <div class="panel mt-5 text-center">
      <h4 class="fw-bold">Ready to put this into practice?</h4>
      <p style="color:var(--ff-muted)">Join a FitFlow Pro powered gym and get coached programs, tracked progress and a plan that adapts.</p>
      <a href="<?= e(url('public/register.php')) ?>" class="btn btn-ff">Start free trial</a>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../views/website/footer.php'; ?>
