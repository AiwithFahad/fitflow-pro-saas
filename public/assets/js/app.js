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


// ---------------------------------------------------------------------
// FitFlow Pro — FF AJAX layer (upgrade): Fetch API wrapper with CSRF,
// toast notifications, confirm dialogs, live tables (search / filters /
// pagination), AJAX modal forms and Chart.js dashboard refresh.
// All dashboard trees live exactly one folder below the app root, so the
// API base is always ../api/ relative to the current page.
// ---------------------------------------------------------------------
(function () {
  var FF = window.FF = window.FF || {};
  FF.apiBase = '../api/';
  FF._csrf = null;
  FF.charts = {};

  FF.csrf = function () {
    if (FF._csrf) return Promise.resolve(FF._csrf);
    var meta = document.querySelector('input[name="csrf_token"]');
    if (meta && meta.value) { FF._csrf = meta.value; return Promise.resolve(FF._csrf); }
    return fetch(FF.apiBase + 'csrf.php', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) { FF._csrf = j.csrf_token || ''; return FF._csrf; })
      .catch(function () { return ''; });
  };

  /** Core fetch helper. data → JSON body for POST, query string for GET. */
  FF.api = function (path, opts) {
    opts = opts || {};
    var method = (opts.method || 'GET').toUpperCase();
    var url = FF.apiBase + path;
    var init = { method: method, credentials: 'same-origin', headers: {} };
    if (method === 'GET' && opts.data) {
      var qs = new URLSearchParams();
      Object.keys(opts.data).forEach(function (k) {
        if (opts.data[k] !== '' && opts.data[k] !== null && opts.data[k] !== undefined) qs.set(k, opts.data[k]);
      });
      var s = qs.toString();
      if (s) url += (url.indexOf('?') > -1 ? '&' : '?') + s;
    }
    var send = function () {
      if (method !== 'GET') {
        var fd = new FormData();
        var data = opts.data || {};
        Object.keys(data).forEach(function (k) { if (data[k] !== undefined && data[k] !== null) fd.append(k, data[k]); });
        if (FF._csrf) fd.append('csrf_token', FF._csrf);
        init.body = fd;
      }
      return fetch(url, init).then(function (r) {
        return r.text().then(function (text) {
          var json = null;
          try { json = JSON.parse(text); } catch (e) { /* non-JSON */ }
          if (!r.ok || !json || json.ok === false) {
            var msg = (json && json.error) ? json.error : ('Request failed (' + r.status + ')');
            var err = new Error(msg); err.status = r.status; err.payload = json;
            throw err;
          }
          return json;
        });
      });
    };
    if (method !== 'GET' && !FF._csrf) return FF.csrf().then(send);
    return send();
  };

  // --- Toasts ----------------------------------------------------------
  FF.toast = function (message, type) {
    type = type || 'success';
    var box = document.getElementById('ffToastBox');
    if (!box) {
      box = document.createElement('div');
      box.id = 'ffToastBox';
      box.className = 'toast-container position-fixed top-0 end-0 p-3';
      box.style.zIndex = '1090';
      document.body.appendChild(box);
    }
    var el = document.createElement('div');
    el.className = 'toast align-items-center text-bg-' + (type === 'error' ? 'danger' : type) + ' border-0';
    el.setAttribute('role', 'alert');
    el.innerHTML = '<div class="d-flex"><div class="toast-body"></div>' +
      '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>';
    el.querySelector('.toast-body').textContent = message;
    box.appendChild(el);
    var t = new bootstrap.Toast(el, { delay: 4200 });
    t.show();
    el.addEventListener('hidden.bs.toast', function () { el.remove(); });
  };

  // --- Confirm dialog (Promise<boolean>) --------------------------------
  FF.confirm = function (message, title) {
    return new Promise(function (resolve) {
      var id = 'ffConfirmModal';
      var modal = document.getElementById(id);
      if (!modal) {
        modal = document.createElement('div');
        modal.id = id;
        modal.className = 'modal fade';
        modal.innerHTML =
          '<div class="modal-dialog modal-dialog-centered"><div class="modal-content">' +
          '<div class="modal-header"><h5 class="modal-title"></h5>' +
          '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>' +
          '<div class="modal-body"></div>' +
          '<div class="modal-footer"><button type="button" class="btn btn-ff-outline" data-ff-no>Cancel</button>' +
          '<button type="button" class="btn btn-ff" data-ff-yes>Confirm</button></div></div></div>';
        document.body.appendChild(modal);
      }
      modal.querySelector('.modal-title').textContent = title || 'Please confirm';
      modal.querySelector('.modal-body').textContent = message;
      var bs = bootstrap.Modal.getOrCreateInstance(modal);
      var done = false;
      function finish(v) { if (!done) { done = true; resolve(v); } }
      modal.querySelector('[data-ff-yes]').onclick = function () { bs.hide(); finish(true); };
      modal.querySelector('[data-ff-no]').onclick = function () { bs.hide(); finish(false); };
      modal.addEventListener('hidden.bs.modal', function () { finish(false); }, { once: true });
      bs.show();
    });
  };

  // --- Charts ------------------------------------------------------------
  FF.registerChart = function (id, chart) { FF.charts[id] = chart; return chart; };
  FF.updateChart = function (id, labels, datasets) {
    var c = FF.charts[id];
    if (!c) return;
    if (labels) c.data.labels = labels;
    (datasets || []).forEach(function (ds, i) {
      if (c.data.datasets[i]) c.data.datasets[i].data = ds;
    });
    c.update();
  };

  // --- Live tables: search + filters + pagination over the JSON API ------
  FF.liveTable = function (cfg) {
    var tbody = typeof cfg.tbody === 'string' ? document.getElementById(cfg.tbody) : cfg.tbody;
    if (!tbody) return null;
    var state = { page: 1, q: '', filters: {} };
    var perPage = cfg.perPage || 15;
    var pagerEl = cfg.pager ? (typeof cfg.pager === 'string' ? document.getElementById(cfg.pager) : cfg.pager) : null;

    function params() {
      var p = { page: state.page, per_page: perPage };
      if (state.q) p.q = state.q;
      Object.keys(state.filters).forEach(function (k) { if (state.filters[k] !== '') p[k] = state.filters[k]; });
      if (cfg.extraParams) Object.assign(p, cfg.extraParams());
      return p;
    }
    function renderPager(res) {
      if (!pagerEl) return;
      var pages = res.pages || 1;
      var html = '';
      if (pages > 1) {
        html += '<button type="button" class="btn btn-sm btn-ff-outline me-1" data-pg="' + (state.page - 1) + '"' + (state.page <= 1 ? ' disabled' : '') + '>&laquo;</button>';
        for (var i = 1; i <= pages; i++) {
          if (pages > 9 && i > 2 && i < pages - 1 && Math.abs(i - state.page) > 1) { if (html.slice(-1) !== '…') html += '<span class="mx-1">…</span>'; continue; }
          html += '<button type="button" class="btn btn-sm me-1 ' + (i === state.page ? 'btn-ff' : 'btn-ff-outline') + '" data-pg="' + i + '">' + i + '</button>';
        }
        html += '<button type="button" class="btn btn-sm btn-ff-outline" data-pg="' + (state.page + 1) + '"' + (state.page >= pages ? ' disabled' : '') + '>&raquo;</button>';
      }
      html += '<span class="small ms-2" style="color:var(--ff-muted)">' + (res.total || 0) + ' record(s)</span>';
      pagerEl.innerHTML = html;
      pagerEl.querySelectorAll('[data-pg]').forEach(function (b) {
        b.addEventListener('click', function () {
          var p = parseInt(b.getAttribute('data-pg'), 10);
          if (p >= 1 && p <= pages) { state.page = p; load(); }
        });
      });
    }
    function load() {
      tbody.innerHTML = '<tr><td colspan="12" class="text-center py-4" style="color:var(--ff-muted)"><span class="spinner-border spinner-border-sm me-2"></span>Loading…</td></tr>';
      return FF.api(cfg.endpoint, { data: params() }).then(function (res) {
        var rows = res.data || [];
        tbody.innerHTML = rows.length ? rows.map(cfg.renderRow).join('') :
          '<tr><td colspan="12" class="text-center py-4" style="color:var(--ff-muted)">No records match.</td></tr>';
        renderPager(res);
        if (cfg.onLoad) cfg.onLoad(res);
        return res;
      }).catch(function (err) {
        tbody.innerHTML = '<tr><td colspan="12" class="text-center py-4 text-danger"></td></tr>';
        tbody.querySelector('td').textContent = err.message;
      });
    }
    var api = {
      load: load,
      refresh: function () { return load(); },
      setFilter: function (k, v) { state.filters[k] = v; state.page = 1; },
      setQuery: function (q) { state.q = q; state.page = 1; },
      state: state
    };
    if (cfg.search) {
      var sEl = typeof cfg.search === 'string' ? document.getElementById(cfg.search) : cfg.search;
      if (sEl) {
        var deb = null;
        sEl.addEventListener('input', function () {
          clearTimeout(deb);
          deb = setTimeout(function () { api.setQuery(sEl.value.trim()); load(); }, 250);
        });
      }
    }
    (cfg.filters || []).forEach(function (f) {
      var el = typeof f.el === 'string' ? document.getElementById(f.el) : f.el;
      if (el) el.addEventListener('change', function () { api.setFilter(f.name, el.value); load(); });
    });
    load();
    return api;
  };

  // Auto-bindings run on DOMContentLoaded: page-level $extraScripts load
  // after this file and define the row renderers these bindings reference.
  FF.tables = {};
  document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-live-table]').forEach(function (tbody) {
    var key = tbody.id;
    var renderName = tbody.getAttribute('data-render');
    if (!key || !renderName || typeof window[renderName] !== 'function') return;
    var filters = [];
    document.querySelectorAll('[data-lt-filter][data-lt-for="' + key + '"]').forEach(function (el) {
      filters.push({ el: el, name: el.getAttribute('data-lt-filter') });
    });
    FF.tables[key] = FF.liveTable({
      tbody: tbody,
      endpoint: tbody.getAttribute('data-endpoint'),
      renderRow: window[renderName],
      perPage: parseInt(tbody.getAttribute('data-per-page') || '15', 10),
      search: document.querySelector('[data-lt-search="' + key + '"]'),
      pager: document.querySelector('[data-lt-pager="' + key + '"]'),
      filters: filters
    });
  });

  // --- AJAX forms: <form data-ajax data-endpoint="members.php"> -----------
  document.querySelectorAll('form[data-ajax]').forEach(function (form) {
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var btn = form.querySelector('[type="submit"]');
      var data = {};
      new FormData(form).forEach(function (v, k) { data[k] = v; });
      var go = function () {
        if (btn) { btn.disabled = true; btn.dataset.label = btn.innerHTML; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>'; }
        FF.api(form.getAttribute('data-endpoint'), { method: 'POST', data: data }).then(function (res) {
          FF.toast(res.message || 'Saved.');
          var modalEl = form.closest('.modal');
          if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).hide();
          if (form.hasAttribute('data-reset')) form.reset();
          document.dispatchEvent(new CustomEvent('ff:saved', { detail: { endpoint: form.getAttribute('data-endpoint'), response: res, form: form } }));
          if (form.hasAttribute('data-reload')) setTimeout(function () { window.location.reload(); }, 500);
        }).catch(function (err) {
          FF.toast(err.message, 'error');
        }).finally(function () {
          if (btn) { btn.disabled = false; btn.innerHTML = btn.dataset.label || btn.innerHTML; }
        });
      };
      var confirmMsg = form.getAttribute('data-confirm');
      if (confirmMsg) { FF.confirm(confirmMsg).then(function (ok) { if (ok) go(); }); } else { go(); }
    });
  });

  // Confirm-guarded plain forms (server-posted): <form data-confirm="...">
  document.querySelectorAll('form[data-confirm]:not([data-ajax])').forEach(function (form) {
    form.addEventListener('submit', function (ev) {
      if (form.dataset.confirmed === '1') return;
      ev.preventDefault();
      FF.confirm(form.getAttribute('data-confirm')).then(function (ok) {
        if (ok) { form.dataset.confirmed = '1'; form.submit(); }
      });
    });
  });

  // Notification "mark all read" buttons
  document.querySelectorAll('[data-notif-read-all]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      FF.api('notifications.php', { method: 'POST', data: { action: 'read_all' } }).then(function () {
        var badge = document.getElementById('notifBadge');
        if (badge) badge.remove();
        FF.toast('All notifications marked read.');
      }).catch(function (err) { FF.toast(err.message, 'error'); });
    });
  });

  // Dashboard refresh buttons: [data-ff-refresh] re-runs registered loaders
  document.querySelectorAll('[data-ff-refresh]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      btn.disabled = true;
      Promise.all(FF.loaders.map(function (fn) { return fn(); }))
        .then(function () { FF.toast('Dashboard refreshed with live data.'); })
        .catch(function (err) { FF.toast(err.message, 'error'); })
        .finally(function () { btn.disabled = false; });
    });
  });
  }); // end DOMContentLoaded bindings

  FF.loaders = [];
  FF.onRefresh = function (fn) { FF.loaders.push(fn); };

  // HTML-escape helper for renderers
  FF.esc = function (v) {
    return String(v === null || v === undefined ? '' : v)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  };
  FF.money = function (v) { return '$' + Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
  FF.pill = function (status) {
    return '<span class="status-pill st-' + FF.esc(status) + '">' + FF.esc(status) + '</span>';
  };
})();
