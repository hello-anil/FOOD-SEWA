<?php
require_once __DIR__ . '/config.php';

$trackingInput = trim((string) ($_GET['tracking_number'] ?? ''));
$tracking = $trackingInput;
$viewerUser = current_user();
$viewerUserId = (int) ($viewerUser['id'] ?? 0);

if (isset($_GET['ajax'])) {
    if (!rate_limit_allow('track_lookup', client_ip(), 120, 60)) {
        http_json(['error' => 'Too many tracking requests. Please try again shortly.'], 429);
    }

    try {
        $tracking = validate_tracking_number($trackingInput);
    } catch (Throwable $e) {
        http_json(['error' => $e->getMessage()], 422);
    }

    if ($tracking === '') {
        http_json(['error' => 'tracking_number is required'], 422);
    }

    $ajaxOrder = get_order_by_tracking($tracking);
    if (!$ajaxOrder) {
        http_json(['error' => 'Tracking number not found'], 404);
    }

    $ajaxPayload = tracking_payload($ajaxOrder);
    $ajaxPayload['tracking_number'] = $tracking;
    $ajaxPayload['delivery_status'] = $ajaxOrder['delivery_status'];
    $isOwner = ($viewerUserId > 0 && $viewerUserId === (int) ($ajaxOrder['user_id'] ?? 0));
    $ajaxPayload['viewer_scope'] = $isOwner ? 'owner' : 'public';

    if ($isOwner) {
        $ajaxPayload['order_code'] = $ajaxOrder['order_code'];
    } else {
        unset(
            $ajaxPayload['route_path'],
            $ajaxPayload['restaurant'],
            $ajaxPayload['customer'],
            $ajaxPayload['rider'],
            $ajaxPayload['coupon_due_at'],
            $ajaxPayload['late_coupon_claimed'],
            $ajaxPayload['late_coupon_claimable'],
            $ajaxPayload['late_coupon_claim_minutes'],
            $ajaxPayload['late_coupon_min_order_amount']
        );
    }

    http_json($ajaxPayload);
}

$title = 'Track Order';
$order = null;
$payload = null;
$isOwner = false;

if ($trackingInput !== '') {
    if (!rate_limit_allow('track_lookup', client_ip(), 120, 60)) {
        flash_error('Too many tracking requests. Please wait a minute and try again.');
    } else {
        try {
            $tracking = validate_tracking_number($trackingInput);
            $order = get_order_by_tracking($tracking);
            if ($order) {
                $payload = tracking_payload($order);
                $isOwner = ($viewerUserId > 0 && $viewerUserId === (int) ($order['user_id'] ?? 0));
            } else {
                flash_error('Tracking number not found.');
            }
        } catch (Throwable $e) {
            flash_error($e->getMessage());
        }
    }
}

require __DIR__ . '/user/header.php';

$deliveryStatus = strtoupper((string) ($payload['status'] ?? ''));
$isOutForDelivery = $deliveryStatus === 'OUT_FOR_DELIVERY';
$isCouponClaimable = $isOwner && !empty($payload['late_coupon_claimable']);
$isCouponClaimed = $isOwner && !empty($payload['late_coupon_claimed']);
$lateCouponPercent = (int) round((float) ($payload['late_coupon_discount_percent'] ?? late_coupon_discount_percent()));

$statusMessage = '';
if ($deliveryStatus === 'PENDING') {
    $statusMessage = 'Order received. Waiting for restaurant confirmation.';
} elseif ($deliveryStatus === 'CONFIRMED') {
    $statusMessage = 'Order confirmed and being prepared. Live tracking will start when rider is dispatched.';
} elseif ($deliveryStatus === 'OUT_FOR_DELIVERY') {
    $statusMessage = 'Rider is on the way. Live tracking is active now.';
} elseif ($deliveryStatus === 'DELIVERED') {
    $statusMessage = 'Order delivered successfully.';
} elseif ($deliveryStatus === 'CANCELLED') {
    $statusMessage = 'Order was cancelled. Please contact support if this is unexpected.';
}
?>
<?php if ($order && $payload && $isOwner && $isOutForDelivery): ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
<?php endif; ?>
<h1 class="h4 mb-3">Track Your Delivery</h1>
<form method="get" class="row g-2 mb-4">
    <div class="col-md-6">
        <input type="text" name="tracking_number" class="form-control" value="<?= e($tracking) ?>" placeholder="Enter tracking number">
    </div>
    <div class="col-md-2">
        <button class="btn btn-primary w-100">Track</button>
    </div>
</form>

<?php if ($order && $payload): ?>
    <div class="card mb-3">
        <div class="card-body">
            <h5><?= $isOwner ? 'Order ' . e($order['order_code']) : 'Tracking ' . e($tracking) ?></h5>
            <p class="mb-1">Status: <strong id="track-status"><?= e($payload['status']) ?></strong></p>
            <p class="mb-2"><?= e($statusMessage) ?></p>
            <?php if ($isOutForDelivery): ?>
                <p class="mb-1">Progress: <strong id="track-progress"><?= (int) round($payload['progress'] * 100) ?>%</strong></p>
                <div class="progress mb-2" style="height: 10px;">
                    <div id="track-progress-bar" class="progress-bar bg-success" role="progressbar" style="width: <?= (int) round($payload['progress'] * 100) ?>%;" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) round($payload['progress'] * 100) ?>"></div>
                </div>
                <p class="mb-1">Estimated Arrival In: <strong id="track-eta"><?= $payload['eta_minutes'] !== null ? (int) $payload['eta_minutes'] . ' mins' : 'N/A' ?></strong></p>
                <p class="mb-1">Estimated Arrival At: <strong id="track-eta-at"><?= e((string) ($payload['eta_at'] ?? 'N/A')) ?></strong></p>
                <?php if ($isOwner): ?>
                    <p class="mb-1">Route Distance: <strong id="track-distance"><?= e((string) round(((float) ($payload['route_distance_meters'] ?? 0)) / 1000, 2)) ?> km</strong></p>
                <?php endif; ?>
            <?php endif; ?>
            <p class="mb-1 text-muted small">Delayed delivery rewards are available for eligible orders. Late coupon: <?= (int) $lateCouponPercent ?>% off, valid above NPR <?= e(money((float) LATE_COUPON_MIN_ORDER_AMOUNT)) ?>.</p>
            <?php if ($isOwner): ?>
                <p class="mb-1">Delivery Address: <?= e($order['address_line']) ?>, <?= e($order['city']) ?></p>
            <?php else: ?>
                <p class="mb-1">Delivery Address: <em>Hidden for privacy</em></p>
            <?php endif; ?>
            <?php if (!empty($payload['delivered_at'])): ?>
                <p class="mb-0 text-success">Delivered at: <span id="track-delivered"><?= e((string) $payload['delivered_at']) ?></span></p>
            <?php else: ?>
                <p class="mb-0 text-success" id="delivered-wrapper" style="display:none;">Delivered at: <span id="track-delivered"></span></p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($isOutForDelivery && $isOwner): ?>
        <div class="mb-3">
            <button id="liveTrackBtn" type="button" class="btn btn-outline-primary">Track Live Location</button>
        </div>
        <div class="mb-3">
            <div id="claimCouponWrap" style="<?= ($isCouponClaimable || $isCouponClaimed) ? '' : 'display:none;' ?>">
                <button
                    id="claimCouponBtn"
                    type="button"
                    class="btn btn-warning"
                    <?= (!$viewerUser || $isCouponClaimed) ? 'disabled' : '' ?>
                >
                    <?= $isCouponClaimed ? 'Already Claimed' : 'Claim Coupon' ?>
                </button>
                <small id="claimCouponHint" class="text-muted ms-2">
                    <?php if (!$viewerUser): ?>
                        Login required to claim.
                    <?php elseif ($isCouponClaimed): ?>
                        Coupon already claimed.
                    <?php else: ?>
                        Eligible now.
                    <?php endif; ?>
                </small>
            </div>
        </div>

        <div id="liveTrackWrap" class="card" style="display:none;">
            <div class="card-body">
                <h6 class="mb-2">Live Delivery Route</h6>
                <div id="liveMap" style="height: 420px; border-radius: 8px;"></div>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
</main>
<?php if ($order && $payload && $isOwner): ?>
<div class="modal fade" id="couponDialog" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="couponDialogTitle">Coupon</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="couponDialogMessage" class="mb-2"></p>
                <div id="couponCodeBlock" style="display:none;">
                    <label for="couponCodeInput" class="form-label mb-1">Coupon Code</label>
                    <div class="input-group">
                        <input id="couponCodeInput" class="form-control" readonly value="">
                        <button id="couponCopyBtn" type="button" class="btn btn-outline-secondary">Copy</button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
const trackingNumber = <?= json_encode($tracking) ?>;
const orderCode = <?= json_encode((string) ($order['order_code'] ?? '')) ?>;
const initialPayload = <?= json_encode($payload, JSON_UNESCAPED_SLASHES) ?>;
const isLoggedInUser = <?= json_encode((bool) $viewerUser) ?>;
const csrfToken = <?= json_encode(csrf_token()) ?>;

const statusEl = document.getElementById('track-status');
const progressEl = document.getElementById('track-progress');
const progressBarEl = document.getElementById('track-progress-bar');
const etaEl = document.getElementById('track-eta');
const deliveredEl = document.getElementById('track-delivered');
const deliveredWrap = document.getElementById('delivered-wrapper');
const liveBtn = document.getElementById('liveTrackBtn');
const liveWrap = document.getElementById('liveTrackWrap');
const claimWrap = document.getElementById('claimCouponWrap');
const claimBtn = document.getElementById('claimCouponBtn');
const claimHint = document.getElementById('claimCouponHint');
const couponDialogEl = document.getElementById('couponDialog');
const couponDialogTitle = document.getElementById('couponDialogTitle');
const couponDialogMessage = document.getElementById('couponDialogMessage');
const couponCodeBlock = document.getElementById('couponCodeBlock');
const couponCodeInput = document.getElementById('couponCodeInput');
const couponCopyBtn = document.getElementById('couponCopyBtn');

let map = null;
let routeLine = null;
let riderMarker = null;
let restaurantMarker = null;
let customerMarker = null;
let polling = null;
let couponModalInstance = null;

function makePinIcon(symbol, bg, size = 26) {
    return L.divIcon({
      className: '',
      html: '<div style=\"width:' + size + 'px;height:' + size + 'px;border-radius:50%;background:' + bg + ';display:flex;align-items:center;justify-content:center;color:#fff;font-size:' + Math.max(12, Math.floor(size * 0.55)) + 'px;box-shadow:0 2px 6px rgba(0,0,0,0.35);\">' + symbol + '</div>',
      iconSize: [size, size],
      iconAnchor: [Math.floor(size / 2), Math.floor(size / 2)],
      popupAnchor: [0, -Math.floor(size / 2)]
    });
}

function showCouponDialog(title, message, code) {
    if (!couponDialogEl || !window.bootstrap) {
        return;
    }

    couponDialogTitle.textContent = title;
    couponDialogMessage.textContent = message;

    if (code) {
        couponCodeInput.value = code;
        couponCodeBlock.style.display = '';
    } else {
        couponCodeInput.value = '';
        couponCodeBlock.style.display = 'none';
    }

    if (!couponModalInstance) {
        couponModalInstance = new bootstrap.Modal(couponDialogEl);
    }
    couponModalInstance.show();
}

function updateSummary(data) {
    statusEl.textContent = data.status || '-';
    if (progressEl) {
        const pct = Math.round((data.progress || 0) * 100);
        progressEl.textContent = pct + '%';
        if (progressBarEl) {
            progressBarEl.style.width = pct + '%';
            progressBarEl.setAttribute('aria-valuenow', String(pct));
        }
    }
    if (etaEl) {
        etaEl.textContent = data.eta_minutes !== null ? (data.eta_minutes + ' mins') : 'N/A';
    }
    const etaAtEl = document.getElementById('track-eta-at');
    const distanceEl = document.getElementById('track-distance');
    if (etaAtEl) {
        etaAtEl.textContent = data.eta_at || 'N/A';
    }
    if (distanceEl) {
        const km = (Number(data.route_distance_meters || 0) / 1000).toFixed(2);
        distanceEl.textContent = km + ' km';
    }

    if (data.delivered_at) {
        deliveredEl.textContent = data.delivered_at;
        if (deliveredWrap) {
            deliveredWrap.style.display = '';
        }
    }

    if (claimBtn) {
        const claimed = !!data.late_coupon_claimed;
        const claimable = !!data.late_coupon_claimable;
        if (claimWrap) {
            claimWrap.style.display = (claimed || claimable) ? '' : 'none';
        }
        claimBtn.textContent = claimed ? 'Already Claimed' : 'Claim Coupon';
        claimBtn.disabled = claimed || !isLoggedInUser || !claimable;
        if (claimHint) {
            if (claimed) {
                claimHint.textContent = 'Coupon already claimed.';
            } else if (!isLoggedInUser && claimable) {
                claimHint.textContent = 'Login required to claim.';
            } else if (claimable) {
                claimHint.textContent = 'Eligible now.';
            } else {
                claimHint.textContent = '';
            }
        }
    }
}

function initMap(data) {
    const restaurant = data.restaurant;
    const customer = data.customer;
    const rider = data.rider;

    map = L.map('liveMap');
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    const restaurantIcon = makePinIcon('🍽', '#198754', 30);
    const homeIcon = makePinIcon('🏠', '#0d6efd', 30);
    const bikeIcon = makePinIcon('🏍', '#fd7e14', 22);

    restaurantMarker = L.marker([restaurant.lat, restaurant.lng], {icon: restaurantIcon}).addTo(map).bindPopup('Restaurant Location');
    customerMarker = L.marker([customer.lat, customer.lng], {icon: homeIcon}).addTo(map).bindPopup('Customer Home Location');
    riderMarker = L.marker([rider.lat, rider.lng], {icon: bikeIcon}).addTo(map).bindPopup('Bike Rider Live Position');

    const path = Array.isArray(data.route_path) && data.route_path.length > 1
      ? data.route_path.map(p => [p.lat, p.lng])
      : [[restaurant.lat, restaurant.lng], [customer.lat, customer.lng]];
    routeLine = L.polyline(path, {color: '#0d6efd', weight: 4, opacity: 0.7}).addTo(map);

    map.fitBounds(routeLine.getBounds(), {padding: [30, 30]});
  }

function updateMap(data) {
    if (!map) return;

    const rider = data.rider;
    const restaurant = data.restaurant;
    const customer = data.customer;

    riderMarker.setLatLng([rider.lat, rider.lng]);
    restaurantMarker.setLatLng([restaurant.lat, restaurant.lng]);
    customerMarker.setLatLng([customer.lat, customer.lng]);
    const path = Array.isArray(data.route_path) && data.route_path.length > 1
      ? data.route_path.map(p => [p.lat, p.lng])
      : [[restaurant.lat, restaurant.lng], [customer.lat, customer.lng]];
    routeLine.setLatLngs(path);
}

async function pollTracking() {
    try {
        const res = await fetch(<?= json_encode(base_url('/track.php')) ?> + '?ajax=1&tracking_number=' + encodeURIComponent(trackingNumber), {cache: 'no-store'});
        if (!res.ok) {
            return;
        }

        const data = await res.json();
        updateSummary(data);
        updateMap(data);
    } catch (e) {
    }
}

if (liveBtn) {
    liveBtn.addEventListener('click', function() {
        if (!liveWrap) {
            return;
        }
        liveWrap.style.display = '';
        liveBtn.disabled = true;
        liveBtn.textContent = 'Live Tracking Enabled';

        if (!map) {
            initMap(initialPayload);
            updateSummary(initialPayload);
        }

        if (!polling) {
            polling = setInterval(pollTracking, 5000);
        }
    });
}

if (claimBtn) {
    claimBtn.addEventListener('click', async function() {
        if (claimBtn.disabled) {
            return;
        }

        claimBtn.disabled = true;
        claimBtn.textContent = 'Claiming...';

        try {
            const body = new URLSearchParams();
            body.set('action', 'claim_coupon');
            body.set('order_code', orderCode);
            body.set('_csrf', csrfToken);

            const res = await fetch(<?= json_encode(base_url('/user/track_order_realtime.php')) ?>, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: body.toString(),
                credentials: 'same-origin'
            });

            const data = await res.json();
            if (!res.ok || !data.success) {
                throw new Error(data.error || 'Coupon claim failed.');
            }

            showCouponDialog(
                'Coupon Claimed',
                'Your ' + data.coupon.discount_percent + '% off coupon is ready. Minimum order: NPR ' + data.coupon.min_order_amount + '.',
                data.coupon.code
            );
            claimBtn.textContent = 'Already Claimed';
            claimBtn.disabled = true;
            claimHint.textContent = 'Coupon already claimed.';
        } catch (e) {
            claimBtn.textContent = 'Claim Coupon';
            claimBtn.disabled = !isLoggedInUser;
            showCouponDialog('Coupon Claim Failed', e.message || 'Unable to claim coupon.', '');
        }
    });
}

if (couponCopyBtn) {
    couponCopyBtn.addEventListener('click', async function() {
        const code = couponCodeInput ? couponCodeInput.value : '';
        if (!code) {
            return;
        }

        try {
            await navigator.clipboard.writeText(code);
            couponCopyBtn.textContent = 'Copied';
            setTimeout(() => { couponCopyBtn.textContent = 'Copy'; }, 1200);
        } catch (e) {
            couponCodeInput.select();
            document.execCommand('copy');
            couponCopyBtn.textContent = 'Copied';
            setTimeout(() => { couponCopyBtn.textContent = 'Copy'; }, 1200);
        }
    });
}
</script>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
