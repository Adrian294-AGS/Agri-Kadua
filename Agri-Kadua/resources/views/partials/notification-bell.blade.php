<style>
.nb{position:relative;display:inline-block;margin-right:10px;vertical-align:middle}
.nb [hidden]{display:none !important}
.nb-btn{position:relative;display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;padding:0;border:1px solid #cfd5cf;border-radius:50%;background:#fff;color:#14532d;cursor:pointer}
.nb-btn:hover{background:#dcfce7}
.nb-btn:focus-visible{outline:3px solid #14532d;outline-offset:2px}
.nb-badge{position:absolute;top:-4px;right:-4px;min-width:20px;height:20px;padding:0 5px;border-radius:99px;background:#991b1b;color:#fff;font-size:12px;font-weight:700;line-height:20px;text-align:center}
.nb-panel{position:absolute;right:0;top:calc(100% + 8px);width:360px;max-width:92vw;background:#fff;border:1px solid #cfd5cf;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.15);z-index:1000;overflow:hidden;text-align:left}
.nb-head{display:flex;justify-content:space-between;align-items:center;padding:12px 14px;border-bottom:1px solid #e6eae6;color:#1b261b}
.nb-link{border:0;background:none;color:#14532d;font-weight:600;text-decoration:underline;cursor:pointer;padding:0}
.nb-list{list-style:none;margin:0;padding:0;max-height:380px;overflow-y:auto}
.nb-item{display:block;width:100%;padding:12px 14px;border:0;border-bottom:1px solid #eef1ee;background:#fff;text-align:left;cursor:pointer;color:#1b261b}
.nb-item:hover{background:#f4f6f4}
.nb-item.is-unread{background:#dcfce7}
.nb-title{display:block;font-weight:700;font-size:14px}
.nb-msg{display:block;margin-top:2px;font-size:13px;color:#3b463b}
.nb-time{display:block;margin-top:4px;font-size:12px;color:#5d685d}
.nb-empty{padding:18px 14px;color:#5d685d;font-size:14px}
.nb-toast{position:fixed;right:24px;bottom:24px;max-width:340px;padding:12px 16px;border-radius:12px;background:#1b261b;color:#fff;font-size:14px;z-index:1100;box-shadow:0 10px 30px rgba(0,0,0,.25)}
</style>
<div class="nb" id="notif-bell" data-feed="{{ route('notifications.index', [], false) }}">
    <button type="button" class="nb-btn" id="nb-toggle" aria-label="Notifications" aria-expanded="false" aria-haspopup="true">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <span class="nb-badge" id="nb-badge" hidden>0</span>
    </button>
    <div class="nb-panel" id="nb-panel" role="dialog" aria-label="Notifications" hidden>
        <div class="nb-head">
            <strong>Notifications</strong>
            <button type="button" class="nb-link" id="nb-readall">Mark all read</button>
        </div>
        <ul class="nb-list" id="nb-list"></ul>
        <div class="nb-empty" id="nb-empty">No notifications yet.</div>
    </div>
    <div class="nb-toast" id="nb-toast" role="status" aria-live="polite" hidden></div>
</div>
<script>
(function () {
  var root = document.getElementById('notif-bell');
  if (!root) { return; }

  var feedUrl = root.getAttribute('data-feed');
  var baseUrl = feedUrl.replace(/\/$/, '');
  var csrfMeta = document.querySelector('meta[name="csrf-token"]');
  var csrf = csrfMeta ? csrfMeta.getAttribute('content') : '';
  var toggle = document.getElementById('nb-toggle');
  var panel = document.getElementById('nb-panel');
  var list = document.getElementById('nb-list');
  var empty = document.getElementById('nb-empty');
  var badge = document.getElementById('nb-badge');
  var readAll = document.getElementById('nb-readall');
  var toast = document.getElementById('nb-toast');
  var timer = null;
  var toastTimer = null;
  var stopped = false;
  var newestId = null;

  function request(url, method) {
    return fetch(url, {
      method: method || 'GET',
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrf
      }
    });
  }

  function safeUrl(url) {
    return typeof url === 'string' && url.charAt(0) === '/' && url.charAt(1) !== '/';
  }

  function setBadge(count) {
    if (count > 0) {
      badge.hidden = false;
      badge.textContent = count > 9 ? '9+' : String(count);
      toggle.setAttribute('aria-label', 'Notifications, ' + count + ' unread');
    } else {
      badge.hidden = true;
      toggle.setAttribute('aria-label', 'Notifications');
    }
  }

  function openItem(item) {
    var go = function () {
      if (safeUrl(item.url)) { window.location.href = item.url; }
    };
    if (item.read) { go(); return; }
    request(baseUrl + '/' + encodeURIComponent(item.id) + '/read', 'POST').then(go, go);
  }

  function line(className, text) {
    var span = document.createElement('span');
    span.className = className;
    span.textContent = text;
    return span;
  }

  function render(items) {
    list.textContent = '';
    items.forEach(function (item) {
      var li = document.createElement('li');
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'nb-item' + (item.read ? '' : ' is-unread');
      btn.appendChild(line('nb-title', item.title));
      btn.appendChild(line('nb-msg', item.message));
      btn.appendChild(line('nb-time', item.time || ''));
      btn.addEventListener('click', function () { openItem(item); });
      li.appendChild(btn);
      list.appendChild(li);
    });
    empty.hidden = items.length > 0;
  }

  function showToast(item) {
    toast.textContent = item.title + ': ' + item.message;
    toast.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { toast.hidden = true; }, 6000);
  }

  function schedule() {
    if (stopped) { return; }
    clearTimeout(timer);
    timer = setTimeout(poll, document.hidden ? 30000 : 5000);
  }

  function poll() {
    if (stopped) { return; }
    request(feedUrl).then(function (res) {
      if (res.status === 401 || res.status === 419) { stopped = true; return null; }
      return res.ok ? res.json() : null;
    }).then(function (data) {
      if (!data) { return; }
      var items = data.items || [];
      setBadge(data.unread_count || 0);
      render(items);
      if (items.length > 0) {
        if (newestId !== null && items[0].id !== newestId && !items[0].read) { showToast(items[0]); }
        newestId = items[0].id;
      } else if (newestId === null) {
        newestId = '';
      }
    }).catch(function () {
      /* network problem: try again on the next tick */
    }).then(schedule);
  }

  function closePanel() {
    panel.hidden = true;
    toggle.setAttribute('aria-expanded', 'false');
  }

  toggle.addEventListener('click', function () {
    panel.hidden = !panel.hidden;
    toggle.setAttribute('aria-expanded', String(!panel.hidden));
  });

  readAll.addEventListener('click', function () {
    request(baseUrl + '/read-all', 'POST').then(poll, poll);
  });

  document.addEventListener('click', function (event) {
    if (!root.contains(event.target)) { closePanel(); }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') { closePanel(); }
  });

  document.addEventListener('visibilitychange', function () {
    if (!document.hidden && !stopped) {
      clearTimeout(timer);
      poll();
    }
  });

  poll();
})();
</script>