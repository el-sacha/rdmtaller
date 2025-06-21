document.addEventListener('DOMContentLoaded', () => {
    const navbarDropdownToggles = document.querySelectorAll('.navbar .dropdown-toggle');
    navbarDropdownToggles.forEach((toggle) => {
        // Omitir el toggler principal del navbar para la vista móvil
        if (toggle.classList.contains('navbar-toggler')) {
            return;
        }
        toggle.addEventListener('click', (event) => {
            if (toggle.getAttribute('href') === '#') {
                event.preventDefault();
            }
            const parentLi = toggle.closest('.nav-item.dropdown');
            if (!parentLi)
                return;
            const an_Menu = parentLi.querySelector('.dropdown-menu');
            if (!an_Menu)
                return;
            // Cerrar otros dropdowns abiertos en la misma sección del navbar
            const navSection = parentLi.closest('.navbar-nav');
            if (navSection) {
                const openDropdownsInSameSection = navSection.querySelectorAll('.nav-item.dropdown .dropdown-menu.show');
                openDropdownsInSameSection.forEach((openDropdown) => {
                    if (openDropdown !== an_Menu) {
                        openDropdown.classList.remove('show');
                        const otherToggle = openDropdown.closest('.nav-item.dropdown')?.querySelector('.dropdown-toggle');
                        if (otherToggle) {
                            otherToggle.setAttribute('aria-expanded', 'false');
                        }
                    }
                });
            }
            // Alternar el dropdown actual
            const isShown = an_Menu.classList.toggle('show');
            toggle.setAttribute('aria-expanded', isShown.toString());
            event.stopPropagation();
        });
    });
    // Cerrar dropdowns si se hace clic fuera
    document.addEventListener('click', (event) => {
        const openDropdowns = document.querySelectorAll('.navbar .dropdown-menu.show');
        const targetElement = event.target;
        if (targetElement.closest('.navbar-toggler')) {
            return;
        }
        let isClickInsideADropdown = false;
        navbarDropdownToggles.forEach((toggle) => {
            const parentDropdown = toggle.closest('.nav-item.dropdown');
            if (parentDropdown?.contains(targetElement)) {
                isClickInsideADropdown = true;
            }
        });
        if (!isClickInsideADropdown) {
            openDropdowns.forEach((openDropdown) => {
                openDropdown.classList.remove('show');
                const toggle = openDropdown.closest('.nav-item.dropdown')?.querySelector('.dropdown-toggle');
                if (toggle) {
                    toggle.setAttribute('aria-expanded', 'false');
                }
            });
        }
    });
});
//# sourceMappingURL=navbarMenu.js.map
