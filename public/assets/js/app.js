// FitFlow Pro — shared front-end behaviour
(function () {
  // Collapsible dashboard sidebar (mobile)
  var toggle = document.getElementById('sidebarToggle');
  var sidebar = document.getElementById('appSidebar');
  var backdrop = document.getElementById('sidebarBackdrop');
  function closeSidebar() {
    if (sidebar) sidebar.classList.remove('open');
    if (backdrop) backdrop.classList.remove('show');
  }
  if (toggle && sidebar) {
    toggle.addEventListener('click', function () {
      sidebar.classList.toggle('open');
      if (backdrop) backdrop.classList.toggle('show');
    });
  }
  if (backdrop) backdrop.addEventListener('click', closeSidebar);

  // Working BMI calculator (used on the website + member portal)
  window.ffCalculateBMI = function (weightId, heightId, outId) {
    var w = parseFloat(document.getElementById(weightId).value);
    var h = parseFloat(document.getElementById(heightId).value);
    var out = document.getElementById(outId);
    if (!w || !h || w <= 0 || h <= 0) {
      out.innerHTML = '<span class="text-warning">Enter a valid weight and height.</span>';
      return;
    }
    var m = h / 100;
    var bmi = w / (m * m);
    var cat = bmi < 18.5 ? 'Underweight' : bmi < 25 ? 'Healthy' : bmi < 30 ? 'Overweight' : 'Obese';
    var color = cat === 'Healthy' ? '#4ade80' : cat === 'Underweight' ? '#7dd3fc' : cat === 'Overweight' ? '#fbbf24' : '#f87171';
    out.innerHTML = 'Your BMI is <strong style="color:' + color + ';font-size:1.4rem">' + bmi.toFixed(1) +
      '</strong> &mdash; <strong>' + cat + '</strong>';
  };

  // Monthly / yearly pricing toggle
  var billingToggle = document.getElementById('billingToggle');
  if (billingToggle) {
    billingToggle.addEventListener('change', function () {
      document.querySelectorAll('[data-price-monthly]').forEach(function (el) {
        var monthly = el.getAttribute('data-price-monthly');
        var yearly = el.getAttribute('data-price-yearly');
        el.textContent = billingToggle.checked ? yearly : monthly;
      });
      document.querySelectorAll('.cycle-label').forEach(function (el) {
        el.textContent = billingToggle.checked ? '/year' : '/month';
      });
    });
  }

  // Auto-submit global search in dashboards (navigates to members search)
  var dashSearch = document.getElementById('dashSearch');
  if (dashSearch) {
    dashSearch.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter') {
        ev.preventDefault();
        var base = dashSearch.getAttribute('data-target');
        if (base) window.location = base + '?q=' + encodeURIComponent(dashSearch.value);
      }
    });
  }
})();
