<footer class="ff-footer pt-5 pb-4 mt-0">
  <div class="container">
    <div class="row g-4">
      <div class="col-md-4">
        <a class="brand-mark mb-3" href="<?= e(url('public/index.php')) ?>"><span class="bm-icon"><i class="bi bi-lightning-charge-fill"></i></span>FitFlow<span class="text-orange">Pro</span></a>
        <p class="mt-3">The complete SaaS platform for men's gyms — members, trainers, memberships, QR check-ins, classes, payments and subscriptions in one powerful system.</p>
        <div class="d-flex gap-3 fs-5">
          <a href="#" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
          <a href="#" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
          <a href="#" aria-label="YouTube"><i class="bi bi-youtube"></i></a>
          <a href="#" aria-label="X"><i class="bi bi-twitter-x"></i></a>
        </div>
      </div>
      <div class="col-6 col-md-2">
        <h6 class="text-white fw-bold mb-3">Platform</h6>
        <ul class="list-unstyled">
          <li><a href="<?= e(url('public/features.php')) ?>">Features</a></li>
          <li><a href="<?= e(url('public/pricing.php')) ?>">Pricing</a></li>
          <li><a href="<?= e(url('public/plans.php')) ?>">Membership Plans</a></li>
          <li><a href="<?= e(url('public/facilities.php')) ?>">Facilities</a></li>
          <li><a href="<?= e(url('public/gallery.php')) ?>">Gallery</a></li>
        </ul>
      </div>
      <div class="col-6 col-md-2">
        <h6 class="text-white fw-bold mb-3">Training</h6>
        <ul class="list-unstyled">
          <li><a href="<?= e(url('public/programs.php')) ?>">Workout Programs</a></li>
          <li><a href="<?= e(url('public/trainers.php')) ?>">Our Trainers</a></li>
          <li><a href="<?= e(url('public/blog.php')) ?>">Fitness Blog</a></li>
          <li><a href="<?= e(url('public/about.php')) ?>">About Us</a></li>
        </ul>
      </div>
      <div class="col-md-4">
        <h6 class="text-white fw-bold mb-3">Stay in the loop</h6>
        <p>Training tips and platform updates, once a month. No spam.</p>
        <form method="post" action="<?= e(url('public/contact.php')) ?>" class="d-flex gap-2">
          <?= csrf_field() ?>
          <input type="hidden" name="form" value="newsletter">
          <input type="email" name="email" class="form-control" placeholder="Your email" required>
          <button class="btn btn-ff" type="submit">Join</button>
        </form>
        <ul class="list-unstyled mt-3">
          <li><a href="<?= e(url('public/privacy.php')) ?>">Privacy Policy</a> · <a href="<?= e(url('public/terms.php')) ?>">Terms of Service</a> · <a href="<?= e(url('public/contact.php')) ?>">Contact</a></li>
        </ul>
      </div>
    </div>
    <hr style="border-color:var(--ff-border)">
    <div class="d-flex flex-wrap justify-content-between small">
      <span>© <?= date('Y') ?> FitFlow Pro. All rights reserved.</span>
      <span>Built for gyms that take training seriously.</span>
    </div>
  </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(url('public/assets/js/app.js')) ?>"></script>
</body>
</html>
