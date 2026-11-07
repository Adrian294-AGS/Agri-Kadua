const $ = (s) => document.querySelector(s);
const peso = (n) => '₱' + Math.round(n).toLocaleString('en-US');
const kg = (n) => Number(n).toLocaleString('en-US') + ' kg';
const RATES = { A: 125, B: 110, C: 95 };
const CROPS = ['Arabica Coffee', 'Paddy Rice', 'Yellow Corn', 'Sweet Potato'];
const ROLES = {
  'Warehouse Assistant': { id: 'WA-0042', name: 'Jose Mendoza', icon: '🏭' },
  'Field Coordinator': { id: 'FC-0017', name: 'Ana Reyes', icon: '🌾' },
  'Accounting Clerk': { id: 'AC-0009', name: 'Rita Cruz', icon: '💰' },
  'Cooperative Manager': { id: 'CM-0001', name: 'Ciara Ocampo', icon: '📊' },
};
const NAV = {
  'Warehouse Assistant': [['home', 'Home'], ['receive', 'Receive harvest'], ['stock', 'Stock'], ['farmers', 'Farmers']],
  'Field Coordinator': [['home', 'Home'], ['farmers', 'Farmers']],
  'Accounting Clerk': [['home', 'Home'], ['pay', 'Pay farmers'], ['receipts', 'Receipts'], ['farmers', 'Farmers']],
  'Cooperative Manager': [['home', 'Overview'], ['audit', 'Change history'], ['stock', 'Stock'], ['farmers', 'Farmers']],
};
const SEED = () => ({
  farmers: [
    { id: 'FM-1002', name: 'Ramon Valenzuela', size: '14.5 ha', crop: 'Arabica Coffee', phone: '+63 917 123 4567', loc: 'Sitio Matahimik, Brgy. San Juan' },
    { id: 'FM-1003', name: 'Elena Santos', size: '8.2 ha', crop: 'Paddy Rice', phone: '+63 918 222 3344', loc: 'Purok 4, Brgy. Maligaya' },
    { id: 'FM-1005', name: 'Mateo Alcantara', size: '22.1 ha', crop: 'Sweet Potato', phone: '+63 919 555 7788', loc: 'Highland Ridge, Brgy. Silang' },
    { id: 'FM-1004', name: 'Josephine Cruz', size: '12.0 ha', crop: 'Yellow Corn', phone: '+63 920 111 9900', loc: 'Valley Farm 2, Brgy. Del Pilar' },
    { id: 'FM-1006', name: 'Danilo Aquino', size: '6.5 ha', crop: 'Arabica Coffee', phone: '+63 921 444 8811', loc: 'Poblacion Norte' },
  ],
  stock: { 'Arabica Coffee': 14200, 'Paddy Rice': 12900, 'Yellow Corn': 4600, 'Sweet Potato': 890 },
  ready: [
    { id: 'RP-1', ref: 'TR-9818', farmer: 'Elena Santos', product: 'Paddy Rice', grade: 'B', net: 3900, at: 'Oct 24, 09:30' },
    { id: 'RP-2', ref: 'TR-9815', farmer: 'Mateo Alcantara', product: 'Sweet Potato', grade: 'A', net: 1750, at: 'Oct 23, 15:45' },
  ],
  paid: [{ id: 'PAY-1', ref: 'PAY-2026-0412', farmer: 'Ramon Valenzuela', product: 'Arabica Coffee', grade: 'A', net: 1210, rate: 125, amount: 151250, method: 'Cash', by: 'Rita Cruz', at: 'Oct 24, 14:02' }],
  audit: [
    { who: 'Rita Cruz', what: 'Paid Ramon Valenzuela ₱151,250', why: 'Harvest verified & accepted on scale', at: 'Oct 24, 14:02' },
    { who: 'Jose Mendoza', what: 'Took out 500 kg of Yellow Corn', why: 'Given to seed multiplication farmer', at: 'Oct 23, 16:40' },
  ],
});

// ---- state (localStorage replaces the React context) ----
let role = 'Warehouse Assistant', screen = 'home', offline = false, chosen = role;
let db = SEED();
try { db = JSON.parse(localStorage.getItem('coopsync_db')) || db; } catch (e) {}
const save = () => { try { localStorage.setItem('coopsync_db', JSON.stringify(db)); } catch (e) {} };
const user = () => ROLES[role];
const now = () => 'Oct 24, ' + new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false });
const log = (what, why) => { db.audit.unshift({ who: user().name, what, why, at: now() }); save(); };
const toast = (m) => { const t = $('#toast'); t.textContent = m; t.hidden = false; setTimeout(() => (t.hidden = true), 2500); };
const say = (sel, text, bad) => { const el = $(sel); el.textContent = text; el.className = 'msg' + (bad ? ' er' : ''); el.hidden = false; };
const opts = (list) => list.map((x) => `<option>${x}</option>`).join('');
const NO_NET = 'No internet. Nothing was saved. Please try again when the signal is back.';

// ---- actions (same rules as CoopContext.tsx) ----
function receive() {
  if (offline) return say('#rMsg', NO_NET, true);
  const gross = +$('#rGross').value || 0, net = gross - (+$('#rDed').value || 0) - (+$('#rRej').value || 0);
  if (net <= 0) return say('#rMsg', 'Accepted weight must be greater than zero kg.', true);
  const farmer = $('#rFarmer').value, product = $('#rProduct').value;
  const ref = 'TR-' + (9822 + db.ready.length + db.paid.length);
  db.stock[product] = (db.stock[product] || 0) + net;
  db.ready.unshift({ id: 'RP-' + Date.now(), ref, farmer, product, grade: $('#rGrade').value, net, at: now() });
  log(`Received ${net.toLocaleString()} kg of ${product} from ${farmer} (Ref: ${ref})`, 'Harvest verified on intake scale');
  say('#rMsg', `Saved ${ref}: ${kg(net)} accepted.`);
  ['#rGross', '#rDed', '#rRej'].forEach((s) => ($(s).value = 0));
  calcNet();
}
function release() {
  if (offline) return say('#sMsg', NO_NET, true);
  const p = $('#sProduct').value, q = +$('#sQty').value || 0, why = $('#sReason').value.trim();
  if (q <= 0 || !why) return say('#sMsg', 'Enter a quantity and a reason.', true);
  if (q > db.stock[p]) return say('#sMsg', `Not enough stock. Only ${kg(db.stock[p])} left.`, true);
  db.stock[p] -= q; log(`Took out ${q.toLocaleString()} kg of ${p}`, why); say('#sMsg', 'Stock updated.'); render();
}
function pay(id) {
  if (offline) return toast(NO_NET);
  const r = db.ready.find((x) => x.id === id); if (!r) return;
  const rate = RATES[r.grade], amount = r.net * rate, method = $('#pMethod').value;
  const ref = 'PAY-2026-' + String(412 + db.paid.length).padStart(4, '0');
  db.paid.unshift({ id: 'PAY-' + Date.now(), ref, farmer: r.farmer, product: r.product, grade: r.grade, net: r.net, rate, amount, method, by: user().name, at: now() });
  db.ready = db.ready.filter((x) => x.id !== id);
  log(`Paid ${r.farmer} ${peso(amount)} via ${method} (Ref: ${ref})`, 'Harvest verified and disbursed'); toast('Paid ' + peso(amount)); render();
}

// ---- views ----
const calcNet = () => ($('#rNet').textContent = kg(Math.max(0, (+$('#rGross').value || 0) - (+$('#rDed').value || 0) - (+$('#rRej').value || 0))));
const card = (l, r, sub) => `<div class="card"><div class="row"><div><b>${l}</b><small>${sub || ''}</small></div>${r}</div></div>`;
const R = {
  home() {
    $('#homeHello').textContent = `Hello, ${user().name.split(' ')[0]}`;
    const total = Object.values(db.stock).reduce((a, b) => a + b, 0);
    $('#homeTiles').innerHTML = [['Total stock', kg(total)], ['Waiting payout', db.ready.length], ['Paid receipts', db.paid.length], ['Farmers', db.farmers.length]]
      .map(([a, b]) => `<div class="tile"><small>${a}</small><b>${b}</b></div>`).join('');
    $('#homeActions').innerHTML = NAV[role].filter((n) => n[0] !== 'home').map(([id, l]) => `<button class="go" data-go="${id}">${l}</button>`).join('');
  },
  receive() { $('#rMsg').hidden = true; calcNet(); },
  stock() {
    $('#stockList').innerHTML = CROPS.map((c) => { const q = db.stock[c], t = q < 1000 ? ['Critical low', 'er'] : q < 5000 ? ['Low', 'wa'] : ['Adequate', ''];
      return `<div class="card"><div class="row"><b>${c}</b><span class="tag ${t[1]}">${t[0]}</span></div><div class="amt">${kg(q)}</div><div class="bar"><i style="width:${Math.min(100, q / 150)}%"></i></div></div>`; }).join('');
    $('#releaseBox').hidden = role === 'Cooperative Manager'; $('#sMsg').hidden = true;
  },
  pay() {
    $('#payList').innerHTML = db.ready.map((r) => card(r.farmer, `<span class="amt">${peso(r.net * RATES[r.grade])}</span>`, `${r.ref} · ${r.product} · Grade ${r.grade} · ${kg(r.net)}`)
      .replace('</div></div></div>', `</div></div><button class="btn" data-pay="${r.id}">Pay now</button></div>`)).join('') || '<p class="sub">Nothing waiting for payment.</p>';
  },
  receipts() { $('#receiptList').innerHTML = db.paid.map((p) => card(p.farmer, `<span class="amt">${peso(p.amount)}</span>`, `${p.ref} · ${kg(p.net)} × ₱${p.rate} · ${p.method} · ${p.at}`)).join(''); },
  farmers() { $('#farmerList').innerHTML = db.farmers.map((f) => card(f.name, `<span class="tag">${f.crop}</span>`, `${f.id} · ${f.size} · ${f.phone}<br>${f.loc}`)).join(''); },
  audit() { $('#auditList').innerHTML = db.audit.map((a) => card(a.what, `<small>${a.at}</small>`, `${a.who} — ${a.why}`)).join(''); },
};
function render() {
  document.querySelectorAll('[data-view]').forEach((s) => (s.hidden = s.dataset.view !== screen));
  $('#nav').innerHTML = NAV[role].map(([id, l]) => `<button data-go="${id}" class="${id === screen ? 'on' : ''}">${l}${id === 'pay' && db.ready.length ? ` <span class="tag">${db.ready.length}</span>` : ''}</button>`).join('');
  $('#screenTitle').textContent = (NAV[role].find((n) => n[0] === screen) || [0, 'Home'])[1];
  $('#uIcon').textContent = user().icon; $('#uName').textContent = user().name; $('#uRole').textContent = role;
  $('#btnOffline').textContent = offline ? 'Offline' : 'Online'; $('#offlineBanner').hidden = !offline;
  R[screen]();
}
const go = (s) => { screen = s; render(); window.scrollTo(0, 0); };

// ---- wiring ----
function init() {
  $('#roleBtns').innerHTML = Object.keys(ROLES).map((r) => `<button data-role="${r}" class="${r === chosen ? 'on' : ''}"><span>${ROLES[r].icon}</span>${r}</button>`).join('');
  ['#rFarmer'].forEach((s) => ($(s).innerHTML = opts(db.farmers.map((f) => f.name))));
  ['#rProduct', '#sProduct'].forEach((s) => ($(s).innerHTML = opts(CROPS)));
  ['#rGross', '#rDed', '#rRej'].forEach((s) => $(s).addEventListener('input', calcNet));
  document.addEventListener('click', (e) => {
    const t = e.target.closest('[data-go],[data-pay],[data-role]'); if (!t) return;
    if (t.dataset.go) go(t.dataset.go);
    if (t.dataset.pay) pay(t.dataset.pay);
    if (t.dataset.role) { chosen = t.dataset.role; document.querySelectorAll('#roleBtns button').forEach((b) => b.classList.toggle('on', b === t)); }
  });
  $('#btnLogin').onclick = () => {
    if (offline || !$('#empId').value.trim() || !$('#empPw').value.trim()) return say('#loginErr', offline ? NO_NET : 'Enter your employee ID and password.', true);
    role = chosen; $('#login').hidden = true; $('#app').hidden = false; go('home');
  };
  $('#btnLogout').onclick = () => { $('#app').hidden = true; $('#login').hidden = false; };
  $('#btnOffline').onclick = () => { offline = !offline; render(); };
  $('#btnReceive').onclick = receive; $('#btnRelease').onclick = release;
  $('#btnReset').onclick = () => { db = SEED(); save(); toast('Demo data reset'); render(); };
}
document.addEventListener('DOMContentLoaded', init);
