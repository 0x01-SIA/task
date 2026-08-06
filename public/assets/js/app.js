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
    const installActionButtons = Array.from(document.querySelectorAll('[data-install-app-action]'));
    let lastDrawerTrigger = null;
    let menuOpen = false;
    let drawerOpen = false;
    let deferredInstallPrompt = null;

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

    const isStandaloneApp = () => window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

    const updateInstallActionVisibility = () => {
        const shouldShow = deferredInstallPrompt !== null && !isStandaloneApp();

        installActionButtons.forEach((button) => {
            button.hidden = !shouldShow;
        });
    };

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredInstallPrompt = event;
        updateInstallActionVisibility();
    });

    window.addEventListener('appinstalled', () => {
        deferredInstallPrompt = null;
        updateInstallActionVisibility();
    });

    window.matchMedia('(display-mode: standalone)').addEventListener('change', () => {
        if (isStandaloneApp()) {
            deferredInstallPrompt = null;
        }

        updateInstallActionVisibility();
    });

    installActionButtons.forEach((button) => {
        button.addEventListener('click', async () => {
            if (deferredInstallPrompt === null) {
                updateInstallActionVisibility();
                return;
            }

            const installPrompt = deferredInstallPrompt;
            deferredInstallPrompt = null;
            updateInstallActionVisibility();

            try {
                await installPrompt.prompt();
                await installPrompt.userChoice;
            } catch (error) {
                // Browsers may reject if the prompt is no longer available; keep the action hidden until a new event arrives.
            }
        });
    });

    updateInstallActionVisibility();

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

const decodeBase64Json = (encoded) => {
    try {
        return JSON.parse(atob(encoded || ''));
    } catch (error) {
        return null;
    }
};

const searchableSelectInstances = new WeakMap();
let searchableSelectActiveInstance = null;

const closeActiveSearchableSelect = ({ restoreFocus = false } = {}) => {
    if (searchableSelectActiveInstance === null) {
        return;
    }

    searchableSelectActiveInstance.close({ restoreFocus });
};

const ensureSearchableSelectGlobalListeners = (() => {
    let attached = false;

    return () => {
        if (attached) {
            return;
        }

        document.addEventListener('pointerdown', (event) => {
            if (!(event.target instanceof Node) || searchableSelectActiveInstance === null) {
                return;
            }

            if (searchableSelectActiveInstance.root.contains(event.target)) {
                return;
            }

            closeActiveSearchableSelect();
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape' || searchableSelectActiveInstance === null) {
                return;
            }

            event.preventDefault();
            closeActiveSearchableSelect({ restoreFocus: true });
        });

        attached = true;
    };
})();

const enhanceSearchableSelect = (select, {
    placeholder = 'Select an option',
    searchPlaceholder = 'Search',
    emptyMessage = 'No results found',
} = {}) => {
    if (!(select instanceof HTMLSelectElement) || searchableSelectInstances.has(select)) {
        return searchableSelectInstances.get(select) || null;
    }

    ensureSearchableSelectGlobalListeners();

    const optionData = Array.from(select.options).map((option, index) => ({
        value: option.value,
        label: option.textContent?.trim() || '',
        searchText: `${option.textContent || ''} ${option.getAttribute('data-search') || ''}`.trim().toLowerCase(),
        disabled: option.disabled,
        isPlaceholder: index === 0 && option.value === '',
    }));
    const hasPlaceholderOption = optionData.some((option) => option.isPlaceholder);

    select.classList.add('searchable-select__native');
    select.setAttribute('tabindex', '-1');

    const root = document.createElement('div');
    root.className = `searchable-select${select.classList.contains('is-invalid') ? ' is-invalid' : ''}`;
    root.dataset.searchableSelect = '';

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'searchable-select__trigger';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');

    const triggerLabel = document.createElement('span');
    triggerLabel.className = 'searchable-select__trigger-label';

    const triggerMeta = document.createElement('span');
    triggerMeta.className = 'searchable-select__trigger-meta';
    triggerMeta.textContent = 'Search';

    trigger.append(triggerLabel, triggerMeta);

    const panel = document.createElement('div');
    panel.className = 'searchable-select__panel';
    panel.hidden = true;

    const searchInput = document.createElement('input');
    searchInput.type = 'search';
    searchInput.className = 'searchable-select__input';
    searchInput.placeholder = searchPlaceholder;
    searchInput.setAttribute('aria-label', searchPlaceholder);
    searchInput.autocomplete = 'off';
    searchInput.spellcheck = false;

    const results = document.createElement('div');
    results.className = 'searchable-select__results';
    results.setAttribute('role', 'listbox');

    const emptyState = document.createElement('div');
    emptyState.className = 'searchable-select__empty';
    emptyState.textContent = emptyMessage;
    emptyState.hidden = true;

    panel.append(searchInput, results, emptyState);
    select.parentNode?.insertBefore(root, select);
    root.append(select, trigger, panel);

    let filteredOptions = [];
    let activeIndex = -1;

    const syncInvalidState = () => {
        root.classList.toggle('is-invalid', select.classList.contains('is-invalid'));
        trigger.disabled = select.disabled;
    };

    const selectedOption = () => optionData.find((option) => option.value === select.value) || optionData[0] || null;

    const updateTriggerLabel = () => {
        const currentOption = selectedOption();
        const hasSelection = currentOption !== null && !currentOption.isPlaceholder && currentOption.value !== '';

        triggerLabel.textContent = hasSelection ? currentOption.label : placeholder;
        trigger.classList.toggle('is-placeholder', !hasSelection);
        trigger.title = hasSelection ? currentOption.label : placeholder;
    };

    const setActiveOption = (index) => {
        activeIndex = index;

        results.querySelectorAll('.searchable-select__option').forEach((optionElement, optionIndex) => {
            const isActive = optionIndex === activeIndex;
            optionElement.classList.toggle('is-active', isActive);

            if (isActive) {
                optionElement.scrollIntoView({ block: 'nearest' });
            }
        });
    };

    const applySelection = (value) => {
        if (select.value === value) {
            updateTriggerLabel();
            return;
        }

        select.value = value;
        updateTriggerLabel();
        syncInvalidState();
        select.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const renderOptions = () => {
        const query = searchInput.value.trim().toLowerCase();
        const currentValue = select.value;

        filteredOptions = optionData.filter((option) => {
            if (option.disabled) {
                return false;
            }

            if (option.isPlaceholder) {
                return hasPlaceholderOption && query === '';
            }

            return query === '' || option.searchText.includes(query);
        });

        results.innerHTML = '';

        filteredOptions.forEach((option) => {
            const optionButton = document.createElement('button');
            optionButton.type = 'button';
            optionButton.className = 'searchable-select__option';
            optionButton.setAttribute('role', 'option');
            optionButton.dataset.value = option.value;
            optionButton.textContent = option.label;

            if (option.value === currentValue) {
                optionButton.classList.add('is-selected');
                optionButton.setAttribute('aria-selected', 'true');
            }

            optionButton.addEventListener('click', () => {
                applySelection(option.value);
                instance.close({ restoreFocus: true });
            });

            results.append(optionButton);
        });

        emptyState.hidden = filteredOptions.length > 0;
        activeIndex = filteredOptions.findIndex((option) => option.value === currentValue && !option.isPlaceholder);

        if (activeIndex < 0 && filteredOptions.length > 0) {
            activeIndex = 0;
        }

        setActiveOption(activeIndex);
    };

    const instance = {
        root,
        close: ({ restoreFocus = false } = {}) => {
            if (panel.hidden) {
                return;
            }

            panel.hidden = true;
            root.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
            searchInput.value = '';
            searchableSelectActiveInstance = searchableSelectActiveInstance === instance ? null : searchableSelectActiveInstance;
            renderOptions();

            if (restoreFocus) {
                trigger.focus();
            }
        },
        open: () => {
            if (!panel.hidden) {
                searchInput.focus();
                searchInput.select();
                return;
            }

            if (searchableSelectActiveInstance !== null && searchableSelectActiveInstance !== instance) {
                searchableSelectActiveInstance.close();
            }

            searchableSelectActiveInstance = instance;
            panel.hidden = false;
            root.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
            renderOptions();
            searchInput.focus();
            searchInput.select();
        },
        sync: () => {
            syncInvalidState();
            updateTriggerLabel();
            renderOptions();
        },
    };

    trigger.addEventListener('click', () => {
        if (select.disabled) {
            return;
        }

        if (panel.hidden) {
            instance.open();
            return;
        }

        instance.close({ restoreFocus: true });
    });

    trigger.addEventListener('keydown', (event) => {
        if (!['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
            return;
        }

        event.preventDefault();
        instance.open();
    });

    searchInput.addEventListener('input', () => {
        renderOptions();
    });

    searchInput.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();

            if (filteredOptions.length === 0) {
                return;
            }

            setActiveOption(Math.min(activeIndex + 1, filteredOptions.length - 1));
            return;
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();

            if (filteredOptions.length === 0) {
                return;
            }

            setActiveOption(Math.max(activeIndex - 1, 0));
            return;
        }

        if (event.key === 'Enter') {
            event.preventDefault();

            if (activeIndex < 0 || !filteredOptions[activeIndex]) {
                return;
            }

            applySelection(filteredOptions[activeIndex].value);
            instance.close({ restoreFocus: true });
            return;
        }

        if (event.key === 'Tab') {
            instance.close();
        }
    });

    select.addEventListener('change', () => {
        updateTriggerLabel();
        renderOptions();
    });

    const observer = new MutationObserver(() => {
        syncInvalidState();
    });
    observer.observe(select, { attributes: true, attributeFilter: ['class', 'disabled'] });

    searchableSelectInstances.set(select, instance);
    instance.sync();
    return instance;
};

document.querySelectorAll('[data-job-material-form]').forEach((form) => {
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const materialCatalog = decodeBase64Json(form.getAttribute('data-material-catalog') || '') || [];
    const materialSelect = form.querySelector('[data-job-material-select]');
    const entryTypeSelect = form.querySelector('[data-job-material-entry-type]');
    const quantityInput = form.querySelector('[data-job-material-quantity]');
    const returnField = form.querySelector('[data-device-return-field]');
    const usedDeviceEditor = document.querySelector('[data-used-device-editor]');

    if (!(materialSelect instanceof HTMLSelectElement) || !(entryTypeSelect instanceof HTMLSelectElement) || !(quantityInput instanceof HTMLInputElement)) {
        return;
    }

    enhanceSearchableSelect(materialSelect, {
        placeholder: 'Select a material',
        searchPlaceholder: 'Search by SKU or material name',
        emptyMessage: 'No materials match your search',
    });

    const selectedMaterial = () => materialCatalog.find((material) => String(material.id) === materialSelect.value) || null;

    const updateMaterialFormState = () => {
        const material = selectedMaterial();
        const isDevice = material?.is_device === true;
        const isReturned = entryTypeSelect.value === 'returned';

        if (isDevice) {
            quantityInput.value = '1.000';
            quantityInput.readOnly = true;
        } else {
            quantityInput.readOnly = false;
        }

        if (returnField instanceof HTMLElement) {
            const shouldShow = isDevice && isReturned;
            returnField.hidden = !shouldShow;
            returnField.classList.toggle('is-visible', shouldShow);
        }
    };

    materialSelect.addEventListener('change', updateMaterialFormState);
    entryTypeSelect.addEventListener('change', updateMaterialFormState);
    updateMaterialFormState();

    if (!(usedDeviceEditor instanceof HTMLElement)) {
        return;
    }

    const accessoryCatalog = decodeBase64Json(usedDeviceEditor.getAttribute('data-accessory-catalog') || '') || [];
    const editorForm = usedDeviceEditor.querySelector('[data-used-device-editor-form]');
    const editorMaterialId = usedDeviceEditor.querySelector('[data-used-device-material-id]');
    const accessoryRows = usedDeviceEditor.querySelector('[data-accessory-rows]');
    const accessoryEmptyState = usedDeviceEditor.querySelector('[data-accessory-empty-state]');

    if (!(editorForm instanceof HTMLFormElement) || !(editorMaterialId instanceof HTMLInputElement) || !(accessoryRows instanceof HTMLElement)) {
        return;
    }

    const syncAccessoryEmptyState = () => {
        if (!(accessoryEmptyState instanceof HTMLElement)) {
            return;
        }

        accessoryEmptyState.classList.toggle('d-none', accessoryCatalog.length > 0 || accessoryRows.children.length > 0);
    };

    const buildAccessoryRow = (value = {}) => {
        const row = document.createElement('div');
        row.className = 'device-accessory-row';

        const select = document.createElement('select');
        select.className = 'form-select';
        select.name = 'accessory_material_id[]';
        select.setAttribute('data-accessory-search-select', '');
        select.add(new Option('Select accessory', ''));
        accessoryCatalog.forEach((accessory) => {
            const option = new Option(accessory.label, String(accessory.id));
            option.selected = String(value.material_id || '') === String(accessory.id);
            select.add(option);
        });

        const quantity = document.createElement('input');
        quantity.className = 'form-control';
        quantity.name = 'accessory_quantity[]';
        quantity.type = 'text';
        quantity.inputMode = 'decimal';
        quantity.placeholder = 'Qty';
        quantity.value = typeof value.quantity === 'string' ? value.quantity : '';

        const removeButton = document.createElement('button');
        removeButton.className = 'btn btn-outline-danger btn-sm';
        removeButton.type = 'button';
        removeButton.textContent = 'Remove';
        removeButton.setAttribute('data-remove-accessory-row', '');

        row.append(select, quantity, removeButton);
        enhanceSearchableSelect(select, {
            placeholder: 'Select accessory',
            searchPlaceholder: 'Search by SKU or accessory name',
            emptyMessage: 'No accessories match your search',
        });
        return row;
    };

    const openEditor = (payload) => {
        const material = materialCatalog.find((item) => String(item.id) === String(payload.material_id || ''));
        const title = usedDeviceEditor.querySelector('.device-editor__header h3');
        const subtitle = usedDeviceEditor.querySelector('.device-editor__header p');
        const deviceIdInput = usedDeviceEditor.querySelector('#used_device_identifier');
        const objectNameInput = usedDeviceEditor.querySelector('#object_name');

        editorForm.action = payload.formAction || form.action;
        editorMaterialId.value = String(payload.material_id || '');
        usedDeviceEditor.hidden = false;
        usedDeviceEditor.classList.add('is-open');

        if (title instanceof HTMLElement) {
            title.textContent = payload.mode === 'edit' ? 'Edit installed device' : 'Add installed device';
        }

        if (subtitle instanceof HTMLElement && material) {
            const skuPart = material.sku ? ` (${material.sku})` : '';
            subtitle.textContent = `${material.name}${skuPart} - ${material.unit} · Quantity fixed to 1`;
        }

        if (deviceIdInput instanceof HTMLInputElement) {
            deviceIdInput.value = typeof payload.device_identifier === 'string' ? payload.device_identifier : '';
        }

        if (objectNameInput instanceof HTMLInputElement) {
            objectNameInput.value = typeof payload.object_name === 'string' ? payload.object_name : '';
        }

        accessoryRows.innerHTML = '';
        (Array.isArray(payload.accessories) ? payload.accessories : []).forEach((accessory) => {
            accessoryRows.append(buildAccessoryRow(accessory));
        });
        syncAccessoryEmptyState();
        usedDeviceEditor.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };

    const closeEditor = () => {
        usedDeviceEditor.hidden = true;
        usedDeviceEditor.classList.remove('is-open');
    };

    form.addEventListener('submit', (event) => {
        const material = selectedMaterial();

        if (material?.is_device !== true || entryTypeSelect.value !== 'used') {
            return;
        }

        event.preventDefault();
        openEditor({
            mode: 'create',
            formAction: form.action,
            material_id: material.id,
            device_identifier: '',
            object_name: '',
            accessories: [],
        });
    });

    usedDeviceEditor.querySelectorAll('[data-used-device-editor-close]').forEach((button) => {
        button.addEventListener('click', closeEditor);
    });

    document.querySelectorAll('[data-open-used-device-editor]').forEach((button) => {
        button.addEventListener('click', () => {
            const payload = decodeBase64Json(button.getAttribute('data-open-used-device-editor') || '');

            if (payload && typeof payload === 'object') {
                openEditor(payload);
            }
        });
    });

    usedDeviceEditor.querySelectorAll('[data-add-accessory-row]').forEach((button) => {
        button.addEventListener('click', () => {
            const row = buildAccessoryRow();
            accessoryRows.append(row);
            syncAccessoryEmptyState();
        });
    });

    usedDeviceEditor.addEventListener('click', (event) => {
        const target = event.target;

        if (!(target instanceof HTMLElement) || !target.hasAttribute('data-remove-accessory-row')) {
            return;
        }

        event.preventDefault();
        target.closest('.device-accessory-row')?.remove();
        syncAccessoryEmptyState();
    });

    syncAccessoryEmptyState();
    usedDeviceEditor.querySelectorAll('[data-accessory-search-select]').forEach((select) => {
        enhanceSearchableSelect(select, {
            placeholder: 'Select accessory',
            searchPlaceholder: 'Search by SKU or accessory name',
            emptyMessage: 'No accessories match your search',
        });
    });
});
