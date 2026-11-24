<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Worker Dashboard — E-Sheba</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <link rel="stylesheet" href="../css/style.css">
</head>
<body>

<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
Auth::requireRole('worker');
$myId   = Auth::id();
$myName = $_SESSION['user_name'] ?? 'Worker';

// Get worker data
$worker = DB::fetchOne(
    "SELECT w.*, u.name, u.email, u.avatar, u.phone
     FROM workers w JOIN users u ON w.user_id=u.id
     WHERE w.user_id=?", [$myId]
);
if (!$worker) { header('../register.php?role=worker'); exit; }

$wId = $worker['id'];

// Stats
$totalBookings = DB::fetchOne("SELECT COUNT(*) c FROM bookings WHERE worker_id=?",[$wId])['c'];
$completedJobs = DB::fetchOne("SELECT COUNT(*) c FROM bookings WHERE worker_id=? AND status='completed'",[$wId])['c'];
$pendingJobs   = DB::fetchOne("SELECT COUNT(*) c FROM bookings WHERE worker_id=? AND status='pending'",[$wId])['c'];
$totalEarnings = DB::fetchOne("SELECT COALESCE(SUM(total_amount),0) c FROM bookings WHERE worker_id=? AND pay_status='paid'",[$wId])['c'];
$unreadMessages= DB::fetchOne("SELECT COUNT(*) c FROM messages WHERE receiver_id=? AND is_read=0",[$myId])['c'];

// Active subscription
$subscription = DB::fetchOne("SELECT * FROM subscriptions WHERE worker_id=? AND status='active' ORDER BY expires_at DESC LIMIT 1",[$wId]);

// Recent bookings
$bookings = DB::fetchAll(
    "SELECT b.*, u.name as customer_name, c.name as category_name
     FROM bookings b
     JOIN users u ON b.customer_id=u.id
     JOIN categories c ON b.category_id=c.id
     WHERE b.worker_id=? ORDER BY b.created_at DESC LIMIT 8",
    [$wId]
);

// Recent reviews
$reviews = DB::fetchAll(
    "SELECT r.*, u.name as customer_name FROM reviews r
     JOIN users u ON r.customer_id=u.id
     WHERE r.worker_id=? ORDER BY r.created_at DESC LIMIT 4",
    [$wId]
);

// Welcome banner
$welcome = isset($_GET['welcome']);
?>

<!-- Navbar -->
<nav class="navbar-esheba">
  <div class="container d-flex align-items-center justify-content-between">
    <a href="../index.php" class="brand text-decoration-none">🔧 E-Sheba</a>
    <div class="d-flex align-items-center gap-3">
      <a href="../chat.php" class="btn btn-link position-relative text-muted p-2">
        <i class="fa fa-comment-dots fs-5"></i>
        <?php if($unreadMessages>0): ?><span class="notification-badge"><?= $unreadMessages ?></span><?php endif; ?>
      </a>
      <div class="dropdown">
        <button class="btn d-flex align-items-center gap-2 border rounded-pill px-3 py-1" data-bs-toggle="dropdown">
          <div class="chat-avatar" style="width:32px;height:32px;font-size:.75rem"><?= strtoupper($myName[0]) ?></div>
          <span class="d-none d-md-inline" style="font-size:.88rem;font-weight:600"><?= htmlspecialchars($myName) ?></span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-2" style="border-radius:12px">
          <li><a class="dropdown-item py-2" href="profile.php"><i class="fa fa-user me-2 text-primary"></i>My Profile</a></li>
          <li><a class="dropdown-item py-2" href="subscription.php"><i class="fa fa-crown me-2 text-warning"></i>Subscription</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item py-2 text-danger" href="../api/logout.php"><i class="fa fa-sign-out-alt me-2"></i>Logout</a></li>
        </ul>
      </div>
    </div>
  </div>
</nav>

<div class="dashboard-layout">

  <!-- ══ Sidebar ══ -->
  <div class="sidebar">
    <div class="p-4 border-bottom" style="border-color:rgba(255,255,255,.1)!important">
      <div class="d-flex align-items-center gap-3">
        <div class="chat-avatar" style="width:48px;height:48px;font-size:1rem"><?= strtoupper($myName[0]) ?></div>
        <div>
          <div class="text-white fw-700" style="font-size:.9rem"><?= htmlspecialchars($myName) ?></div>
          <div style="font-size:.72rem;color:rgba(255,255,255,.5)">Service Provider</div>
          <?php if($subscription): ?>
          <span style="background:#fbbc05;color:#333;font-size:.65rem;padding:2px 8px;border-radius:50px;font-weight:700">PRO ✓</span>
          <?php else: ?>
          <span style="background:var(--danger);color:#fff;font-size:.65rem;padding:2px 8px;border-radius:50px;font-weight:700">Subscribe!</span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="sidebar-menu">
      <div class="sidebar-label">Main</div>
      <a href="dashboard.php" class="sidebar-item active"><i class="fa fa-tachometer-alt"></i> Dashboard</a>
      <a href="bookings.php"  class="sidebar-item"><i class="fa fa-calendar-check"></i> Bookings <span class="ms-auto badge bg-warning text-dark"><?= $pendingJobs ?></span></a>
      <a href="../chat.php"   class="sidebar-item"><i class="fa fa-comments"></i> Messages <span class="ms-auto badge bg-danger"><?= $unreadMessages ?: '' ?></span></a>
      <div class="sidebar-label">Profile</div>
      <a href="profile.php"      class="sidebar-item"><i class="fa fa-user-edit"></i> Edit Profile</a>
      <a href="subscription.php" class="sidebar-item"><i class="fa fa-crown"></i> Subscription</a>
      <a href="earnings.php"     class="sidebar-item"><i class="fa fa-wallet"></i> Earnings</a>
      <a href="reviews.php"      class="sidebar-item"><i class="fa fa-star"></i> Reviews</a>
      <div class="sidebar-label">Account</div>
      <a href="../api/logout.php" class="sidebar-item"><i class="fa fa-sign-out-alt"></i> Logout</a>
    </div>
  </div>

  <!-- ══ Main Content ══ -->
  <div class="dashboard-content">

    <?php if($welcome): ?>
    <div class="alert alert-success d-flex align-items-center gap-3 mb-4" style="border-radius:14px">
      <span style="font-size:28px">🎉</span>
      <div>
        <strong>Welcome to E-Sheba!</strong> Your profile is under review. To appear in search results, please <a href="subscription.php" class="fw-700">activate your subscription</a>.
      </div>
    </div>
    <?php endif; ?>

    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
      <h1 class="page-title mb-0">Dashboard</h1>
      <!-- Availability Toggle -->
      <div class="availability-card <?= !$worker['is_available']?'off':'' ?>" id="availCard" style="padding:16px 20px">
        <div>
          <div class="fw-700" style="font-size:.95rem">Availability</div>
          <div style="font-size:.8rem;opacity:.85" id="availText"><?= $worker['is_available']?'You are AVAILABLE':'You are BUSY' ?></div>
        </div>
        <label class="toggle-switch ms-auto" title="Toggle availability">
          <input type="checkbox" id="availToggle" <?= $worker['is_available']?'checked':'' ?> onchange="toggleAvailability(this.checked)">
          <span class="toggle-slider"></span>
        </label>
      </div>
    </div>

    <!-- Stat Cards -->
    <div class="row g-3 mb-4">
      <?php $stats = [
        ['fa-calendar-check','Total Bookings',$totalBookings,'#1a73e8','linear-gradient(135deg,#e8f0fe,#d0e3ff)'],
        ['fa-tools','Completed Jobs',$completedJobs,'#34a853','linear-gradient(135deg,#e6f4ea,#ceead6)'],
        ['fa-clock','Pending Jobs',$pendingJobs,'#fbbc05','linear-gradient(135deg,#fef9e0,#fde68a)'],
        ['fa-wallet','Total Earnings','৳'.number_format($totalEarnings),'#8b5cf6','linear-gradient(135deg,#f3e8ff,#ddd6fe)'],
        ['fa-star','Avg Rating',$worker['avg_rating'].'★','#f59e0b','linear-gradient(135deg,#fffbeb,#fde68a)'],
        ['fa-comment-dots','Unread Msgs',$unreadMessages,'#06b6d4','linear-gradient(135deg,#ecfeff,#cffafe)'],
      ]; foreach($stats as [$icon,$label,$val,$color,$bg]): ?>
      <div class="col-6 col-xl-4">
        <div class="stat-card">
          <div class="stat-icon" style="background:<?= $bg ?>;color:<?= $color ?>"><i class="fa <?= $icon ?>"></i></div>
          <div class="stat-info">
            <div class="num"><?= $val ?></div>
            <div class="label"><?= $label ?></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="row g-4">
      <!-- Recent Bookings -->
      <div class="col-lg-8">
        <div class="stat-card flex-column align-items-stretch p-0" style="border-radius:var(--radius-md);overflow:hidden">
          <div class="p-4 border-bottom d-flex align-items-center justify-content-between">
            <h5 class="fw-700 mb-0"><i class="fa fa-calendar-alt me-2 text-primary"></i>Recent Bookings</h5>
            <a href="bookings.php" class="btn btn-sm btn-outline-primary rounded-pill">View All</a>
          </div>
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead style="background:#f8f9ff"><tr>
                <th class="ps-4 py-3">Customer</th><th>Service</th><th>Date</th><th>Amount</th><th>Status</th><th>Action</th>
              </tr></thead>
              <tbody>
                <?php if(empty($bookings)): ?>
                <tr><td colspan="6" class="text-center py-5 text-muted"><i class="fa fa-calendar fa-2x mb-2 d-block"></i>No bookings yet</td></tr>
                <?php else: foreach($bookings as $b):
                  $badgeClass = match($b['status']){
                    'completed'=>'success','accepted'=>'primary','in_progress'=>'info','cancelled'=>'danger',default=>'warning'
                  };
                ?>
                <tr>
                  <td class="ps-4 py-3">
                    <div class="d-flex align-items-center gap-2">
                      <div class="chat-avatar" style="width:32px;height:32px;font-size:.7rem"><?= strtoupper($b['customer_name'][0]) ?></div>
                      <span class="fw-600" style="font-size:.88rem"><?= htmlspecialchars($b['customer_name']) ?></span>
                    </div>
                  </td>
                  <td style="font-size:.85rem"><?= htmlspecialchars($b['category_name']) ?></td>
                  <td style="font-size:.82rem;color:var(--muted)"><?= $b['scheduled_at'] ? date('d M, H:i',strtotime($b['scheduled_at'])) : '—' ?></td>
                  <td style="font-size:.85rem;font-weight:600"><?= $b['total_amount'] ? '৳'.number_format($b['total_amount']) : '—' ?></td>
                  <td><span class="badge bg-<?= $badgeClass ?>"><?= ucfirst(str_replace('_',' ',$b['status'])) ?></span></td>
                  <td>
                    <?php if($b['status']==='pending'): ?>
                    <button class="btn btn-sm btn-success py-1 px-2" onclick="updateBooking(<?= $b['id'] ?>,'accepted')">Accept</button>
                    <button class="btn btn-sm btn-outline-danger py-1 px-2" onclick="updateBooking(<?= $b['id'] ?>,'cancelled')">Decline</button>
                    <?php elseif($b['status']==='accepted'): ?>
                    <button class="btn btn-sm btn-primary py-1 px-2" onclick="updateBooking(<?= $b['id'] ?>,'in_progress')">Start</button>
                    <?php elseif($b['status']==='in_progress'): ?>
                    <button class="btn btn-sm btn-success py-1 px-2" onclick="updateBooking(<?= $b['id'] ?>,'completed')">Complete</button>
                    <?php else: ?>
                    <a href="../chat.php?to=<?= $b['customer_id'] ?>" class="btn btn-sm btn-outline-primary py-1 px-2"><i class="fa fa-comment"></i></a>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Right Column -->
      <div class="col-lg-4">

        <!-- Subscription Status -->
        <div class="mb-3 p-4 rounded-3" style="background:<?= $subscription?'linear-gradient(135deg,#1a73e8,#0d47a1)':'linear-gradient(135deg,#ea4335,#c62828)' ?>;color:#fff">
          <div class="d-flex align-items-center gap-2 mb-2">
            <i class="fa fa-crown fa-lg" style="color:#fbbc05"></i>
            <span class="fw-700">Subscription</span>
          </div>
          <?php if($subscription): ?>
          <div style="font-size:1.3rem;font-weight:800">ACTIVE ✓</div>
          <div style="font-size:.82rem;opacity:.8">Expires: <?= date('d M Y',strtotime($subscription['expires_at'])) ?></div>
          <?php else: ?>
          <div style="font-size:1.1rem;font-weight:800">Not Subscribed</div>
          <div style="font-size:.82rem;opacity:.85;margin:8px 0">Subscribe for ৳399/month to appear in search results</div>
          <a href="subscription.php" style="background:#fbbc05;color:#333;padding:8px 20px;border-radius:50px;font-weight:700;font-size:.85rem;text-decoration:none;display:inline-block;margin-top:4px">Subscribe Now →</a>
          <?php endif; ?>
        </div>

        <!-- Live Location -->
        <div class="stat-card flex-column align-items-stretch p-0 mb-3" style="border-radius:var(--radius-md);overflow:hidden">
          <div class="p-3 border-bottom d-flex align-items-center justify-content-between">
            <h6 class="fw-700 mb-0"><i class="fa fa-map-marker-alt me-2 text-danger"></i>Your Location</h6>
            <button class="btn btn-sm btn-outline-primary rounded-pill" onclick="shareLocation()">Update</button>
          </div>
          <div class="location-map" style="height:180px;border-radius:0;border:none">
            <?php if($worker['latitude'] && $worker['longitude']): ?>
            <div id="workerMap" style="height:180px"></div>
            <?php else: ?>
            <div class="d-flex align-items-center justify-content-center h-100 flex-column" style="background:#f0f4ff">
              <i class="fa fa-map-marker-alt fa-2x text-muted mb-2"></i>
              <span style="font-size:.82rem;color:var(--muted)">Location not set</span>
              <button class="btn btn-sm btn-primary mt-2" onclick="shareLocation()">Share Location</button>
            </div>
            <?php endif; ?>
          </div>
          <div class="p-3" style="font-size:.8rem;color:var(--muted)">
            <i class="fa fa-info-circle me-1"></i>Sharing your location helps customers find you nearby.
          </div>
        </div>

        <!-- Recent Reviews -->
        <div class="stat-card flex-column align-items-stretch p-0" style="border-radius:var(--radius-md);overflow:hidden">
          <div class="p-3 border-bottom">
            <h6 class="fw-700 mb-0"><i class="fa fa-star me-2 text-warning"></i>Recent Reviews</h6>
          </div>
          <div class="p-3" style="display:flex;flex-direction:column;gap:12px">
            <?php if(empty($reviews)): ?>
            <div class="text-center py-3 text-muted" style="font-size:.85rem"><i class="fa fa-star fa-lg mb-2 d-block" style="opacity:.3"></i>No reviews yet</div>
            <?php else: foreach($reviews as $r): ?>
            <div class="p-3 rounded-2" style="background:#f8f9ff">
              <div class="d-flex align-items-center gap-2 mb-1">
                <div class="chat-avatar" style="width:28px;height:28px;font-size:.65rem"><?= strtoupper($r['customer_name'][0]) ?></div>
                <span class="fw-600" style="font-size:.82rem"><?= htmlspecialchars($r['customer_name']) ?></span>
                <span class="ms-auto text-warning" style="font-size:.8rem"><?= str_repeat('★',$r['rating']) ?></span>
              </div>
              <?php if($r['comment']): ?>
              <p class="mb-0" style="font-size:.8rem;color:#555">"<?= htmlspecialchars(mb_substr($r['comment'],0,80)) ?>"</p>
              <?php endif; ?>
            </div>
            <?php endforeach; endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Call Overlay -->
<div class="call-overlay" id="callOverlay">
  <div class="call-avatar" id="callAvatar"></div>
  <div class="call-name" id="callName"></div>
  <div class="call-status" id="callStatus">Incoming Call...</div>
  <div class="call-controls">
    <button class="call-btn accept" onclick="acceptCall()"><i class="fa fa-phone"></i></button>
    <button class="call-btn hangup" onclick="endCall()"><i class="fa fa-phone-slash"></i></button>
    <button class="call-btn mute"   onclick="toggleMute()"><i class="fa fa-microphone"></i></button>
  </div>
</div>

<div class="call-overlay" id="incomingCallOverlay">
  <div class="call-avatar" id="incomingCallAvatar"></div>
  <div class="call-name" id="incomingCallName"></div>
  <div class="call-status">Incoming Call...</div>
  <div class="call-controls">
    <button class="call-btn accept" onclick="acceptCall()"><i class="fa fa-phone"></i></button>
    <button class="call-btn hangup" onclick="declineCall()"><i class="fa fa-phone-slash"></i></button>
  </div>
</div>
<div class="toast-container" id="toastContainer"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
<script src="../js/app.js"></script>
<script src="../js/call.js"></script>
<script>
const CHAT_CONFIG = {
  myId: <?= $myId ?>,
  peerId: null,
  peerName: null,
  pusherKey: '<?= PUSHER_KEY ?>',
  pusherCluster: '<?= PUSHER_CLUSTER ?>',
  agoraAppId: '<?= AGORA_APP_ID ?>',
};

// ── Availability Toggle ──────────────────────────────────────
function toggleAvailability(available) {
  const card = document.getElementById('availCard');
  const text = document.getElementById('availText');
  requestLocation((lat, lng) => {
    $.ajax({
      url:  '../api/update-availability.php',
      type: 'POST',
      contentType: 'application/json',
      data: JSON.stringify({ available: available?1:0, lat, lng }),
      success: (res) => {
        if (res.success) {
          card.className = 'availability-card' + (available?'':' off');
          text.textContent = available ? 'You are AVAILABLE' : 'You are BUSY';
          showToast(available ? 'You are now visible to customers!' : 'You are marked as busy.', available?'success':'info');
          // Update map
          if (available) showLocationOnMap(lat, lng);
        }
      },
      error: () => showToast('Could not update status.','error')
    });
  });
  // If location denied, still update status
  $.ajax({
    url: '../api/update-availability.php',
    type: 'POST', contentType: 'application/json',
    data: JSON.stringify({ available: available?1:0 }),
    success: () => {}
  });
}

function shareLocation() {
  requestLocation((lat, lng) => {
    $.ajax({
      url:  '../api/update-availability.php',
      type: 'POST', contentType: 'application/json',
      data: JSON.stringify({ lat, lng }),
      success: () => { showLocationOnMap(lat, lng); showToast('Location updated!','success'); }
    });
  });
}

function showLocationOnMap(lat, lng) {
  const mapEl = document.getElementById('workerMap');
  if (!mapEl) {
    const container = mapEl ? mapEl.parentNode : document.querySelector('.location-map');
    if (container) container.innerHTML = '<div id="workerMap" style="height:180px"></div>';
  }
  setTimeout(() => initWorkerMap(lat, lng, '<?= addslashes($myName) ?>', true, 'workerMap'), 100);
}

<?php if($worker['latitude'] && $worker['longitude']): ?>
document.addEventListener('DOMContentLoaded',()=>{
  initWorkerMap(<?= $worker['latitude'] ?>,<?= $worker['longitude'] ?>,'<?= addslashes($worker['name']) ?>',<?= $worker['is_available']?'true':'false' ?>,'workerMap');
});
<?php endif; ?>

// ── Update booking status ────────────────────────────────────
function updateBooking(id, status) {
  if (!confirm(`Change booking status to "${status}"?`)) return;
  $.post('../api/update-booking.php',{booking_id: id, id: id, status: status},function(res){
    if(res.success){ showToast('Booking updated!','success'); setTimeout(()=>location.reload(),1200); }
    else showToast(res.error,'error');
  },'json');
}

// ── Pusher: listen for incoming calls on worker dashboard ──
const pusherW = new Pusher('<?= PUSHER_KEY ?>', {cluster:'<?= PUSHER_CLUSTER ?>',authEndpoint:'../api/pusher-auth.php'});
const wCh = pusherW.subscribe('presence-esheba');
// Listen on all private channels for incoming calls (via presence)
// In production, subscribe to worker's specific channel for call signals
</script>
</body>
</html>
