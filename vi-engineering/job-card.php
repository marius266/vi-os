<?php

require __DIR__ . '/config/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/client-master.php';
ob_start();
require __DIR__ . '/auth-ui.php';
$authToolbarHtml = ob_get_clean();

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['job_csrf'])) $_SESSION['job_csrf'] = bin2hex(random_bytes(32));
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) { http_response_code(400); exit('Invalid job ID.'); }
$stmt = $pdo->prepare('SELECT * FROM job_cards WHERE id = ?');
$stmt->execute([$id]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$job) { http_response_code(404); exit('Job card not found.'); }
// Prefer permanent client ID; use a unique telephone match for older, unlinked jobs.
$client = null;
if (!empty($job['client_id'])) {
    $s = $pdo->prepare('SELECT * FROM clients WHERE id = ?');
    $s->execute([(int)$job['client_id']]);
    $client = $s->fetch(PDO::FETCH_ASSOC) ?: null;
} else {
    $phone = trim((string)($job['customer_telephone'] ?? ''));
    if ($phone !== '') {
        $s = $pdo->prepare('SELECT * FROM clients WHERE TRIM(telephone) = ? LIMIT 2');
        $s->execute([$phone]);
        $matches = $s->fetchAll(PDO::FETCH_ASSOC);
        if (count($matches) === 1) $client = $matches[0];
    }
}
if ($client) {
    $client = masterClient($pdo,(int)$client['id']) ?: $client;
    $clientRaw = json_decode($client['raw_data'] ?? '{}', true);
    $job['client_details'] = is_array($clientRaw) ? $clientRaw : [];
    $job['customer_name'] = $client['client_name'];
    $job['client_address_fallback'] = $client['full_address'];
    $job['client_vat_fallback'] = $client['vat_number'];
    $job['client_telephone_current'] = $client['telephone'];
    $job['master_details_active'] = true;
    $job['linked_client_id'] = (int)$client['id'];
}
$raw = json_decode($job['raw_data'] ?? '{}', true);
if (!is_array($raw)) $raw = [];
$job['raw'] = $raw;
$job['record_hash'] = hash('sha256', $job['raw_data']);
unset($job['raw_data']);
$payload = json_encode($job, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Visual Impact SA | Engineering Job Sheet</title>
    <link rel="stylesheet" href="Job-Card.css?v=<?= filemtime(__DIR__ . '/Job-Card.css') ?>">
</head>

<body>
<?php require __DIR__ . '/theme-ui.php'; ?>
    <div class="auth-toolbar"><?= $authToolbarHtml ?></div>
    <div class="toolbar"><a href="jobs.php" style="padding:11px 17px;background:#475569;color:white;border-radius:6px;text-decoration:none">← Back to jobs</a><?php if (!empty($job['linked_client_id'])): ?><a href="client-history.php?id=<?= (int)$job['linked_client_id'] ?>" style="padding:11px 17px;background:#1d4ed8;color:white;border-radius:6px;text-decoration:none">Client Job History</a><?php endif; ?><button type="button" onclick="window.print()">Print / Save as PDF</button><button type="button" class="secondary" onclick="downloadJSON()">Export form data</button><button type="button" id="editBtn">Edit</button><button type="button" id="deleteBtn" style="background:#b91c1c;color:white">Delete Record</button><button type="submit" id="saveBtn" form="jobForm" hidden>Save Changes</button><button type="button" class="secondary" id="cancelBtn" hidden>Cancel</button><small id="editMessage">View only until Edit is clicked.</small></div>
    <form class="sheet" id="jobForm" method="post" action="save-job.php">
        <input type="hidden" name="job_id" value="<?= (int)$id ?>">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['job_csrf'], ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="record_hash" value="<?= htmlspecialchars($job['record_hash'], ENT_QUOTES, 'UTF-8') ?>">
        <header class="head">
            <div>
                <div class="brand">Visual Impact Engineering SA</div>
                <div class="top-fields">
                    <div class="field"><label for="job">Job #</label><input id="job" name="job_number" >
                    </div>
                    <div class="field"><label for="client_top">Client</label><input id="client_top" name="client_top">
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
                    <div class="field"><label>Client</label><input name="client" id="client"></div>
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
                <div class="field"><label>Tech</label><input name="technician"></div>
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
        </section>
    </form>
    <script src="parts-cart.js?v=<?= filemtime(__DIR__ . '/parts-cart.js') ?>"></script>
    <script>
        const form = document.getElementById('jobForm');
        document.getElementById('client').addEventListener('input', e => 
        {
            document.getElementById('client_top').value = e.target.value 
        });
        document.getElementById('client_top').addEventListener('input', e =>
        {
            document.getElementById('client').value = e.target.value 
        });
        function calculate() 
        {
            const l = Number(document.getElementById('labour').value) || 0,
            p = Number(document.getElementById('parts_cost').value) || 0,
            s = l + p;
            document.getElementById('subtotal').value = s.toFixed(2);
            document.getElementById('vat').value = (s * .15).toFixed(2);
            document.getElementById('total').value = (s * 1.15).toFixed(2) 
        }
        function downloadJSON() 
        {
            const data = {};
            for (const e of form.elements) 
            {
                if (!e.name) continue;
                if ((e.type === 'radio' || e.type === 'checkbox') && !e.checked) 
                {
                    if (!(e.name in data)) data[e.name] = e.type === 'checkbox' ? false : '';
                continue 
                }
                data[e.name] = e.type === 'checkbox' ? true : e.value 
            }
            const blob = new Blob([JSON.stringify(data, null, 2)], 
            {
                type: 'application/json' 
            }),
            a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = (document.getElementById('job').value || 'job-sheet') + '.json';
            a.click();
            setTimeout(() => URL.revokeObjectURL(a.href), 1000) 
        }
        const imported = <?= $payload ?>;
        const raw = imported.raw || {};
        const clientDetails = imported.client_details || {};
        const clientPick = (...keys) => {
            for (const k of keys) {
                const v = clientDetails[k];
                if (v !== undefined && v !== null && String(v).trim() !== '') return String(v);
            }
            return '';
        };
        const clientAddress = [
            clientPick('Addr'), clientPick('Addr 1'), clientPick('Addr 2'),
            clientPick('Addr 3'), clientPick('Addr 4')
        ].filter(Boolean).join('\n');
        const pick = (...keys) => {
            for (const k of keys) {
                const v = raw[k];
                if (v !== undefined && v !== null && String(v).trim() !== '') return String(v);
            }
            return '';
        };
        const dateISO = value => {
            if (!value) return '';
            const text = String(value).trim();
            // MySQL datetime: 2026-10-14 00:00:00
            if (/^\d{4}-\d{2}-\d{2}/.test(text)) {
                return text.substring(0, 10);
            }
            // FileMaker date: 14/10/2026
            const match = text.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
            if (match) {
                const day = match[1].padStart(2, '0');
                const month = match[2].padStart(2, '0');
                return `${match[3]}-${month}-${day}`;
            }
            return '';
        };
        const time24 = value => {
            if (!value) return '';
            const s = String(value).trim();
            const m = s.match(/^(\d{1,2}):(\d{2})(?::(\d{2}))?\s*(AM|PM)?$/i);
            if (!m) return '';
            let h = Number(m[1]);
            if (m[4]) h = h % 12 + (m[4].toUpperCase() === 'PM' ? 12 : 0);
            if (h > 23) return '';
            return `${String(h).padStart(2,'0')}:${m[2]}:${m[3] || '00'}`;
        };
        const amount = value => {
            const s = String(value ?? '').replace(/[^\d.,-]/g, '');
            if (!s) return '';
            let n = s;
            if (s.includes(',') && s.includes('.')) n = s.lastIndexOf(',') > s.lastIndexOf('.') ? s.replace(/\./g,'').replace(',','.') : s.replace(/,/g,'');
            else if (s.includes(',')) n = s.replace(',','.');
            const v = Number(n);
            return Number.isFinite(v) ? v.toFixed(2) : '';
        };
        const set = (name, value) => {
            if (value === undefined || value === null || value === '') return;
            const els = form.querySelectorAll(`[name="${name}"]`);
            for (const el of els) {
                if (el.type === 'radio') el.checked = el.value.toLowerCase() === String(value).trim().toLowerCase();
                else if (el.type === 'checkbox') el.checked = /^(1|true|yes|on)$/i.test(String(value));
                else el.value = value;
            }
        };
        const status = pick('JOB STATUS') || imported.job_status;
        const normalizedStatus = ['Open','Closed','Pending'].find(s => s.toLowerCase() === String(status).trim().toLowerCase()) || (status ? 'Other' : '');
        const warranty = pick('WARRANTY STATUS') || imported.warranty_status;
        const normalizedWarranty = /non.?warranty/i.test(warranty) ? 'Non-warranty' : /warranty/i.test(warranty) ? 'Warranty' : /^n\/?a$/i.test(warranty) ? 'N/A' : '';
        const mappings = {
            job_number: imported.legacy_job_number,
            client_top: imported.master_details_active ? imported.customer_name : (imported.customer_name || clientPick('Client') || ''),
            client: imported.customer_name || clientPick('Client') || '',
            telephone: imported.master_details_active ? (imported.client_telephone_current || '') : (imported.client_telephone_current || imported.customer_telephone),
            cell: imported.master_details_active ? clientPick('Cell Phone 1') : (clientPick('Cell Phone 1') || pick('CUSTOMER CELL', 'CELL')),
            fax: imported.master_details_active ? clientPick('Fax') : (clientPick('Fax') || pick('CUSTOMER FAX')),
            email: imported.master_details_active ? clientPick('Internet Addr') : (clientPick('Internet Addr') || pick('CUSTOMER EMAIL', 'EMAIL')),
            contact: imported.master_details_active ? clientPick('Contact') : (clientPick('Contact') || [pick('CUSTOMER CONTACT FIRST'),pick('CUSTOMER CONTACT LAST')].filter(Boolean).join(' ') || pick('CUSTOMER CONTACT')),
            order_number: pick('CUSTOMER ORDER Nº','CUSTOMER ORDER NO'),
            address: imported.master_details_active ? (imported.client_address_fallback || clientAddress) : ([pick('CUSTOMER ADDRESS'),pick('CUSTOMER ADDRESS 1'),pick('CUSTOMER ADDRESS 2')].filter(Boolean).join('\n') || imported.client_address_fallback || clientAddress),
            client_vat: imported.client_vat_fallback || clientPick('VAT#'),
            warranty: normalizedWarranty,
            date_in: dateISO(imported.date_in_raw),
            time_in: time24(imported.time_in_raw),
            machine: imported.machine_type,
            serial_top: imported.serial_number,
            job_serial: imported.serial_number,
            job_type: imported.job_type,
            model: imported.machine_type || pick('MACHINE TYPE'), // No separate MODEL column in the latest FileMaker export
            problem: imported.fault,
            priority: imported.priority,
            job_status: normalizedStatus,
            out_for_repair: pick('OutForRepair'),
            repair_reference: pick('RepairRefNum'),
            accessories_in: pick('ACCESSORIES','ACCESSORIES IN','ACTION TAKEN 2'),
            job_accessories: pick('ACCESSORIES','ACCESSORIES IN','ACTION TAKEN 2'),
            technician: pick('TECHNICIAN','TECH','ENGINEER','ACTION TAKEN 1'),
            invoice_number: pick('Invoice','INVOICE'),
            work_date: dateISO(pick('ACTION DATE','ACTION DATE 1')),
            book_reference: pick('BOOK REF :','BOOK REF'),
            time_spent: pick('TIME SPENT HRS','Total Hrs'),
            paid: pick('PAID'),
            action: pick('ACTION TAKEN'),
            parts_description: pick('PARTS USED'),
            labour: amount(imported.labour_raw),
            parts_cost: amount(imported.parts_raw),
            subtotal: amount(imported.subtotal_raw),
            vat: amount(imported.vat_raw),
            total: amount(imported.total_raw)
        };
        Object.entries(mappings).forEach(([name,value]) => set(name,value));
        window.viPartsCart.init(imported.raw?.VI_PARTS_CART, pick('PARTS USED'), amount(imported.parts_raw));
        // Preserve historical totals until the cart is changed.
        const initialValues = new Map();
        for (const el of form.elements) {
            if (!el.name || el.type === 'hidden') continue;
            initialValues.set(el, el.type === 'checkbox' || el.type === 'radio' ? el.checked : el.value);
        }
        const lock = locked => {
            for (const el of form.elements) {
                if (!el.name || el.type === 'hidden') continue;
                if (['subtotal', 'vat', 'total', 'job_number'].includes(el.name)) { el.readOnly = true; continue; }
                if (el.tagName === 'SELECT' || el.type === 'checkbox' || el.type === 'radio') el.disabled = locked;
                else el.readOnly = locked;
            }
            document.getElementById('editBtn').hidden = !locked;
            document.getElementById('saveBtn').hidden = locked;
            document.getElementById('cancelBtn').hidden = locked;
            document.getElementById('editMessage').textContent = locked ? 'View only until Edit is clicked.' : 'Editing — Save Changes to store updates.';
        };
        document.getElementById('deleteBtn').addEventListener('click', () => {
            const jobNumber = String(imported.legacy_job_number ?? '');
            if (!confirm(`Permanently delete Job #${jobNumber}? This cannot be undone.`)) return;
            const typed = prompt(`Type ${jobNumber} to confirm permanent deletion:`);
            if (typed !== jobNumber) { alert('Deletion cancelled.'); return; }
            const deletionForm = document.createElement('form');
            deletionForm.method = 'post';
            deletionForm.action = 'delete-job.php';
            for (const [name, value] of Object.entries({
                job_id: String(imported.id),
                job_number: jobNumber,
                csrf: form.elements['csrf'].value
            })) {
                const input = document.createElement('input');
                input.type = 'hidden'; input.name = name; input.value = value;
                deletionForm.appendChild(input);
            }
            document.body.appendChild(deletionForm);
            deletionForm.submit();
        });
        lock(true);
        window.viPartsCart.setLocked(true);
        document.getElementById('editBtn').addEventListener('click', () => { lock(false); window.viPartsCart.setLocked(false); });
        document.getElementById('cancelBtn').addEventListener('click', () => location.reload());
        form.addEventListener('submit', event => {
            if (document.getElementById('saveBtn').hidden) { event.preventDefault(); return; }
            if (!confirm('Save changes to this job card?')) event.preventDefault();
        });
        for (const name of ['labour']) {
            form.elements[name].addEventListener('input', () => {
                if (!document.getElementById('saveBtn').hidden) calculate();
            });
        }
        const query = new URLSearchParams(location.search);
        if (query.get('saved') === '1') document.getElementById('editMessage').textContent = 'Changes saved successfully.';
    </script>
</body>

</html>