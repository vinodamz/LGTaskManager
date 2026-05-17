// LG Task Manager — PIN modal numpad.
// Each profile card opens the modal pre-filled with that user's id+name.
// Submits to login.php as multipart form data; expects JSON back.
(function () {
    const overlay = document.getElementById('pinOverlay');
    if (!overlay) return;

    const dots     = document.getElementById('pinDots');
    const errEl    = document.getElementById('pinError');
    const hello    = document.getElementById('pinHello');
    const modal    = overlay.querySelector('.pin-modal');
    const submitBtn = document.getElementById('pinSubmit');

    const PIN_MIN = 4;
    const PIN_MAX = 6;

    let uid  = null;
    let pin  = '';
    let busy = false;

    function open(card) {
        uid = card.dataset.uid;
        pin = '';
        errEl.textContent = ' ';
        const firstName = (card.dataset.name || '').split(' ')[0];
        hello.textContent = 'Hi, ' + firstName + ' —';
        const cardColor = getComputedStyle(card).getPropertyValue('--card');
        if (cardColor) {
            modal.style.setProperty('--card', cardColor.trim());
        }
        overlay.hidden = false;
        requestAnimationFrame(() => overlay.classList.add('is-open'));
        render();
    }

    function close() {
        overlay.classList.remove('is-open');
        setTimeout(() => { overlay.hidden = true; }, 220);
    }

    function render() {
        Array.from(dots.children).forEach((d, i) => {
            d.classList.toggle('on', i < pin.length);
        });
        if (submitBtn) submitBtn.disabled = pin.length < PIN_MIN || busy;
    }

    function shake() {
        modal.classList.remove('shake');
        void modal.offsetWidth; // force reflow
        modal.classList.add('shake');
    }

    async function submit() {
        if (pin.length < PIN_MIN) return;
        busy = true;
        errEl.textContent = ' ';
        try {
            const fd = new FormData();
            fd.append('user_id', uid);
            fd.append('pin', pin);
            fd.append('_csrf', window.LGTM_CSRF || '');
            const res = await fetch('login.php', {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const data = await res.json();
            if (data.ok) {
                window.location.href = data.redirect || 'index.php';
                return;
            }
            errEl.textContent = data.error || 'Wrong PIN';
            pin = ''; render(); shake();
        } catch (err) {
            errEl.textContent = 'Network error';
            pin = ''; render(); shake();
        } finally {
            busy = false;
        }
    }

    document.querySelectorAll('.profile-card').forEach(card => {
        card.addEventListener('click', () => open(card));
    });

    document.getElementById('pinClose').addEventListener('click', close);
    overlay.addEventListener('click', e => { if (e.target === overlay) close(); });

    document.getElementById('numpad').addEventListener('click', e => {
        const b = e.target.closest('.key');
        if (!b || busy) return;
        const k = b.dataset.k;
        if (k === 'clear')           pin = '';
        else if (k === 'back')       pin = pin.slice(0, -1);
        else if (pin.length < PIN_MAX) pin += k;
        render();
        // Auto-submit only when the user has typed PIN_MAX (6) digits.
        // For shorter PINs (4–5), the Sign-in button below the numpad submits.
        if (pin.length === PIN_MAX) submit();
    });

    if (submitBtn) submitBtn.addEventListener('click', () => { if (!busy && pin.length >= PIN_MIN) submit(); });

    document.addEventListener('keydown', e => {
        if (overlay.hidden || busy) return;
        if (/^\d$/.test(e.key) && pin.length < PIN_MAX) {
            pin += e.key; render();
            // On keyboard, don't auto-submit so user can type a longer PIN.
        } else if (e.key === 'Enter') {
            submit();
        } else if (e.key === 'Backspace') {
            pin = pin.slice(0, -1); render();
        } else if (e.key === 'Escape') {
            close();
        }
    });
})();
