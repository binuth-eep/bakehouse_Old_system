<?php
// ---------- DATABASE CONNECTION ----------
$host = "localhost";
$user = "root";
$pass = "";
$db   = "bakehouse";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("DB Connection failed: " . $conn->connect_error);
}

// ---------- HANDLE POST ACTIONS (INSERT / EDIT / DELETE / RETURN) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ---------- EDIT SALE ----------
    if (isset($_POST['edit_sale'])) {
        $id       = (int)$_POST['id'];
        $date     = $_POST['date'];
        $customer = $_POST['customer'];
        $total    = (float)$_POST['total'];
        $cost     = (float)$_POST['cost'];
        $status   = $_POST['status'];

        $stmt = $conn->prepare("UPDATE sales SET date=?, customer=?, total=?, cost=?, status=? WHERE id=?");
        $stmt->bind_param("ssddsi", $date, $customer, $total, $cost, $status, $id);
        $stmt->execute();
        $stmt->close();

        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // ---------- DELETE SALE ----------
    if (isset($_POST['delete_sale'])) {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("DELETE FROM sales WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // ---------- INSERT SALE ----------
    if (isset($_POST['add_sale'])) {
        $date     = $_POST['date'];
        $customer = $_POST['customer'];
        $total    = (float)$_POST['total'];
        $cost     = (float)$_POST['cost'];
        $status   = $_POST['status'];

        $stmt = $conn->prepare("INSERT INTO sales (date, customer, total, cost, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdds", $date, $customer, $total, $cost, $status);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            echo "<script>
                    alert('✅ Sale record added successfully!');
                    window.location.href = window.location.href.split('?')[0];
                  </script>";
            exit;
        } else {
            $insertError = $conn->error;
        }
    }

    // ---------- ADD RETURN ----------
    if (isset($_POST['add_return'])) {
        $sale_id = (int)$_POST['sale_id'];
        $date    = $_POST['date'];
        $reason  = $_POST['reason'];
        $refund  = (float)$_POST['refund'];

        $stmt = $conn->prepare("INSERT INTO returns (sale_id, date, reason, refund) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("issd", $sale_id, $date, $reason, $refund);
        $stmt->execute();
        $stmt->close();

        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// ---------- BUILD FILTER ----------
$where = "1=1";
if (!empty($_GET['from']) && !empty($_GET['to'])) {
    $from = $_GET['from'];
    $to   = $_GET['to'];
    $where .= " AND date BETWEEN '" . $conn->real_escape_string($from) . "' AND '" . $conn->real_escape_string($to) . "'";
}
if (!empty($_GET['status'])) {
    $status = $conn->real_escape_string($_GET['status']);
    $where .= " AND status = '" . $status . "'";
}
if (!empty($_GET['customer'])) {
    $customer = $conn->real_escape_string($_GET['customer']);
    $where .= " AND customer LIKE '%" . $customer . "%'";
}

// ---------- FETCH SALES ----------
$sql = "SELECT id, date, customer, total, cost, status FROM sales WHERE $where ORDER BY id DESC";
$result = $conn->query($sql);
$sales = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $sales[] = $row;
    }
}

// ---------- FETCH RETURNS ----------
$sql = "SELECT r.id, r.sale_id, r.date, r.reason, r.refund, s.customer 
        FROM returns r 
        JOIN sales s ON r.sale_id = s.id
        ORDER BY r.id DESC";
$result = $conn->query($sql);
$returns = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $returns[] = $row;
    }
}

// ---------- DASH STATS ----------
$sql = "SELECT COUNT(*) AS total_orders FROM sales";
$res = $conn->query($sql);
$totalOrders = ($res) ? (int)$res->fetch_assoc()['total_orders'] : 0;

$sql = "SELECT COUNT(DISTINCT customer) AS total_cus FROM sales";
$res = $conn->query($sql);
$totalCus = ($res) ? (int)$res->fetch_assoc()['total_cus'] : 0;

$sql = "SELECT IFNULL(SUM(total),0) AS today_revenue FROM sales WHERE date = CURDATE()";
$res = $conn->query($sql);
$todayRevenue = ($res) ? (float)$res->fetch_assoc()['today_revenue'] : 0.0;

$sql = "SELECT COUNT(*) AS today_sales FROM sales WHERE date = CURDATE()";
$res = $conn->query($sql);
$todaySales = ($res) ? (int)$res->fetch_assoc()['today_sales'] : 0;

$sql = "SELECT IFNULL(SUM(total - cost),0) AS profit_loss FROM sales";
$res = $conn->query($sql);
$profitLoss = ($res) ? (float)$res->fetch_assoc()['profit_loss'] : 0.0;

$sql = "SELECT IFNULL(SUM(refund),0) AS total_returns FROM returns";
$res = $conn->query($sql);
$totalReturns = ($res) ? (float)$res->fetch_assoc()['total_returns'] : 0.0;

?>
  <style>
    body {font-family: Arial; margin:0; padding:20px;}
    h1 {text-align:center;}
   
   
    button {padding:6px 12px; margin:2px; cursor:pointer;}
    .addform {display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5);}
    .addform-content {background:#fff; margin:5% auto; padding:20px; border-radius:10px; width:400px; position:relative;}
    .close {position:absolute; right:15px; top:10px; cursor:pointer; font-size:22px;}
    .hidden {display:none;}
    #filterForm {margin:15px 0; padding:10px; background:#f4f4f4; border-radius:8px;}
  </style>
<?php include '../main.html'; ?>

<aside class="sidebarr">
  <nav>
    <div class="add-btn">
      <div><button class="salesbtn stb" id="salesbtn" onclick="mainPopup()">ADD</button></div>
      <div><button class="salesbtn" onclick="togglePopup()">Filter</button></div>
    </div>
    <section class="layout-side">
      <div class="card">
        <h3>Total Today Sales</h3>
        <p><?= $todaySales ?></p>
      </div>
      <div class="card">
        <h3>Total Orders</h3>
        <p><?= $totalOrders ?></p>
      </div>
      <div class="card">
        <h3>Total Customers</h3>
        <p><?= $totalCus ?></p>
      </div>
      <div class="card">
        <h3>Profit / Loss</h3>
        <p><?= ($profitLoss >= 0 ? "+$" : "-$") . number_format(abs($profitLoss), 2) ?></p>
      </div>
      <div class="card">
        <h3>Total Returns</h3>
        <p>$<?= number_format($totalReturns, 2) ?></p>
      </div>
    </section>
  </nav>
</aside>

<main class="free-area">
  
        <div class="toolbar" id="popup" style="display:none;">
          <div class="filter-bar">
            <!-- FILTER FORM -->
            <form method="GET">
              From: <input type="date" name="from" value="<?= htmlspecialchars($_GET['from'] ?? '') ?>">
              To: <input type="date" name="to" value="<?= htmlspecialchars($_GET['to'] ?? '') ?>">
              Status:
              <select name="status">
                <option value="">All</option>
                <option <?= (($_GET['status'] ?? '') === "Completed" ? "selected" : "") ?>>Completed</option>
                <option <?= (($_GET['status'] ?? '') === "Pending" ? "selected" : "") ?>>Pending</option>
                <option <?= (($_GET['status'] ?? '') === "Cancelled" ? "selected" : "") ?>>Cancelled</option>
              </select>
              Customer: <input type="text" name="customer" value="<?= htmlspecialchars($_GET['customer'] ?? '') ?>">
              <button type="submit">Filter</button>
            </form>
          </div>
        </div>
  <!-- Add Sale Form -->
  <div id="addForm" class="addform">
    <div class="addform-content">
      <span class="close" onclick="closeForm()">&times;</span>
      <h2>Add New Sale</h2>
      <form method="POST" action="">
        <label>Date</label>
        <input type="date" name="date" required>
        <label>Customer</label>
        <input type="text" name="customer" required>
        <label>Total</label>
        <input type="number" step="0.01" name="total" required>
        <label>Cost</label>
        <input type="number" step="0.01" name="cost" required>
        <label>Status</label>
        <select name="status" required>
          <option value="Pending">Pending</option>
          <option value="Completed">Completed</option>
          <option value="Cancelled">Cancelled</option>
        </select>
        <button type="submit" name="add_sale">Save</button>
      </form>
    </div>
  </div>

  <!-- Sales Table -->
  <section class="panel active">
    <div class="table-wrap" id="main">
      <table id="salesTable">
        <thead>
          <tr>
            <th >ID</th><th>Date</th><th>Customer</th><th>Total</th><th>Cost</th><th>Profit/Loss</th><th>Status</th><th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($sales)): ?>
            <tr><td colspan="8">No records found</td></tr>
          <?php else: ?>
            <?php foreach ($sales as $s): ?>
              <?php $pl = $s['total'] - $s['cost']; ?>
              <tr data-id="<?= $s['id'] ?>" data-date="<?= $s['date'] ?>" data-customer="<?= $s['customer'] ?>" data-total="<?= $s['total'] ?>" data-cost="<?= $s['cost'] ?>" data-status="<?= $s['status'] ?>">
                <td><?= $s['id'] ?></td>
                <td><?= $s['date'] ?></td>
                <td><?= $s['customer'] ?></td>
                <td>$<?= number_format($s['total'],2) ?></td>
                <td>$<?= number_format($s['cost'],2) ?></td>
                <td><?= ($pl >= 0 ? "+$" : "-$") . number_format(abs($pl),2) ?></td>
                <td><?= $s['status'] ?></td>
                <td>
                  <button class="editBtn">✏️</button>
                  <button class="delBtn">🗑️</button>
                  <button class="returnBtn">↩️</button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <!-- Returns Table -->
  <section id = "main">
    <h2>Returns</h2>
        <div class="table-wrap" id="main">
      <table id="salesTable">
      <thead  style=" z-index: 1";>
        <tr><th>ID</th><th>Sale ID</th><th>Customer</th><th>Date</th><th>Reason</th><th>Refund</th></tr>
      </thead>
      <tbody>
        <?php if (empty($returns)): ?>
          <tr><td colspan="6">No returns yet</td></tr>
        <?php else: ?>
          <?php foreach ($returns as $r): ?>
            <tr>
              <td><?= $r['id'] ?></td>
              <td><?= $r['sale_id'] ?></td>
              <td><?= $r['customer'] ?></td>
              <td><?= $r['date'] ?></td>
              <td><?= $r['reason'] ?></td>
              <td>$<?= number_format($r['refund'],2) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table></div>
  </section>

  <!-- Edit Modal -->
  <div class="addform" id="editModal">
    <div class="addform-content">
      <span class="close" onclick="closeModal('editModal')">&times;</span>
      <h2>Edit Sale</h2>
      <form method="post">
        <input type="hidden" name="id" id="edit_id">
        <label>Date</label>
        <input type="date" name="date" id="edit_date" required>
        <label>Customer</label>
        <input type="text" name="customer" id="edit_customer" required>
        <label>Total</label>
        <input type="number" step="0.01" name="total" id="edit_total" required>
        <label>Cost</label>
        <input type="number" step="0.01" name="cost" id="edit_cost" required>
        <label>Status</label>
        <select name="status" id="edit_status" required>
          <option value="Pending">Pending</option>
          <option value="Completed">Completed</option>
          <option value="Cancelled">Cancelled</option>
        </select>
        <button type="submit" name="edit_sale">Update</button>
      </form>
    </div>
  </div>

  <!-- Delete Modal -->
  <div class="addform" id="deleteModal">
    <div class="addform-content">
      <span class="close" onclick="closeModal('deleteModal')">&times;</span>
      <h2>Delete Sale</h2>
      <form method="post">
        <input type="hidden" name="id" id="delete_id">
        <p>Are you sure?</p>
        <button type="submit" name="delete_sale">Delete</button>
      </form>
    </div>
  </div>

  <!-- Return Modal -->
  <div class="addform" id="returnModal">
    <div class="addform-content">
      <span class="close" onclick="closeModal('returnModal')">&times;</span>
      <h2>Add Return</h2>
      <form method="post">
        <input type="hidden" name="sale_id" id="return_sale_id">
        <label>Date</label>
        <input type="date" name="date" required>
        <label>Reason</label>
        <textarea name="reason" required></textarea>
        <label>Refund</label>
        <input type="number" step="0.01" name="refund" required>
        <button type="submit" name="add_return">Save Return</button>
      </form>
    </div>
  </div>
</main>

<script>
      // Toggle filter popup
    function togglePopup() {
      let popup = document.getElementById("popup");
      popup.style.display = (popup.style.display === "none") ? "flex" : "none";
    }
function togglePopup(){let p=document.getElementById("popup");p.style.display=(p.style.display==="none")?"flex":"none";}
function mainPopup(){let f=document.getElementById("addForm");let m=document.getElementById("main");let b=document.getElementById("salesbtn");if(!f.style.display||f.style.display==="none"){f.style.display="flex";m.style.display="none";b.textContent="TABLE";}else{f.style.display="none";m.style.display="block";b.textContent="ADD";}}
function closeForm(){document.getElementById("addForm").style.display="none";document.getElementById("main").style.display="block";document.getElementById("salesbtn").textContent="ADD";}
function openModal(id){let modal=document.getElementById(id);if(modal) modal.style.display="flex";}
function closeModal(id){let modal=document.getElementById(id);if(modal) modal.style.display="none";document.getElementById("main").style.display="flex";}

// Row actions
document.querySelectorAll("#salesTable tbody tr").forEach(row=>{
  row.querySelector(".editBtn").addEventListener("click",()=>{
    document.getElementById('edit_id').value=row.dataset.id;
    document.getElementById('edit_date').value=row.dataset.date;
    document.getElementById('edit_customer').value=row.dataset.customer;
    document.getElementById('edit_total').value=row.dataset.total;
    document.getElementById('edit_cost').value=row.dataset.cost;
    document.getElementById('edit_status').value=row.dataset.status;
    document.getElementById("main").style.display="none";
    openModal('editModal');
  });
  row.querySelector(".delBtn").addEventListener("click",()=>{
    document.getElementById('delete_id').value=row.dataset.id;
    document.getElementById("main").style.display="none";
    openModal('deleteModal');
  });
  row.querySelector(".returnBtn").addEventListener("click",()=>{
    document.getElementById('return_sale_id').value=row.dataset.id;
    document.getElementById("main").style.display="none";
    openModal('returnModal');
  });
});
</script>
