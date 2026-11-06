<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Gym Owner', 'Gym Admin', 'Accountant', 'Branch Manager'], Auth::homeFor(current_user()));
$tid = tenant_id();
$dashTitle = 'Payments & Finance';
$activeNav = 'payments';
$totals = Payment::totals($tid);
$members = Member::all($tid, 'name ASC', 500);
$branches = Branch::all($tid, 'name ASC', 100);
$receipt = null;
if (!empty($_GET['receipt'])) {
    $receipt = Database::fetch(
        'SELECT p.*, m.name AS member_name FROM payments p LEFT JOIN members m ON m.id = p.member_id WHERE p.id = ? AND p.tenant_id = ?',
        [(int) $_GET['receipt'], $tid]
    );
}

$extraScripts = '<script>
function renderPaymentRow(p){
  var actions = "<a class=\"btn btn-sm btn-outline-light\" href=\"receipt.php?payment_id=" + p.id + "\">Receipt</a>";
  if (p.status === "pending") {
    actions += " <button type=\"button\" class=\"btn btn-sm btn-outline-success\" data-verify=\"" + p.id + "\">Verify</button>" +
               " <button type=\"button\" class=\"btn btn-sm btn-outline-danger\" data-fail=\"" + p.id + "\">Fail</button>";
  }
  return "<tr><td>#" + p.id + "</td>" +
    "<td><span class=\"fw-bold\">" + FF.esc(p.description || p.purpose) + "</span><br><small style=\"color:var(--ff-muted)\">" + FF.esc(p.member_name || "—") + " · " + FF.esc(p.purpose) + (p.reference ? " · " + FF.esc(p.reference) : "") + "</small></td>" +
    "<td class=\"fw-bold\">" + FF.money(p.amount) + "</td><td>" + FF.esc(p.method) + "</td>" +
    "<td>" + FF.pill(p.status) + "</td><td>" + FF.esc((p.paid_at || p.created_at || "").substring(0,10)) + "</td>" +
    "<td class=\"text-end text-nowrap\">" + actions + "</td></tr>";
}
function renderExpenseRow(x){
  return "<tr><td>" + FF.esc(x.expense_date) + "</td><td><span class=\"fw-bold\">" + FF.esc(x.title) + "</span><br><small style=\"color:var(--ff-muted)\">" + FF.esc(x.category) + (x.branch_name ? " · " + FF.esc(x.branch_name) : "") + "</small></td>" +
    "<td class=\"fw-bold\">" + FF.money(x.amount) + "</td><td>" + FF.esc(x.method) + "</td>" +
    "<td class=\"text-end\"><button type=\"button\" class=\"btn btn-sm btn-outline-danger\" data-expdel=\"" + x.id + "\">Delete</button></td></tr>";
}
document.addEventListener("DOMContentLoaded", function(){
  document.addEventListener("ff:saved", function(e){
    if (FF.tables.paymentsTbody) FF.tables.paymentsTbody.refresh();
    if (FF.tables.expensesTbody) FF.tables.expensesTbody.refresh();
    loadSummary();
  });
  document.getElementById("paymentsTbody").addEventListener("click", function(ev){
    var v = ev.target.closest("[data-verify]"), f = ev.target.closest("[data-fail]");
    if (v) act("verify", v.getAttribute("data-verify"), "Verify payment #" + v.getAttribute("data-verify") + "? Only do this once the money is confirmed received.");
    if (f) act("fail", f.getAttribute("data-fail"), "Mark payment #" + f.getAttribute("data-fail") + " as failed?");
    function act(action, id, msg){
      FF.confirm(msg, "Payment").then(function(ok){
        if (!ok) return;
        FF.api("payments.php", {method:"POST", data:{action:action, payment_id:id}})
          .then(function(r){ FF.toast(r.message); FF.tables.paymentsTbody.refresh(); })
          .catch(function(e){ FF.toast(e.message, "error"); });
      });
    }
  });
  document.getElementById("expensesTbody").addEventListener("click", function(ev){
    var d = ev.target.closest("[data-expdel]");
    if (!d) return;
    FF.confirm("Delete this expense?", "Expense").then(function(ok){
      if (!ok) return;
      FF.api("finance.php", {method:"POST", data:{action:"expense_delete", id: d.getAttribute("data-expdel")}})
        .then(function(r){ FF.toast(r.message); FF.tables.expensesTbody.refresh(); loadSummary(); })
        .catch(function(e){ FF.toast(e.message, "error"); });
    });
  });
  function loadSummary(){
    FF.api("finance.php", {data:{view:"summary"}}).then(function(res){
      document.getElementById("finRevenue").textContent = FF.money(res.revenue_verified);
      document.getElementById("finExpenses").textContent = FF.money(res.expenses);
      document.getElementById("finSalaries").textContent = FF.money(res.salaries_period);
      document.getElementById("finNet").textContent = FF.money(res.net);
    }).catch(function(){});
  }
  loadSummary();
});
</script>';

require __DIR__ . '/../views/gym-admin/header.php';
?>
<?php if ($receipt): ?>
<div class="panel mb-3" id="receiptPanel">
  <div class="d-flex justify-content-between"><h5><i class="bi bi-receipt text-orange me-1"></i>Payment receipt</h5>
    <a class="btn btn-sm btn-ff-outline" href="<?= e(url('admin/receipt.php')) ?>?payment_id=<?= (int) $receipt['id'] ?>">Printable version</a></div>
  <table class="table table-sm mb-0" style="max-width:520px">
    <tr><th>Receipt #</th><td><?= e($receipt['reference'] ?? ('PAY-' . $receipt['id'])) ?></td></tr>
    <tr><th>Member</th><td><?= e($receipt['member_name'] ?? '—') ?></td></tr>
    <tr><th>Description</th><td><?= e($receipt['description'] ?? '') ?></td></tr>
    <tr><th>Amount</th><td class="fw-bold"><?= money($receipt['amount']) ?></td></tr>
    <tr><th>Status</th><td><span class="status-pill st-<?= e($receipt['status']) ?>"><?= e($receipt['status']) ?></span></td></tr>
  </table>
</div>
<?php endif; ?>

<div class="row g-3 mb-1">
  <div class="col-6 col-xl-3"><div class="kpi"><div class="k-label">Verified revenue (all time)</div><div class="k-value"><?= money($totals['verified']) ?></div></div></div>
  <div class="col-6 col-xl-3"><div class="kpi"><div class="k-label">Pending verification</div><div class="k-value"><?= money($totals['pending']) ?></div><div class="small" style="color:var(--ff-muted)">never counted until verified</div></div></div>
  <div class="col-6 col-xl-3"><div class="kpi"><div class="k-label">This month — revenue / expenses</div><div class="k-value fs-4"><span id="finRevenue">…</span> <small style="color:var(--ff-muted)">/</small> <span id="finExpenses">…</span></div></div></div>
  <div class="col-6 col-xl-3"><div class="kpi"><div class="k-label">This month — salaries / net</div><div class="k-value fs-4"><span id="finSalaries">…</span> <small style="color:var(--ff-muted)">/</small> <span id="finNet">…</span></div></div></div>
</div>

<div class="panel mb-3">
  <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <h5 class="mb-0 me-2">Payments</h5>
    <select class="form-select" style="max-width:170px" data-lt-filter="status" data-lt-for="paymentsTbody">
      <option value="">All statuses</option><option value="pending">Pending</option><option value="verified">Verified</option><option value="failed">Failed</option><option value="refunded">Refunded</option>
    </select>
    <select class="form-select" style="max-width:170px" data-lt-filter="purpose" data-lt-for="paymentsTbody">
      <option value="">All purposes</option><option value="membership">Membership</option><option value="subscription">Subscription</option><option value="other">Other</option>
    </select>
    <div class="ms-auto">
      <?php if (can('payments.manage')): ?><button type="button" class="btn btn-ff" data-bs-toggle="modal" data-bs-target="#recordModal"><i class="bi bi-plus-lg me-1"></i>Record payment</button><?php endif; ?>
    </div>
  </div>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>#</th><th>Description</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th><th class="text-end">Actions</th></tr></thead>
    <tbody id="paymentsTbody" data-live-table data-endpoint="payments.php" data-render="renderPaymentRow" data-per-page="15"></tbody>
  </table></div>
  <div class="d-flex align-items-center mt-3" data-lt-pager="paymentsTbody"></div>
</div>

<div class="panel">
  <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <h5 class="mb-0 me-2">Expenses</h5>
    <div class="ms-auto">
      <?php if (can('finance.manage')): ?><button type="button" class="btn btn-ff-outline" data-bs-toggle="modal" data-bs-target="#expenseModal"><i class="bi bi-plus-lg me-1"></i>Add expense</button><?php endif; ?>
    </div>
  </div>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Date</th><th>Expense</th><th>Amount</th><th>Method</th><th class="text-end">Actions</th></tr></thead>
    <tbody id="expensesTbody" data-live-table data-endpoint="finance.php?view=expenses" data-render="renderExpenseRow" data-per-page="10"></tbody>
  </table></div>
  <div class="d-flex align-items-center mt-3" data-lt-pager="expensesTbody"></div>
  <p class="small mt-2 mb-0" style="color:var(--ff-muted)">Full finance reports (revenue vs expenses vs salaries, CSV) live under Reports. Salary records live under Staff &amp; Salaries.</p>
</div>

<?php if (can('payments.manage')): ?>
<div class="modal fade" id="recordModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form data-ajax data-endpoint="payments.php" data-reset>
    <div class="modal-header"><h5 class="modal-title">Record a payment</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body">
      <input type="hidden" name="action" value="record">
      <p class="small" style="color:var(--ff-muted)">The payment starts as <strong>pending</strong> and becomes revenue only after verification.</p>
      <div class="mb-2"><label class="form-label">Member (optional)</label><select class="form-select" name="member_id"><option value="">—</option><?php foreach ($members as $m): ?><option value="<?= (int) $m['id'] ?>"><?= e($m['name']) ?></option><?php endforeach; ?></select></div>
      <div class="mb-2"><label class="form-label">Description</label><input class="form-control" name="description" required placeholder="e.g. PT package — 10 sessions"></div>
      <div class="row g-2">
        <div class="col-6 mb-2"><label class="form-label">Amount ($)</label><input type="number" step="0.01" class="form-control" name="amount" required></div>
        <div class="col-6 mb-2"><label class="form-label">Purpose</label><select class="form-select" name="purpose"><option value="membership">Membership</option><option value="other">Other</option></select></div>
        <div class="col-6 mb-2"><label class="form-label">Method</label><select class="form-select" name="method"><option value="cash">Cash</option><option value="card">Card</option><option value="bank_transfer">Bank transfer</option><option value="manual">Manual</option></select></div>
        <div class="col-6 mb-2"><label class="form-label">Reference</label><input class="form-control" name="reference" placeholder="Optional"></div>
      </div>
    </div>
    <div class="modal-footer"><button class="btn btn-ff">Record (pending)</button></div>
  </form>
</div></div></div>
<?php endif; ?>

<?php if (can('finance.manage')): ?>
<div class="modal fade" id="expenseModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form data-ajax data-endpoint="finance.php" data-reset>
    <div class="modal-header"><h5 class="modal-title">Add expense</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body">
      <input type="hidden" name="action" value="expense_create">
      <div class="mb-2"><label class="form-label">Title</label><input class="form-control" name="title" required placeholder="e.g. Electricity — Main Branch"></div>
      <div class="row g-2">
        <div class="col-6 mb-2"><label class="form-label">Category</label><select class="form-select" name="category"><?php foreach (Expense::categories() as $c): ?><option><?= $c ?></option><?php endforeach; ?></select></div>
        <div class="col-6 mb-2"><label class="form-label">Amount ($)</label><input type="number" step="0.01" class="form-control" name="amount" required></div>
        <div class="col-6 mb-2"><label class="form-label">Date</label><input type="date" class="form-control" name="expense_date" value="<?= e(date('Y-m-d')) ?>"></div>
        <div class="col-6 mb-2"><label class="form-label">Branch</label><select class="form-select" name="branch_id"><option value="">—</option><?php foreach ($branches as $b): ?><option value="<?= (int) $b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-6 mb-2"><label class="form-label">Method</label><select class="form-select" name="method"><option value="bank">Bank</option><option value="card">Card</option><option value="cash">Cash</option></select></div>
        <div class="col-6 mb-2"><label class="form-label">Reference</label><input class="form-control" name="reference" placeholder="Bill / txn no."></div>
      </div>
      <div class="mb-2"><label class="form-label">Notes</label><input class="form-control" name="notes"></div>
    </div>
    <div class="modal-footer"><button class="btn btn-ff">Save expense</button></div>
  </form>
</div></div></div>
<?php endif; ?>
<?php require __DIR__ . '/../views/gym-admin/footer.php'; ?>
