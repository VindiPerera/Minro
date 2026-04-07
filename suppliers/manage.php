<?php
require_once __DIR__ . '/../includes/functions.php';
requireAuth();
if (!isAdmin()) { header('Location: ' . BASE_URL . '/dashboard/index.php'); exit; }

$id = (int)($_GET['id'] ?? 0);
$db = getDB();
$errors = [];
$supplier = null;

if ($id) {
    $stmt = $db->prepare("SELECT * FROM suppliers WHERE id=?");
    $stmt->execute([$id]);
    $supplier = $stmt->fetch();
    if (!$supplier) { header('Location: ' . BASE_URL . '/suppliers/index.php'); exit; }
    $pageTitle = 'Edit Supplier';
} else {
    $pageTitle = 'Add Supplier';
}

$data = $supplier ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $website = trim($_POST['website'] ?? '');
    
    // Handle contacts array
    $contactNames   = $_POST['contact_name'] ?? [];
    $contactTypes   = $_POST['contact_type'] ?? [];
    $contactNumbers = $_POST['contact_number'] ?? [];
    
    $contacts = [];
    foreach ($contactNames as $i => $cName) {
        $cName = trim($cName);
        $cType = $contactTypes[$i] ?? 'local';
        $cNum  = trim($contactNumbers[$i] ?? '');
        if ($cName || $cNum) {
            if ($cName && preg_match('/[0-9]/', $cName)) {
                $errors[] = 'Contact name cannot contain numbers.';
            }
            if ($cType === 'local') {
                if ($cNum && !preg_match('/^\d{1,10}$/', $cNum)) {
                    $errors[] = 'Local phone number must contain only up to 10 digits.';
                }
            } else {
                if ($cNum && preg_match('/[A-Za-z]/', $cNum)) {
                    $errors[] = 'Foreign phone number cannot contain letters.';
                }
            }
            $contacts[] = ['name' => $cName, 'type' => $cType, 'phone' => $cNum];
        }
    }

    if (!$name) $errors[] = 'Supplier name is required.';
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
    if ($website && !filter_var($website, FILTER_VALIDATE_URL)) {
        if (!preg_match("~^(?:f|ht)tps?://~i", $website)) {
             $website = "http://" . $website;
        }
        if (!filter_var($website, FILTER_VALIDATE_URL)) {
             $errors[] = 'Invalid website URL.';
        }
    }

    if (empty($errors)) {
        $contactsJson = json_encode($contacts);
        if ($id) {
            $db->prepare("UPDATE suppliers SET name=?,email=?,address=?,website=?,contacts=? WHERE id=?")
               ->execute([$name, $email, $address, $website, $contactsJson, $id]);
            setFlash('success', 'Supplier updated.');
        } else {
            $db->prepare("INSERT INTO suppliers (name,email,address,website,contacts) VALUES (?,?,?,?,?)")
               ->execute([$name, $email, $address, $website, $contactsJson]);
            setFlash('success', 'Supplier added.');
        }
        header('Location: ' . BASE_URL . '/suppliers/index.php'); exit;
    } else {
        // Keep old data visually
        $data['name'] = $name;
        $data['email'] = $email;
        $data['address'] = $address;
        $data['website'] = $website;
    }
}

// Decode contacts for form prefilling
$existingContacts = [];
if (!empty($data['contacts'])) {
    $existingContacts = json_decode($data['contacts'], true) ?: [];
}

require_once __DIR__ . '/../includes/header.php';
?>

<?php showFlash(); ?>
<?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= e($e) ?></div><?php endforeach; ?>

<div class="page-header d-flex justify-content-between align-items-center">
  <div>
    <h4><i class="fas fa-truck me-2 text-primary"></i><?= $pageTitle ?></h4>
    <p><?= $id ? 'Update supplier details.' : 'Create a new supplier record.' ?></p>
  </div>
  <a href="<?= BASE_URL ?>/suppliers/index.php" class="btn btn-outline-secondary">
    <i class="fas fa-arrow-left me-2"></i>Back
  </a>
</div>

<div class="card bg-dark text-light border-0 shadow-sm">
  <div class="card-header border-bottom border-secondary text-center">
    <h5 class="mb-0 fw-bold"><?= $pageTitle ?></h5>
  </div>
  <div class="card-body">
    <form method="POST">
        
      <div class="row mb-3">
        <div class="col-md-6">
          <label class="form-label text-light">Supplier Name:</label>
          <input type="text" name="name" class="form-control bg-light text-dark" value="<?= e($data['name'] ?? '') ?>" required autofocus>
        </div>
        <div class="col-md-6">
          <label class="form-label text-light">Email:</label>
          <input type="email" name="email" class="form-control bg-white text-dark" value="<?= e($data['email'] ?? '') ?>">
        </div>
      </div>

      <div class="row mb-3">
        <div class="col-md-6">
          <label class="form-label text-light">Address:</label>
          <input type="text" name="address" class="form-control bg-white text-dark" value="<?= e($data['address'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label text-light">Website:</label>
          <input type="text" name="website" class="form-control bg-white text-dark" value="<?= e($data['website'] ?? '') ?>" placeholder="office.com">
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label text-light">Phone Contacts:</label>
        <div id="contacts-container">
            <?php if (empty($existingContacts)): ?>
                <div class="row mb-2 contact-row">
                    <div class="col-md-4">
                      <input type="text" name="contact_name[]" class="form-control bg-white text-dark contact-name" placeholder="Name">
                    </div>
                    <div class="col-md-2">
                        <select name="contact_type[]" class="form-select bg-white text-dark contact-type">
                            <option value="local">Local</option>
                            <option value="foreign">Foreign</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                      <input type="text" name="contact_number[]" class="form-control bg-white text-dark contact-number" placeholder="Phone Number (10 digits)">
                    </div>
                    <div class="col-md-2">
                      <button type="button" class="btn btn-danger remove-contact">Remove</button>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($existingContacts as $contact): ?>
                    <?php $cType = $contact['type'] ?? 'local'; ?>
                    <div class="row mb-2 contact-row">
                        <div class="col-md-4">
                          <input type="text" name="contact_name[]" class="form-control bg-white text-dark contact-name" placeholder="Name" value="<?= e($contact['name'] ?? '') ?>" >
                        </div>
                        <div class="col-md-2">
                            <select name="contact_type[]" class="form-select bg-white text-dark contact-type">
                                <option value="local" <?= $cType === 'local' ? 'selected' : '' ?>>Local</option>
                                <option value="foreign" <?= $cType === 'foreign' ? 'selected' : '' ?>>Foreign</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                          <input type="text" name="contact_number[]" class="form-control bg-white text-dark contact-number" placeholder="<?= $cType === 'local' ? 'Phone Number (10 digits)' : 'Foreign Phone Number' ?>" value="<?= e($contact['phone'] ?? '') ?>" >
                        </div>
                        <div class="col-md-2">
                          <button type="button" class="btn btn-danger remove-contact">Remove</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <button type="button" class="btn btn-success mt-2" id="add-contact-btn">
            + Add Contact
        </button>
      </div>

      <div class="d-flex justify-content-center gap-2 mt-4">
        <button type="submit" class="btn btn-primary px-4 py-2 text-white" style="background-color: #2563eb; border: none;">Save</button>
        <a href="<?= BASE_URL ?>/suppliers/index.php" class="btn btn-secondary px-4 py-2 bg-light text-dark">Cancel</a>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('contacts-container');
    const addBtn = document.getElementById('add-contact-btn');

    addBtn.addEventListener('click', function() {
        const row = document.createElement('div');
        row.className = 'row mb-2 contact-row';
        row.innerHTML = `
            <div class="col-md-4">
              <input type="text" name="contact_name[]" class="form-control bg-white text-dark contact-name" placeholder="Name">
            </div>
            <div class="col-md-2">
                <select name="contact_type[]" class="form-select bg-white text-dark contact-type">
                    <option value="local">Local</option>
                    <option value="foreign">Foreign</option>
                </select>
            </div>
            <div class="col-md-4">
              <input type="text" name="contact_number[]" class="form-control bg-white text-dark contact-number" placeholder="Phone Number (10 digits)">
            </div>
            <div class="col-md-2">
              <button type="button" class="btn btn-danger remove-contact">Remove</button>
            </div>
        `;
        container.appendChild(row);
    });

    container.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-contact')) {
            e.target.closest('.contact-row').remove();
        }
    });

    container.addEventListener('input', function(e) {
        if (e.target.classList.contains('contact-name')) {
            e.target.value = e.target.value.replace(/[^A-Za-z\s]/g, '');
        }
        
        if (e.target.classList.contains('contact-number')) {
            const row = e.target.closest('.contact-row');
            const type = row.querySelector('.contact-type').value;
            
            if (type === 'local') {
                e.target.value = e.target.value.replace(/[^0-9]/g, '').slice(0, 10);
            } else {
                e.target.value = e.target.value.replace(/[a-zA-Z]/g, '');
            }
        }
    });

    container.addEventListener('change', function(e) {
        if (e.target.classList.contains('contact-type')) {
            const row = e.target.closest('.contact-row');
            const numberInput = row.querySelector('.contact-number');
            
            if (e.target.value === 'local') {
                numberInput.value = numberInput.value.replace(/[^0-9]/g, '').slice(0, 10);
                numberInput.placeholder = 'Phone Number (10 digits)';
            } else {
                numberInput.value = numberInput.value.replace(/[a-zA-Z]/g, '');
                numberInput.placeholder = 'Foreign Phone Number';
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>