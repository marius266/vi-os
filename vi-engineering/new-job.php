<?php
require __DIR__ . '/config/database.php';
require_once __DIR__ . '/auth.php';
require __DIR__ . '/auth-ui.php';
$loggedInTechnician = trim((string)(current_user()['username'] ?? ''));

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['job_csrf'])) $_SESSION['job_csrf'] = bin2hex(random_bytes(32));
$clients = $pdo->query("SELECT id, client_name, telephone, full_address, vat_number, raw_data FROM clients WHERE master_client_id IS NULL AND TRIM(COALESCE(client_name,'')) <> '' ORDER BY client_name, id")->fetchAll(PDO::FETCH_ASSOC);
$clientPayload = [];
foreach ($clients as $c) {
    $r = json_decode($c['raw_data'] ?? '{}', true);
    if (!is_array($r)) $r = [];
    $clientPayload[(string)$c['id']] = [
        'name'=>$c['client_name'], 'phone'=>$c['telephone'], 'address'=>$c['full_address'],
        'vat'=>$c['vat_number'], 'cell'=>(string)($r['Cell Phone 1'] ?? ''),
        'fax'=>(string)($r['Fax'] ?? ''), 'email'=>(string)($r['Internet Addr'] ?? '')
    ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Visual Impact SA | New Job Card</title>
    <link rel="stylesheet" href="Job-Card.css?v=<?= filemtime(__DIR__ . '/Job-Card.css') ?>">
    <style>
      .client-picker{padding:14px;margin-bottom:12px;border:1px solid #cbd5e1;border-radius:6px;background:#f1f5f9;color:#17212b}
      .client-picker label{display:block;margin-bottom:7px;color:inherit}
      .client-picker #clientSearch,.client-picker #clientSelect{display:block;width:100%;box-sizing:border-box;background:#fff;color:#17212b;border:1px solid #94a3b8;border-radius:5px;padding:10px;font:14px Arial,sans-serif}
      .client-picker #clientSearch::placeholder{color:#64748b;opacity:1}
      .client-picker #clientSelect{margin-top:6px;min-height:100px}
      .client-picker #clientSelect option{padding:7px}
      .client-picker .client-results-label{margin-top:12px}
      .client-picker small{display:block;margin-top:8px;color:#475569}
      .client-picker .client-selected{margin-top:8px;font-weight:bold;color:#166534}
      html.vi-dark .client-picker{background:#26354b!important;color:#f8fafc!important;border-color:#475569!important}
      html.vi-dark .client-picker #clientSearch,html.vi-dark .client-picker #clientSelect{background:#101c2f!important;color:#f8fafc!important;border-color:#64748b!important}
      html.vi-dark .client-picker small{color:#cbd5e1!important}
      html.vi-dark .client-picker .client-selected{color:#86efac!important}
      html.vi-dark .client-picker #clientSearch::placeholder{color:#cbd5e1!important}
      body[data-theme="dark"] .client-picker,body.dark .client-picker,[data-theme="dark"] .client-picker{background:#26354b;color:#f8fafc;border-color:#475569}
      body[data-theme="dark"] .client-picker #clientSearch,body[data-theme="dark"] .client-picker #clientSelect,body.dark .client-picker #clientSearch,body.dark .client-picker #clientSelect,[data-theme="dark"] .client-picker #clientSearch,[data-theme="dark"] .client-picker #clientSelect{background:#101c2f;color:#f8fafc;border-color:#64748b}
      body[data-theme="dark"] .client-picker #clientSearch::placeholder,body.dark .client-picker #clientSearch::placeholder,[data-theme="dark"] .client-picker #clientSearch::placeholder{color:#cbd5e1}
      body[data-theme="dark"] .client-picker small,body.dark .client-picker small,[data-theme="dark"] .client-picker small{color:#cbd5e1}
      body[data-theme="dark"] .client-picker .client-selected,body.dark .client-picker .client-selected,[data-theme="dark"] .client-picker .client-selected{color:#86efac}
    </style>
</head>

<body>
<?php require __DIR__ . '/theme-ui.php'; ?>
    <div class="toolbar"><a href="jobs.php" style="padding:11px 17px;background:#475569;color:white;border-radius:6px;text-decoration:none">← Back to jobs</a><button type="submit" form="jobForm" id="createBtn">Create Job Card</button><small>New job — choose an existing client and complete the fields.</small></div>
    <form class="sheet" id="jobForm" method="post" action="create-job.php">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['job_csrf'], ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="client_id" id="selectedClientId" value="">
        <div class="client-picker">
          <label for="clientSearch"><strong>Search existing client *</strong></label>
          <input type="search" id="clientSearch" autocomplete="off" placeholder="Type client name, email or telephone" aria-controls="clientSelect" aria-describedby="clientSearchHelp">
          <label for="clientSelect" class="client-results-label">Matching clients</label>
          <select id="clientSelect" size="5" aria-label="Matching clients" required>
            <option value="">Start typing to find a client</option>
          </select>
          <small id="clientSearchHelp">Type to filter, then select a client from the results. Only master clients are available.</small>
          <div id="clientSelected" class="client-selected" aria-live="polite"></div>
        </div>
        <header class="head">
            <div>
                <div class="brand">Visual Impact SA</div>
                <div class="top-fields">
                    <div class="field"><label for="job">Job #</label><input id="job" name="job_number" readonly placeholder="Assigned when saved" >
                    </div>
                    <div class="field"><label for="client_top">Client</label><input id="client_top" name="client_top" readonly>
                    </div>
                    <div class="field"><label for="date_in">Date In</label><input type="date" id="date_in"
                            name="date_in" ></div>
                    <div class="field"><label for="time_in">Time In</label><input type="time" step="1" id="time_in"
                            name="time_in" ></div>
                </div>
            </div>
            <div>
                <div class="offices">
                    <div>Visual Impact CT<br>1 Glynnville Terrace<br>Gardens<br>Cape Town<br>8001<br>Tel: (021) 468 6000
                    </div>
                    <div>Visual Impact JHB<br>32 Bompas Rd<br>Dunkeld West<br>Johannesburg<br>2169<br>Tel: (011) 788
                        9879<br>VAT#: 4300196476</div>
                </div>
            </div>
        </header>
        <div class="receipt-full-width">
            <div class="ref">Ref. Receipt</div>
            <div class="terms"><strong>Terms &amp; Conditions:</strong> Visual Impact and its employees will not be
                    held liable for damages or loss of data or software. The backup of data and software remains the
                    responsibility of the client. All equipment handed in for repairs is at the client's own risk.
                    Insurance remains the responsibility of the client. All repairs may be subject to a minimum charge
                    of 1 hour labour. We undertake to repair machines within a reasonable time. Estimated times are
                    given in good faith, but may be subject to change without notice due to factors beyond our control.
                    All repairs are strictly COD unless prior arrangements were made. Repairs not collected within 90
                    days will be sold to defray cost.</div>
        </div>
        <section class="section client-info">
            <h2>CLIENT INFORMATION</h2>
            <div class="grid">
                <div>
                    <div class="field"><label>Client</label><input name="client" id="client" readonly></div>
                    <div class="field"><label>Tel #</label><input name="telephone" type="tel"></div>
                    <div class="field"><label>Cell #</label><input name="cell" type="tel"></div>
                    <div class="field"><label>Fax #</label><input name="fax"></div>
                    <div class="field"><label>E-Mail</label><input name="email" type="email"></div>
                    <div class="field"><label>Contact</label><input name="contact"></div>
                    <div class="field"><label>Order #</label><input name="order_number"></div>
                </div>
                <div>
                    <div class="field stack"><label>Address</label><textarea name="address" rows="7"></textarea></div>
                    <div class="field"><label>VAT #</label><input name="client_vat"></div>
                    <div class="field stack"><label>WARRANTY STATUS</label>
                        <div class="choices"><label><input type="radio" name="warranty" value="Warranty">
                                Warranty</label><label><input type="radio" name="warranty" value="Non-warranty">
                                Non-warranty</label><label><input type="radio" name="warranty" value="N/A"> N/A</label>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <section class="section">
            <h2>Job Sheet</h2>
            <div class="grid job-sheet-original-grid">
                <div class="job-sheet-left">
                    <div class="field"><label>Job Type</label><input name="job_type"></div>
                    <div class="field"><label>Model</label><input name="model"></div>
                    <div class="field"><label>Serial #</label><input name="job_serial"></div>
                    <div class="field stack"><label>Problem</label><textarea name="problem" rows="10"></textarea></div>
                    <div class="field"><label>Priority</label><select name="priority">
                        <option value=""></option>
                        <option>Low</option>
                        <option>Normal</option>
                        <option>High</option>
                        <option>Urgent</option>
                    </select></div>
                </div>
                <div class="job-sheet-right">
                    <div class="field"><label>Out for Repair</label>
                        <div class="choices"><label><input type="checkbox" name="out_for_repair"> Yes</label></div>
                        <label style="min-width:78px">Repair Ref #</label><input name="repair_reference">
                    </div>
                    <div class="field stack"><label>Accessories</label><textarea name="job_accessories" rows="16"></textarea></div>
                    <div class="field"><label>Job Status</label>
                        <div class="choices"><label><input type="radio" name="job_status" value="Open" checked> Open</label>
                            <label><input type="radio" name="job_status" value="Closed"> Closed</label>
                            <label><input type="radio" name="job_status" value="Pending"> Pending</label>
                            <label><input type="radio" name="job_status" value="Other"> Other</label>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <section class="section">
            <h2>Work Done</h2>
            <div class="grid">
                <div class="field"><label>Tech</label><input name="technician" value="<?= htmlspecialchars($loggedInTechnician, ENT_QUOTES, 'UTF-8') ?>" readonly></div>
                <div class="field"><label>Invoice #</label><input name="invoice_number"></div>
                <div class="field"><label>Date</label><input type="date" name="work_date"></div>
                <div class="field"><label>Time Spent</label><input name="time_spent" placeholder="e.g. 1.5 hours"></div>
                <div class="field"><label>PAID</label>
                    <div class="choices"><label><input type="radio" name="paid" value="Yes"> YES</label><label><input
                                type="radio" name="paid" value="No"> NO</label></div>
                </div>
            </div>
            <div class="two-panel" style="margin-top:10px">
                <div class="field stack"><label>Action</label><textarea name="action" rows="9"></textarea></div>
                <div>
                    <div class="parts-cart"><div class="parts-cart-heading"><strong>Parts Used</strong><button type="button" id="addPartBtn">+ Add Part</button></div><div class="parts-cart-scroll"><table class="parts-cart-table"><thead><tr><th>Part description</th><th>Qty</th><th>Price (R)</th><th>Total (R)</th><th></th></tr></thead><tbody id="partsCartRows"></tbody></table></div><input type="hidden" name="parts_cart_json" id="partsCartJson"><input type="hidden" name="parts_description" id="partsDescription"></div>
                    <div class="costs">
                        <div class="field"><label>Labour</label><input id="labour" name="labour" type="number"
                                step="0.01" min="0"></div>
                        <div class="field"><label>Parts</label><input id="parts_cost" name="parts_cost" type="number" readonly
                                step="0.01" min="0"></div>
                        <div class="field"><label>Sub</label><input id="subtotal" name="subtotal" readonly></div>
                        <div class="field"><label>VAT (15%)</label><input id="vat" name="vat" readonly></div>
                        <div class="field total"><label>Total</label><input id="total" name="total" readonly></div>
                    </div>
                </div>
            </div>
        </section>
        <section class="section">
            <div class="signature-section">
                <div class="signature-row">
                    <span>Client Signature (In)</span>
                    <div class="signature-line"></div>
                    <span>Date</span>
                    <div class="date-line"></div>
                    <span>Name</span>
                    <div class="name-line"></div>
                </div>

                <div class="signature-row">
                    <span>Client Signature (Out)</span>
                    <div class="signature-line"></div>
                    <span>Date</span>
                    <div class="date-line"></div>
                    <span>Name</span>
                    <div class="name-line"></div>
                </div>
            </div>
            <div class="footer"><strong>Terms &amp; Conditions:</strong> Visual Impact and its employees will not be
                held liable for damages or loss of data or software. The backup of data and software remains the
                responsibility of the client. All equipment handed in for repairs is at the client's own risk. Insurance
                remains the responsibility of the client. All repairs may be subject to a minimum charge of 1 hour
                labour. We undertake to repair equipment within a reasonable time. Estimated times are given in good
                faith, but may be subject to change without notice due to factors beyond our control. All repairs are
                strictly COD unless prior arrangements were made. Repairs not collected within 90 days will be sold to
                defray cost. A minimum 1 hour labour may be charged as a quote rejection fee depending on time spent to
                provide the quote.</div>
        </section>
    </form>
<script src="parts-cart.js?v=<?= filemtime(__DIR__ . '/parts-cart.js') ?>"></script>
<script>
const form = document.getElementById('jobForm');
const clients = <?= json_encode($clientPayload, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR) ?>;
const set = (name, value) => { const el = form.elements.namedItem(name); if (el && !('length' in el)) el.value = value ?? ''; };
const clientSearch = document.getElementById('clientSearch');
const clientSelect = document.getElementById('clientSelect');
const selectedClientLabel = document.getElementById('clientSelected');
const clientEntries = Object.entries(clients).map(([id,c]) => ({id,c,search: [c.name,c.email,c.phone,c.cell].join(' ').toLocaleLowerCase()}));
function renderClientMatches() {
  const query = clientSearch.value.trim().toLocaleLowerCase();
  const matches = query ? clientEntries.filter(x => x.search.includes(query)).slice(0,100) : [];
  clientSelect.replaceChildren();
  if (!matches.length) {
    const option = new Option(query ? 'No matching clients' : 'Start typing to find a client', '');
    option.disabled = true;
    clientSelect.add(option);
  } else {
    for (const {id,c} of matches) {
      clientSelect.add(new Option([c.name,c.phone,c.email].filter(Boolean).join(' — '),id));
    }
    clientSelect.selectedIndex = -1;
  }
}
clientSearch.addEventListener('input', () => {
  document.getElementById('selectedClientId').value = '';
  selectedClientLabel.textContent = '';
  clientSelect.setCustomValidity('');
  for (const field of ['client','client_top','telephone','cell','fax','email','address','client_vat']) set(field,'');
  renderClientMatches();
});
clientSearch.addEventListener('keydown', e => {
  if (e.key === 'ArrowDown') { e.preventDefault(); clientSelect.focus(); }
});
document.getElementById('clientSelect').addEventListener('change', e => {
  const c = clients[e.target.value];
  document.getElementById('selectedClientId').value = c ? e.target.value : '';
  selectedClientLabel.textContent = c ? 'Selected: ' + c.name + (c.phone ? ' — ' + c.phone : '') : '';
  for (const [field,key] of [['client','name'],['client_top','name'],['telephone','phone'],['cell','cell'],['fax','fax'],['email','email'],['address','address'],['client_vat','vat']]) set(field, c?.[key] || '');
});
const now = new Date();
set('date_in', `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')}`);
set('time_in', `${String(now.getHours()).padStart(2,'0')}:${String(now.getMinutes()).padStart(2,'0')}`);
function calculate() {
  const l = Number(form.elements['labour'].value) || 0, p = Number(form.elements['parts_cost'].value) || 0;
  const s = l+p; set('subtotal',s.toFixed(2)); set('vat',(s*.15).toFixed(2)); set('total',(s*1.15).toFixed(2));
}
for (const n of ['labour']) form.elements[n].addEventListener('input',calculate);
form.addEventListener('submit',e=>{
 if (!document.getElementById('selectedClientId').value) { e.preventDefault(); clientSearch.focus(); alert('Search for and select a client.'); return; }
 calculate();
 if (!confirm('Create this new job card?')) { e.preventDefault(); return; }
 document.getElementById('createBtn').disabled = true;
});
calculate();
// Initialize the parts cart separately so a missing/failed parts-cart.js
// cannot prevent the client search handlers above from being registered.
try {
  if (window.viPartsCart && typeof window.viPartsCart.init === 'function') {
    window.viPartsCart.init([], '', '0');
  } else {
    console.error('Parts Cart script is missing: check parts-cart.js');
  }
} catch (error) {
  console.error('Parts Cart initialization failed:', error);
}
</script></body></html>
