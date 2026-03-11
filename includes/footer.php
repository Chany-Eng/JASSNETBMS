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
        });
    </script>
</body>
</html>