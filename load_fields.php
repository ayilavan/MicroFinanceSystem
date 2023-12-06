<?php include 'db_connect.php'; ?>

<?php
extract($_POST);

if (isset($id)) {
    $stmt = $conn->prepare("SELECT * FROM payments WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $paymentData = $result->fetch_assoc();
        foreach ($paymentData as $k => $val) {
            $$k = $val;
        }
    }
    
    $stmt->close();
}

$stmt = $conn->prepare("SELECT l.*, CONCAT(b.lastname, ', ', b.firstname, ' ', b.middlename) AS name, b.contact_no, b.address FROM loan_list l INNER JOIN borrowers b ON b.id = l.borrower_id WHERE l.id = ?");
$stmt->bind_param("i", $loan_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $loanData = $result->fetch_assoc();
    foreach ($loanData as $k => $v) {
        $meta[$k] = $v;
    }

    $type_arr_stmt = $conn->prepare("SELECT * FROM loan_types WHERE id = ?");
    $type_arr_stmt->bind_param("i", $meta['loan_type_id']);
    $type_arr_stmt->execute();
    $typeResult = $type_arr_stmt->get_result();
    $type_arrData = $typeResult->fetch_array();
    $type_arr_stmt->close();

    $plan_arr_stmt = $conn->prepare("SELECT *, CONCAT(months,' month/s [ ', interest_percentage, '%, ', penalty_rate, ' ]') AS plan FROM loan_plan WHERE id = ?");
    $plan_arr_stmt->bind_param("i", $meta['plan_id']);
    $plan_arr_stmt->execute();
    $planResult = $plan_arr_stmt->get_result();
    $plan_arrData = $planResult->fetch_array();
    $plan_arr_stmt->close();

    $monthly = ($meta['amount'] + ($meta['amount'] * ($plan_arrData['interest_percentage'] / 100))) / $plan_arrData['months'];
    $penalty = $monthly * ($plan_arrData['penalty_rate'] / 100);

    $payments_stmt = $conn->prepare("SELECT * FROM payments WHERE loan_id = ?");
    $payments_stmt->bind_param("i", $loan_id);
    $payments_stmt->execute();
    $payments = $payments_stmt->get_result();
    $paid = $payments->num_rows;
    $payments_stmt->close();

    $offset = $paid > 0 ? " OFFSET $paid " : "";
    $next_stmt = $conn->prepare("SELECT * FROM loan_schedules WHERE loan_id = ? ORDER BY DATE(date_due) ASC LIMIT 1 $offset");
    $next_stmt->bind_param("i", $loan_id);
    $next_stmt->execute();
    $next = $next_stmt->get_result()->fetch_assoc()['date_due'];
    $next_stmt->close();

    $sum_paid = 0;
    while ($p = $payments->fetch_assoc()) {
        $sum_paid += ($p['amount'] - $p['penalty_amount']);
    }
}
?>

<div class="col-lg-12">
    <hr>
    <div class="row">
        <div class="col-md-5">
            <div class="form-group">
                <label for="">Payee</label>
                <input name="payee" class="form-control" required="" value="<?php echo isset($payee) ? $payee : (isset($meta['name']) ? $meta['name'] : ''); ?>">
            </div>
        </div>
    </div>
    <hr>
    <div class="row">
        <div class="col-md-5">
            <p><small>Monthly amount: <b><?php echo number_format($monthly, 2); ?></b></small></p>
            <p><small>Penalty: <b><?php echo $add = (date('Ymd', strtotime($next)) < date("Ymd")) ?  $penalty : 0; ?></b></small></p>
            <p><small>Payable Amount: <b><?php echo number_format($monthly + $add, 2); ?></b></small></p>
        </div>
        <div class="col-md-5">
            <div class="form-group">
                <label for="">Amount</label>
                <input type="number" name="amount" step="any" min="" class="form-control text-right" required="" value="<?php echo isset($amount) ? $amount : ''; ?>">
                <input type="hidden" name="penalty_amount" value="<?php echo $add; ?>">
                <input type="hidden" name="loan_id" value="<?php echo $loan_id; ?>">
                <input type="hidden" name="overdue" value="<?php echo $add > 0 ? 1 : 0; ?>">
            </div>
        </div>
    </div>
</div>
