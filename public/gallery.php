<?php
require_once __DIR__ . '/../app/bootstrap.php';
$pageTitle = 'Gallery — FitFlow Pro';
$activePage = 'gallery';
require __DIR__ . '/../views/website/header.php';
$photos = [
    ['https://images.unsplash.com/photo-1534438327276-14e5300c3a48?w=800&q=80&auto=format&fit=crop', 'Dumbbell rack at dawn'],
    ['https://images.unsplash.com/photo-1571902943202-507ec2618e8f?w=800&q=80&auto=format&fit=crop', 'Evening strength session'],
    ['https://images.unsplash.com/photo-1526506118085-60ce8714f8c5?w=800&q=80&auto=format&fit=crop', 'Barbell curls'],
    ['https://images.unsplash.com/photo-1517836357463-d25dfeac3438?w=800&q=80&auto=format&fit=crop', 'Dumbbell work'],
    ['https://images.unsplash.com/photo-1599058917212-d750089bc07e?w=800&q=80&auto=format&fit=crop', 'Free weights arena'],
    ['https://images.unsplash.com/photo-1541534741688-6078c6bfb5c5?w=800&q=80&auto=format&fit=crop', 'Bodyweight conditioning'],
    ['https://images.unsplash.com/photo-1550345332-09e3ac987658?w=800&q=80&auto=format&fit=crop', 'Transformation — 16 weeks'],
    ['https://images.unsplash.com/photo-1583864697784-a0efc8379f70?w=800&q=80&auto=format&fit=crop', 'Transformation — fight camp'],
    ['https://images.unsplash.com/photo-1538805060514-97d9cc17730c?w=800&q=80&auto=format&fit=crop', 'Cardio deck sprints'],
];
?>
<section class="section-pad">
  <div class="container">
    <div class="text-center mb-5"><span class="section-tag">Gallery</span>
      <h1 class="display-title">Inside<br><span class="accent">the iron paradise</span></h1>
      <p style="color:var(--ff-muted)">Training floors, equipment and member transformations from FitFlow Pro powered gyms.</p></div>
    <div class="row g-3">
      <?php foreach ($photos as [$src, $alt]): ?>
      <div class="col-6 col-md-4"><img class="gallery-img" src="<?= e($src) ?>" alt="<?= e($alt) ?>" loading="lazy"></div>
      <?php endforeach; ?>
    </div>
    <div class="text-center mt-5"><a href="<?= e(url('public/register.php')) ?>" class="btn btn-ff btn-lg">Become our next transformation</a></div>
  </div>
</section>
<?php require __DIR__ . '/../views/website/footer.php'; ?>
