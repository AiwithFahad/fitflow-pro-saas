// FitFlow Pro — static demo behaviour (mock data, no backend)
(function () {
  // Collapsible sidebar
  var toggle = document.getElementById('sidebarToggle');
  var sidebar = document.getElementById('appSidebar');
  var backdrop = document.getElementById('sidebarBackdrop');
  if (toggle && sidebar) {
    toggle.addEventListener('click', function () {
      sidebar.classList.toggle('open');
      if (backdrop) backdrop.classList.toggle('show');
    });
  }
  if (backdrop) backdrop.addEventListener('click', function () {
    sidebar.classList.remove('open'); backdrop.classList.remove('show');
  });

  // Working BMI calculator
  window.ffCalculateBMI = function (weightId, heightId, outId) {
    var w = parseFloat(document.getElementById(weightId).value);
    var h = parseFloat(document.getElementById(heightId).value);
    var out = document.getElementById(outId);
    if (!w || !h || w <= 0 || h <= 0) {
      out.innerHTML = '<span style="color:#fbbf24">Enter a valid weight and height.</span>';
      return;
    }
    var m = h / 100, bmi = w / (m * m);
    var cat = bmi < 18.5 ? 'Underweight' : bmi < 25 ? 'Healthy' : bmi < 30 ? 'Overweight' : 'Obese';
    var color = cat === 'Healthy' ? '#4ade80' : cat === 'Underweight' ? '#7dd3fc' : cat === 'Overweight' ? '#fbbf24' : '#f87171';
    out.innerHTML = 'Your BMI is <strong style="color:' + color + ';font-size:1.4rem">' + bmi.toFixed(1) + '</strong> — <strong>' + cat + '</strong>';
  };

  // Monthly/yearly SaaS pricing toggle
  var billingToggle = document.getElementById('billingToggle');
  if (billingToggle) {
    billingToggle.addEventListener('change', function () {
      document.querySelectorAll('[data-price-monthly]').forEach(function (el) {
        el.textContent = billingToggle.checked ? el.getAttribute('data-price-yearly') : el.getAttribute('data-price-monthly');
      });
      document.querySelectorAll('.cycle-label').forEach(function (el) {
        el.textContent = billingToggle.checked ? '/year' : '/month';
      });
    });
  }

  // Mock QR check-in: known demo tokens toggle in/out
  var visits = {}; // token -> true when in gym
  var names = {
    'FFP-DEMO0001': 'Alex Carter', 'FFP-DEMO0002': 'Brian Cole', 'FFP-DEMO0003': 'Daniel Brooks',
    'FFP-DEMO0004': 'Ethan Wright', 'FFP-DEMO0005': 'Frank Miller', 'FFP-DEMO0006': 'George Lane'
  };
  var qrForm = document.getElementById('qrForm');
  if (qrForm) {
    qrForm.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var input = document.getElementById('qrToken');
      var token = (input.value || '').trim().toUpperCase();
      var result = document.getElementById('qrResult');
      var log = document.getElementById('visitLog');
      if (!names[token]) {
        result.className = 'alert alert-danger mt-3 mb-0';
        result.textContent = 'Token "' + token + '" not recognised. Try FFP-DEMO0001.';
        result.style.display = 'block';
        return;
      }
      var now = new Date();
      var time = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
      if (visits[token]) {
        visits[token] = false;
        result.className = 'alert alert-warning mt-3 mb-0';
        result.textContent = names[token] + ' checked OUT at ' + time + '.';
        addLog(names[token], time, 'Checked out', 'st-inactive');
      } else {
        visits[token] = true;
        result.className = 'alert alert-success mt-3 mb-0';
        result.textContent = names[token] + ' checked IN at ' + time + '. Have a great session!';
        addLog(names[token], time, 'In gym', 'st-active');
      }
      result.style.display = 'block';
      input.value = '';
      function addLog(name, t, state, cls) {
        if (!log) return;
        var row = document.createElement('tr');
        row.innerHTML = '<td class="fw-bold"></td><td></td><td><span class="status-pill ' + cls + '">' + state + '</span></td>';
        row.children[0].textContent = name;
        row.children[1].textContent = t;
        log.prepend(row);
      }
    });
  }

  // Dashboard member search (filters the table client-side)
  var memberSearch = document.getElementById('memberSearch');
  if (memberSearch) {
    memberSearch.addEventListener('input', function () {
      var q = memberSearch.value.toLowerCase();
      document.querySelectorAll('#membersTable tbody tr').forEach(function (row) {
        row.style.display = row.textContent.toLowerCase().indexOf(q) > -1 ? '' : 'none';
      });
    });
  }

  // Charts (dashboard)
  function styleChart() {
    if (window.Chart) {
      Chart.defaults.color = '#a1a1aa';
      Chart.defaults.borderColor = '#2b2b30';
    }
  }
  styleChart();
  var att = document.getElementById('attChart');
  if (att && window.Chart) {
    new Chart(att, { type: 'line', data: { labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'], datasets: [{ label: 'Check-ins', data: [412, 388, 430, 401, 462, 520, 305, 425, 399, 448, 470, 512, 538, 318], borderColor: '#f97316', backgroundColor: 'rgba(249,115,22,.18)', fill: true, tension: .35 }] }, options: { plugins: { legend: { labels: { color: '#e4e4e7' } } }, scales: { y: { beginAtZero: true } } } });
  }
  var rev = document.getElementById('revChart');
  if (rev && window.Chart) {
    new Chart(rev, { type: 'bar', data: { labels: ['Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov'], datasets: [{ label: 'Verified revenue ($)', data: [32400, 35800, 38900, 41200, 44600, 48200], backgroundColor: 'rgba(249,115,22,.6)', borderColor: '#f97316', borderWidth: 1 }] }, options: { plugins: { legend: { labels: { color: '#e4e4e7' } } }, scales: { y: { beginAtZero: true } } } });
  }
  var plan = document.getElementById('planChart');
  if (plan && window.Chart) {
    new Chart(plan, { type: 'doughnut', data: { labels: ['Monthly', 'Quarterly', 'Annual', 'Student'], datasets: [{ data: [248, 186, 92, 64], backgroundColor: ['#f97316', '#fbbf24', '#38bdf8', '#22c55e'] }] }, options: { plugins: { legend: { labels: { color: '#e4e4e7' } } } } });
  }
})();


// ---------------------------------------------------------------------
// Upgrade demo: role dashboard switcher, extra charts, toasts, filters.
// (Static mock data — the real app serves these from its JSON API.)
// ---------------------------------------------------------------------
(function () {
  // Toast helper (demo-local, dark styled)
  function toast(msg) {
    var box = document.getElementById('demoToastBox');
    if (!box) {
      box = document.createElement('div');
      box.id = 'demoToastBox';
      box.style.cssText = 'position:fixed;top:16px;right:16px;z-index:2000;display:flex;flex-direction:column;gap:8px';
      document.body.appendChild(box);
    }
    var el = document.createElement('div');
    el.style.cssText = 'background:#17171a;border:1px solid #2b2b30;border-left:4px solid #f97316;color:#e4e4e7;padding:10px 14px;border-radius:8px;max-width:320px;font-size:.9rem;box-shadow:0 8px 24px rgba(0,0,0,.4)';
    el.textContent = msg;
    box.appendChild(el);
    setTimeout(function () { el.style.opacity = '0'; el.style.transition = 'opacity .4s'; setTimeout(function () { el.remove(); }, 420); }, 3400);
  }
  window.demoToast = toast;

  // Renewal reminders mock action
  var remind = document.getElementById('remindBtn');
  if (remind) remind.addEventListener('click', function () {
    toast('Renewal reminders sent to 3 members expiring within 30 days (demo).');
  });

  // Member status filter (works together with the top-bar search)
  var statusFilter = document.getElementById('memberStatusFilter');
  function applyMemberFilters() {
    var q = (document.getElementById('memberSearch') || {}).value || '';
    q = q.toLowerCase();
    var st = statusFilter ? statusFilter.value : '';
    document.querySelectorAll('#membersTable tbody tr').forEach(function (row) {
      var text = row.textContent.toLowerCase();
      var okQ = text.indexOf(q) > -1;
      var okS = !st || row.textContent.indexOf(st) > -1;
      row.style.display = okQ && okS ? '' : 'none';
    });
  }
  if (statusFilter) statusFilter.addEventListener('change', applyMemberFilters);
  var ms = document.getElementById('memberSearch');
  if (ms) ms.addEventListener('input', applyMemberFilters);

  // Role dashboard switcher
  var roleInfo = {
    admin:   { name: 'Sarah Mitchell', role: 'Gym Admin · Iron Paradise' },
    trainer: { name: 'Jake Thompson', role: 'Trainer · Iron Paradise' },
    member:  { name: 'Alex Carter', role: 'Member · Iron Paradise' }
  };
  var chartsBuilt = {};
  function buildRoleCharts(view) {
    if (!window.Chart || chartsBuilt[view]) return;
    if (view === 'trainer') {
      var tc = document.getElementById('trainerChart');
      if (tc) {
        chartsBuilt.trainer = new Chart(tc, { type: 'bar', data: { labels: ['Jake Thompson', 'Ryan Delgado', 'Chris Novak', 'Sam Whitaker'], datasets: [
          { label: 'Assigned members', data: [86, 74, 68, 41], backgroundColor: 'rgba(249,115,22,.6)', borderColor: '#f97316', borderWidth: 1 },
          { label: 'Member check-ins (30d)', data: [412, 356, 388, 190], backgroundColor: 'rgba(56,189,248,.55)', borderColor: '#38bdf8', borderWidth: 1 }
        ] }, options: { plugins: { legend: { labels: { color: '#e4e4e7' } } }, scales: { y: { beginAtZero: true } } } });
      }
    }
    if (view === 'member') {
      var mc = document.getElementById('memberWeightChart');
      if (mc) {
        chartsBuilt.member = new Chart(mc, { type: 'line', data: { labels: ['Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Jan'], datasets: [
          { label: 'Weight (kg)', data: [92, 90.4, 89.1, 87.8, 86.9, 86.0], borderColor: '#f97316', backgroundColor: 'rgba(249,115,22,.18)', fill: true, tension: .35 }
        ] }, options: { plugins: { legend: { labels: { color: '#e4e4e7' } } }, scales: { y: { beginAtZero: false } } } });
      }
    }
  }
  function setRole(view) {
    var adminBlocks = [document.getElementById('adminKpis'), document.getElementById('adminMain')];
    var trainerView = document.getElementById('viewTrainer');
    var memberView = document.getElementById('viewMember');
    adminBlocks.forEach(function (el) { if (el) el.style.display = view === 'admin' ? '' : 'none'; });
    if (trainerView) trainerView.style.display = view === 'trainer' ? '' : 'none';
    if (memberView) memberView.style.display = view === 'member' ? '' : 'none';
    document.querySelectorAll('[data-role-view]').forEach(function (btn) {
      var active = btn.getAttribute('data-role-view') === view;
      btn.classList.toggle('btn-ff', active);
      btn.classList.toggle('btn-ff-outline', !active);
    });
    var name = document.getElementById('sideUserName');
    var role = document.getElementById('sideUserRole');
    if (name && roleInfo[view]) name.textContent = roleInfo[view].name;
    if (role && roleInfo[view]) role.textContent = roleInfo[view].role;
    buildRoleCharts(view);
  }
  document.querySelectorAll('[data-role-view]').forEach(function (btn) {
    btn.addEventListener('click', function () { setRole(btn.getAttribute('data-role-view')); });
  });
})();
