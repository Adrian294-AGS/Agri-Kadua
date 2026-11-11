<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Co-opSync - San Miguel Farmers Cooperative</title>
  <link rel="stylesheet" href="{{ asset('css/coopsync-prototype.css') }}">
  <script src="{{ asset('js/coopsync-prototype.js') }}" defer></script>
</head>
<body>
  @include('coopsync.login')

  <div id="app" hidden>
    <aside class="side">
      <div class="brand">🌿 <b>Co-opSync</b><small>San Miguel Farmers Coop</small></div>
      <div class="usercard"><span id="uIcon"></span><div><b id="uName"></b><small id="uRole"></small></div></div>
      <nav id="nav"></nav>
    </aside>
    <div class="main">
      <header class="top">
        <span id="screenTitle">Home</span>
        <div>
          <button class="btn sm o" id="btnOffline" type="button">Online</button>
          <button class="btn sm o" id="btnLogout" type="button">Log out</button>
        </div>
      </header>
      <div id="offlineBanner" class="banner" hidden>No internet. Changes cannot be saved right now.</div>
      <main id="pg">
        @include('coopsync.home')
        @include('coopsync.receive')
        @include('coopsync.stock')
        @include('coopsync.pay')
        @include('coopsync.receipts')
        @include('coopsync.farmers')
        @include('coopsync.audit')
      </main>
      <footer class="foot"><span>🌿 Co-opSync · San Miguel Farmers Cooperative</span>
        <button class="link" id="btnReset" type="button">Reset demo data</button></footer>
    </div>
  </div>
  <div id="toast" class="toast" hidden></div>
</body>
</html>
