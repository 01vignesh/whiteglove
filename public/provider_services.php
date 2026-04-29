<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/partials/provider_layout.php';

$user = require_role(['PROVIDER']);
$pdo = db();
$message = '';
$error = '';

function upload_service_images(array $files): array
{
    if (!isset($files['name']) || !is_array($files['name'])) {
        return [];
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    $uploadDir = __DIR__ . '/uploads/services';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Unable to prepare service upload directory.');
    }

    $saved = [];
    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        $errorCode = (int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($errorCode !== UPLOAD_ERR_OK) {
            throw new RuntimeException('One of the service images failed to upload.');
        }

        $tmp = (string) ($files['tmp_name'][$i] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('Invalid uploaded service image.');
        }

        if ((int) ($files['size'][$i] ?? 0) > 3 * 1024 * 1024) {
            throw new RuntimeException('Each service image must be 3MB or smaller.');
        }

        $mime = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = (string) finfo_file($finfo, $tmp);
                finfo_close($finfo);
            }
        }

        if (!isset($allowed[$mime])) {
            throw new RuntimeException('Only JPG, PNG, or WEBP service images are allowed.');
        }

        $fileName = 'service_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
        $target = $uploadDir . '/' . $fileName;
        if (!move_uploaded_file($tmp, $target)) {
            throw new RuntimeException('Failed to save one of the service images.');
        }

        $saved[] = '/WhiteGlove/public/uploads/services/' . $fileName;
    }

    return $saved;
}

$approval = $pdo->prepare('SELECT approval_status FROM provider_profiles WHERE user_id = ? LIMIT 1');
$approval->execute([$user['id']]);
$profile = $approval->fetch();
$isApproved = $profile && $profile['approval_status'] === 'APPROVED';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'create_service');
    try {
        if (!$isApproved) {
            throw new RuntimeException('Your provider profile is not approved yet.');
        }

        if ($action === 'update_service') {
            $serviceId = (int) ($_POST['service_id'] ?? 0);
            $status = strtoupper((string) ($_POST['status'] ?? 'ACTIVE'));
            if (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
                throw new RuntimeException('Invalid service status.');
            }

            $update = $pdo->prepare(
                'UPDATE services
                 SET title = ?, city = ?, event_type = ?, description = ?, base_price = ?, status = ?
                 WHERE id = ? AND provider_id = ?'
            );
            $update->execute([
                (string) ($_POST['title'] ?? ''),
                (string) ($_POST['city'] ?? ''),
                (string) ($_POST['event_type'] ?? ''),
                (string) ($_POST['description'] ?? ''),
                (float) ($_POST['base_price'] ?? 0),
                $status,
                $serviceId,
                (int) $user['id'],
            ]);

            if ($update->rowCount() === 0) {
                throw new RuntimeException('Service not found or unchanged.');
            }

            $imagePaths = upload_service_images($_FILES['service_images'] ?? []);
            if (count($imagePaths) > 0) {
                $nextSortStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) AS max_sort FROM service_images WHERE service_id = ?');
                $nextSortStmt->execute([$serviceId]);
                $maxSort = (int) (($nextSortStmt->fetch()['max_sort'] ?? 0));
                foreach ($imagePaths as $idx => $path) {
                    add_service_image($serviceId, $path, $maxSort + $idx + 1);
                }
            }
            $message = 'Service updated successfully.';
        } elseif ($action === 'delete_service') {
            $serviceId = (int) ($_POST['service_id'] ?? 0);
            if ($serviceId <= 0) {
                throw new RuntimeException('Invalid service selected for deletion.');
            }

            $ownerStmt = $pdo->prepare('SELECT id FROM services WHERE id = ? AND provider_id = ? LIMIT 1');
            $ownerStmt->execute([$serviceId, (int) $user['id']]);
            if (!$ownerStmt->fetch()) {
                throw new RuntimeException('Service not found.');
            }

            $imgStmt = $pdo->prepare('SELECT image_url FROM service_images WHERE service_id = ?');
            $imgStmt->execute([$serviceId]);
            $images = $imgStmt->fetchAll();

            $deleteStmt = $pdo->prepare('DELETE FROM services WHERE id = ? AND provider_id = ?');
            $deleteStmt->execute([$serviceId, (int) $user['id']]);

            foreach ($images as $img) {
                $url = (string) ($img['image_url'] ?? '');
                if (strpos($url, '/WhiteGlove/public/uploads/services/') === 0) {
                    $localPath = __DIR__ . str_replace('/WhiteGlove/public', '', $url);
                    if (is_file($localPath)) {
                        @unlink($localPath);
                    }
                }
            }

            $message = 'Service deleted successfully.';
        } else {
            $imagePaths = upload_service_images($_FILES['service_images'] ?? []);
            $id = create_service(
                (int) $user['id'],
                (string) ($_POST['title'] ?? ''),
                (string) ($_POST['city'] ?? ''),
                (string) ($_POST['event_type'] ?? ''),
                (float) ($_POST['base_price'] ?? 0),
                (string) ($_POST['description'] ?? '')
            );
            foreach ($imagePaths as $idx => $path) {
                add_service_image($id, $path, $idx + 1);
            }
            $message = 'Service created. ID: ' . $id . '. Images uploaded: ' . count($imagePaths);
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$serviceStmt = $pdo->prepare(
    'SELECT s.id, s.title, s.city, s.event_type, s.description, s.base_price, s.status,
            (SELECT si.image_url
             FROM service_images si
             WHERE si.service_id = s.id
             ORDER BY si.sort_order ASC, si.id ASC
             LIMIT 1) AS cover_image,
            (SELECT COUNT(*) FROM service_images sc WHERE sc.service_id = s.id) AS image_count
     FROM services s
     WHERE s.provider_id = ? AND s.title NOT LIKE "Custom Bid Booking #%"
     ORDER BY s.created_at DESC'
);
$serviceStmt->execute([$user['id']]);
$services = $serviceStmt->fetchAll();

$editServiceId = (int) ($_GET['edit_id'] ?? 0);
$editService = null;
if ($editServiceId > 0) {
    foreach ($services as $svc) {
        if ((int) $svc['id'] === $editServiceId) {
            $editService = $svc;
            break;
        }
    }
}
?>
<?php render_provider_page_start('Provider Services'); ?>
    <div class="provider-hero">
        <h1 class="h4 mb-1">Provider Services</h1>
        <p class="mb-0">Create and manage your service listings.</p>
    </div>
    <?php if (!$isApproved): ?>
        <div class="alert alert-warning">Your provider account is pending approval. Creating services is locked.</div>
    <?php endif; ?>
    <?php if ($message !== ''): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if ($editService !== null): ?>
        <section class="provider-card">
            <h2 class="h5">Edit Service #<?php echo (int) $editService['id']; ?></h2>
            <form method="post" class="row g-2" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_service">
                <input type="hidden" name="service_id" value="<?php echo (int) $editService['id']; ?>">
                <div class="col-12"><input class="form-control" name="title" value="<?php echo htmlspecialchars((string) $editService['title'], ENT_QUOTES, 'UTF-8'); ?>" required></div>
                <div class="col-md-6"><input class="form-control" name="city" value="<?php echo htmlspecialchars((string) $editService['city'], ENT_QUOTES, 'UTF-8'); ?>" required></div>
                <div class="col-md-6"><input class="form-control" name="event_type" value="<?php echo htmlspecialchars((string) $editService['event_type'], ENT_QUOTES, 'UTF-8'); ?>" required></div>
                <div class="col-12">
                    <textarea class="form-control" name="description" rows="3" required><?php echo htmlspecialchars((string) $editService['description'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <div class="col-md-6"><input class="form-control" type="number" step="0.01" name="base_price" value="<?php echo htmlspecialchars((string) $editService['base_price'], ENT_QUOTES, 'UTF-8'); ?>" required></div>
                <div class="col-md-6">
                    <select class="form-select" name="status">
                        <option value="ACTIVE" <?php echo (string) $editService['status'] === 'ACTIVE' ? 'selected' : ''; ?>>ACTIVE</option>
                        <option value="INACTIVE" <?php echo (string) $editService['status'] === 'INACTIVE' ? 'selected' : ''; ?>>INACTIVE</option>
                    </select>
                </div>
                <div class="col-12">
                    <input class="form-control" type="file" name="service_images[]" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" multiple>
                    <small class="text-muted">Optional: add more gallery images while editing.</small>
                </div>
                <div class="col-md-6"><button class="btn btn-primary w-100" type="submit">Update Service</button></div>
                <div class="col-md-6"><a class="btn btn-outline-secondary w-100" href="/WhiteGlove/public/provider_services.php">Cancel Edit</a></div>
            </form>
        </section>
    <?php endif; ?>

    <section class="provider-card">
            <h2 class="h5">Add Service</h2>
            <form method="post" class="row g-2" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create_service">
                <div class="col-12"><input class="form-control" name="title" placeholder="Service title" required></div>
                <div class="col-md-6"><input class="form-control" name="city" placeholder="City" required></div>
                <div class="col-md-6"><input class="form-control" name="event_type" placeholder="Event type" required></div>
                <div class="col-12">
                    <textarea class="form-control" name="description" rows="3" placeholder="Service description, inclusions, uniqueness..." required></textarea>
                </div>
                <div class="col-12"><input class="form-control" type="number" step="0.01" name="base_price" placeholder="Base price" required></div>
                <div class="col-12">
                    <input class="form-control" type="file" name="service_images[]" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" multiple>
                    <small class="text-muted">Upload multiple images (JPG/PNG/WEBP, 3MB each).</small>
                </div>
                <div class="col-12"><button class="btn btn-primary w-100" type="submit">Create Service</button></div>
            </form>
    </section>

    <section class="provider-card">
            <h2 class="h5">My Services</h2>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead><tr><th>ID</th><th>Service</th><th>City</th><th>Type</th><th>Description</th><th>Price</th><th>Images</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($services as $s): ?>
                        <tr>
                            <td><?php echo (int) $s['id']; ?></td>
                            <td>
                                <div class="service-cell">
                                    <?php if ((string) ($s['cover_image'] ?? '') !== ''): ?>
                                        <img class="service-thumb" src="<?php echo htmlspecialchars((string) $s['cover_image'], ENT_QUOTES, 'UTF-8'); ?>" alt="Service image">
                                    <?php else: ?>
                                        <div class="service-thumb service-thumb-placeholder">No image</div>
                                    <?php endif; ?>
                                    <div class="service-title"><?php echo htmlspecialchars($s['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($s['city'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($s['event_type'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><div class="service-desc"><?php echo htmlspecialchars((string) $s['description'], ENT_QUOTES, 'UTF-8'); ?></div></td>
                            <td><?php echo number_format((float) $s['base_price'], 2); ?></td>
                            <td><?php echo (int) $s['image_count']; ?></td>
                            <td><?php echo htmlspecialchars($s['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <div class="d-flex gap-2">
                                    <a class="btn btn-sm btn-outline-secondary" href="/WhiteGlove/public/service_view.php?id=<?php echo (int) $s['id']; ?>">View</a>
                                    <a class="btn btn-sm btn-outline-primary" href="/WhiteGlove/public/provider_services.php?edit_id=<?php echo (int) $s['id']; ?>">Edit</a>
                                    <form method="post" onsubmit="return confirm('Delete this service and all its gallery images?');">
                                        <input type="hidden" name="action" value="delete_service">
                                        <input type="hidden" name="service_id" value="<?php echo (int) $s['id']; ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
    </section>
<?php render_provider_page_end(); ?>
