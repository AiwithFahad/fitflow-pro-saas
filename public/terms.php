<?php
require_once __DIR__ . '/../app/bootstrap.php';
$pageTitle = 'Terms of Service — FitFlow Pro';
require __DIR__ . '/../views/website/header.php';
?>
<section class="section-pad">
  <div class="container" style="max-width:820px">
    <h1 class="display-title mb-4">Terms of <span class="accent">Service</span></h1>
    <p style="color:var(--ff-muted)">Last updated: <?= date('F Y') ?>. By creating a FitFlow Pro account you agree to these terms.</p>
    <h4 class="fw-bold mt-4">1. The service</h4>
    <p style="color:var(--ff-muted)">FitFlow Pro provides multi-tenant gym management software — memberships, attendance, coaching tools, payments records and subscriptions — on a subscription basis.</p>
    <h4 class="fw-bold mt-4">2. Accounts and tenants</h4>
    <p style="color:var(--ff-muted)">Each gym operates as an isolated tenant. The gym owner is responsible for the accuracy of records they create, for inviting staff responsibly, and for their members' data.</p>
    <h4 class="fw-bold mt-4">3. Subscriptions and billing</h4>
    <p style="color:var(--ff-muted)">Plans are billed monthly or yearly in advance. Trials run for 14 days without charge. After a subscription expires, a grace period applies before access is restricted; data is retained according to the privacy policy. Plan limits (members, trainers, branches, classes, staff, storage) are enforced as published on the pricing page.</p>
    <h4 class="fw-bold mt-4">4. Payments recorded in the system</h4>
    <p style="color:var(--ff-muted)">Payments recorded manually are marked pending until the business verifies them. FitFlow Pro is a record-keeping tool and is not a payment processor unless a gateway is explicitly connected.</p>
    <h4 class="fw-bold mt-4">5. Acceptable use</h4>
    <p style="color:var(--ff-muted)">You must not attempt to access another tenant's data, probe the platform for vulnerabilities without permission, or use the service for unlawful purposes. Suspended tenants may appeal via support.</p>
    <h4 class="fw-bold mt-4">6. Liability</h4>
    <p style="color:var(--ff-muted)">The service is provided "as is" with commercially reasonable efforts toward availability and data safety, including documented backup procedures. Nothing in these terms limits liability that cannot be limited by law.</p>
  </div>
</section>
<?php require __DIR__ . '/../views/website/footer.php'; ?>
