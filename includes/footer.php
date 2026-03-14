            </div>
            <footer class="app-site-footer text-center text-lg-end">
                <div class="small">&copy; <?php echo date('Y'); ?> JASSNET Incame. All rights reserved.</div>
            </footer>
        </div>
    </main>

    <?php
    $showWelcomeToast = !empty($_SESSION['login_success']);
    $welcomeUserName = trim((string) ($_SESSION['login_success_name'] ?? ($_SESSION['full_name'] ?? ($_SESSION['username'] ?? 'User'))));
    $welcomeRole = strtolower(trim((string) ($_SESSION['role'] ?? '')));
    $authTransition = $_SESSION['auth_transition'] ?? null;
    $showLoginTransition = is_array($authTransition) && (($authTransition['type'] ?? '') === 'login');
    $loginTransitionName = trim((string) ($authTransition['name'] ?? $welcomeUserName));
    $loginTransitionTitle = 'Login successful';
    $loginTransitionCopy = ($loginTransitionName !== '' ? $loginTransitionName : 'User') . ', workspace yako iko tayari.';
    $welcomeToastTitle = 'Karibu tena, ' . ($welcomeUserName !== '' ? $welcomeUserName : 'User');
    $welcomeToastCopy = 'Workspace yako iko tayari. Endelea na approvals, reports, au quick actions zako.';
    if (str_contains($welcomeRole, 'manager')) {
        $welcomeToastCopy = 'Queue ya approvals inakusubiri. Fungua requests na uendelee na hatua zinazofuata.';
    } elseif (str_contains($welcomeRole, 'accountant')) {
        $welcomeToastCopy = 'Processing queue iko tayari. Kagua payouts, receipts, na final approvals zako.';
    } elseif (str_contains($welcomeRole, 'director')) {
        $welcomeToastCopy = 'Maamuzi ya mwisho yanakusubiri. Pitia approvals na operational priorities za leo.';
    } elseif (str_contains($welcomeRole, 'store keeper')) {
        $welcomeToastCopy = 'Stock na issue requests ziko tayari. Angalia low stock na approvals za store.';
    } elseif (str_contains($welcomeRole, 'technician')) {
        $welcomeToastCopy = 'Station worklist yako iko tayari. Pitia progress updates na installation tasks.';
    } elseif (str_contains($welcomeRole, 'sales')) {
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

            const shouldShowLoaderForLink = function(link) {
                if (!link || link.hasAttribute('data-no-loader')) {
                    return false;
                }

                const href = link.getAttribute('href') || '';
                if (href === '' || href === '#' || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) {
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
                        if (!form.hasAttribute('data-no-loader')) {
                            showLoadingOverlay();
                        }
                    });
                });

                window.addEventListener('pageshow', function() {
                    hideLoadingOverlay();
                });
            }

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