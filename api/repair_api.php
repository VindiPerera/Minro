<?php
require_once __DIR__ . '/../includes/functions.php';
requireAuth();

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    $db = getDB();

    switch ($action) {

        // ─── Update job status ──────────────────────────────────────────────
        case 'update_status':
            requireAuth('admin','cashier','technician');
            $jobId   = (int)($_POST['job_id'] ?? 0);
            $status  = $_POST['status'] ?? '';
            $notes   = trim($_POST['notes'] ?? '');
            $allowed = ['pending','in_progress','waiting_parts','completed','cancelled'];
            if (!$jobId || !in_array($status, $allowed)) throw new Exception('Invalid request');

            $stmt = $db->prepare("UPDATE repair_jobs SET status=?, notes=CONCAT(COALESCE(notes,''), ?) WHERE id=?");
            $stmt->execute([$status, $notes ? "\n[$status] $notes" : '', $jobId]);
            echo json_encode(['success'=>true, 'message'=>'Status updated']);
            break;

        // ─── Assign technician ──────────────────────────────────────────────
        case 'assign_tech':
            requireAuth('admin','cashier');
            $jobId  = (int)($_POST['job_id'] ?? 0);
            $techId = (int)($_POST['tech_id'] ?? 0);
            if (!$jobId) throw new Exception('Invalid job');
            $db->prepare("UPDATE repair_jobs SET assigned_to=? WHERE id=?")->execute([$techId ?: null, $jobId]);
            echo json_encode(['success'=>true]);
            break;

        // ─── Get job parts ──────────────────────────────────────────────────
        case 'get_parts':
            $jobId = (int)($_GET['job_id'] ?? 0);
            $parts = $db->prepare("SELECT rjp.*, p.code FROM repair_job_parts rjp JOIN products p ON p.id=rjp.product_id WHERE rjp.job_id=?");
            $parts->execute([$jobId]);
            echo json_encode(['success'=>true,'parts'=>$parts->fetchAll()]);
            break;

        // ─── Remove part from job (return to stock) ─────────────────────────
        case 'remove_part':
            requireAuth('admin','technician','cashier');
            $partRowId = (int)($_POST['part_id'] ?? 0);
            if (!$partRowId) throw new Exception('Invalid part');

            $row = $db->prepare("SELECT * FROM repair_job_parts WHERE id=?");
            $row->execute([$partRowId]);
            $row = $row->fetch();
            if (!$row) throw new Exception('Part not found');

            $db->beginTransaction();
            $db->prepare("DELETE FROM repair_job_parts WHERE id=?")->execute([$partRowId]);
            $db->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id=?")->execute([$row['quantity'], $row['product_id']]);
            logStockMovement($row['product_id'], 'return', $row['quantity'], $row['job_id'], 'repair_return', 'Part removed from repair job');
            $db->commit();
            echo json_encode(['success'=>true, 'message'=>'Part removed and stock returned']);
            break;

        // ─── Remove service from job ────────────────────────────────────────
        case 'remove_service':
            requireAuth('admin','cashier');
            $svcRowId = (int)($_POST['service_id'] ?? 0);
            $db->prepare("DELETE FROM repair_job_services WHERE id=?")->execute([$svcRowId]);
            echo json_encode(['success'=>true]);
            break;

        // ─── Get job cost summary ───────────────────────────────────────────
        case 'get_cost_summary':
            $jobId = (int)($_GET['job_id'] ?? 0);
            $svcTotal = $db->prepare("SELECT COALESCE(SUM(price),0) FROM repair_job_services WHERE job_id=?");
            $svcTotal->execute([$jobId]);
            $partsTotal = $db->prepare("SELECT COALESCE(SUM(total),0) FROM repair_job_parts WHERE job_id=?");
            $partsTotal->execute([$jobId]);
            echo json_encode(['success'=>true,'services_total'=>$svcTotal->fetchColumn(),'parts_total'=>$partsTotal->fetchColumn()]);
            break;

        default:
            throw new Exception('Unknown action: ' . $action);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
?>
