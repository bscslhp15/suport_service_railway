document.addEventListener('DOMContentLoaded', function () {
    const focusable = document.querySelectorAll('input, select, button');
    if (focusable.length > 0) {
        focusable[0].focus();
    }

    const sidebar = document.querySelector('.side-nav');
    const pageShell = document.querySelector('.page-shell');
    const topbar = document.querySelector('.topbar');

    const isMobileView = () => window.matchMedia('(max-width: 768px)').matches;

    const expandSidebar = () => {
        if (sidebar && sidebar.classList.contains('collapsed')) {
            sidebar.classList.remove('collapsed');
            if (!isMobileView()) {
                if (pageShell) {
                    pageShell.style.paddingLeft = '280px';
                }
                if (topbar) {
                    topbar.style.left = '280px';
                    topbar.style.width = 'calc(100% - 280px)';
                }
            }
            // Keep page-shell classes and spacing consistent
            setLayoutSpacing();
        }
    };

    const closeAllSubmenus = () => {
        const openSubmenus = sidebar.querySelectorAll('.submenu.open');
        openSubmenus.forEach(submenu => {
            submenu.classList.remove('open');
            submenu.setAttribute('aria-hidden', 'true');
            const toggleButton = submenu.previousElementSibling;
            if (toggleButton && toggleButton.classList.contains('nav-toggle')) {
                toggleButton.setAttribute('aria-expanded', 'false');
            }
        });
    };

    const collapseSidebar = () => {
        if (sidebar && !sidebar.classList.contains('collapsed')) {
            sidebar.classList.add('collapsed');
            closeAllSubmenus();
            if (!isMobileView()) {
                if (pageShell) {
                    pageShell.style.paddingLeft = '80px';
                }
                if (topbar) {
                    topbar.style.left = '80px';
                    topbar.style.width = 'calc(100% - 80px)';
                }
            }
            // Keep page-shell classes and spacing consistent
            setLayoutSpacing();
        }
    };

    let shouldCollapseOnLeave = false;
    if (sidebar) {
        sidebar.addEventListener('mouseenter', function () {
            if (sidebar.classList.contains('collapsed')) {
                expandSidebar();
                shouldCollapseOnLeave = true;
            }
        });

        sidebar.addEventListener('mouseleave', function () {
            if (shouldCollapseOnLeave) {
                collapseSidebar();
                shouldCollapseOnLeave = false;
            }
            // Close any open submenus
            const openSubmenus = sidebar.querySelectorAll('.submenu.open');
            openSubmenus.forEach(submenu => {
                submenu.classList.remove('open');
                submenu.setAttribute('aria-hidden', 'true');
            });
            const expandedToggles = sidebar.querySelectorAll('.nav-toggle[aria-expanded="true"]');
            expandedToggles.forEach(toggle => {
                toggle.setAttribute('aria-expanded', 'false');
            });
        });
    }

    const sidebarToggle = document.getElementById('sidebarToggle');

    const setLayoutSpacing = () => {
        if (isMobileView()) {
            if (pageShell) pageShell.style.paddingLeft = '';
            if (topbar) {
                topbar.style.left = '';
                topbar.style.width = '';
            }
            if (pageShell) {
                pageShell.classList.remove('sidebar-expanded', 'sidebar-collapsed');
            }
            return;
        }
        const collapsed = sidebar && sidebar.classList.contains('collapsed');
        if (pageShell) {
            pageShell.style.paddingLeft = collapsed ? '80px' : '280px';
            pageShell.classList.toggle('sidebar-collapsed', collapsed);
            pageShell.classList.toggle('sidebar-expanded', !collapsed);
        }
        if (topbar) {
            topbar.style.left = collapsed ? '80px' : '280px';
            topbar.style.width = collapsed ? 'calc(100% - 80px)' : 'calc(100% - 280px)';
        }
    };

    const toggleSidebar = () => {
        if (!sidebar) return;
        if (sidebar.classList.contains('collapsed')) {
            expandSidebar();
        } else {
            collapseSidebar();
        }
        setLayoutSpacing();
    };

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function () {
            toggleSidebar();
            shouldCollapseOnLeave = false;
        });
    }

    // Ensure layout matches current sidebar state on load
    setLayoutSpacing();

    // Recompute layout spacing on resize (clear inline offsets when entering mobile)
    window.addEventListener('resize', function () {
        setLayoutSpacing();
    });

    // Move topbar to body on mobile so it's fixed to viewport edges (prevents centered container gaps)
    const originalTopbarParent = topbar ? topbar.parentElement : null;
    const moveTopbarForMobile = () => {
        if (!topbar) return;
        if (isMobileView()) {
            if (topbar.parentElement !== document.body) {
                document.body.appendChild(topbar);
            }
        } else {
            if (originalTopbarParent && topbar.parentElement !== originalTopbarParent) {
                originalTopbarParent.insertBefore(topbar, originalTopbarParent.firstChild);
            }
        }
    };

    // Run on load and resize
    moveTopbarForMobile();
    window.addEventListener('resize', moveTopbarForMobile);

    // Handle icon visibility and moving download link into sidebar on small mobile widths
    const handleMobileIcons = () => {
        // Use 475px breakpoint to cover 425/375/320 test widths reliably
        const isSmallMobile = window.matchMedia('(max-width: 475px)').matches;

        // Hide profile buttons on small mobile
        const profileButtons = document.querySelectorAll('[data-menu-target="profileMenu"]');
        profileButtons.forEach(btn => {
            btn.style.display = isSmallMobile ? 'none' : '';
        });

        // Move download/install button into sidebar as a nav item when small
        const installBtn = document.getElementById('installAppBtn');
        const sidebarNavSection = document.querySelector('.side-nav .nav-section');
        if (isSmallMobile) {
            if (installBtn) installBtn.style.display = 'none';
            if (sidebarNavSection && !document.getElementById('sidebar-download-link')) {
                const downloadAnchor = document.createElement('a');
                downloadAnchor.href = '#';
                downloadAnchor.id = 'sidebar-download-link';
                downloadAnchor.setAttribute('data-tooltip', 'Download');
                downloadAnchor.innerHTML = '<span class="nav-icon"><i class="fa-solid fa-download"></i></span><span class="nav-text">Download</span>';

                // Insert after About link if found, otherwise append
                const aboutLink = sidebarNavSection.querySelector('a[data-tooltip="About"]');
                if (aboutLink && aboutLink.parentElement) {
                    aboutLink.insertAdjacentElement('afterend', downloadAnchor);
                } else {
                    sidebarNavSection.appendChild(downloadAnchor);
                }
            }
        } else {
            if (installBtn) installBtn.style.display = '';
            const existing = document.getElementById('sidebar-download-link');
            if (existing) existing.remove();
        }
    };

    // Run on load and resize
    handleMobileIcons();
    window.addEventListener('resize', handleMobileIcons);

    const toggles = document.querySelectorAll('.nav-toggle');
    toggles.forEach(function (button) {
        const submenu = button.nextElementSibling;
        button.addEventListener('click', function () {
            if (!isMobileView() && sidebar && sidebar.classList.contains('collapsed')) {
                expandSidebar();
            }
            const isOpen = button.getAttribute('aria-expanded') === 'true';
            button.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
            submenu.setAttribute('aria-hidden', isOpen ? 'true' : 'false');
            submenu.classList.toggle('open', !isOpen);
        });
    });

    const navGroups = document.querySelectorAll('.nav-group');
    navGroups.forEach(group => {
        group.addEventListener('mouseleave', (event) => {
            // If the pointer moved to another element inside the sidebar, keep submenu open.
            // Only close when the pointer left the entire sidebar area.
            const related = event.relatedTarget;
            if (related && related.closest && related.closest('.side-nav')) {
                // Pointer is still inside the sidebar; do not close submenu
                return;
            }

            const submenu = group.querySelector('.submenu.open');
            const toggleButton = group.querySelector('.nav-toggle[aria-expanded="true"]');
            if (submenu) {
                submenu.classList.remove('open');
                submenu.setAttribute('aria-hidden', 'true');
            }
            if (toggleButton) {
                toggleButton.setAttribute('aria-expanded', 'false');
            }
        });
    });

    const menuButtons = document.querySelectorAll('[data-menu-target]');
    const topbarMenus = document.querySelectorAll('.topbar-menu');

    const closeMenus = () => {
        topbarMenus.forEach(menu => {
            menu.classList.remove('visible');
        });
        menuButtons.forEach(button => {
            button.setAttribute('aria-expanded', 'false');
        });
    };

    menuButtons.forEach(button => {
        const target = document.getElementById(button.dataset.menuTarget);
        if (!target) {
            return;
        }

        button.addEventListener('click', function (event) {
            const isOpen = target.classList.contains('visible');
            closeMenus();
            if (!isOpen) {
                target.classList.add('visible');
                button.setAttribute('aria-expanded', 'true');
            }
            event.stopPropagation();
        });
    });

    document.addEventListener('click', function (event) {
        if (!event.target.closest('.topbar-menu') && !event.target.closest('[data-menu-target]')) {
            closeMenus();
        }
    });

    const logoutLinks = document.querySelectorAll('a.logout-link');
    logoutLinks.forEach(link => {
        link.addEventListener('click', function (event) {
            const confirmLogout = confirm('Are you sure you want to logout?');
            if (!confirmLogout) {
                event.preventDefault();
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeMenus();
        }
    });

    // Password visibility toggle
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');

    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);

            // Toggle eye icon
            if (type === 'password') {
                this.classList.remove('fa-eye-slash');
                this.classList.add('fa-eye');
            } else {
                this.classList.remove('fa-eye');
                this.classList.add('fa-eye-slash');
            }
        });
    }

    const initTabs = ({ buttonSelector, panelSelector, dataKey = 'tab' }) => {
        const buttons = document.querySelectorAll(buttonSelector);
        const panels = document.querySelectorAll(panelSelector);
        if (!buttons.length || !panels.length) {
            return;
        }

        buttons.forEach(button => {
            button.addEventListener('click', event => {
                event.preventDefault();
                const targetId = button.dataset[dataKey];
                if (!targetId) {
                    return;
                }

                buttons.forEach(btn => {
                    const isActive = btn === button;
                    btn.classList.toggle('active', isActive);
                    btn.setAttribute('aria-selected', String(isActive));
                });

                panels.forEach(panel => {
                    panel.classList.toggle('active', panel.id === targetId);
                });
            });
        });
    };

    initTabs({ buttonSelector: '.tab-button', panelSelector: '.tab-panel', dataKey: 'tab' });
    initTabs({ buttonSelector: '.sub-tab', panelSelector: '.subtab-panel', dataKey: 'subtab' });
    initTabs({ buttonSelector: '.report-tab', panelSelector: '.report-tab-panel', dataKey: 'target' });
});
