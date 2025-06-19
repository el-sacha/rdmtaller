document.addEventListener('DOMContentLoaded', function () {
    var dropdownToggles = document.querySelectorAll('.navbar .dropdown-toggle');

    dropdownToggles.forEach(function (toggle) {
        // Skip the main navbar toggler button for mobile view
        if (toggle.classList.contains('navbar-toggler')) {
            return;
        }
        // Also skip user profile dropdown usually handled well by Bootstrap
        if (toggle.closest('ul.navbar-nav:not(.me-auto)')) {
             // This targets dropdowns in the right-aligned section of navbar
             // Check if it's the user dropdown by ID if a more specific selector is needed
             // if (toggle.id === 'navbarUserDropdown') return; // No longer skipping based on ID
        }


        toggle.addEventListener('click', function (event) {
            // Prevent default for href="#" to avoid jumping to top of page
            if (toggle.getAttribute('href') === '#') {
                event.preventDefault();
            }
            // Stop propagation to prevent the document click listener from immediately closing it
            // if it was meant to open.
            event.stopPropagation();

            var parentLi = toggle.closest('.nav-item.dropdown');
            if (!parentLi) return;

            var an_Menu = parentLi.querySelector('.dropdown-menu');
            if (!an_Menu) return;

            // Close other open dropdowns in the same navbar section (me-auto or the right-aligned one)
            // This helps mimic expected Bootstrap behavior where only one dropdown is open at a time.
            var navSection = parentLi.closest('.navbar-nav');
            if(navSection){
                var openDropdownsInSameSection = navSection.querySelectorAll('.nav-item.dropdown .dropdown-menu.show');
                openDropdownsInSameSection.forEach(function(openDropdown) {
                    if (openDropdown !== an_Menu) {
                        openDropdown.classList.remove('show');
                        var otherToggle = openDropdown.closest('.nav-item.dropdown').querySelector('.dropdown-toggle');
                        if(otherToggle) otherToggle.setAttribute('aria-expanded', 'false');
                    }
                });
            }

            // Toggle current dropdown
            var isShown = an_Menu.classList.toggle('show');
            toggle.setAttribute('aria-expanded', isShown.toString());
        });
    });

    // Close dropdowns if clicking outside
    document.addEventListener('click', function (event) {
        var openDropdowns = document.querySelectorAll('.navbar .dropdown-menu.show');
        var targetElement = event.target;

        // If the click is on the navbar toggler itself, don't close dropdowns, BS handles it
        if (targetElement.closest('.navbar-toggler')) {
            return;
        }

        var isInsideADropdownToggleOrMenu = false;
        // Check if the click originated from within any dropdown toggle or its menu
        document.querySelectorAll('.navbar .nav-item.dropdown').forEach(function(dropdownNavItem){
            if(dropdownNavItem.contains(targetElement)){
                isInsideADropdownToggleOrMenu = true;
            }
        });

        if (!isInsideADropdownToggleOrMenu) {
            openDropdowns.forEach(function (openDropdown) {
                openDropdown.classList.remove('show');
                var toggle = openDropdown.closest('.nav-item.dropdown').querySelector('.dropdown-toggle');
                if(toggle) toggle.setAttribute('aria-expanded', 'false');
            });
        }
    });
});
