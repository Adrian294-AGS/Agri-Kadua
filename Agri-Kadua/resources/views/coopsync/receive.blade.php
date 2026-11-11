<section data-view="receive" hidden>
  <h2>Receive harvest</h2>
  <div class="card">
    <label>Farmer</label><select id="rFarmer"></select>
    <label>Product</label><select id="rProduct"></select>
    <label>Quality grade</label>
    <select id="rGrade"><option>A</option><option>B</option><option>C</option></select>
    <label>Gross weight (kg)</label><input id="rGross" type="number" min="0" value="0">
    <label>Deduction (kg)</label><input id="rDed" type="number" min="0" value="0">
    <label>Rejected (kg)</label><input id="rRej" type="number" min="0" value="0">
    <div class="net">Accepted weight<b id="rNet">0 kg</b></div>
    <p id="rMsg" class="msg" hidden></p>
    <button class="btn" id="btnReceive" type="button">Save harvest</button>
  </div>
</section>
