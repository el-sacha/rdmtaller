document.addEventListener('DOMContentLoaded', () => {
  const navbarDropdownToggles: NodeListOf<HTMLElement> = document.querySelectorAll('.navbar .dropdown-toggle');

  navbarDropdownToggles.forEach((toggle: HTMLElement) => {
    // Omitir el toggler principal del navbar para la vista móvil
    if (toggle.classList.contains('navbar-toggler')) {
      return;
    }

    toggle.addEventListener('click', (event: MouseEvent) => {
      if (toggle.getAttribute('href') === '#') {
        event.preventDefault();
      }

      const parentLi = toggle.closest('.nav-item.dropdown') as HTMLElement | null;
      if (!parentLi) return;

      const an_Menu = parentLi.querySelector('.dropdown-menu') as HTMLElement | null;
      if (!an_Menu) return;

      // Cerrar otros dropdowns abiertos en la misma sección del navbar
      const navSection = parentLi.closest('.navbar-nav') as HTMLElement | null;
      if (navSection) {
        const openDropdownsInSameSection: NodeListOf<HTMLElement> =
          navSection.querySelectorAll('.nav-item.dropdown .dropdown-menu.show');

        openDropdownsInSameSection.forEach((openDropdown: HTMLElement) => {
          if (openDropdown !== an_Menu) {
            openDropdown.classList.remove('show');
            const otherToggle = openDropdown.closest('.nav-item.dropdown')?.querySelector('.dropdown-toggle') as HTMLElement | null;
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
  document.addEventListener('click', (event: MouseEvent) => {
    const openDropdowns: NodeListOf<HTMLElement> = document.querySelectorAll('.navbar .dropdown-menu.show');
    const targetElement = event.target as HTMLElement;

    if (targetElement.closest('.navbar-toggler')) {
      return;
    }

    let isClickInsideADropdown = false;
    navbarDropdownToggles.forEach((toggle: HTMLElement) => {
      const parentDropdown = toggle.closest('.nav-item.dropdown');
      if (parentDropdown?.contains(targetElement)) {
        isClickInsideADropdown = true;
      }
    });

    if (!isClickInsideADropdown) {
      openDropdowns.forEach((openDropdown: HTMLElement) => {
        openDropdown.classList.remove('show');
        const toggle = openDropdown.closest('.nav-item.dropdown')?.querySelector('.dropdown-toggle') as HTMLElement | null;
        if (toggle) {
          toggle.setAttribute('aria-expanded', 'false');
        }
      });
    }
  });
});
