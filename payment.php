<?php
session_start();
include "db.php";

/* 
   UPDATE PAYMENT STATUS 
 */
if(isset($_GET['action']) && isset($_GET['id'])){

    $id = intval($_GET['id']);
    $action = $_GET['action'];

    if(in_array($action, ['success','failed','pending'])){

        $stmt = $conn->prepare("UPDATE payments SET status=? WHERE id=?");
        $stmt->bind_param("si", $action, $id);
        $stmt->execute();

    }
}

/* 
   PAYMENT COUNTS
 */
$successful = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COUNT(*) AS total FROM payments WHERE status='success'"
))['total'];

$failed = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COUNT(*) AS total FROM payments WHERE status='failed'"
))['total'];

$pending = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT COUNT(*) AS total FROM payments WHERE status='pending'"
))['total'];

/* 
   REVENUE REPORTS
 */
$daily = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT SUM(amount) AS total 
FROM payments 
WHERE status='success' 
AND DATE(created_at)=CURDATE()"
))['total'] ?? 0;

$monthly = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT SUM(amount) AS total 
FROM payments 
WHERE status='success' 
AND MONTH(created_at)=MONTH(CURDATE())
AND YEAR(created_at)=YEAR(CURDATE())"
))['total'] ?? 0;

$yearly = mysqli_fetch_assoc(mysqli_query($conn,
"SELECT SUM(amount) AS total 
FROM payments 
WHERE status='success' 
AND YEAR(created_at)=YEAR(CURDATE())"
))['total'] ?? 0;

/* 
   PAYMENT METHODS
 */
$methods = mysqli_query($conn,
"SELECT payment_method, COUNT(*) AS total, SUM(amount) AS revenue
FROM payments
WHERE status='success'
GROUP BY payment_method"
);

/* 
   REVENUE BY COURSE
 */
   $courseRevenue = mysqli_query($conn,
   "SELECT c.title, SUM(p.amount) AS total
   FROM payments p
   JOIN courses c ON p.course_id = c.id
   WHERE p.status = 'success'
   GROUP BY p.course_id, c.title
   ORDER BY total DESC"
   );

/* 
   PAYMENT MONITORING
 */
$payments = mysqli_query($conn,
"SELECT * FROM payments ORDER BY created_at DESC LIMIT 20"
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Payment Management</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
body{
    font-family: Arial;
    background:#f4f6f9;
    margin:0;
    padding:20px;
}

.container{
    max-width:1200px;
    margin:auto;
}

.cards{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:15px;
}

.card{
    background:white;
    padding:20px;
    border-radius:10px;
    box-shadow:0 2px 8px rgba(0,0,0,0.1);
}

table{
    width:100%;
    border-collapse:collapse;
    margin-top:20px;
    background:white;
}

th,td{
    padding:12px;
    border-bottom:1px solid #ddd;
    text-align:left;
}

th{
    background:#2c3e50;
    color:white;
}

.badge{
    padding:5px 10px;
    border-radius:5px;
    font-size:12px;
    color:white;
    text-transform:uppercase;
}

.success{background:green;}
.failed{background:red;}
.pending{background:orange;}

a{
    text-decoration:none;
    font-weight:bold;
}
</style>
</head>

<body>

<div class="container">

<h2><i class="fas fa-credit-card"></i> Payment Management</h2>

<!--  STATS  -->
<div class="cards">
    <div class="card">
        <h3>Successful</h3>
        <h2><?= $successful ?></h2>
    </div>

    <div class="card">
        <h3>Failed</h3>
        <h2><?= $failed ?></h2>
    </div>

    <div class="card">
        <h3>Pending</h3>
        <h2><?= $pending ?></h2>
    </div>
</div>

<!--  REVENUE  -->
<h3 style="margin-top:30px;">Revenue Reports</h3>

<div class="cards">
    <div class="card">
        <h3>Daily</h3>
        <h2>KES <?= number_format($daily,2) ?></h2>
    </div>

    <div class="card">
        <h3>Monthly</h3>
        <h2>KES <?= number_format($monthly,2) ?></h2>
    </div>

    <div class="card">
        <h3>Yearly</h3>
        <h2>KES <?= number_format($yearly,2) ?></h2>
    </div>
</div>

<!--  PAYMENT METHODS  -->
<h3 style="margin-top:30px;">Payment Methods</h3>

<table>
<tr>
    <th>Method</th>
    <th>Transactions</th>
    <th>Revenue</th>
</tr>

<?php while($row = mysqli_fetch_assoc($methods)): ?>
<tr>
    <td><?= strtoupper($row['payment_method']) ?></td>
    <td><?= $row['total'] ?></td>
    <td>KES <?= number_format($row['revenue'],2) ?></td>
</tr>
<?php endwhile; ?>
</table>

<!--  COURSE REVENUE  -->
<h3 style="margin-top:30px;">Revenue by Course</h3>

<table>
<tr>
    <th>Course</th>
    <th>Total Revenue</th>
</tr>

<?php while($row = mysqli_fetch_assoc($courseRevenue)): ?>
<tr>
    <td><?= htmlspecialchars($row['title']) ?></td>
    <td>KES <?= number_format($row['total'],2) ?></td>
</tr>
<?php endwhile; ?>
</table>

<!--  PAYMENT MONITOR  -->
<h3 style="margin-top:30px;">Payment Monitoring</h3>

<table>
<tr>
    <th>ID</th>
    <th>Course</th>
    <th>Amount</th>
    <th>Method</th>
    <th>Status</th>
    <th>Date</th>
    <th>Action</th>
</tr>

<?php while($row = mysqli_fetch_assoc($payments)): ?>
<tr>
    <td><?= $row['id'] ?></td>
    <td><?= htmlspecialchars($row['course_id']) ?></td>
    <td>KES <?= number_format($row['amount'],2) ?></td>
    <td><?= strtoupper($row['payment_method']) ?></td>

    <td>
        <span class="badge <?= $row['status'] ?>">
            <?= strtoupper($row['status']) ?>
        </span>
    </td>

    <td><?= $row['created_at'] ?></td>

    <td>

        <!-- APPROVE -->
        <a href="?action=success&id=<?= $row['id'] ?>" 
           onclick="return confirm('Approve this payment?')" 
           style="color:green;">
           Approve
        </a>

        |

        <!-- REJECT -->
        <a href="?action=failed&id=<?= $row['id'] ?>" 
           onclick="return confirm('Reject this payment?')" 
           style="color:red;">
           Reject
        </a>

        |

        <!-- UPDATE (back to pending) -->
        <a href="?action=pending&id=<?= $row['id'] ?>" 
           onclick="return confirm('Move to pending?')" 
           style="color:orange;">
           Update
        </a>

    </td>
</tr>
<?php endwhile; ?>
</table>

</div>

 <?php

/* 
   STUDENT PAYMENT MONITORING
 */
   $payments = mysqli_query($conn,
"SELECT 
    p.id,
    s.full_name AS student_name,
    u.email,
    c.title AS course,
    p.amount,
    p.payment_method,
    p.status,
    p.created_at
FROM payments p
LEFT JOIN users u ON p.user_id = u.id
LEFT JOIN students s ON s.user_id = u.id
LEFT JOIN courses c ON p.course_id = c.id
ORDER BY p.created_at DESC"
);
?>

<h3 style="margin-top:30px;">Student Payment Monitoring</h3>

<table>
<tr>
    <th>ID</th>
    <th>Student Name</th>
    <th>Email</th>
    <th>Course</th>
    <th>Amount</th>
    <th>Method</th>
    <th>Status</th>
    <th>Date</th>
    <th>Action</th>
</tr>

<?php while($row = mysqli_fetch_assoc($payments)): ?>
<tr>
    <td><?= $row['id'] ?></td>

    <td><?= htmlspecialchars($row['fullname'] ?? 'Unknown') ?></td>

    <td><?= htmlspecialchars($row['email'] ?? '-') ?></td>

    <td><?= htmlspecialchars($row['course']) ?></td>

    <td>KES <?= number_format($row['amount'],2) ?></td>

    <td><?= strtoupper($row['payment_method']) ?></td>

    <td>
        <span class="badge <?= $row['status'] ?>">
            <?= strtoupper($row['status']) ?>
        </span>
    </td>

    <td><?= $row['created_at'] ?></td>

    <td>

        <!-- APPROVE -->
        <a href="?action=success&id=<?= $row['id'] ?>" 
           onclick="return confirm('Approve this payment?')" 
           style="color:green;">
           Approve
        </a>

        |

        <!-- REJECT -->
        <a href="?action=failed&id=<?= $row['id'] ?>" 
           onclick="return confirm('Reject this payment?')" 
           style="color:red;">
           Reject
        </a>

        |

        <!-- PENDING -->
        <a href="?action=pending&id=<?= $row['id'] ?>" 
           onclick="return confirm('Move to pending?')" 
           style="color:orange;">
           Update
        </a>

    </td>
</tr>
<?php endwhile; ?>
</table>
</body>
</html>