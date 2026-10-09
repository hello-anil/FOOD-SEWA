<?php
require_once __DIR__ . '/../config.php';
require_admin();

if (is_post()) {
    verify_csrf();

    try {
        $lat = trim((string) ($_POST['restaurant_lat'] ?? ''));
        $lng = trim((string) ($_POST['restaurant_lng'] ?? ''));
        $lateCouponPercent = (int) ($_POST['late_coupon_discount_percent'] ?? 0);
        $lateCouponThreshold = (int) ($_POST['late_coupon_delay_threshold_minutes'] ?? 0);

        if (!is_numeric($lat) || !is_numeric($lng)) {
            throw new RuntimeException('Please pin a valid restaurant location on map.');
        }

        if ($lateCouponPercent < 1 || $lateCouponPercent > 100) {
            throw new RuntimeException('Late coupon discount must be between 1 and 100 percent.');
        }

        if ($lateCouponThreshold < 1 || $lateCouponThreshold > 240) {
            throw new RuntimeException('Delay threshold must be between 1 and 240 minutes.');
        }

        $latFloat = (float) $lat;
        $lngFloat = (float) $lng;
        validate_coordinates($latFloat, $lngFloat);

        set_system_setting('restaurant_lat', (string) $latFloat);
        set_system_setting('restaurant_lng', (string) $lngFloat);
        set_system_setting('late_coupon_discount_percent', (string) $lateCouponPercent);
        set_system_setting('late_coupon_delay_threshold_minutes', (string) $lateCouponThreshold);
        flash_success('Settings updated successfully.');
    } catch (Throwable $e) {
        flash_error($e->getMessage());
    }

    redirect('/admin/settings.php');
}

$restaurant = restaurant_coordinates();
$lateCouponPercent = (int) round(late_coupon_discount_percent());
$lateCouponThreshold = late_coupon_delay_threshold_minutes();
$title = 'Settings';
require __DIR__ . '/header.php';
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
<div class="row g-3">
    <div class="col-md-6">
        <div class="card"><div class="card-body">
            <h6 class="mb-3">Runtime Settings</h6>
            <table class="table table-sm mb-0">
                <tr><th>Base URL</th><td><?= e(APP_BASE_URL) ?></td></tr>
                <tr><th>Delivery Limit Minutes</th><td><?= (int) DELIVERY_TIME_LIMIT_MINUTES ?></td></tr>
                <tr><th>Late Coupon Algorithm</th><td>Threshold Rule Engine</td></tr>
                <tr><th>Late Coupon Discount</th><td><?= (int) $lateCouponPercent ?>%</td></tr>
                <tr><th>Delay Threshold</th><td><?= (int) $lateCouponThreshold ?> mins</td></tr>
                <tr><th>Restaurant Latitude</th><td><?= e((string) $restaurant['lat']) ?></td></tr>
                <tr><th>Restaurant Longitude</th><td><?= e((string) $restaurant['lng']) ?></td></tr>
            </table>
        </div></div>
    </div>
    <div class="col-md-6">
        <div class="card"><div class="card-body">
            <h6 class="mb-2">Set Restaurant Location (Map)</h6>
            <p class="text-muted small">Click on the map to pin the restaurant location.</p>
            <form method="post" class="row g-2">
                <?= csrf_field() ?>
                <div class="col-12"><div id="restaurantMap" style="height: 320px; border-radius: 8px;"></div></div>
                <div class="col-6"><input id="restaurant_lat" name="restaurant_lat" class="form-control" value="<?= e((string) $restaurant['lat']) ?>" readonly required></div>
                <div class="col-6"><input id="restaurant_lng" name="restaurant_lng" class="form-control" value="<?= e((string) $restaurant['lng']) ?>" readonly required></div>
                <div class="col-6">
                    <label class="form-label mb-1">Late Coupon Discount (%)</label>
                    <input type="number" min="1" max="100" name="late_coupon_discount_percent" class="form-control" value="<?= (int) $lateCouponPercent ?>" required>
                </div>
                <div class="col-6">
                    <label class="form-label mb-1">Delay Threshold (mins)</label>
                    <input type="number" min="1" max="240" name="late_coupon_delay_threshold_minutes" class="form-control" value="<?= (int) $lateCouponThreshold ?>" required>
                </div>
                <div class="col-12 d-grid"><button class="btn btn-primary">Save Settings</button></div>
            </form>
        </div></div>
    </div>
</div>
</main>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const restaurantLatInput = document.getElementById('restaurant_lat');
const restaurantLngInput = document.getElementById('restaurant_lng');
const restaurantLat = parseFloat(restaurantLatInput.value || '27.717245');
const restaurantLng = parseFloat(restaurantLngInput.value || '85.323960');

const restaurantMap = L.map('restaurantMap').setView([restaurantLat, restaurantLng], 14);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  maxZoom: 19,
  attribution: '&copy; OpenStreetMap contributors'
}).addTo(restaurantMap);

let restaurantMarker = L.marker([restaurantLat, restaurantLng], {draggable: true}).addTo(restaurantMap);

function setRestaurantPoint(lat, lng) {
  restaurantMarker.setLatLng([lat, lng]);
  restaurantLatInput.value = lat.toFixed(7);
  restaurantLngInput.value = lng.toFixed(7);
}

restaurantMap.on('click', function(e) {
  setRestaurantPoint(e.latlng.lat, e.latlng.lng);
});

restaurantMarker.on('dragend', function() {
  const p = restaurantMarker.getLatLng();
  setRestaurantPoint(p.lat, p.lng);
});
</script>
</body>
</html>
