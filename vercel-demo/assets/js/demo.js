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
