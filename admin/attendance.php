<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_login('../public/login.php');
require_role(['Gym Owner', 'Gym Admin', 'Branch Manager', 'Trainer', 'Receptionist'], Auth::homeFor(current_user()));
$tid = tenant_id();
$dashTitle = 'Attendance & Check-in Desk';
$activeNav = 'attendance';
$canManage = can('attendance.manage');
$canCorrect = can('attendance.correct');
$members = Member::all($tid, 'name ASC', 500);
$trainers = Trainer::all($tid, 'name ASC', 100);
$today = Attendance::today($tid, 200);
$trainerToday = TrainerAttendance::today($tid);
$corrections = Attendance::corrections($tid, 15);
$report = Attendance::report($tid, 'daily');

$extraScripts = '<script>
function attRow(r){
  var state = r.check_out_at ? "<span class=\"status-pill st-inactive\">Checked out</span>" : "<span class=\"status-pill st-active\">In gym</span>";
  var outBtn = r.check_out_at ? "" : "<button type=\"button\" class=\"btn btn-sm btn-outline-warning\" data-out=\"" + r.id + "\">Check out</button> ";
  var corrBtn = "<button type=\"button\" class=\"btn btn-sm btn-outline-light\" data-correct=\"" + r.id + "\" data-in=\"" + FF.esc((r.check_in_at || "").replace(" ", "T").substring(0,16)) + "\" data-outv=\"" + FF.esc((r.check_out_at || "").replace(" ", "T").substring(0,16)) + "\">Correct</button>";
  return "<tr><td class=\"fw-bold\">" + FF.esc(r.member_name) + "</td><td>" + FF.esc((r.check_in_at || "").substring(11,16)) + "</td><td>" + (r.check_out_at ? FF.esc(r.check_out_at.substring(11,16)) : "—") + "</td><td>" + FF.esc(r.method) + "</td><td>" + state + "</td><td class=\"text-end text-nowrap\">" + outBtn + corrBtn + "</td></tr>";
}
function refreshToday(){
  return FF.api("attendance.php?view=today").then(function(res){
    document.getElementById("todayBody").innerHTML = (res.data || []).map(attRow).join("") || "<tr><td colspan=\"6\" style=\"color:var(--ff-muted)\">No check-ins yet today.</td></tr>";
    document.getElementById("inGymCount").textContent = res.in_gym || 0;
  });
}
function refreshTrainers(){
  return FF.api("attendance.php?view=trainers").then(function(res){
    document.getElementById("trainerAttBody").innerHTML = (res.data || []).map(function(r){
      return "<tr><td class=\"fw-bold\">" + FF.esc(r.trainer_name) + "</td><td>" + FF.esc((r.check_in_at || "").substring(11,16)) + "</td><td>" + (r.check_out_at ? FF.esc(r.check_out_at.substring(11,16)) : "<span class=\"status-pill st-active\">On shift</span>") + "</td></tr>";
    }).join("") || "<tr><td colspan=\"3\" style=\"color:var(--ff-muted)\">No trainer check-ins yet today.</td></tr>";
  });
}
document.addEventListener("DOMContentLoaded", function(){
  document.addEventListener("ff:saved", function(){ refreshToday(); });
  FF.onRefresh(function(){ return Promise.all([refreshToday(), refreshTrainers()]); });

  document.getElementById("todayBody").addEventListener("click", function(ev){
    var out = ev.target.closest("[data-out]");
    var cor = ev.target.closest("[data-correct]");
    if (out) {
      FF.api("attendance.php", {method:"POST", data:{action:"out", attendance_id: out.getAttribute("data-out")}})
        .then(function(r){ FF.toast(r.message); refreshToday(); })
        .catch(function(e){ FF.toast(e.message, "error"); });
    }
    if (cor) {
      var f = document.getElementById("correctForm");
      f.querySelector("[name=\"attendance_id\"]").value = cor.getAttribute("data-correct");
      f.querySelector("[name=\"check_in_at\"]").value = cor.getAttribute("data-in") || "";
      f.querySelector("[name=\"check_out_at\"]").value = cor.getAttribute("data-outv") || "";
      bootstrap.Modal.getOrCreateInstance(document.getElementById("correctModal")).show();
    }
  });

  document.querySelectorAll("[data-ttoggle]").forEach(function(btn){
    btn.addEventListener("click", function(){
      FF.api("attendance.php", {method:"POST", data:{action:"trainer_toggle", trainer_id: btn.getAttribute("data-ttoggle")}})
        .then(function(r){ FF.toast(r.message); refreshTrainers(); })
        .catch(function(e){ FF.toast(e.message, "error"); });
    });
  });

  // Attendance report chart + table
  var reportChart = null;
  function loadReport(period){
    FF.api("attendance.php", {data:{view:"report", period:period}}).then(function(res){
      var labels = res.data.map(function(r){ return r.label; });
      var data = res.data.map(function(r){ return parseInt(r.checkins, 10); });
      document.getElementById("reportBody").innerHTML = res.data.map(function(r){
        return "<tr><td>" + FF.esc(r.label) + "</td><td>" + r.checkins + "</td><td>" + r.members + "</td></tr>";
      }).join("") || "<tr><td colspan=\"3\" style=\"color:var(--ff-muted)\">No attendance data.</td></tr>";
      if (window.Chart) {
        if (!reportChart) {
          reportChart = FF.registerChart("attReport", new Chart(document.getElementById("reportChart"), {type:"bar", data:{labels:labels, datasets:[{label:"Check-ins", data:data, backgroundColor:"rgba(249,115,22,.6)", borderColor:"#f97316", borderWidth:1}]}, options:{scales:{y:{beginAtZero:true, ticks:{precision:0}}}}}));
        } else {
          FF.updateChart("attReport", labels, [data]);
        }
      }
    }).catch(function(e){ FF.toast(e.message, "error"); });
  }
  document.querySelectorAll("[data-period]").forEach(function(btn){
    btn.addEventListener("click", function(){
      document.querySelectorAll("[data-period]").forEach(function(b){ b.classList.remove("active"); });
      btn.classList.add("active");
      loadReport(btn.getAttribute("data-period"));
    });
  });
  loadReport("daily");
});
</script>';

require __DIR__ . '/../views/gym-admin/header.php';
?>
<div class="row g-3">
  <div class="col-xl-4">
    <div class="panel mb-3">
      <h5><i class="bi bi-qr-code-scan text-orange me-1"></i>QR check-in</h5>
      <p class="small" style="color:var(--ff-muted)">Scan or type a member QR token (e.g. FFP-DEMO0001). Scanning again checks the member out.</p>
      <?php if ($canManage): ?>
      <form data-ajax data-endpoint="attendance.php" data-reset>
        <input type="hidden" name="action" value="scan">
        <div class="input-group">
          <input class="form-control" name="qr_token" placeholder="FFP-…" autocomplete="off" required>
          <button class="btn btn-ff">Scan</button>
        </div>
      </form>
      <?php else: ?><p class="small mb-0">Your role cannot manage attendance.</p><?php endif; ?>
      <div class="qr-box mt-3">
        <div style="font-size:3.4rem;line-height:1;color:#111"><i class="bi bi-qr-code"></i></div>
        <div class="qr-token">FFP-DEMO0001</div>
        <div class="small text-muted">Demo token — Alex Carter</div>
      </div>
    </div>
    <div class="panel mb-3">
      <h5>Manual check-in</h5>
      <p class="small" style="color:var(--ff-muted)">For members without their QR code. A member with an open session cannot be checked in twice — the duplicate is refused.</p>
      <?php if ($canManage): ?>
      <form id="manualInForm" class="d-flex gap-2">
        <select class="form-select" id="manualMember" required>
          <option value="">Choose member…</option>
          <?php foreach ($members as $m): ?><?php if ($m['status'] === 'active'): ?><option value="<?= (int) $m['id'] ?>"><?= e($m['name']) ?> (<?= e($m['member_code'] ?? '') ?>)</option><?php endif; ?><?php endforeach; ?>
        </select>
        <button class="btn btn-ff" type="submit">Check in</button>
      </form>
      <script>
      document.getElementById('manualInForm').addEventListener('submit', function(ev){
        ev.preventDefault();
        var id = document.getElementById('manualMember').value;
        if (!id) return;
        FF.api('attendance.php', {method:'POST', data:{action:'manual_in', member_id:id}})
          .then(function(r){ FF.toast(r.message); refreshToday(); })
          .catch(function(e){ FF.toast(e.message, 'error'); });
      });
      </script>
      <?php endif; ?>
    </div>
    <div class="panel">
      <h5>Trainer attendance (today)</h5>
      <div class="d-flex flex-wrap gap-1 mb-3">
        <?php foreach ($trainers as $t): ?><?php if ($t['status'] === 'active'): ?>
          <button type="button" class="btn btn-sm btn-ff-outline" data-ttoggle="<?= (int) $t['id'] ?>"><?= e($t['name']) ?> — in/out</button>
        <?php endif; ?><?php endforeach; ?>
      </div>
      <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>Trainer</th><th>In</th><th>Out</th></tr></thead>
        <tbody id="trainerAttBody">
        <?php foreach ($trainerToday as $r): ?>
          <tr><td class="fw-bold"><?= e($r['trainer_name']) ?></td><td><?= e(substr((string) $r['check_in_at'], 11, 5)) ?></td><td><?= $r['check_out_at'] ? e(substr((string) $r['check_out_at'], 11, 5)) : '<span class="status-pill st-active">On shift</span>' ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$trainerToday): ?><tr><td colspan="3" style="color:var(--ff-muted)">No trainer check-ins yet today.</td></tr><?php endif; ?>
        </tbody></table></div>
    </div>
  </div>

  <div class="col-xl-8">
    <div class="panel mb-3">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="mb-0">Today's member sessions — <span id="inGymCount"><?= count(array_filter($today, fn ($r) => empty($r['check_out_at']))) ?></span> in gym now</h5>
        <button type="button" class="btn btn-sm btn-ff-outline" data-ff-refresh><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
      </div>
      <div class="table-responsive" style="max-height:420px;overflow:auto"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Member</th><th>In</th><th>Out</th><th>Method</th><th>State</th><th class="text-end">Actions</th></tr></thead>
        <tbody id="todayBody">
        <?php foreach ($today as $r): ?>
          <tr><td class="fw-bold"><?= e($r['member_name']) ?></td><td><?= e(substr((string) $r['check_in_at'], 11, 5)) ?></td>
            <td><?= $r['check_out_at'] ? e(substr((string) $r['check_out_at'], 11, 5)) : '—' ?></td><td><?= e($r['method']) ?></td>
            <td><?= $r['check_out_at'] ? '<span class="status-pill st-inactive">Checked out</span>' : '<span class="status-pill st-active">In gym</span>' ?></td>
            <td class="text-end text-nowrap">
              <?php if (empty($r['check_out_at']) && $canManage): ?><button type="button" class="btn btn-sm btn-outline-warning" data-out="<?= (int) $r['id'] ?>">Check out</button><?php endif; ?>
              <?php if ($canCorrect): ?><button type="button" class="btn btn-sm btn-outline-light" data-correct="<?= (int) $r['id'] ?>" data-in="<?= e(str_replace(' ', 'T', substr((string) $r['check_in_at'], 0, 16))) ?>" data-outv="<?= e($r['check_out_at'] ? str_replace(' ', 'T', substr((string) $r['check_out_at'], 0, 16)) : '') ?>">Correct</button><?php endif; ?>
            </td></tr>
        <?php endforeach; ?>
        <?php if (!$today): ?><tr><td colspan="6" style="color:var(--ff-muted)">No check-ins yet today.</td></tr><?php endif; ?>
        </tbody></table></div>
    </div>

    <div class="panel mb-3">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="mb-0">Attendance reports</h5>
        <div class="btn-group btn-group-sm">
          <button type="button" class="btn btn-ff-outline active" data-period="daily">Daily</button>
          <button type="button" class="btn btn-ff-outline" data-period="weekly">Weekly</button>
          <button type="button" class="btn btn-ff-outline" data-period="monthly">Monthly</button>
        </div>
      </div>
      <div class="row g-3">
        <div class="col-md-7"><canvas id="reportChart" class="chart"></canvas></div>
        <div class="col-md-5"><div class="table-responsive"><table class="table table-sm align-middle mb-0">
          <thead><tr><th>Period</th><th>Check-ins</th><th>Members</th></tr></thead>
          <tbody id="reportBody">
          <?php foreach ($report as $r): ?><tr><td><?= e($r['label']) ?></td><td><?= (int) $r['checkins'] ?></td><td><?= (int) $r['members'] ?></td></tr><?php endforeach; ?>
          </tbody></table></div></div>
      </div>
    </div>

    <div class="panel">
      <h5>Correction audit trail</h5>
      <p class="small" style="color:var(--ff-muted)">Every correction stores the old and new values, the reason and who made it. Nothing is silently overwritten.</p>
      <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>When</th><th>Member</th><th>Field</th><th>Old → New</th><th>Reason</th><th>By</th></tr></thead>
        <tbody>
        <?php foreach ($corrections as $c): ?>
          <tr><td class="small"><?= e(substr((string) $c['created_at'], 0, 16)) ?></td><td><?= e($c['member_name'] ?? ('#' . $c['subject_id'])) ?></td>
            <td><?= e($c['field']) ?></td><td class="small"><?= e($c['old_value'] ?? '—') ?> → <?= e($c['new_value'] ?? '—') ?></td>
            <td class="small"><?= e($c['reason'] ?? '') ?></td><td><?= e($c['corrected_by_name'] ?? '') ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$corrections): ?><tr><td colspan="6" style="color:var(--ff-muted)">No corrections recorded yet.</td></tr><?php endif; ?>
        </tbody></table></div>
    </div>
  </div>
</div>

<?php if ($canCorrect): ?>
<div class="modal fade" id="correctModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form data-ajax data-endpoint="attendance.php" id="correctForm">
    <div class="modal-header"><h5 class="modal-title">Correct attendance record</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body">
      <input type="hidden" name="action" value="correct"><input type="hidden" name="attendance_id">
      <div class="row g-2">
        <div class="col-6"><label class="form-label">Check-in</label><input type="datetime-local" class="form-control" name="check_in_at"></div>
        <div class="col-6"><label class="form-label">Check-out (blank = still in gym)</label><input type="datetime-local" class="form-control" name="check_out_at"></div>
        <div class="col-12"><label class="form-label">Reason (required, kept in the audit trail)</label><input class="form-control" name="reason" required placeholder="e.g. Front-desk clock error, confirmed on camera"></div>
      </div>
    </div>
    <div class="modal-footer"><button class="btn btn-ff">Save correction</button></div>
  </form>
</div></div></div>
<?php endif; ?>
<?php require __DIR__ . '/../views/gym-admin/footer.php'; ?>
