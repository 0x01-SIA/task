document.documentElement.classList.add('js-ready');

const customerSelect = document.querySelector('[data-customer-location-filter="customer"]');
const locationSelect = document.querySelector('[data-customer-location-filter="location"]');

if (customerSelect && locationSelect) {
    const updateLocationOptions = () => {
        const customerId = customerSelect.value;
        const selectedOption = locationSelect.options[locationSelect.selectedIndex] ?? null;

        Array.from(locationSelect.options).forEach((option, index) => {
            if (index === 0) {
                option.hidden = false;
                return;
            }

            const optionCustomerId = option.getAttribute('data-customer-id');
            const matches = customerId === '' || optionCustomerId === customerId;

            option.hidden = !matches;
        });

        if (selectedOption !== null && selectedOption.hidden) {
            locationSelect.value = '';
        }
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

        return {
            x: event.clientX - bounds.left,
            y: event.clientY - bounds.top,
        };
    };

    const beginStroke = (event) => {
        event.preventDefault();
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

        event.preventDefault();
        const nextPoint = pointFromEvent(event);
        context.beginPath();
        context.moveTo(lastPoint.x, lastPoint.y);
        context.lineTo(nextPoint.x, nextPoint.y);
        context.stroke();
        lastPoint = nextPoint;
    };

    const endStroke = () => {
        if (!drawing) {
            return;
        }

        drawing = false;
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

    canvas.addEventListener('pointerdown', beginStroke);
    canvas.addEventListener('pointermove', continueStroke);
    canvas.addEventListener('pointerup', endStroke);
    canvas.addEventListener('pointerleave', endStroke);
    canvas.addEventListener('pointercancel', endStroke);
    clearButton.addEventListener('click', clearSignature);
    form.addEventListener('submit', () => {
        output.value = hasInk ? canvas.toDataURL('image/png') : '';
    });
    window.addEventListener('resize', resizeCanvas);
});
