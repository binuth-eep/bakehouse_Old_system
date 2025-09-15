<?php
// ---------- DB: orders CRUD (keep style unchanged) ----------
$host = "localhost";
$user = "root";
$pass = "";
$db   = "bakehouse2";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("DB Connection failed: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// Helper for bind_param dynamic refs (used if needed)
function refValues($arr){
    $refs = [];
    foreach ($arr as $k => $v) $refs[$k] = &$arr[$k];
    return $refs;
}

// Handle POST actions: add / edit / delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add') {
        $order_date = $_POST['order_date'] ?: null;
        $customer   = trim($_POST['customer'] ?? '');
        $product    = trim($_POST['product'] ?? '');
        $quantity   = (int)($_POST['quantity'] ?? 1);
        $price      = (float)($_POST['price'] ?? 0.00);
        $status     = $_POST['status'] ?? 'Pending';


        $stmt = $conn->prepare("INSERT INTO orders (order_date, customer, product, quantity, price, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssids", $order_date, $customer, $product, $quantity, $price, $status);
        $ok = $stmt->execute();
        $err = $stmt->error;
        $stmt->close();

        if (!$ok) $flash_error = "Insert failed: " . $err;
        else { header("Location: " . $_SERVER['PHP_SELF']); exit; }
    }

    if ($action === 'edit') {
        $id         = (int)($_POST['id'] ?? 0);          
        $order_date = $_POST['order_date'] ?: null;
        $customer   = trim($_POST['customer'] ?? '');
        $product    = trim($_POST['product'] ?? '');
        $quantity   = (int)($_POST['quantity'] ?? 1);
        $price      = (float)($_POST['price'] ?? 0.00);
        $status     = $_POST['status'] ?? 'Pending';

        $stmt = $conn->prepare("UPDATE orders SET order_date = ?, customer = ?, product = ?, quantity = ?, price = ?, status = ? WHERE id = ?");
        $stmt->bind_param("sssidsi", $order_date, $customer, $product, $quantity, $price, $status, $id);
        $ok = $stmt->execute();
        $err = $stmt->error;
        $stmt->close();

        if (!$ok) $flash_error = "Update failed: " . $err;
        else { header("Location: " . $_SERVER['PHP_SELF']); exit; }
    }


    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM orders WHERE id = ?");
        $stmt->bind_param("i", $id);
        $ok = $stmt->execute();
        $err = $stmt->error;
        $stmt->close();

        if (!$ok) $flash_error = "Delete failed: " . $err;
        else { header("Location: " . $_SERVER['PHP_SELF']); exit; }
    }

    // ---------- Return order handler ----------
if ($action === 'return') {
    $id = (int)($_POST['id'] ?? 0);
    $return_qty = (int)($_POST['return_quantity'] ?? 1);
    $reason = trim($_POST['return_reason'] ?? '');
    $refund_amount = (float)($_POST['refund_amount'] ?? 0.00);
    $return_date = $_POST['return_date'] ?: date('Y-m-d');

    if ($id <= 0) {
        $flash_error = "Invalid order id for return.";
    } else {
        // check order exists
        $sel = $conn->prepare("SELECT id, quantity, status FROM orders WHERE id = ?");
        $sel->bind_param("i", $id);
        $sel->execute();
        $res = $sel->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $sel->close();

        if (!$row) {
            $flash_error = "Order not found (id: $id)";
        } else {
            // optional: ensure return_qty <= original quantity
            $origQty = (int)$row['quantity'];
            if ($return_qty <= 0 || $return_qty > $origQty) {
                $flash_error = "Invalid return quantity (must be between 1 and $origQty).";
            } else {
                // Insert into returns table if exists
                $ins = $conn->prepare("INSERT INTO returns (order_id, return_date, quantity, reason, refund_amount) VALUES (?, ?, ?, ?, ?)");
                if ($ins) {
                    $ins->bind_param("isisd", $id, $return_date, $return_qty, $reason, $refund_amount);
                    $ins->execute();
                    $errIns = $ins->error;
                    $ins->close();
                    if ($errIns) {
                        $flash_error = "Failed to record return: " . $errIns;
                    }
                } else {
                    // table may not exist, ignore and continue to update order status
                }

                // Update order status to Returned (or 'Refunded' depending on your workflow)
                $newStatus = 'Returned';
                $upd = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
                $upd->bind_param("si", $newStatus, $id);
                $ok = $upd->execute();
                $errUpd = $upd->error;
                $upd->close();

                if (!$ok) $flash_error = "Failed to update order status: " . $errUpd;
                else { header("Location: " . $_SERVER['PHP_SELF']); exit; }
            }
        }
    }
}

}

// Fetch orders for display
$sql = "SELECT id, order_date, customer, product, quantity, price, status FROM orders ORDER BY id DESC";
$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();
$orders = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Count total orders
$res2 = $conn->query("SELECT COUNT(*) AS total_orders FROM orders");
$row2 = $res2->fetch_assoc();
$totalOrders = (int)$row2['total_orders'];
?>
 <?php
// sale.php
include '../main.html';
?>
  <!-- Right sidebar / small stats -->
      <aside class="sidebarr">
        <nav>
          <div class="add-btn">
            <div><button class="salesbtn stb" id="salesbtn" onclick="mainPopup()">ADD</button></div>
            <div><button class="salesbtn" onclick="togglePopup()">Filter</button></div>
          </div>
          <section class="layout-side">
            <div class="cards">
            <div class="card">
              <h3>Total Returns</h3>
              <p id="cardTotal">Rs.0</p>
            </div>
            <div class="card">
              <h3>Total Orders</h3>
              <p id="cardOrders"><?= $totalOrders ?></p>
            </div>
            <div class="card">
              <h3>Total Customers</h3>
              <p id="cardCustomers">0</p>
            </div>
          </section>
        </nav>
      </aside>
<main class="free-area">
      <!-- Orders Panel -->
      <section id="orders" class="panel active">
         <div class="content">
        <h2>Order Management</h2>
        
          </div>
        <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Order ID</th>
              <th>Customer</th>
              <th>Product</th>
              <th>Quantity</th>
              <th>Price</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="orderTable">
            <?php if(empty($orders)): ?>
              <tr><td colspan="7" style="text-align:center;padding:18px">No orders found</td></tr>
            <?php else: foreach($orders as $o): ?>
              <tr>
                <td><?= htmlspecialchars($o['id']) ?></td>
                <td><?= htmlspecialchars($o['customer']) ?></td>
                <td><?= htmlspecialchars($o['product']) ?></td>
                <td><?= (int)$o['quantity'] ?></td>
                <td><?= number_format((float)$o['price'], 2) ?></td>
                <td><?= htmlspecialchars($o['status']) ?></td>
                <td>
                  <!-- View (data attributes) -->
                  <button class="btnview"
                    type="button"
                    data-id="<?= htmlspecialchars($o['id']) ?>"
                    data-order-date="<?= htmlspecialchars($o['order_date']) ?>"
                    data-customer="<?= htmlspecialchars($o['customer']) ?>"
                    data-product="<?= htmlspecialchars($o['product']) ?>"
                    data-quantity="<?= (int)$o['quantity'] ?>"
                    data-price="<?= htmlspecialchars($o['price']) ?>"
                    data-status="<?= htmlspecialchars($o['status']) ?>">
                    <i class="fa-solid fa-eye"></i>
                  </button>

                  <!-- Update (data attributes) -->
                  <button class="btnupdate"
                    type="button"
                    data-id="<?= htmlspecialchars($o['id']) ?>"
                    data-order-date="<?= htmlspecialchars($o['order_date']) ?>"
                    data-customer="<?= htmlspecialchars($o['customer']) ?>"
                    data-product="<?= htmlspecialchars($o['product']) ?>"
                    data-quantity="<?= (int)$o['quantity'] ?>"
                    data-price="<?= htmlspecialchars($o['price']) ?>"
                    data-status="<?= htmlspecialchars($o['status']) ?>">
                    <i class="fa-regular fa-pen-to-square"></i>
                  </button>

                  <!-- Delete -->
                  <form method="post" style="display:inline" onsubmit="return confirm('Delete order #<?= $o['id'] ?>?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $o['id'] ?>">
                    <button class="btndelete" type="submit"><i class="fa-solid fa-trash-can"></i></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
        </div>
      </section>

      <!-- Other Panels -->
      <section id="dashboard" class="panel"><h2>Dashboard</h2></section>
      <section id="products" class="panel"><h2>Product Management</h2></section>
      <section id="users" class="panel"><h2>User Management</h2></section>
       <!-- Export -->
      <section id="sales-export" class="panel">
        <div class="content">
          <h2>Export Reports</h2>
          <div class="export-grid">
            <div class="row">
              <input type="date" id="expFrom" />
              <input type="date" id="expTo" />
              <select id="expStatus">
                <option value="">All Status</option>
                <option>Completed</option>
                <option>Pending</option>
                <option>Cancelled</option>
              </select>
              <input type="text" id="expCustomer" placeholder="Customer" />
            </div>
            <div class="row">
              <button class="btn" id="expCsv">Export CSV</button>
              <button class="btn secondary" id="expXlsx">Export Excel</button>
              <button class="btn warn" id="expPdf">Export PDF</button>
              <span class="muted">Exports use live, filtered data.</span>
            </div>
          </div>
        </div>
      </section>
    </div>
    </main>
  </div>

   
    
  </main>
</div>
  <!-- Modal Add/Edit for ORDERS (uses your .modal-backdrop/.modal styles) -->
  <div class="modal-backdrop" id="orderModalBackdrop">
    <div class="modal">
      <h2 id="orderModalTitle">Add Order</h2>
      <form id="orderForm" method="post" style="margin-top:12px">
        <input type="hidden" name="action" id="order_form_action" value="add">
        <input type="hidden" name="id" id="order_form_id" value="0">

        <div class="form-grid">
          <div><label>Date</label><input id="order_form_date" name="order_date" type="date"></div>
          <div><label>Quantity</label><input id="order_form_quantity" name="quantity" type="number" min="1" value="1"></div>
          <div class="full"><label>Price</label><input id="order_form_price" name="price" type="number" step="0.01" min="0" value="0.00" required></div>
          <div class="full"><label>Customer</label><input id="order_form_customer" name="customer" type="text" required></div>
          <div class="full"><label>Product</label><input id="order_form_product" name="product" type="text" required></div>
          <div class="full">
            <label>Status</label>
            <select id="order_form_status" name="status">
                <option>Order Received</option>
                <option>Payment Confirmed</option>
                <option>Queued for Baking</option>
                <option>In Preparation</option>
                <option>Decorating</option>
                <option>Ready for Pickup</option>
                <option>Out for Delivery</option>
                <option>Completed</option>
                <option>Cancelled</option>
                <option>Refunded</option>
              </select>

          </div>
        </div>

        <div class="footer" style="margin-top:12px">
          <button type="button" class="btn light" onclick="closeOrderModal()">Cancel</button>
          <button type="submit" class="btn">Save</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Return Modal -->
<div class="modal-backdrop" id="returnModalBackdrop" style="display:none">
  <div class="modal">
    <h2 id="returnModalTitle">Return Order</h2>
    <form id="returnForm" method="post" style="margin-top:12px">
      <input type="hidden" name="action" value="return">
      <input type="hidden" name="id" id="return_order_id" value="0">
      <div class="form-grid">
        <div><label>Return Date</label><input name="return_date" id="return_date" type="date" value="<?= date('Y-m-d') ?>"></div>
        <div><label>Quantity to return</label><input name="return_quantity" id="return_quantity" type="number" min="1" value="1"></div>
        <div class="full"><label>Refund Amount</label><input name="refund_amount" id="return_refund_amount" type="number" step="0.01" min="0" value="0.00"></div>
        <div class="full"><label>Reason</label><textarea name="return_reason" id="return_reason" rows="3"></textarea></div>
      </div>
      <div class="footer" style="margin-top:12px">
        <button type="button" class="btn light" onclick="closeReturnModal()">Cancel</button>
        <button type="submit" class="btn">Save Return</button>
      </div>
    </form>
  </div>
</div>


  <!-- =========== Your existing JS (unchanged) =========== -->
<script>
  document.addEventListener('DOMContentLoaded', () => {
  const orderModalBackdrop = document.getElementById('orderModalBackdrop');
  const orderForm = document.getElementById('orderForm');
  const inAction = (val) => document.getElementById('order_form_action').value = val;
  const inId = (val) => document.getElementById('order_form_id').value = val;
  const inDate = (val) => document.getElementById('order_form_date').value = val;
  const inCustomer = (val) => document.getElementById('order_form_customer').value = val;
  const inProduct = (val) => document.getElementById('order_form_product').value = val;
  const inQuantity = (val) => document.getElementById('order_form_quantity').value = val;
  const inPrice = (val) => {
    const el = document.getElementById('order_form_price');
    if (el) el.value = (val === undefined || val === null) ? '0.00' : Number(val).toFixed(2);
  };
  const inStatus = (val) => document.getElementById('order_form_status').value = val;
  const saveBtn = () => document.querySelector('#orderModalBackdrop .footer .btn:not(.light)');
  const today = () => new Date().toISOString().slice(0,10);
  // Also ensure that after server submit (page reload) modal is closed
  // ----- wire data-* buttons to modal functions (robust) -----
function parseAndCallOpen(btn, fn) {
  const ds = btn.dataset;
  // dataset properties: orderDate, customer, product, quantity, price, status, id
  const id = ds.id;
  const order_date = ds.orderDate || '';
  const customer = ds.customer || '';
  const product = ds.product || '';
  const quantity = ds.quantity || '1';
  const price = ds.price || '0.00';
  const status = ds.status || '';
  try {
    fn(id, order_date, customer, product, Number(quantity), price, status);
  } catch (err) {
    console.error('Failed to call modal function', err, { id, order_date, customer, product, quantity, price, status });
  }
}
// Tab wiring with alias fallback
(function(){
  // alias map: map sample data-page values to actual panel ids in your page
  const panelAlias = {
    'sales-dashboard': 'orders',        // sample -> your orders panel id
    'sales-analysis': 'sales-analysis', // keep if you have that id (or map to your return panel id)
    'sales-export': 'sales-export'      // likely already matches
  };

  function activatePanelByName(name) {
    // if direct match exists use it, otherwise try alias map
    let id = name;
    if (!document.getElementById(id) && panelAlias[name]) id = panelAlias[name];

    const panel = document.getElementById(id);
    if (!panel) {
      console.warn('activatePanel: no panel found for', name, '->', id);
      return;
    }
    document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
    panel.classList.add('active');
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.toggle('active', b.dataset.page === name));
  }

  // wire buttons
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      const page = btn.dataset.page;
      activatePanelByName(page);
    });
  });

  // optional: activate initial panel from whichever has .active
  const initial = document.querySelector('.tab-btn.active')?.dataset.page || document.querySelector('.panel.active')?.id;
  if (initial) activatePanelByName(initial);
})();


// ---------- Return modal wiring ----------
(function(){
  const returnModalBackdrop = document.getElementById('returnModalBackdrop');
  const returnForm = document.getElementById('returnForm');

  function openReturnModal(id, origQty, price){
    document.getElementById('returnModalTitle').textContent = 'Return Order #' + id;
    document.getElementById('return_order_id').value = String(id);
    document.getElementById('return_quantity').value = Math.min(1, origQty) ? '1' : '1';
    document.getElementById('return_refund_amount').value = (Number(price) || 0).toFixed(2);
    document.getElementById('return_reason').value = '';
    returnModalBackdrop.style.display = 'flex';
  }
  window.closeReturnModal = function(){ returnModalBackdrop.style.display = 'none'; };

  // attach handlers to dynamic buttons
  document.querySelectorAll('.btnreturn').forEach(btn => {
    btn.addEventListener('click', () => {
      const ds = btn.dataset;
      const id = ds.id || ds['id'] || btn.getAttribute('data-id');
      const qty = Number(ds.quantity || btn.getAttribute('data-quantity') || 1);
      const price = Number(ds.price || btn.getAttribute('data-price') || 0);
      openReturnModal(id, qty, price);
    });
  });

  // prevent accidental GET of the page on view or other modes
  if (returnForm) {
    returnForm.addEventListener('submit', (e) => {
      // allow normal post to server. (action=return is supplied)
      // you could add client-side validation here:
      const q = Number(document.getElementById('return_quantity').value);
      if (!q || q < 1) {
        e.preventDefault();
        alert('Return quantity must be at least 1');
      }
      // otherwise form submits to order.php (server handles it)
    });
  }

  // close when clicking backdrop
  if (returnModalBackdrop) {
    returnModalBackdrop.addEventListener('click', (ev) => { if (ev.target === returnModalBackdrop) closeReturnModal(); });
  }
})();

// ---------- Export utilities (works on the orders table DOM) ----------
(function(){
  function gatherVisibleOrders() {
    const rows = Array.from(document.querySelectorAll('#orderTable tr')).filter(r => r.style.display !== 'none');
    const out = rows.map(r => {
      const tds = r.querySelectorAll('td');
      if (!tds.length) return null;
      return {
        id: tds[0].textContent.trim(),
        customer: tds[1].textContent.trim(),
        product: tds[2].textContent.trim(),
        quantity: tds[3].textContent.trim(),
        price: tds[4].textContent.trim(),
        status: tds[5].textContent.trim()
      };
    }).filter(Boolean);
    return out;
  }

  function quoteCSV(s) {
    const str = String(s ?? '');
    return /[",\n]/.test(str) ? `"${str.replace(/"/g, '""')}"` : str;
  }

  function toCSV(rows) {
    const header = ['Order ID','Customer','Product','Quantity','Price','Status'];
    const lines = [header.join(',')];
    rows.forEach(r => lines.push([quoteCSV(r.id), quoteCSV(r.customer), quoteCSV(r.product), r.quantity, r.price, quoteCSV(r.status)].join(',')));
    return lines.join('\n');
  }

  function download(name, blob) {
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = name;
    a.click();
    setTimeout(()=>URL.revokeObjectURL(a.href), 1000);
  }

  // wire export buttons (IDs in your export panel: expCsv, expXlsx, expPdf)
  const btnCsv = document.getElementById('expCsv');
  const btnXlsx = document.getElementById('expXlsx');
  const btnPdf = document.getElementById('expPdf');

  if (btnCsv) btnCsv.addEventListener('click', () => {
    const rows = gatherVisibleOrders();
    const csv = toCSV(rows);
    download('orders_export.csv', new Blob([csv], { type: 'text/csv' }));
  });

  if (btnXlsx) btnXlsx.addEventListener('click', () => {
    const rows = gatherVisibleOrders().map(r => ({ 'Order ID': r.id, 'Customer': r.customer, 'Product': r.product, 'Quantity': +r.quantity, 'Price': r.price, 'Status': r.status }));
    const ws = XLSX.utils.json_to_sheet(rows);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Orders');
    XLSX.writeFile(wb, 'orders_export.xlsx');
  });

  if (btnPdf) btnPdf.addEventListener('click', () => {
    const rows = gatherVisibleOrders();
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    doc.setFontSize(12);
    doc.text('Orders Report', 14, 16);
    const body = rows.map(r => [r.id, r.customer, r.product, r.quantity, r.price, r.status]);
    doc.autoTable({ startY: 22, head: [['ID','Customer','Product','Qty','Price','Status']], body });
    doc.save('orders_export.pdf');
  });
})();


document.querySelectorAll('.btnupdate').forEach(btn => {
  btn.addEventListener('click', (e) => {
    parseAndCallOpen(btn, window.openOrderEdit);
  });
});

document.querySelectorAll('.btnview').forEach(btn => {
  btn.addEventListener('click', (e) => {
    parseAndCallOpen(btn, window.openOrderView);
  });
});


  // Open Add
  window.openOrderAdd = function(){
    document.getElementById('orderModalTitle').textContent = 'Add Order';
    inAction('add');
    inId('0');
    inDate(today());
    inCustomer('');
    inProduct('');
    inQuantity('1');
    inPrice('0.00');
    inStatus('Pending');
    // ensure form enabled
    enableFormFields();
    if (saveBtn()) saveBtn().style.display = '';
    orderModalBackdrop.style.display = 'flex';
  };

  // Open Edit - called by onclick attributes in PHP rows
  // signature now includes price
  window.openOrderEdit = function(id, order_date, customer, product, quantity, price, status){
    console.log('openOrderEdit called with', id, order_date, customer, product, quantity, price, status);
    document.getElementById('orderModalTitle').textContent = 'Edit Order #' + id;
    inAction('edit');
    inId(String(id));
    inDate(order_date || today());
    inCustomer(customer || '');
    inProduct(product || '');
    inQuantity(String(quantity ?? 1));
    inPrice(price ?? '0.00');
    inStatus(status || 'Pending');
    enableFormFields();
    if (saveBtn()) saveBtn().style.display = '';
    orderModalBackdrop.style.display = 'flex';
  };

  // Open View (read-only) — accepts price param too
  window.openOrderView = function(id, order_date, customer, product, quantity, price, status){
    console.log('openOrderView called with', id, order_date, customer, product, quantity, price, status);
    document.getElementById('orderModalTitle').textContent = 'View Order #' + id;
    inAction('view');
    inId(String(id));
    inDate(order_date || today());
    inCustomer(customer || '');
    inProduct(product || '');
    inQuantity(String(quantity ?? 1));
    inPrice(price ?? '0.00');
    inStatus(status || '');
    // disable inputs & hide Save
    disableFormFields();
    const s = saveBtn();
    if (s) s.style.display = 'none';
    orderModalBackdrop.style.display = 'flex';
  };

  function disableFormFields(){
    ['order_form_date','order_form_customer','order_form_product','order_form_quantity','order_form_price','order_form_status'].forEach(n=>{
      const el = document.getElementById(n);
      if (el) el.setAttribute('disabled','disabled');
    });
  }
  function enableFormFields(){
    ['order_form_date','order_form_customer','order_form_product','order_form_quantity','order_form_price','order_form_status'].forEach(n=>{
      const el = document.getElementById(n);
      if (el) el.removeAttribute('disabled');
    });
  }

  // Close modal
  window.closeOrderModal = function(){
    enableFormFields();
    const s = saveBtn();
    if (s) s.style.display = '';
    orderModalBackdrop.style.display = 'none';
  };

  // Prevent submission when in view mode; otherwise allow normal POST to server
  if (orderForm) {
    orderForm.addEventListener('submit', function(e){
      const act = document.getElementById('order_form_action').value;
      if (act === 'view') {
        e.preventDefault();
        return false;
      }
      // otherwise let the form submit (POST to same page)
    });
  }

  // Close when clicking backdrop
  if (orderModalBackdrop) {
    orderModalBackdrop.addEventListener('click', (ev) => {
      if (ev.target === orderModalBackdrop) closeOrderModal();
    });
  }

  // Close on ESC
  document.addEventListener('keydown', (ev) => {
    if (ev.key === 'Escape' && orderModalBackdrop.style.display === 'flex') {
      closeOrderModal();
    }
  });

  // GLOBAL SEARCH: filter server-rendered rows (search across all visible columns)
  const globalSearch = document.getElementById('globalSearch');
  const tbody = document.getElementById('orderTable');

  function filterTable() {
    const q = (globalSearch?.value || '').trim().toLowerCase();
    if (!tbody) return;
    const rows = Array.from(tbody.querySelectorAll('tr'));
    if (!q) {
      rows.forEach(r => r.style.display = '');
      return;
    }
    rows.forEach(r => {
      const tds = Array.from(r.querySelectorAll('td'));
      if (!tds.length) { r.style.display = ''; return; }
      // combine all cell text for robust matching (includes price if present)
      const rowText = tds.map(td => (td.textContent || '').toLowerCase()).join(' ');
      const match = rowText.includes(q);
      r.style.display = match ? '' : 'none';
    });
  }

  if (globalSearch) {
    globalSearch.addEventListener('input', filterTable);
  }

  // If the page has "Add" UI element with id btnAdd, wire it
  const btnAdd = document.getElementById('btnAdd');
  if (btnAdd) btnAdd.addEventListener('click', openOrderAdd);

  // Also ensure that after server submit (page reload) modal is closed
  closeOrderModal();
});
</script>
</body>

</html>