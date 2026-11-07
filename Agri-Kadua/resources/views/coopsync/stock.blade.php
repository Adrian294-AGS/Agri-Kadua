<section data-view="stock" hidden>
  <h2>Warehouse stock</h2>
  <div id="stockList"></div>
  <div class="card" id="releaseBox">
    <h3>Take out stock</h3>
    <label>Product</label><select id="sProduct"></select>
    <label>Quantity (kg)</label><input id="sQty" type="number" min="0" value="0">
    <label>Reason</label><input id="sReason">
    <p id="sMsg" class="msg" hidden></p>
    <button class="btn" id="btnRelease" type="button">Confirm take out</button>
  </div>
</section>
