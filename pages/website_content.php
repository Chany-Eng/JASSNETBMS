<?php
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    header('Location: ../index.php');
    exit();
}

if (!appCanManageSiteContent()) {
    header('Location: ../dashboard.php?error=unauthorized');
    exit();
}

ensureSiteContentSchema($conn);

$error = '';
$success_message = $_SESSION['success_message'] ?? '';
if ($success_message) {
    unset($_SESSION['success_message']);
}

$themeDefaults = appGetLoginThemeDefaults();
$fontChoices = appGetLoginFontChoices();
$currentTheme = appGetLoginThemeSettings($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_theme'])) {
        $headingFontKey = (string) ($_POST['login_heading_font'] ?? $themeDefaults['login_heading_font']);
        if (!isset($fontChoices[$headingFontKey])) {
            $headingFontKey = $themeDefaults['login_heading_font'];
        }

        $bodyFontKey = (string) ($_POST['login_body_font'] ?? $themeDefaults['login_body_font']);
        if (!isset($fontChoices[$bodyFontKey])) {
            $bodyFontKey = $themeDefaults['login_body_font'];
        }

        $settingsToSave = [
            'login_primary_color' => appNormalizeHexColor((string) ($_POST['login_primary_color'] ?? ''), $themeDefaults['login_primary_color']),
            'login_secondary_color' => appNormalizeHexColor((string) ($_POST['login_secondary_color'] ?? ''), $themeDefaults['login_secondary_color']),
            'login_accent_color' => appNormalizeHexColor((string) ($_POST['login_accent_color'] ?? ''), $themeDefaults['login_accent_color']),
            'login_brand_text_color' => appNormalizeHexColor((string) ($_POST['login_brand_text_color'] ?? ''), $themeDefaults['login_brand_text_color']),
            'login_heading_color' => appNormalizeHexColor((string) ($_POST['login_heading_color'] ?? ''), $themeDefaults['login_heading_color']),
            'login_body_text_color' => appNormalizeHexColor((string) ($_POST['login_body_text_color'] ?? ''), $themeDefaults['login_body_text_color']),
            'login_heading_font' => $headingFontKey,
            'login_body_font' => $bodyFontKey,
            'login_base_font_size' => (string) appNormalizeIntegerRange($_POST['login_base_font_size'] ?? null, 14, 20, (int) $themeDefaults['login_base_font_size']),
            'login_brand_title_size' => (string) appNormalizeIntegerRange($_POST['login_brand_title_size'] ?? null, 40, 72, (int) $themeDefaults['login_brand_title_size']),
        ];

        $saveOk = true;
        foreach ($settingsToSave as $settingKey => $settingValue) {
            if (!appUpsertSiteSetting($conn, $settingKey, $settingValue, (int) ($_SESSION['user_id'] ?? 0))) {
                $saveOk = false;
                break;
            }
        }

        if ($saveOk) {
            appLogActivity($conn, 'UPDATE_SITE_THEME', 'Updated public login page colors, fonts, and typography settings.', 'site_settings');
            $_SESSION['success_message'] = 'Login/index appearance updated successfully.';
            header('Location: website_content.php');
            exit();
        }

        $error = 'Failed to save appearance settings.';
    } elseif (isset($_POST['upload_slide'])) {
        $slideTitle = trim((string) ($_POST['slide_title'] ?? ''));
        $slideCaption = trim((string) ($_POST['slide_caption'] ?? ''));
        $sortOrder = appNormalizeIntegerRange($_POST['slide_sort_order'] ?? null, 0, 999, 50);
        $uploadedFile = $_FILES['slide_image'] ?? null;

        if ($slideTitle === '') {
            $error = 'Slide title is required.';
        } elseif (!is_array($uploadedFile) || (int) ($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $error = 'Choose a JPG or PNG image to upload as a slide.';
        } else {
            $extension = strtolower(pathinfo((string) ($uploadedFile['name'] ?? ''), PATHINFO_EXTENSION));
            if (!in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
                $error = 'Only JPG, JPEG, and PNG slide images are allowed.';
            } else {
                $uploadResult = uploadFile($uploadedFile, 'uploads/login_slides/');
                if (isset($uploadResult['error'])) {
                    $error = (string) $uploadResult['error'];
                } else {
                    $imagePath = 'uploads/login_slides/' . (string) $uploadResult['success'];
                    $stmt = $conn->prepare('INSERT INTO site_slides (title, caption, image_path, sort_order, is_active, created_by) VALUES (?, ?, ?, ?, 1, ?)');
                    if ($stmt) {
                        $createdBy = (int) ($_SESSION['user_id'] ?? 0);
                        $stmt->bind_param('sssii', $slideTitle, $slideCaption, $imagePath, $sortOrder, $createdBy);
                        if ($stmt->execute()) {
                            appLogActivity($conn, 'ADD_SITE_SLIDE', 'Uploaded a new public login slide: ' . $slideTitle, 'site_slides', (int) $stmt->insert_id);
                            $_SESSION['success_message'] = 'Slide image uploaded successfully.';
                            header('Location: website_content.php');
                            exit();
                        }
                    }

                    appDeleteManagedSlideFile($imagePath);
                    $error = 'Failed to save the uploaded slide.';
                }
            }
        }
    } elseif (isset($_POST['update_slide'])) {
        $slideId = (int) ($_POST['slide_id'] ?? 0);
        $slideTitle = trim((string) ($_POST['slide_title'] ?? ''));
        $slideCaption = trim((string) ($_POST['slide_caption'] ?? ''));
        $sortOrder = appNormalizeIntegerRange($_POST['slide_sort_order'] ?? null, 0, 999, 50);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($slideId <= 0 || $slideTitle === '') {
            $error = 'Slide title is required before saving changes.';
        } else {
            $stmt = $conn->prepare('UPDATE site_slides SET title = ?, caption = ?, sort_order = ?, is_active = ? WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('ssiii', $slideTitle, $slideCaption, $sortOrder, $isActive, $slideId);
                if ($stmt->execute()) {
                    appLogActivity($conn, 'UPDATE_SITE_SLIDE', 'Updated slide #' . $slideId . ' visibility and display order.', 'site_slides', $slideId);
                    $_SESSION['success_message'] = 'Slide updated successfully.';
                    header('Location: website_content.php');
                    exit();
                }
            }

            $error = 'Failed to update the selected slide.';
        }
    } elseif (isset($_POST['delete_slide'])) {
        $slideId = (int) ($_POST['slide_id'] ?? 0);
        if ($slideId <= 0) {
            $error = 'Invalid slide selected for deletion.';
        } else {
            $lookupStmt = $conn->prepare('SELECT title, image_path FROM site_slides WHERE id = ? LIMIT 1');
            $slideRow = null;
            if ($lookupStmt) {
                $lookupStmt->bind_param('i', $slideId);
                $lookupStmt->execute();
                $slideRow = $lookupStmt->get_result()->fetch_assoc();
            }

            if (!$slideRow) {
                $error = 'Slide not found.';
            } else {
                $deleteStmt = $conn->prepare('DELETE FROM site_slides WHERE id = ?');
                if ($deleteStmt) {
                    $deleteStmt->bind_param('i', $slideId);
                    if ($deleteStmt->execute()) {
                        appDeleteManagedSlideFile((string) ($slideRow['image_path'] ?? ''));
                        appLogActivity($conn, 'DELETE_SITE_SLIDE', 'Removed public login slide: ' . trim((string) ($slideRow['title'] ?? ('slide #' . $slideId))), 'site_slides', $slideId);
                        $_SESSION['success_message'] = 'Slide removed successfully.';
                        header('Location: website_content.php');
                        exit();
                    }
                }

                $error = 'Failed to remove the selected slide.';
            }
        }
    }
}

$currentTheme = appGetLoginThemeSettings($conn);
$slides = appGetLoginSlides($conn, false);

include '../includes/header.php';
?>

<?php echo renderPageHero([
    'eyebrow' => 'Website Content',
    'icon' => 'fa-palette',
    'title' => 'Login Page Appearance & Slides',
    'subtitle' => 'Control announcement access, public login slide images, and the visual style of the index/login experience from one workspace.',
    'badges' => ['Slides', 'Colors', 'Typography'],
]); ?>

<?php if ($success_message): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars(formatSuccessMessage($success_message)); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <div class="col-xl-7">
        <div class="card page-shell-card h-100">
            <div class="card-header bg-white border-0 pb-0">
                <h5 class="mb-1"><i class="fas fa-swatchbook me-2 text-primary"></i>Appearance Settings</h5>
                <div class="text-muted small">Customize colors, font style, and text size for the public login page.</div>
            </div>
            <div class="card-body pt-3">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="login_primary_color" class="form-label">Primary Color</label>
                            <input type="color" id="login_primary_color" name="login_primary_color" class="form-control form-control-color w-100" value="<?php echo htmlspecialchars($currentTheme['primary_color']); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="login_accent_color" class="form-label">Accent Color</label>
                            <input type="color" id="login_accent_color" name="login_accent_color" class="form-control form-control-color w-100" value="<?php echo htmlspecialchars($currentTheme['accent_color']); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="login_secondary_color" class="form-label">Secondary Color</label>
                            <input type="color" id="login_secondary_color" name="login_secondary_color" class="form-control form-control-color w-100" value="<?php echo htmlspecialchars($currentTheme['secondary_color']); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="login_brand_text_color" class="form-label">Hero Text Color</label>
                            <input type="color" id="login_brand_text_color" name="login_brand_text_color" class="form-control form-control-color w-100" value="<?php echo htmlspecialchars($currentTheme['brand_text_color']); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="login_heading_color" class="form-label">Form Heading Color</label>
                            <input type="color" id="login_heading_color" name="login_heading_color" class="form-control form-control-color w-100" value="<?php echo htmlspecialchars($currentTheme['heading_color']); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="login_body_text_color" class="form-label">Body Text Color</label>
                            <input type="color" id="login_body_text_color" name="login_body_text_color" class="form-control form-control-color w-100" value="<?php echo htmlspecialchars($currentTheme['body_text_color']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="login_heading_font" class="form-label">Heading Font Style</label>
                            <select id="login_heading_font" name="login_heading_font" class="form-select">
                                <?php foreach ($fontChoices as $fontKey => $fontMeta): ?>
                                    <option value="<?php echo htmlspecialchars($fontKey); ?>" <?php echo $currentTheme['heading_font_key'] === $fontKey ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) ($fontMeta['label'] ?? $fontKey)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="login_body_font" class="form-label">Body Font Style</label>
                            <select id="login_body_font" name="login_body_font" class="form-select">
                                <?php foreach ($fontChoices as $fontKey => $fontMeta): ?>
                                    <option value="<?php echo htmlspecialchars($fontKey); ?>" <?php echo $currentTheme['body_font_key'] === $fontKey ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) ($fontMeta['label'] ?? $fontKey)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="login_base_font_size" class="form-label">Body Font Size (px)</label>
                            <input type="number" id="login_base_font_size" name="login_base_font_size" class="form-control" min="14" max="20" value="<?php echo (int) $currentTheme['base_font_size']; ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="login_brand_title_size" class="form-label">Hero Title Size (px)</label>
                            <input type="number" id="login_brand_title_size" name="login_brand_title_size" class="form-control" min="40" max="72" value="<?php echo (int) $currentTheme['brand_title_size']; ?>">
                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-4">
                        <button type="submit" name="save_theme" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save Appearance</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card page-shell-card h-100">
            <div class="card-header bg-white border-0 pb-0">
                <h5 class="mb-1"><i class="fas fa-image me-2 text-primary"></i>Add New Slide</h5>
                <div class="text-muted small">Upload a new image for the login/index carousel and choose where it should appear.</div>
            </div>
            <div class="card-body pt-3">
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="slide_title" class="form-label">Slide Title</label>
                        <input type="text" id="slide_title" name="slide_title" class="form-control" maxlength="150" required>
                    </div>
                    <div class="mb-3">
                        <label for="slide_caption" class="form-label">Short Note</label>
                        <textarea id="slide_caption" name="slide_caption" class="form-control" rows="3" maxlength="255" placeholder="Optional internal note or caption"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="slide_sort_order" class="form-label">Display Order</label>
                        <input type="number" id="slide_sort_order" name="slide_sort_order" class="form-control" min="0" max="999" value="50">
                    </div>
                    <div class="mb-3">
                        <label for="slide_image" class="form-label">Slide Image</label>
                        <input type="file" id="slide_image" name="slide_image" class="form-control" accept=".jpg,.jpeg,.png,image/jpeg,image/png" required>
                        <div class="form-text">Recommended landscape image. Supported formats: JPG, JPEG, PNG.</div>
                    </div>
                    <button type="submit" name="upload_slide" class="btn btn-primary w-100"><i class="fas fa-upload me-2"></i>Upload Slide</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card page-shell-card">
    <div class="card-header bg-white border-0">
        <h5 class="mb-1"><i class="fas fa-layer-group me-2 text-primary"></i>Existing Slides</h5>
        <div class="text-muted small">Show, hide, reorder, or remove the slide images currently available on the login/index page.</div>
    </div>
    <div class="card-body">
        <?php if ($slides === []): ?>
            <div class="text-muted">No slide images are configured right now.</div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($slides as $slide): ?>
                    <div class="col-xl-4 col-md-6">
                        <div class="border rounded-4 overflow-hidden h-100 bg-white shadow-sm">
                            <img src="<?php echo htmlspecialchars(appBuildPublicAssetUrl((string) $slide['image_path'])); ?>" alt="<?php echo htmlspecialchars((string) $slide['title']); ?>" class="w-100" style="height: 220px; object-fit: cover; background: #eef3f8;">
                            <div class="p-3">
                                <form method="POST">
                                    <input type="hidden" name="slide_id" value="<?php echo (int) $slide['id']; ?>">
                                    <div class="mb-3">
                                        <label class="form-label">Title</label>
                                        <input type="text" name="slide_title" class="form-control" maxlength="150" value="<?php echo htmlspecialchars((string) $slide['title']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Short Note</label>
                                        <textarea name="slide_caption" class="form-control" rows="3" maxlength="255"><?php echo htmlspecialchars((string) $slide['caption']); ?></textarea>
                                    </div>
                                    <div class="row g-2 align-items-end">
                                        <div class="col-6">
                                            <label class="form-label">Order</label>
                                            <input type="number" name="slide_sort_order" class="form-control" min="0" max="999" value="<?php echo (int) $slide['sort_order']; ?>">
                                        </div>
                                        <div class="col-6">
                                            <div class="form-check border rounded-3 px-3 py-2 h-100 d-flex align-items-center">
                                                <input type="checkbox" class="form-check-input me-2" id="slide_visible_<?php echo (int) $slide['id']; ?>" name="is_active" <?php echo !empty($slide['is_active']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="slide_visible_<?php echo (int) $slide['id']; ?>">Visible</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2 mt-3">
                                        <button type="submit" name="update_slide" class="btn btn-outline-primary flex-grow-1">Save</button>
                                        <button type="submit" name="delete_slide" class="btn btn-outline-danger" onclick="return confirm('Remove this slide permanently?');">Remove</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>