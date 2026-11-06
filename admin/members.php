<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Gym Owner', 'Gym Admin', 'Branch Manager', 'Trainer', 'Receptionist'], Auth::homeFor(current_user()));
$tid = tenant_id();
$dashTitle = 'Members';
$activeNav = 'members';
$canMedical = can('members.medical');
$branches = Branch::all($tid, 'name ASC', 100);
$trainers = Trainer::all($tid, 'name ASC', 100);
$plans = MembershipPlan::active($tid);
$planNames = array_values(array_unique(array_map(fn ($p) => $p['name'], $plans)));

$extraScripts = '<script>
function statusPill(s){ return "<span class=\"status-pill st-" + FF.esc(s) + "\">" + FF.esc(s) + "</span>"; }
function renderMemberRow(m){
  var photo = m.photo_url ? "<img src=\"" + FF.esc(m.photo_url) + "\" alt=\"\" class=\"rounded-circle me-2\" width=\"34\" height=\"34\" style=\"object-fit:cover\">" : "";
  var plan = m.membership_plan ? FF.esc(m.membership_plan) + " <small style=\"color:var(--ff-muted)\">→ " + FF.esc(m.membership_end || "") + (m.days_left !== null && m.days_left !== undefined ? " (" + m.days_left + "d)" : "") + "</small>" : "<span style=\"color:var(--ff-muted)\">No membership</span>";
  return "<tr>" +
    "<td><div class=\"d-flex align-items-center\">" + photo + "<div><span class=\"fw-bold\">" + FF.esc(m.name) + "</span><br><small style=\"color:var(--ff-muted)\">" + FF.esc(m.member_code || "") + " · QR " + FF.esc(m.qr_token || "") + "</small></div></div></td>" +
    "<td>" + FF.esc(m.email || "") + "<br><small style=\"color:var(--ff-muted)\">" + FF.esc(m.phone || "") + "</small></td>" +
    "<td>" + FF.esc(m.branch_name || "—") + "<br><small style=\"color:var(--ff-muted)\">" + FF.esc(m.trainer_name || "No trainer") + "</small></td>" +
    "<td>" + plan + "<br>" + statusPill(m.computed_status || "none") + "</td>" +
    "<td>" + statusPill(m.status) + "</td>" +
    "<td class=\"text-end text-nowrap\">" +
      "<button type=\"button\" class=\"btn btn-sm btn-outline-light\" data-view=\"" + m.id + "\">View</button> " +
      "<button type=\"button\" class=\"btn btn-sm btn-outline-light\" data-edit=\"" + m.id + "\">Edit</button> " +
      "<button type=\"button\" class=\"btn btn-sm btn-outline-danger\" data-del=\"" + m.id + "\" data-name=\"" + FF.esc(m.name) + "\">Delete</button>" +
    "</td></tr>";
}

document.addEventListener("DOMContentLoaded", function(){
  var tbody = document.getElementById("membersTbody");
  function refresh(){ if (FF.tables.membersTbody) FF.tables.membersTbody.refresh(); }
  document.addEventListener("ff:saved", refresh);

  tbody.addEventListener("click", function(ev){
    var viewBtn = ev.target.closest("[data-view]");
    var editBtn = ev.target.closest("[data-edit]");
    var delBtn = ev.target.closest("[data-del]");
    if (viewBtn) openMember(parseInt(viewBtn.getAttribute("data-view"), 10), false);
    if (editBtn) openMember(parseInt(editBtn.getAttribute("data-edit"), 10), true);
    if (delBtn) {
      var id = parseInt(delBtn.getAttribute("data-del"), 10);
      FF.confirm("Delete member \"" + delBtn.getAttribute("data-name") + "\"? Their memberships, attendance and records are removed too.", "Delete member")
        .then(function(ok){
          if (!ok) return;
          FF.api("members.php", {method:"POST", data:{action:"delete", id:id}})
            .then(function(r){ FF.toast(r.message); refresh(); })
            .catch(function(e){ FF.toast(e.message, "error"); });
        });
    }
  });

  function fillEdit(m){
    var f = document.getElementById("editForm");
    ["id","name","email","phone","photo_url","address","emergency_contact_name","emergency_contact_phone","gender","date_of_birth","height_cm","weight_kg","status"].forEach(function(k){
      var el = f.querySelector("[name=\"" + k + "\"]");
      if (el) el.value = m[k] === null || m[k] === undefined ? "" : m[k];
    });
    f.querySelector("[name=\"branch_id\"]").value = m.branch_id || "";
    f.querySelector("[name=\"trainer_id\"]").value = m.trainer_id || "";
  }

  window.openMember = function(id, edit){
    FF.api("members.php?id=" + id).then(function(res){
      var m = res.member, ms = res.membership;
      if (edit) { fillEdit(m); bootstrap.Modal.getOrCreateInstance(document.getElementById("editModal")).show(); return; }
      var html = "";
      html += "<div class=\"d-flex align-items-center gap-3 mb-3\">" +
        (m.photo_url ? "<img src=\"" + FF.esc(m.photo_url) + "\" class=\"rounded-circle\" width=\"64\" height=\"64\" style=\"object-fit:cover\">" : "") +
        "<div><h5 class=\"mb-0\">" + FF.esc(m.name) + "</h5><span style=\"color:var(--ff-muted)\">" + FF.esc(m.member_code || "") + " · QR " + FF.esc(m.qr_token || "") + " · joined " + FF.esc(m.joined_at || "") + "</span></div></div>";
      html += "<table class=\"table table-sm\"><tbody>" +
        "<tr><th style=\"width:38%\">Contact</th><td>" + FF.esc(m.email || "—") + " · " + FF.esc(m.phone || "—") + "</td></tr>" +
        "<tr><th>Address</th><td>" + FF.esc(m.address || "—") + "</td></tr>" +
        "<tr><th>Emergency contact</th><td>" + FF.esc(m.emergency_contact_name || "—") + (m.emergency_contact_phone ? " · " + FF.esc(m.emergency_contact_phone) : "") + "</td></tr>" +
        "<tr><th>Body</th><td>" + (m.height_cm ? m.height_cm + " cm" : "—") + " · " + (m.weight_kg ? m.weight_kg + " kg" : "—") + " · BMI " + (m.bmi || "—") + "</td></tr>" +
        "<tr><th>Trainer</th><td>" + (res.trainer ? FF.esc(res.trainer.name) + " (" + FF.esc(res.trainer.specialty || "") + ")" : "—") + "</td></tr></tbody></table>";
      if (ms) {
        var pct = 0;
        if (ms.start_date && ms.end_date) {
          var total = (new Date(ms.end_date) - new Date(ms.start_date)) / 86400000;
          pct = total > 0 ? Math.max(0, Math.min(100, Math.round(ms.days_left / total * 100))) : 0;
        }
        html += "<h6 class=\"mt-3\">Membership — " + FF.esc(ms.plan_name) + "</h6>" +
          "<div class=\"d-flex justify-content-between small mb-1\"><span>" + FF.esc(ms.start_date) + " → " + FF.esc(ms.end_date) + "</span><span><strong>" + ms.days_left + " days left</strong> · " + statusPill(ms.computed_status) + "</span></div>" +
          "<div class=\"progress mb-2\" style=\"height:8px;background:#26262b\"><div class=\"progress-bar\" style=\"width:" + pct + "%;background:var(--ff-orange)\"></div></div>" +
          "<div class=\"d-flex gap-1 flex-wrap\" id=\"msOps\" data-ms=\"" + ms.id + "\">" +
            "<button class=\"btn btn-sm btn-outline-info\" data-msop=\"renew\">Renew</button>" +
            (ms.status === "active" || ms.status === "expired" ? "<button class=\"btn btn-sm btn-outline-warning\" data-msop=\"suspend\">Suspend</button>" : "") +
            (ms.status === "suspended" ? "<button class=\"btn btn-sm btn-outline-success\" data-msop=\"reactivate\">Reactivate</button>" : "") +
            (ms.status !== "cancelled" ? "<button class=\"btn btn-sm btn-outline-danger\" data-msop=\"cancel\">Cancel</button>" : "") +
          "</div>";
      } else {
        html += "<p class=\"small mt-3\" style=\"color:var(--ff-muted)\">No membership yet — assign one from the Memberships page.</p>";
      }
      if (res.goals && res.goals.length) {
        html += "<h6 class=\"mt-3\">Fitness goals</h6><ul class=\"small mb-0\">" + res.goals.map(function(g){
          return "<li>" + FF.esc(g.title) + " — " + FF.esc(g.current_value || 0) + " / " + FF.esc(g.target_value || "") + " " + FF.esc(g.unit || "") + " (" + FF.esc(g.status) + ")</li>";
        }).join("") + "</ul>";
      }
      if (res.progress && res.progress.length) {
        html += "<h6 class=\"mt-3\">Progress history</h6><ul class=\"small mb-0\">" + res.progress.slice(0,5).map(function(p){
          return "<li>" + FF.esc((p.recorded_at || "").substring(0,10)) + " — " + FF.esc(p.weight_kg) + " kg · BMI " + FF.esc(p.bmi || "—") + (p.notes ? " · " + FF.esc(p.notes) : "") + "</li>";
        }).join("") + "</ul>";
      }
      if (res.medical_restricted) {
        html += "<div class=\"alert alert-dark mt-3 mb-0 small\"><i class=\"bi bi-lock me-1\"></i>Medical clearance records are restricted — your role does not hold the <code>members.medical</code> permission.</div>";
      } else if (res.medical) {
        var med = res.medical;
        html += "<h6 class=\"mt-3\">Medical clearance " + statusPill(med.clearance_status) + "</h6><table class=\"table table-sm mb-0\"><tbody>" +
          "<tr><th style=\"width:38%\">Conditions</th><td>" + FF.esc(med.conditions || "—") + "</td></tr>" +
          "<tr><th>Allergies</th><td>" + FF.esc(med.allergies || "—") + "</td></tr>" +
          "<tr><th>Medications</th><td>" + FF.esc(med.medications || "—") + "</td></tr>" +
          "<tr><th>Doctor</th><td>" + FF.esc(med.doctor_name || "—") + "</td></tr>" +
          "<tr><th>Valid</th><td>" + FF.esc(med.clearance_date || "—") + " → " + FF.esc(med.expiry_date || "—") + "</td></tr>" +
          "<tr><th>Notes</th><td>" + FF.esc(med.notes || "—") + "</td></tr></tbody></table>";
      } else {
        html += "<p class=\"small mt-3 mb-0\" style=\"color:var(--ff-muted)\">No medical clearance record on file.</p>";
      }
      document.getElementById("memberDetailBody").innerHTML = html;
      document.getElementById("progMemberId").value = m.id;
      var medForm = document.getElementById("medicalForm");
      if (medForm) medForm.querySelector("[name=\"member_id\"]").value = m.id;
      bootstrap.Modal.getOrCreateInstance(document.getElementById("memberModal")).show();
      var ops = document.getElementById("msOps");
      if (ops) ops.addEventListener("click", function(ev){
        var b = ev.target.closest("[data-msop]");
        if (!b) return;
        var op = b.getAttribute("data-msop");
        FF.confirm("Apply \"" + op + "\" to this membership?", "Membership").then(function(ok){
          if (!ok) return;
          FF.api("members.php", {method:"POST", data:{action:"membership_status", membership_id: ops.getAttribute("data-ms"), op: op}})
            .then(function(r){ FF.toast(r.message); bootstrap.Modal.getOrCreateInstance(document.getElementById("memberModal")).hide(); refresh(); })
            .catch(function(e){ FF.toast(e.message, "error"); });
        });
      });
    }).catch(function(e){ FF.toast(e.message, "error"); });
  };

  // CSV export follows the current filters.
  document.getElementById("exportBtn").addEventListener("click", function(){
    var p = new URLSearchParams();
    var q = document.querySelector("[data-lt-search=\"membersTbody\"]");
    if (q && q.value) p.set("q", q.value);
    document.querySelectorAll("[data-lt-filter][data-lt-for=\"membersTbody\"]").forEach(function(el){
      if (el.value) p.set(el.getAttribute("data-lt-filter"), el.value);
    });
    window.location = "../api/members-export.php?" + p.toString();
  });
});
</script>';

require __DIR__ . '/../views/gym-admin/header.php';
?>
<div class="panel">
  <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <div class="search-box"><i class="bi bi-search"></i><input data-lt-search="membersTbody" placeholder="Live search name, email, phone, member ID, QR…"></div>
    <select class="form-select" style="max-width:170px" data-lt-filter="status" data-lt-for="membersTbody">
      <option value="">All member states</option><option value="active">Active</option><option value="inactive">Inactive</option><option value="suspended">Suspended</option><option value="cancelled">Cancelled</option>
    </select>
    <select class="form-select" style="max-width:190px" data-lt-filter="branch_id" data-lt-for="membersTbody">
      <option value="">All branches</option>
      <?php foreach ($branches as $b): ?><option value="<?= (int) $b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?>
    </select>
    <select class="form-select" style="max-width:190px" data-lt-filter="plan" data-lt-for="membersTbody">
      <option value="">All plans</option>
      <?php foreach ($planNames as $pn): ?><option value="<?= e($pn) ?>"><?= e($pn) ?></option><?php endforeach; ?>
    </select>
    <div class="ms-auto d-flex gap-2">
      <button type="button" class="btn btn-ff-outline" id="exportBtn"><i class="bi bi-download me-1"></i>Export CSV</button>
      <?php if (can('members.create')): ?><button type="button" class="btn btn-ff" data-bs-toggle="modal" data-bs-target="#createModal"><i class="bi bi-person-plus me-1"></i>New member</button><?php endif; ?>
    </div>
  </div>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Member</th><th>Contact</th><th>Branch / Trainer</th><th>Membership</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
    <tbody id="membersTbody" data-live-table data-endpoint="members.php" data-render="renderMemberRow" data-per-page="15"></tbody>
  </table></div>
  <div class="d-flex align-items-center mt-3" data-lt-pager="membersTbody"></div>
  <p class="small mt-2 mb-0" style="color:var(--ff-muted)">Membership IDs (FFP-M…) and QR tokens are generated automatically. Medical records open only for roles holding the medical permission<?= $canMedical ? ' — your role has it' : '' ?>.</p>
</div>

<!-- Create member -->
<div class="modal fade" id="createModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
  <form data-ajax data-endpoint="members.php" data-reset>
    <div class="modal-header"><h5 class="modal-title">Register member</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body">
      <input type="hidden" name="action" value="create">
      <div class="row g-2">
        <div class="col-md-6"><label class="form-label">Full name *</label><input class="form-control" name="name" required></div>
        <div class="col-md-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email"></div>
        <div class="col-md-3"><label class="form-label">Phone</label><input class="form-control" name="phone"></div>
        <div class="col-md-6"><label class="form-label">Photo URL</label><input class="form-control" name="photo_url" placeholder="https://…"></div>
        <div class="col-md-6"><label class="form-label">Address</label><input class="form-control" name="address"></div>
        <div class="col-md-4"><label class="form-label">Emergency contact name</label><input class="form-control" name="emergency_contact_name"></div>
        <div class="col-md-4"><label class="form-label">Emergency contact phone</label><input class="form-control" name="emergency_contact_phone"></div>
        <div class="col-md-4"><label class="form-label">Date of birth</label><input type="date" class="form-control" name="date_of_birth"></div>
        <div class="col-md-3"><label class="form-label">Gender</label><select class="form-select" name="gender"><option value="male">Male</option><option value="female">Female</option><option value="other">Other</option></select></div>
        <div class="col-md-3"><label class="form-label">Height (cm)</label><input type="number" step="0.1" class="form-control" name="height_cm"></div>
        <div class="col-md-3"><label class="form-label">Weight (kg)</label><input type="number" step="0.1" class="form-control" name="weight_kg"></div>
        <div class="col-md-3"><label class="form-label">Branch</label><select class="form-select" name="branch_id"><option value="">—</option><?php foreach ($branches as $b): ?><option value="<?= (int) $b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">Trainer</label><select class="form-select" name="trainer_id"><option value="">—</option><?php foreach ($trainers as $t): ?><option value="<?= (int) $t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-5"><label class="form-label">Start membership plan (optional)</label><select class="form-select" name="plan_id"><option value="">No plan yet</option><?php foreach ($plans as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?> — <?= money($p['price']) ?> / <?= (int) $p['duration_days'] ?> days</option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label">Coupon code</label><input class="form-control" name="coupon_code" placeholder="WELCOME10"></div>
      </div>
    </div>
    <div class="modal-footer"><button class="btn btn-ff">Register member</button></div>
  </form>
</div></div></div>

<!-- Edit member -->
<div class="modal fade" id="editModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
  <form data-ajax data-endpoint="members.php" id="editForm">
    <div class="modal-header"><h5 class="modal-title">Edit member</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body">
      <input type="hidden" name="action" value="update"><input type="hidden" name="id">
      <div class="row g-2">
        <div class="col-md-6"><label class="form-label">Full name</label><input class="form-control" name="name" required></div>
        <div class="col-md-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email"></div>
        <div class="col-md-3"><label class="form-label">Phone</label><input class="form-control" name="phone"></div>
        <div class="col-md-6"><label class="form-label">Photo URL</label><input class="form-control" name="photo_url"></div>
        <div class="col-md-6"><label class="form-label">Address</label><input class="form-control" name="address"></div>
        <div class="col-md-4"><label class="form-label">Emergency contact name</label><input class="form-control" name="emergency_contact_name"></div>
        <div class="col-md-4"><label class="form-label">Emergency contact phone</label><input class="form-control" name="emergency_contact_phone"></div>
        <div class="col-md-4"><label class="form-label">Date of birth</label><input type="date" class="form-control" name="date_of_birth"></div>
        <div class="col-md-3"><label class="form-label">Gender</label><select class="form-select" name="gender"><option value="male">Male</option><option value="female">Female</option><option value="other">Other</option></select></div>
        <div class="col-md-3"><label class="form-label">Height (cm)</label><input type="number" step="0.1" class="form-control" name="height_cm"></div>
        <div class="col-md-3"><label class="form-label">Weight (kg)</label><input type="number" step="0.1" class="form-control" name="weight_kg"></div>
        <div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><option value="active">Active</option><option value="inactive">Inactive</option><option value="suspended">Suspended</option><option value="cancelled">Cancelled</option></select></div>
        <div class="col-md-6"><label class="form-label">Branch</label><select class="form-select" name="branch_id"><option value="">—</option><?php foreach ($branches as $b): ?><option value="<?= (int) $b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label">Trainer</label><select class="form-select" name="trainer_id"><option value="">—</option><?php foreach ($trainers as $t): ?><option value="<?= (int) $t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach; ?></select></div>
      </div>
    </div>
    <div class="modal-footer"><button class="btn btn-ff">Save changes</button></div>
  </form>
</div></div></div>

<!-- Member detail -->
<div class="modal fade" id="memberModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Member profile</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
  <div class="modal-body">
    <div id="memberDetailBody"></div>
    <hr>
    <form data-ajax data-endpoint="members.php" data-reset class="row g-2 align-items-end">
      <input type="hidden" name="action" value="progress"><input type="hidden" name="member_id" id="progMemberId">
      <div class="col-md-3"><label class="form-label">Weight (kg)</label><input type="number" step="0.1" class="form-control" name="weight_kg" required></div>
      <div class="col-md-3"><label class="form-label">Body fat %</label><input type="number" step="0.1" class="form-control" name="body_fat_percent"></div>
      <div class="col-md-4"><label class="form-label">Notes</label><input class="form-control" name="notes"></div>
      <div class="col-md-2"><button class="btn btn-ff w-100">Log progress</button></div>
    </form>
    <?php if ($canMedical): ?>
    <hr>
    <h6>Save medical clearance record</h6>
    <form data-ajax data-endpoint="members.php" data-reset id="medicalForm" class="row g-2">
      <input type="hidden" name="action" value="medical_save"><input type="hidden" name="member_id">
      <div class="col-md-3"><label class="form-label">Clearance</label><select class="form-select" name="clearance_status"><option value="cleared">Cleared</option><option value="pending">Pending</option><option value="restricted">Restricted</option><option value="expired">Expired</option></select></div>
      <div class="col-md-3"><label class="form-label">Doctor</label><input class="form-control" name="doctor_name"></div>
      <div class="col-md-3"><label class="form-label">Clearance date</label><input type="date" class="form-control" name="clearance_date"></div>
      <div class="col-md-3"><label class="form-label">Expiry date</label><input type="date" class="form-control" name="expiry_date"></div>
      <div class="col-md-4"><label class="form-label">Conditions</label><input class="form-control" name="conditions"></div>
      <div class="col-md-4"><label class="form-label">Allergies</label><input class="form-control" name="allergies"></div>
      <div class="col-md-4"><label class="form-label">Medications</label><input class="form-control" name="medications"></div>
      <div class="col-md-10"><label class="form-label">Notes</label><input class="form-control" name="notes"></div>
      <div class="col-md-2 d-flex align-items-end"><button class="btn btn-ff w-100">Save record</button></div>
    </form>
    <?php endif; ?>
  </div>
</div></div></div>
<?php require __DIR__ . '/../views/gym-admin/footer.php'; ?>
