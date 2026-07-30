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
