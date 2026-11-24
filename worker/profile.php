<?php
// ============================================================
// Worker Profile Settings — E-Sheba
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
Auth::requireRole('worker');

$myId = Auth::id();
$msg = '';
$err = '';

$worker = DB::fetchOne(
    "SELECT w.*, u.name, u.email, u.phone, u.avatar 
     FROM workers w JOIN users u ON w.user_id = u.id 
     WHERE w.user_id = ?",
    [$myId]
);

if (!$worker) {
    header("Location: ../register.php?role=worker");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone    = trim($_POST['phone'] ?? '');
    $bio      = trim($_POST['bio'] ?? '');
    $price    = (float)($_POST['price_per_hour'] ?? 0);
    $area     = trim($_POST['working_area'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $thana    = trim($_POST['thana'] ?? '');
    $exp      = (int)($_POST['experience_years'] ?? 0);

    if (!$district || !$thana || $price <= 0) {
        $err = 'Please fill all required fields.';
    } else {
        DB::query("UPDATE users SET phone = ? WHERE id = ?", [$phone, $myId]);
        DB::query(
            "UPDATE workers SET bio = ?, price_per_hour = ?, working_area = ?, district = ?, thana = ?, experience_years = ? WHERE id = ?",
            [$bio, $price, $area, $district, $thana, $exp, $worker['id']]
        );
        $msg = 'Profile updated successfully!';
        $worker = DB::fetchOne(
            "SELECT w.*, u.name, u.email, u.phone, u.avatar 
             FROM workers w JOIN users u ON w.user_id = u.id 
             WHERE w.user_id = ?",
            [$myId]
        );
    }
}

$districts = ['Dhaka','Chittagong','Sylhet','Rajshahi','Khulna','Barishal','Rangpur','Mymensingh','Comilla','Narayanganj','Gazipur'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Worker Profile Settings — <?= APP_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../css/style.css">
</head>
<body style="background:#f4f6fb">

<nav class="navbar-esheba">
  <div class="container d-flex align-items-center justify-content-between">
    <a href="../index.php" class="brand text-decoration-none">🔧 <?= APP_NAME ?></a>
    <div class="d-flex align-items-center gap-2">
      <a href="dashboard.php" class="btn btn-outline-secondary rounded-pill px-3" style="font-size:.85rem">
        <i class="fa fa-arrow-left me-1"></i>Back to Dashboard
      </a>
      <a href="../worker-profile.php?id=<?= $worker['id'] ?>" class="btn btn-outline-primary rounded-pill px-3" style="font-size:.85rem">
        <i class="fa fa-eye me-1"></i>View Public Profile
      </a>
    </div>
  </div>
</nav>

<div class="container py-4" style="max-width:760px">
  
  <div class="bg-white rounded-4 border p-4 p-md-5">
    <div class="d-flex align-items-center justify-content-between mb-4 border-bottom pb-3">
      <div>
        <h4 class="fw-800 mb-0">Service Provider Settings</h4>
        <p class="text-muted mb-0" style="font-size:.85rem">Update your public service rate, service area, and biography.</p>
      </div>
      <div class="chat-avatar" style="width:48px;height:48px;font-size:1.1rem"><?= strtoupper($worker['name'][0]) ?></div>
    </div>

    <?php if ($msg): ?>
      <div class="alert alert-success d-flex align-items-center gap-2 mb-3" style="border-radius:10px;font-size:.88rem">
        <i class="fa fa-check-circle"></i> <?= $msg ?>
      </div>
    <?php endif; ?>

    <?php if ($err): ?>
      <div class="alert alert-danger d-flex align-items-center gap-2 mb-3" style="border-radius:10px;font-size:.88rem">
        <i class="fa fa-exclamation-circle"></i> <?= $err ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label fw-600" style="font-size:.85rem">Full Name</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($worker['name']) ?>" disabled>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-600" style="font-size:.85rem">Email</label>
          <input type="email" class="form-control" value="<?= htmlspecialchars($worker['email']) ?>" disabled>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-600" style="font-size:.85rem">Contact Phone *</label>
          <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($worker['phone'] ?? '') ?>" required>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-600" style="font-size:.85rem">Standard Hourly Rate (৳ BDT) *</label>
          <input type="number" step="10" name="price_per_hour" class="form-control" value="<?= (int)$worker['price_per_hour'] ?>" required>
        </div>

        <div class="col-md-4">
          <label class="form-label fw-600" style="font-size:.85rem">District *</label>
          <select name="district" class="form-select" required>
            <?php foreach ($districts as $d): ?>
              <option value="<?= $d ?>" <?= $worker['district'] === $d ? 'selected' : '' ?>><?= $d ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label fw-600" style="font-size:.85rem">Thana / Upazila *</label>
          <input type="text" name="thana" class="form-control" value="<?= htmlspecialchars($worker['thana']) ?>" required>
        </div>

        <div class="col-md-4">
          <label class="form-label fw-600" style="font-size:.85rem">Experience (Years)</label>
          <input type="number" name="experience_years" class="form-control" value="<?= (int)$worker['experience_years'] ?>" min="0" max="50">
        </div>

        <div class="col-12">
          <label class="form-label fw-600" style="font-size:.85rem">Working Area / Specific Locations</label>
          <input type="text" name="working_area" class="form-control" value="<?= htmlspecialchars($worker['working_area']) ?>" placeholder="e.g. Mirpur, Pallabi, Dhanmondi">
        </div>

        <div class="col-12">
          <label class="form-label fw-600" style="font-size:.85rem">Biography & Service Description</label>
          <textarea name="bio" class="form-control" rows="4" placeholder="Tell customers about your skills, certifications, and reliability..."><?= htmlspecialchars($worker['bio'] ?? '') ?></textarea>
        </div>

        <div class="col-12 text-end mt-4">
          <button type="submit" class="btn btn-primary rounded-pill px-4 fw-700">Save Changes</button>
        </div>
      </div>
    </form>
  </div>

</div>

</body>
</html>
