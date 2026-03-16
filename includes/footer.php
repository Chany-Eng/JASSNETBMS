            </div>
            <footer class="app-site-footer text-center text-lg-end">
                <div class="small">&copy; <?php echo date('Y'); ?> JASSNET Incame. All rights reserved.</div>
            </footer>
        </div>
    </main>

    <?php
    $showWelcomeToast = !empty($_SESSION['login_success']);
    $welcomeUserName = trim((string) ($_SESSION['login_success_name'] ?? ($_SESSION['full_name'] ?? ($_SESSION['username'] ?? 'User'))));
    $welcomeRole = appFormatRoleList((string) ($_SESSION['role'] ?? ''));
    $authTransition = $_SESSION['auth_transition'] ?? null;
    $showLoginTransition = is_array($authTransition) && (($authTransition['type'] ?? '') === 'login');
    $loginTransitionName = trim((string) ($authTransition['name'] ?? $welcomeUserName));
    $loginTransitionTitle = 'Login successful';
    $loginTransitionCopy = ($loginTransitionName !== '' ? $loginTransitionName : 'User') . ', workspace yako iko tayari.';
    $welcomeToastTitle = 'Karibu tena, ' . ($welcomeUserName !== '' ? $welcomeUserName : 'User');
    $welcomeToastCopy = 'Workspace yako iko tayari. Endelea na approvals, reports, au quick actions zako.';
    if (appCurrentSessionHasRole(['Manager'])) {
        $welcomeToastCopy = 'Queue ya approvals inakusubiri. Fungua requests na uendelee na hatua zinazofuata.';
    } elseif (appCurrentSessionHasRole(['Accountant'])) {
        $welcomeToastCopy = 'Processing queue iko tayari. Kagua payouts, receipts, na final approvals zako.';
    } elseif (appCurrentSessionHasRole(['Director'])) {
        $welcomeToastCopy = 'Maamuzi ya mwisho yanakusubiri. Pitia approvals na operational priorities za leo.';
    } elseif (appCurrentSessionHasRole(['Store Keeper'])) {
        $welcomeToastCopy = 'Stock na issue requests ziko tayari. Angalia low stock na approvals za store.';
    } elseif (appCurrentSessionHasRole(['Technician'])) {
        $welcomeToastCopy = 'Station worklist yako iko tayari. Pitia progress updates na installation tasks.';
    } elseif (appCurrentSessionHasRole(['Sales'])) {
        $welcomeToastCopy = 'Lead na request follow-up zako ziko tayari. Endelea na entries na receipt updates.';
    }
    unset($_SESSION['login_success'], $_SESSION['login_success_name'], $_SESSION['auth_transition']);
    ?>
    <?php if ($showWelcomeToast): ?>
    <div class="toast-container app-welcome-toast-container position-fixed top-0 end-0 p-3">
        <div id="loginWelcomeToast" class="toast app-welcome-toast border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <div class="app-welcome-toast-title"><i class="fas fa-hand-sparkles me-2"></i><?php echo htmlspecialchars($welcomeToastTitle); ?></div>
                    <div class="app-welcome-toast-copy"><?php echo htmlspecialchars($welcomeToastCopy); ?></div>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="modal fade" id="sessionWarningModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-body text-center p-4 p-lg-5">
                    <div class="mb-3 text-warning" style="font-size: 2.4rem;"><i class="fas fa-hourglass-half"></i></div>
                    <h5 class="mb-2">Session warning</h5>
                    <p class="text-muted mb-2">No activity was detected. JASSNET will log you out soon if no action is taken.</p>
                    <div class="fw-semibold">Auto logout in <span id="sessionWarningCountdown">30</span> seconds</div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="sessionTimeoutModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-body text-center p-4 p-lg-5">
                    <div class="mb-3 text-warning" style="font-size: 2.4rem;"><i class="fas fa-clock"></i></div>
                    <h5 class="mb-2">Session timeout</h5>
                    <p class="text-muted mb-0">No activity was detected for 4 minutes. JASSNET is logging you out and returning you to the login page.</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo $base_path; ?>assets/js/main.js"></script>

    <script>
        // Sidebar toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebarCollapse = document.getElementById('sidebarCollapse');
            const loadingOverlay = document.getElementById('appLoadingOverlay');
            const sessionWarningModalEl = document.getElementById('sessionWarningModal');
            const sessionWarningCountdownEl = document.getElementById('sessionWarningCountdown');
            const sessionTimeoutModalEl = document.getElementById('sessionTimeoutModal');
            const inactivityLimitMs = <?= (int) SESSION_TIMEOUT * 1000 ?>;
            const inactivityWarningLeadMs = 30000;
            const inactivityLogoutUrl = <?php echo json_encode((defined('APP_URL') ? APP_URL : '') . '/logout.php?reason=inactive', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
            let inactivityWarningTimer = null;
            let inactivityLogoutTimer = null;
            let warningCountdownInterval = null;
            let inactivityTriggered = false;
            const showLoadingOverlay = function(options) {
                if (!loadingOverlay) {
                    return;
                }

                if (window.JassnetLoadingOverlay) {
                    window.JassnetLoadingOverlay.show(loadingOverlay, options || {});
                    return;
                }

                loadingOverlay.classList.add('show');
            };
            const hideLoadingOverlay = function() {
                if (!loadingOverlay) {
                    return;
                }

                if (window.JassnetLoadingOverlay) {
                    window.JassnetLoadingOverlay.hide(loadingOverlay);
                    window.JassnetLoadingOverlay.reset(loadingOverlay);
                    return;
                }

                loadingOverlay.classList.remove('show');
            };
            const hideSessionWarningPrompt = function() {
                if (warningCountdownInterval !== null) {
                    window.clearInterval(warningCountdownInterval);
                    warningCountdownInterval = null;
                }

                if (sessionWarningCountdownEl) {
                    sessionWarningCountdownEl.textContent = '30';
                }

                if (window.bootstrap && sessionWarningModalEl) {
                    bootstrap.Modal.getOrCreateInstance(sessionWarningModalEl).hide();
                }
            };
            const showSessionWarningPrompt = function() {
                if (inactivityTriggered) {
                    return;
                }

                let remainingSeconds = Math.ceil(inactivityWarningLeadMs / 1000);
                if (sessionWarningCountdownEl) {
                    sessionWarningCountdownEl.textContent = String(remainingSeconds);
                }

                if (window.bootstrap && sessionWarningModalEl) {
                    bootstrap.Modal.getOrCreateInstance(sessionWarningModalEl).show();
                }

                if (warningCountdownInterval !== null) {
                    window.clearInterval(warningCountdownInterval);
                }

                warningCountdownInterval = window.setInterval(function() {
                    remainingSeconds -= 1;
                    if (sessionWarningCountdownEl) {
                        sessionWarningCountdownEl.textContent = String(Math.max(remainingSeconds, 0));
                    }

                    if (remainingSeconds <= 0) {
                        window.clearInterval(warningCountdownInterval);
                        warningCountdownInterval = null;
                    }
                }, 1000);
            };
            const showSessionTimeoutPrompt = function() {
                if (inactivityTriggered) {
                    return;
                }

                inactivityTriggered = true;
                hideSessionWarningPrompt();

                if (window.bootstrap && sessionTimeoutModalEl) {
                    bootstrap.Modal.getOrCreateInstance(sessionTimeoutModalEl).show();
                }

                showLoadingOverlay({
                    title: 'Session timeout',
                    message: 'No activity was detected for 4 minutes. JASSNET is logging you out.'
                });

                window.setTimeout(function() {
                    window.location.href = inactivityLogoutUrl;
                }, 1600);
            };
            const resetInactivityTimer = function() {
                if (inactivityTriggered) {
                    return;
                }

                hideSessionWarningPrompt();

                if (inactivityWarningTimer !== null) {
                    window.clearTimeout(inactivityWarningTimer);
                }

                if (inactivityLogoutTimer !== null) {
                    window.clearTimeout(inactivityLogoutTimer);
                }

                inactivityWarningTimer = window.setTimeout(showSessionWarningPrompt, Math.max(inactivityLimitMs - inactivityWarningLeadMs, 0));
                inactivityLogoutTimer = window.setTimeout(showSessionTimeoutPrompt, inactivityLimitMs);
            };
            const isLogoutLink = function(link) {
                const href = (link.getAttribute('href') || '').toLowerCase();
                return href.endsWith('/logout.php') || href.endsWith('logout.php');
            };

            // Mobile sidebar toggle
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                    sidebarOverlay.classList.toggle('show');
                });
            }

            // Desktop sidebar collapse
            if (sidebarCollapse) {
                sidebarCollapse.addEventListener('click', function() {
                    sidebar.classList.toggle('collapsed');
                });
            }

            // Close sidebar when clicking overlay
            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', function() {
                    sidebar.classList.remove('show');
                    sidebarOverlay.classList.remove('show');
                });
            }

            // Close sidebar on mobile when clicking a menu item
            const menuLinks = sidebar.querySelectorAll('.menu a');
            menuLinks.forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth < 992) {
                        sidebar.classList.remove('show');
                        sidebarOverlay.classList.remove('show');
                    }
                });
            });

            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 992) {
                    sidebar.classList.remove('show');
                    sidebarOverlay.classList.remove('show');
                }
            });

            // Handle sidebar dropdown menus
            const dropdownToggles = sidebar.querySelectorAll('.dropdown-toggle');
            const syncDropdownState = function(toggle, forceExpanded = null) {
                const targetSelector = toggle.getAttribute('data-bs-target');
                if (!targetSelector) {
                    return;
                }

                const target = document.querySelector(targetSelector);
                if (!target) {
                    return;
                }

                const expanded = forceExpanded === null ? target.classList.contains('show') : !!forceExpanded;
                toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                target.classList.toggle('show', expanded);
            };

            dropdownToggles.forEach(toggle => {
                syncDropdownState(toggle);
            });

            dropdownToggles.forEach(toggle => {
                toggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Close other dropdowns
                    dropdownToggles.forEach(otherToggle => {
                        if (otherToggle !== toggle) {
                            syncDropdownState(otherToggle, false);
                        }
                    });
                    
                    // Toggle current dropdown
                    syncDropdownState(this, this.getAttribute('aria-expanded') !== 'true');
                });
            });

            // Close dropdowns when clicking outside
            document.addEventListener('click', function(e) {
                if (!sidebar.contains(e.target)) {
                    dropdownToggles.forEach(toggle => {
                        syncDropdownState(toggle, toggle.classList.contains('active'));
                    });
                }
            });

            const isDownloadIntentUrl = function(rawUrl) {
                if (!rawUrl || rawUrl === '#' || rawUrl.startsWith('javascript:') || rawUrl.startsWith('mailto:') || rawUrl.startsWith('tel:')) {
                    return false;
                }

                try {
                    const parsedUrl = new URL(rawUrl, window.location.href);
                    const format = (parsedUrl.searchParams.get('format') || '').toLowerCase();
                    const exportAction = (parsedUrl.searchParams.get('export') || '').toLowerCase();
                    const action = (parsedUrl.searchParams.get('action') || '').toLowerCase();
                    const download = (parsedUrl.searchParams.get('download') || '').toLowerCase();
                    const pathname = parsedUrl.pathname.toLowerCase();

                    if (['pdf', 'excel', 'csv', 'xlsx'].includes(format)) {
                        return true;
                    }

                    if (['pdf', 'excel', 'csv', 'xlsx', 'batch-pdf'].includes(exportAction)) {
                        return true;
                    }

                    if (['pdf', 'download'].includes(action) || ['pdf', 'excel', 'csv'].includes(download)) {
                        return true;
                    }

                    return pathname.endsWith('.pdf') || pathname.endsWith('.csv') || pathname.endsWith('.xlsx') || pathname.endsWith('.xls');
                } catch (error) {
                    return /(?:[?&](?:export|format|action|download)=(?:pdf|excel|csv|xlsx|batch-pdf))|\.(?:pdf|csv|xlsx|xls)(?:$|[?#])/i.test(rawUrl);
                }
            };

            const isDownloadIntentForm = function(form) {
                if (!form) {
                    return false;
                }

                if (form.hasAttribute('data-download')) {
                    return true;
                }

                const action = form.getAttribute('action') || window.location.href;
                if (isDownloadIntentUrl(action)) {
                    return true;
                }

                const formData = new FormData(form);
                const format = (formData.get('format') || '').toString().toLowerCase();
                const exportAction = (formData.get('export') || '').toString().toLowerCase();
                const actionValue = (formData.get('action') || '').toString().toLowerCase();
                const download = (formData.get('download') || '').toString().toLowerCase();

                return ['pdf', 'excel', 'csv', 'xlsx'].includes(format)
                    || ['pdf', 'excel', 'csv', 'xlsx', 'batch-pdf'].includes(exportAction)
                    || ['pdf', 'download'].includes(actionValue)
                    || ['pdf', 'excel', 'csv'].includes(download);
            };

            const shouldShowLoaderForLink = function(link) {
                if (!link || link.hasAttribute('data-no-loader')) {
                    return false;
                }

                const href = link.getAttribute('href') || '';
                if (href === '' || href === '#' || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) {
                    return false;
                }

                if (link.hasAttribute('download') || link.getAttribute('rel') === 'download' || isDownloadIntentUrl(href)) {
                    return false;
                }

                if (link.target === '_blank' || href.includes('#')) {
                    return false;
                }

                return true;
            };

            if (loadingOverlay) {
                document.querySelectorAll('a').forEach(function(link) {
                    link.addEventListener('click', function() {
                        if (shouldShowLoaderForLink(link)) {
                            if (isLogoutLink(link)) {
                                showLoadingOverlay({
                                    title: 'Signing out',
                                    message: 'Please wait while JASSNET securely ends your session.'
                                });
                            } else {
                                showLoadingOverlay();
                            }
                        }
                    });
                });

                document.querySelectorAll('form').forEach(function(form) {
                    form.addEventListener('submit', function() {
                        if (!form.hasAttribute('data-no-loader') && !isDownloadIntentForm(form)) {
                            showLoadingOverlay();
                        }
                    });
                });

                window.addEventListener('pageshow', function() {
                    hideLoadingOverlay();
                });
            }

            ['click', 'keydown', 'mousemove', 'scroll', 'touchstart'].forEach(function(eventName) {
                document.addEventListener(eventName, resetInactivityTimer, { passive: true });
            });

            window.addEventListener('focus', resetInactivityTimer);
            resetInactivityTimer();

            const loginTransition = <?php echo json_encode($showLoginTransition ? ['title' => $loginTransitionTitle, 'message' => $loginTransitionCopy] : null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            if (loginTransition && loadingOverlay) {
                showLoadingOverlay(loginTransition);
                window.setTimeout(function() {
                    hideLoadingOverlay();
                }, 1050);
            }

            if (window.location.hash) {
                const targetElement = document.querySelector(window.location.hash);
                if (targetElement) {
                    targetElement.classList.add('app-target-highlight');
                    targetElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    window.setTimeout(function() {
                        targetElement.classList.remove('app-target-highlight');
                    }, 2600);
                }
            }

            const loginWelcomeToast = document.getElementById('loginWelcomeToast');
            if (loginWelcomeToast && window.bootstrap) {
                window.setTimeout(function() {
                    bootstrap.Toast.getOrCreateInstance(loginWelcomeToast, { delay: 3600 }).show();
                }, 220);
            }
        });
    </script>
</body>
</html>