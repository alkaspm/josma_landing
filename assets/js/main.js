document.addEventListener('DOMContentLoaded', () => { const y = document.getElementById('year'); if (y) y.textContent = new Date().getFullYear(); const f = document.getElementById('contactForm'); if (!f) return; const a = document.getElementById('formAlert'); const b = document.getElementById('submitBtn'); const d = b.querySelector('.default-label'); const l = b.querySelector('.loading-label'); f.addEventListener('submit', async e => { e.preventDefault(); if (!f.checkValidity()) { e.stopPropagation(); f.classList.add('was-validated'); return } if (document.getElementById('website').value.trim() !== '') return; const fd = new FormData(f); d.classList.add('d-none'); l.classList.remove('d-none'); b.disabled = true; a.className = 'alert d-none'; try { const r = await fetch('contact.php', { method: 'POST', body: fd }); const data = await r.json(); if (data.success) { a.className = 'alert alert-success'; a.textContent = data.message || '¡Mensaje enviado!'; f.reset(); f.classList.remove('was-validated') } else { a.className = 'alert alert-danger'; a.textContent = data.message || 'No se pudo enviar el mensaje.' } } catch (err) { a.className = 'alert alert-danger'; a.textContent = 'Error de red. Intenta más tarde.' } finally { d.classList.remove('d-none'); l.classList.add('d-none'); b.disabled = false } }) });

// Global Loading State for Primary Buttons
document.querySelectorAll('a.btn-primary').forEach(btn => {
    btn.addEventListener('click', function (e) {
        // Only apply if it's external navigation, not anchor links
        if (!this.getAttribute('href').startsWith('#')) {
            const originalText = this.innerHTML;

            // Add Bootstrap spinner
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true" style="border-width: 0.15em;"></span> Cargando...';
            this.style.opacity = '0.8';
            this.style.pointerEvents = 'none';

            // Fallback: restore button if navigation is cancelled or takes too long (8 seconds)
            setTimeout(() => {
                this.innerHTML = originalText;
                this.style.opacity = '1';
                this.style.pointerEvents = 'auto';
            }, 8000);
        }
    });
});