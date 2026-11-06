<?php
require_once __DIR__ . '/../app/bootstrap.php';
$pageTitle = 'Privacy Policy — FitFlow Pro';
require __DIR__ . '/../views/website/header.php';
?>
<section class="section-pad">
  <div class="container" style="max-width:820px">
    <h1 class="display-title mb-4">Privacy <span class="accent">Policy</span></h1>
    <p style="color:var(--ff-muted)">Last updated: <?= date('F Y') ?>. This policy explains what FitFlow Pro collects, why, and how tenant data stays separated.</p>
    <h4 class="fw-bold mt-4">1. Data we process</h4>
    <p style="color:var(--ff-muted)">Account data (name, email, phone), gym business records created by tenants (members, memberships, attendance, payments), and technical logs (logins, activity and audit trails) needed to operate and secure the platform.</p>
    <h4 class="fw-bold mt-4">2. Tenant isolation</h4>
    <p style="color:var(--ff-muted)">Every tenant's records carry a tenant identifier and are only visible to that tenant's authorised users. Platform administrators access tenant data only for support, billing and security purposes, and such access is logged.</p>
    <h4 class="fw-bold mt-4">3. Health-related data</h4>
    <p style="color:var(--ff-muted)">Members may store body measurements (weight, BMI, body-fat percentage) and fitness goals. This data belongs to the member's gym, is visible only to the member and their authorised coaches, and can be removed on request to the gym.</p>
    <h4 class="fw-bold mt-4">4. Payments</h4>
    <p style="color:var(--ff-muted)">We record payment amounts, methods and verification status. Manually recorded payments remain "pending" until verified by the business. We never store full card numbers.</p>
    <h4 class="fw-bold mt-4">5. Security</h4>
    <p style="color:var(--ff-muted)">Passwords are hashed with modern algorithms, sessions are protected, and sensitive actions are audit-logged. See the security documentation shipped with the application for details.</p>
    <h4 class="fw-bold mt-4">6. Your rights</h4>
    <p style="color:var(--ff-muted)">You may request access, correction or deletion of your personal data through your gym or via the contact page. We respond within 30 days.</p>
  </div>
</section>
<?php require __DIR__ . '/../views/website/footer.php'; ?>
