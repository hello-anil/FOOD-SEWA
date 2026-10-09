<?php
require_once __DIR__ . '/../config.php';
require_user();

$userId = (int) $_SESSION['user_id'];

if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add') {
            save_user_address($userId, $_POST);
            flash_success('Address added.');
        }

        if ($action === 'set_default') {
            $addressId = (int) $_POST['address_id'];
            assert_address_owner($addressId, $userId);
            db()->begin_transaction();
            $stmt = db()->prepare('UPDATE addresses SET is_default = 0 WHERE user_id = ?');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt = db()->prepare('UPDATE addresses SET is_default = 1 WHERE id = ? AND user_id = ?');
            $stmt->bind_param('ii', $addressId, $userId);
            $stmt->execute();
            db()->commit();
            flash_success('Default address updated.');
        }

        if ($action === 'delete') {
            $addressId = (int) $_POST['address_id'];
            assert_address_owner($addressId, $userId);
            $stmt = db()->prepare('DELETE FROM addresses WHERE id = ? AND user_id = ?');
            $stmt->bind_param('ii', $addressId, $userId);
            $stmt->execute();
            flash_success('Address removed.');
        }
    } catch (Throwable $e) {
        if (db()->errno) {
            db()->rollback();
        }
        flash_error($e->getMessage());
    }

    redirect('/user/addresses.php');
}

$addresses = get_user_addresses($userId);
$restaurant = restaurant_coordinates();
$title = 'Addresses';
require __DIR__ . '/header.php';
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
<div class="row g-3">
    <div class="col-md-5">
        <div class="card"><div class="card-body">
            <h6>Add Address</h6>
            <p class="text-muted small mb-2">You can save address in either way:</p>
            <p class="text-muted small">1) Fill Address Line 1 + City + Zone, or 2) select a location on map.</p>
            <form method="post" class="row g-2">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <div class="col-12">
                    <label class="form-label mb-1">Address Name</label>
                    <select name="address_name" id="address_name" class="form-select" required>
                        <option value="HOME">Home</option>
                        <option value="OFFICE">Office</option>
                        <option value="OTHER">Other</option>
                    </select>
                </div>
                <div class="col-12 d-none" id="address_name_other_wrap">
                    <input name="address_name_other" id="address_name_other" class="form-control" placeholder="Custom address name (e.g. Sister House)">
                </div>
                <div class="col-12">
                    <label class="form-label mb-1">Address Line 1</label>
                    <input name="address_line_1" class="form-control" placeholder="Street, house/building number">
                </div>
                <div class="col-12">
                    <label class="form-label mb-1">Address Line 2 (Optional)</label>
                    <input name="address_line_2" class="form-control" placeholder="Apartment, floor, landmark">
                </div>
                <div class="col-6">
                    <label class="form-label mb-1">City</label>
                    <input name="city" class="form-control" placeholder="City">
                </div>
                <div class="col-6">
                    <label class="form-label mb-1">Zone</label>
                    <input name="zone" class="form-control" placeholder="Zone / Area">
                </div>
                <div class="col-12"><div id="addressMap" style="height: 280px; border-radius: 8px;"></div></div>
                <div class="col-6"><input id="addr_lat" name="lat" class="form-control" placeholder="Latitude (optional if text address filled)" readonly></div>
                <div class="col-6"><input id="addr_lng" name="lng" class="form-control" placeholder="Longitude (optional if text address filled)" readonly></div>
                <div class="col-12 d-grid"><button type="button" id="useMyLocation" class="btn btn-outline-secondary btn-sm">Use My Current Location</button></div>
                <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_default" value="1" id="default"><label class="form-check-label" for="default">Set as default</label></div></div>
                <div class="col-12"><button class="btn btn-primary w-100">Save</button></div>
            </form>
        </div></div>
    </div>
    <div class="col-md-7">
        <div class="card"><div class="card-body">
            <h6>Saved Addresses</h6>
            <?php if (!$addresses): ?>
                <p class="text-muted small mb-0">No address saved yet.</p>
            <?php endif; ?>
            <?php foreach ($addresses as $addr): ?>
                <div class="border rounded p-2 mb-2">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <strong><?= e((string) $addr['address_name']) ?></strong>
                            <?php if ((int) $addr['is_default'] === 1): ?>
                                <span class="badge text-bg-primary ms-1">Default</span>
                            <?php endif; ?>
                            <?php if (trim((string) ($addr['address_line_1'] ?? '')) !== ''): ?>
                                <p class="mb-1 small mt-1"><?= e((string) $addr['address_line_1']) ?></p>
                            <?php endif; ?>
                            <?php if (trim((string) ($addr['address_line_2'] ?? '')) !== ''): ?>
                                <p class="mb-1 small"><?= e((string) $addr['address_line_2']) ?></p>
                            <?php endif; ?>
                            <?php if (trim((string) ($addr['city'] ?? '')) !== '' || trim((string) ($addr['zone'] ?? '')) !== ''): ?>
                                <p class="mb-1 small text-muted"><?= e((string) $addr['city']) ?><?= trim((string) ($addr['city'] ?? '')) !== '' && trim((string) ($addr['zone'] ?? '')) !== '' ? ', ' : '' ?><?= e((string) $addr['zone']) ?></p>
                            <?php endif; ?>
                            <?php if (trim((string) ($addr['address_line_1'] ?? '')) === '' && trim((string) ($addr['city'] ?? '')) === '' && trim((string) ($addr['zone'] ?? '')) === '' && $addr['lat'] !== null && $addr['lng'] !== null): ?>
                                <p class="mb-1 small text-muted">Map-only saved location</p>
                            <?php endif; ?>
                            <?php if ($addr['lat'] !== null && $addr['lng'] !== null): ?>
                                <p class="mb-1 small text-muted">Pinned: <?= e((string) $addr['lat']) ?>, <?= e((string) $addr['lng']) ?></p>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted text-end"><?= e($addr['phone']) ?></small>
                    </div>
                    <div class="d-flex gap-2">
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="set_default">
                            <input type="hidden" name="address_id" value="<?= (int) $addr['id'] ?>">
                            <button class="btn btn-sm btn-outline-primary">Default</button>
                        </form>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="address_id" value="<?= (int) $addr['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div></div>
    </div>
</div>
</main>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const latInput = document.getElementById('addr_lat');
const lngInput = document.getElementById('addr_lng');
const addressNameSelect = document.getElementById('address_name');
const addressNameOtherWrap = document.getElementById('address_name_other_wrap');
const addressNameOtherInput = document.getElementById('address_name_other');
const initialLat = <?= json_encode((float) $restaurant['lat']) ?>;
const initialLng = <?= json_encode((float) $restaurant['lng']) ?>;

function toggleAddressNameOther() {
  const isOther = addressNameSelect.value === 'OTHER';
  addressNameOtherWrap.classList.toggle('d-none', !isOther);
  addressNameOtherInput.required = isOther;
  if (!isOther) {
    addressNameOtherInput.value = '';
  }
}

toggleAddressNameOther();
addressNameSelect.addEventListener('change', toggleAddressNameOther);

const map = L.map('addressMap').setView([initialLat, initialLng], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  maxZoom: 19,
  attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);

let marker = L.marker([initialLat, initialLng], {draggable: true}).addTo(map);

function setPoint(lat, lng) {
  marker.setLatLng([lat, lng]);
  latInput.value = Number(lat).toFixed(7);
  lngInput.value = Number(lng).toFixed(7);
}

map.on('click', function(e) {
  setPoint(e.latlng.lat, e.latlng.lng);
});

marker.on('dragend', function() {
  const p = marker.getLatLng();
  setPoint(p.lat, p.lng);
});

document.getElementById('useMyLocation').addEventListener('click', function() {
  if (!navigator.geolocation) {
    alert('Geolocation is not supported in this browser.');
    return;
  }
  navigator.geolocation.getCurrentPosition(function(pos) {
    const lat = pos.coords.latitude;
    const lng = pos.coords.longitude;
    map.setView([lat, lng], 16);
    setPoint(lat, lng);
  }, function() {
    alert('Unable to fetch current location. Please pin manually.');
  });
});
</script>
</body>
</html>
