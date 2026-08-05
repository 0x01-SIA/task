document.documentElement.classList.add('js-ready');

const customerSelect = document.querySelector('[data-customer-location-filter="customer"]');
const locationSelect = document.querySelector('[data-customer-location-filter="location"]');

if (customerSelect && locationSelect) {
    const placeholderOption = locationSelect.options[0] ?? null;
    const placeholderValue = placeholderOption?.value ?? '';
    const placeholderLabel = placeholderOption?.textContent ?? '';
    const emptyLabel = locationSelect.getAttribute('data-empty-label') ?? 'No locations available for this customer';
    let locationCatalog = [];

    try {
        const encodedCatalog = locationSelect.getAttribute('data-location-catalog') ?? '';
        const decodedCatalog = encodedCatalog === '' ? '[]' : atob(encodedCatalog);
        locationCatalog = JSON.parse(decodedCatalog);
    } catch (error) {
        locationCatalog = [];
    }

    const buildOptionLabel = (location) => {
        const addressLine = typeof location.address_line === 'string' ? location.address_line.trim() : '';

        return addressLine === '' ? location.name : `${location.name} - ${addressLine}`;
    };

    const updateLocationOptions = () => {
        const customerId = customerSelect.value;
        const selectedValue = locationSelect.value;
        const matchingLocations = customerId === ''
            ? []
            : locationCatalog.filter((location) => String(location.customer_id) === customerId);

        locationSelect.innerHTML = '';
        locationSelect.add(new Option(placeholderLabel, placeholderValue));

        matchingLocations.forEach((location) => {
            locationSelect.add(new Option(buildOptionLabel(location), String(location.id)));
        });

        const stillValid = matchingLocations.some((location) => String(location.id) === selectedValue);

        if (matchingLocations.length === 0 && customerId !== '') {
            locationSelect.add(new Option(emptyLabel, placeholderValue));
            locationSelect.value = placeholderValue;
            return;
        }

        if (stillValid) {
            locationSelect.value = selectedValue;
            return;
        }

        if (matchingLocations.length === 1) {
            locationSelect.value = String(matchingLocations[0].id);
            return;
        }

        locationSelect.value = placeholderValue;
    };

    customerSelect.addEventListener('change', updateLocationOptions);
    updateLocationOptions();
}

document.querySelectorAll('[data-map-link]').forEach((link) => {
    if (!(link instanceof HTMLAnchorElement)) {
        return;
    }

    const nativeHref = link.getAttribute('href') ?? '';
    const fallbackHref = link.getAttribute('data-map-fallback') ?? '';

    if (nativeHref === '' || fallbackHref === '' || !nativeHref.startsWith('geo:')) {
        return;
    }

    link.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        event.preventDefault();

        let fallbackTimer = window.setTimeout(() => {
            window.open(fallbackHref, '_blank', 'noopener,noreferrer');
            fallbackTimer = 0;
        }, 700);

        const cancelFallback = () => {
            if (fallbackTimer !== 0) {
                window.clearTimeout(fallbackTimer);
                fallbackTimer = 0;
            }

            window.removeEventListener('pagehide', cancelFallback);
            document.removeEventListener('visibilitychange', handleVisibilityChange);
        };

        const handleVisibilityChange = () => {
            if (document.visibilityState === 'hidden') {
                cancelFallback();
            }
        };

        window.addEventListener('pagehide', cancelFallback, { once: true });
        document.addEventListener('visibilitychange', handleVisibilityChange);
        window.location.href = nativeHref;
    });
});

document.querySelectorAll('[data-signature-form]').forEach((form) => {
    const canvas = form.querySelector('[data-signature-canvas]');
    const output = form.querySelector('[data-signature-output]');
    const clearButton = form.querySelector('[data-signature-clear]');
    const pad = form.querySelector('[data-signature-pad]');

    if (!(canvas instanceof HTMLCanvasElement) || !(output instanceof HTMLInputElement) || !(clearButton instanceof HTMLButtonElement) || !(pad instanceof HTMLElement)) {
        return;
    }

    const context = canvas.getContext('2d');

    if (context === null) {
        return;
    }

    let drawing = false;
    let hasInk = false;
    let lastPoint = null;
    let activePointerId = null;

    const setupContext = () => {
        context.lineCap = 'round';
        context.lineJoin = 'round';
        context.strokeStyle = '#0f172a';
        context.lineWidth = 2.4;
    };

    const resizeCanvas = () => {
        const ratio = window.devicePixelRatio || 1;
        const bounds = canvas.getBoundingClientRect();
        const nextWidth = Math.max(320, Math.round(bounds.width));
        const nextHeight = Math.max(180, Math.round(nextWidth * 0.375));
        const snapshot = hasInk ? canvas.toDataURL('image/png') : output.value;

        canvas.width = Math.round(nextWidth * ratio);
        canvas.height = Math.round(nextHeight * ratio);
        canvas.style.height = `${nextHeight}px`;
        context.setTransform(1, 0, 0, 1, 0, 0);
        context.scale(ratio, ratio);
        context.clearRect(0, 0, nextWidth, nextHeight);
        setupContext();

        if (snapshot !== '') {
            restoreSignature(snapshot);
        }
    };

    const restoreSignature = (dataUrl) => {
        const image = new Image();
        image.onload = () => {
            const width = canvas.width / (window.devicePixelRatio || 1);
            const height = canvas.height / (window.devicePixelRatio || 1);
            context.clearRect(0, 0, width, height);
            context.drawImage(image, 0, 0, width, height);
            hasInk = true;
            output.value = dataUrl;
            pad.classList.remove('is-empty');
        };
        image.src = dataUrl;
    };

    const pointFromEvent = (event) => {
        const bounds = canvas.getBoundingClientRect();
        const source = 'touches' in event && event.touches.length > 0
            ? event.touches[0]
            : 'changedTouches' in event && event.changedTouches.length > 0
                ? event.changedTouches[0]
                : event;

        return {
            x: source.clientX - bounds.left,
            y: source.clientY - bounds.top,
        };
    };

    const beginStroke = (event) => {
        event.preventDefault();

        if ('pointerId' in event) {
            activePointerId = event.pointerId;
            if (typeof canvas.setPointerCapture === 'function') {
                canvas.setPointerCapture(event.pointerId);
            }
        }

        drawing = true;
        lastPoint = pointFromEvent(event);
        hasInk = true;
        pad.classList.remove('is-empty');
        context.beginPath();
        context.moveTo(lastPoint.x, lastPoint.y);
    };

    const continueStroke = (event) => {
        if (!drawing || lastPoint === null) {
            return;
        }

        if ('pointerId' in event && activePointerId !== null && event.pointerId !== activePointerId) {
            return;
        }

        event.preventDefault();
        const nextPoint = pointFromEvent(event);
        context.beginPath();
        context.moveTo(lastPoint.x, lastPoint.y);
        context.lineTo(nextPoint.x, nextPoint.y);
        context.stroke();
        lastPoint = nextPoint;
    };

    const endStroke = (event) => {
        if (!drawing) {
            return;
        }

        if (event && 'pointerId' in event && activePointerId !== null && event.pointerId !== activePointerId) {
            return;
        }

        if (event && 'pointerId' in event && typeof canvas.releasePointerCapture === 'function') {
            try {
                canvas.releasePointerCapture(event.pointerId);
            } catch (error) {
                // Ignore browsers that reject release when capture was not set.
            }
        }

        drawing = false;
        activePointerId = null;
        lastPoint = null;
        output.value = hasInk ? canvas.toDataURL('image/png') : '';
    };

    const clearSignature = () => {
        const width = canvas.width / (window.devicePixelRatio || 1);
        const height = canvas.height / (window.devicePixelRatio || 1);
        context.clearRect(0, 0, width, height);
        setupContext();
        drawing = false;
        hasInk = false;
        activePointerId = null;
        lastPoint = null;
        output.value = '';
        pad.classList.add('is-empty');
    };

    const initialSignature = output.value;

    setupContext();
    clearSignature();
    resizeCanvas();

    if (initialSignature !== '') {
        restoreSignature(initialSignature);
    }

    if ('PointerEvent' in window) {
        canvas.addEventListener('pointerdown', beginStroke);
        canvas.addEventListener('pointermove', continueStroke);
        canvas.addEventListener('pointerup', endStroke);
        canvas.addEventListener('pointerleave', endStroke);
        canvas.addEventListener('pointercancel', endStroke);
    } else {
        canvas.addEventListener('mousedown', beginStroke);
        canvas.addEventListener('mousemove', continueStroke);
        canvas.addEventListener('mouseup', endStroke);
        canvas.addEventListener('mouseleave', endStroke);
        canvas.addEventListener('touchstart', beginStroke, { passive: false });
        canvas.addEventListener('touchmove', continueStroke, { passive: false });
        canvas.addEventListener('touchend', endStroke, { passive: false });
        canvas.addEventListener('touchcancel', endStroke, { passive: false });
    }
    clearButton.addEventListener('click', clearSignature);
    form.addEventListener('submit', () => {
        output.value = hasInk ? canvas.toDataURL('image/png') : '';
    });
    window.addEventListener('resize', resizeCanvas);
});

(() => {
    const root = document.documentElement;
    const body = document.body;
    const storageKey = 'task-theme-preference';
    const media = window.matchMedia('(prefers-color-scheme: dark)');
    const menuToggle = document.querySelector('[data-menu-toggle]');
    const menuPanel = document.querySelector('[data-menu-panel]');
    const drawer = document.querySelector('[data-drawer]');
    const drawerToggle = document.querySelector('[data-drawer-toggle]');
    const drawerClose = document.querySelector('[data-drawer-close]');
    const overlay = document.querySelector('[data-app-overlay]');
    const themeButtons = Array.from(document.querySelectorAll('[data-theme-option]'));
    let lastDrawerTrigger = null;
    let menuOpen = false;
    let drawerOpen = false;

    const focusableSelector = [
        'a[href]',
        'button:not([disabled])',
        'input:not([disabled])',
        'select:not([disabled])',
        'textarea:not([disabled])',
        '[tabindex]:not([tabindex="-1"])',
    ].join(',');

    const resolveTheme = (preference) => (
        preference === 'dark' || (preference === 'system' && media.matches) ? 'dark' : 'light'
    );

    const applyTheme = (preference) => {
        const normalizedPreference = ['light', 'dark', 'system'].includes(preference) ? preference : 'system';
        const resolvedTheme = resolveTheme(normalizedPreference);

        root.setAttribute('data-theme-preference', normalizedPreference);
        root.setAttribute('data-theme', resolvedTheme);
        root.setAttribute('data-bs-theme', resolvedTheme);
        localStorage.setItem(storageKey, normalizedPreference);

        themeButtons.forEach((button) => {
            const isActive = button.getAttribute('data-theme-option') === normalizedPreference;
            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-checked', isActive ? 'true' : 'false');
        });
    };

    const storedTheme = localStorage.getItem(storageKey) || root.getAttribute('data-theme-preference') || 'system';
    applyTheme(storedTheme);
    media.addEventListener('change', () => {
        const currentPreference = root.getAttribute('data-theme-preference') || 'system';

        if (currentPreference === 'system') {
            applyTheme('system');
        }
    });

    themeButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const selectedTheme = button.getAttribute('data-theme-option') || 'system';
            applyTheme(selectedTheme);
        });
    });

    const firstFocusable = (container) => {
        if (!(container instanceof HTMLElement)) {
            return null;
        }

        return container.querySelector(focusableSelector);
    };

    const updateOverlay = () => {
        if (!(overlay instanceof HTMLElement)) {
            return;
        }

        overlay.hidden = !drawerOpen;
        body.classList.toggle('app-shell--locked', drawerOpen);
    };

    const closeMenu = ({ restoreFocus = true } = {}) => {
        if (!menuOpen || !(menuPanel instanceof HTMLElement) || !(menuToggle instanceof HTMLButtonElement)) {
            return;
        }

        menuOpen = false;
        menuPanel.hidden = true;
        menuToggle.setAttribute('aria-expanded', 'false');

        if (restoreFocus) {
            menuToggle.focus();
        }

        updateOverlay();
    };

    const openMenu = () => {
        if (!(menuPanel instanceof HTMLElement) || !(menuToggle instanceof HTMLButtonElement)) {
            return;
        }

        closeDrawer({ restoreFocus: false });
        menuOpen = true;
        menuPanel.hidden = false;
        menuToggle.setAttribute('aria-expanded', 'true');
        updateOverlay();
        firstFocusable(menuPanel)?.focus();
    };

    const closeDrawer = ({ restoreFocus = true } = {}) => {
        if (!drawerOpen || !(drawer instanceof HTMLElement)) {
            return;
        }

        drawerOpen = false;
        drawer.hidden = true;
        drawer.setAttribute('aria-hidden', 'true');

        if (drawerToggle instanceof HTMLButtonElement) {
            drawerToggle.setAttribute('aria-expanded', 'false');
        }

        if (restoreFocus && lastDrawerTrigger instanceof HTMLElement) {
            lastDrawerTrigger.focus();
        }

        updateOverlay();
    };

    const openDrawer = (trigger) => {
        if (!(drawer instanceof HTMLElement)) {
            return;
        }

        closeMenu({ restoreFocus: false });
        lastDrawerTrigger = trigger instanceof HTMLElement ? trigger : drawerToggle;
        drawerOpen = true;
        drawer.hidden = false;
        drawer.setAttribute('aria-hidden', 'false');

        if (drawerToggle instanceof HTMLButtonElement) {
            drawerToggle.setAttribute('aria-expanded', 'true');
        }

        updateOverlay();
        firstFocusable(drawer)?.focus();
    };

    menuToggle?.addEventListener('click', () => {
        if (menuOpen) {
            closeMenu();
            return;
        }

        openMenu();
    });

    drawerToggle?.addEventListener('click', (event) => {
        const trigger = event.currentTarget instanceof HTMLElement ? event.currentTarget : null;

        if (drawerOpen) {
            closeDrawer();
            return;
        }

        openDrawer(trigger);
    });

    drawerClose?.addEventListener('click', () => {
        closeDrawer();
    });

    drawer?.querySelectorAll('a, button[type="submit"]').forEach((element) => {
        element.addEventListener('click', () => {
            closeDrawer({ restoreFocus: false });
        });
    });

    overlay?.addEventListener('click', () => {
        closeDrawer({ restoreFocus: false });
    });

    document.addEventListener('click', (event) => {
        const target = event.target;

        if (!(target instanceof Node)) {
            return;
        }

        if (menuOpen && menuPanel instanceof HTMLElement && menuToggle instanceof HTMLButtonElement) {
            const clickedInsideMenu = menuPanel.contains(target) || menuToggle.contains(target);

            if (!clickedInsideMenu) {
                closeMenu({ restoreFocus: false });
            }
        }

        if (drawerOpen && drawer instanceof HTMLElement && drawerToggle instanceof HTMLButtonElement) {
            const clickedInsideDrawer = drawer.contains(target) || drawerToggle.contains(target);

            if (!clickedInsideDrawer) {
                closeDrawer({ restoreFocus: false });
            }
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeMenu();
            closeDrawer();
        }

        if (event.key !== 'Tab') {
            return;
        }

        const activeContainer = menuOpen
            ? menuPanel
            : drawerOpen
                ? drawer
                : null;

        if (!(activeContainer instanceof HTMLElement)) {
            return;
        }

        const focusableElements = Array.from(activeContainer.querySelectorAll(focusableSelector))
            .filter((element) => element instanceof HTMLElement && !element.hasAttribute('hidden'));

        if (focusableElements.length === 0) {
            return;
        }

        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];
        const activeElement = document.activeElement;

        if (event.shiftKey && activeElement === firstElement) {
            event.preventDefault();
            lastElement.focus();
        } else if (!event.shiftKey && activeElement === lastElement) {
            event.preventDefault();
            firstElement.focus();
        }
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 767) {
            closeDrawer({ restoreFocus: false });
        }
    });
})();
