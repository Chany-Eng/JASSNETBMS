            </div>
            <footer class="app-site-footer">
                <div class="row g-4 align-items-center mx-0">
                    <div class="col-lg-5 px-0">
                        <h5 class="text-white mb-2">JASSNET Business Management System</h5>
                        <p class="mb-0 small">Operations, approvals, payouts, inventory, stations, and announcements managed in one workspace.</p>
                    </div>
                    <div class="col-lg-4 px-0">
                        <div class="small text-uppercase fw-semibold mb-2" style="letter-spacing: 0.08em; color: #93c5fd;">Quick Access</div>
                        <div class="d-flex flex-wrap gap-3 small">
                            <a href="<?php echo $base_path; ?>dashboard.php" class="text-decoration-none">Dashboard</a>
                            <a href="<?php echo $base_path; ?>pages/view_income.php" class="text-decoration-none">Income</a>
                            <a href="<?php echo $base_path; ?>pages/view_expense_requests.php" class="text-decoration-none">Expenses</a>
                            <a href="<?php echo $base_path; ?>pages/stations.php" class="text-decoration-none">Stations</a>
                        </div>
                    </div>
                    <div class="col-lg-3 px-0 text-lg-end">
                        <div class="small text-uppercase fw-semibold mb-2" style="letter-spacing: 0.08em; color: #93c5fd;">Copyright</div>
                        <div class="small">&copy; <?php echo date('Y'); ?> JASSNET Incame. All rights reserved.</div>
                        <div class="small">Built for internal business operations.</div>
                    </div>
                </div>
            </footer>
        </div>
    </main>

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
            dropdownToggles.forEach(toggle => {
                toggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Close other dropdowns
                    dropdownToggles.forEach(otherToggle => {
                        if (otherToggle !== toggle) {
                            const otherTarget = document.querySelector(otherToggle.getAttribute('data-bs-target'));
                            if (otherTarget) {
                                otherTarget.classList.remove('show');
                                otherToggle.setAttribute('aria-expanded', 'false');
                            }
                        }
                    });
                    
                    // Toggle current dropdown
                    const target = document.querySelector(this.getAttribute('data-bs-target'));
                    if (target) {
                        const isExpanded = this.getAttribute('aria-expanded') === 'true';
                        this.setAttribute('aria-expanded', !isExpanded);
                        target.classList.toggle('show');
                    }
                });
            });

            // Close dropdowns when clicking outside
            document.addEventListener('click', function(e) {
                if (!sidebar.contains(e.target)) {
                    dropdownToggles.forEach(toggle => {
                        const target = document.querySelector(toggle.getAttribute('data-bs-target'));
                        if (target) {
                            target.classList.remove('show');
                            toggle.setAttribute('aria-expanded', 'false');
                        }
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
                            loadingOverlay.classList.add('show');
                        }
                    });
                });

                document.querySelectorAll('form').forEach(function(form) {
                    form.addEventListener('submit', function() {
                        if (!form.hasAttribute('data-no-loader')) {
                            loadingOverlay.classList.add('show');
                        }
                    });
                });

                window.addEventListener('pageshow', function() {
                    loadingOverlay.classList.remove('show');
                });
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
        });
    </script>
</body>
</html>