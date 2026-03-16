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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_theme'])) {
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
        $_SESSION['success_message'] = 'Appearance settings updated successfully.';
        header('Location: appearance_settings.php');
        exit();
    }

    $error = 'Failed to save appearance settings.';
}

$currentTheme = appGetLoginThemeSettings($conn);

include '../includes/header.php';
?>

<?php echo renderPageHero([
    'eyebrow' => 'Website Content',
    'icon' => 'fa-swatchbook',
    'title' => 'Appearance Settings',
    'badges' => ['Appearance', 'Colors', 'Typography'],
]); ?>

<?php if ($success_message): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars(formatSuccessMessage($success_message)); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="d-flex flex-wrap gap-2 mb-4">
    <a href="appearance_settings.php" class="btn btn-primary"><i class="fas fa-swatchbook me-2"></i>Appearance Settings</a>
    <a href="website_content.php" class="btn btn-outline-primary"><i class="fas fa-images me-2"></i>Existing Slides</a>
</div>

<div class="card page-shell-card">
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

<?php include '../includes/footer.php'; ?>
