/**
 * JOSMA SpA — Landing Page JavaScript (Seguro)
 * 
 * Mejoras de seguridad aplicadas:
 *  - CSRF token fetching antes del envío
 *  - textContent en lugar de innerHTML donde es posible
 *  - Eliminación de manipulación innerHTML con datos dinámicos
 *  - Timestamp del formulario para detección de bots
 *  - Manejo seguro de errores sin exponer detalles internos
 */

// =====================================================================
// 1. Año dinámico del Footer
// =====================================================================
document.addEventListener('DOMContentLoaded', () => {
    const yearEl = document.getElementById('year');
    if (yearEl) yearEl.textContent = new Date().getFullYear();
});

// =====================================================================
// 2. Formulario de Contacto con CSRF Token
// =====================================================================
document.addEventListener('DOMContentLoaded', async () => {
    const form = document.getElementById('contactForm');
    if (!form) return;

    const alertBox = document.getElementById('formAlert');
    const submitBtn = document.getElementById('submitBtn');
    const defaultLabel = submitBtn.querySelector('.default-label');
    const loadingLabel = submitBtn.querySelector('.loading-label');
    const csrfInput = document.getElementById('csrfToken');
    const formLoadTimeInput = document.getElementById('formLoadTime');

    // Registrar timestamp de carga del formulario (detección de bots)
    if (formLoadTimeInput) {
        formLoadTimeInput.value = Math.floor(Date.now() / 1000);
    }

    // Obtener CSRF token del servidor
    async function fetchCsrfToken() {
        try {
            const response = await fetch('contact.php', { method: 'GET' });
            const data = await response.json();
            if (data.csrf_token && csrfInput) {
                csrfInput.value = data.csrf_token;
            }
        } catch (err) {
            // Silenciar error — el servidor validará la ausencia del token
            console.warn('No se pudo obtener token CSRF.');
        }
    }

    // Solicitar token al cargar
    await fetchCsrfToken();

    // Handler del formulario
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        // Validación HTML5
        if (!form.checkValidity()) {
            e.stopPropagation();
            form.classList.add('was-validated');
            return;
        }

        // Honeypot check (client-side)
        const honeypot = document.getElementById('website');
        if (honeypot && honeypot.value.trim() !== '') return;

        // UI: mostrar loading
        defaultLabel.classList.add('d-none');
        loadingLabel.classList.remove('d-none');
        submitBtn.disabled = true;
        alertBox.className = 'alert d-none';

        try {
            const formData = new FormData(form);

            const response = await fetch('contact.php', {
                method: 'POST',
                body: formData,
            });

            const data = await response.json();

            if (data.success) {
                alertBox.className = 'alert alert-success';
                alertBox.textContent = data.message || '¡Mensaje enviado correctamente!';
                form.reset();
                form.classList.remove('was-validated');

                // Obtener nuevo CSRF token para un posible re-envío
                await fetchCsrfToken();

                // Restaurar timestamp
                if (formLoadTimeInput) {
                    formLoadTimeInput.value = Math.floor(Date.now() / 1000);
                }
            } else {
                alertBox.className = 'alert alert-danger';
                alertBox.textContent = data.message || 'No se pudo enviar el mensaje.';

                // Si fue error de CSRF, renovar token
                if (response.status === 403) {
                    await fetchCsrfToken();
                }
            }
        } catch (err) {
            alertBox.className = 'alert alert-danger';
            alertBox.textContent = 'Error de conexión. Por favor intente más tarde o contacte directamente a cotizaciones@josma.cl.';
        } finally {
            defaultLabel.classList.remove('d-none');
            loadingLabel.classList.add('d-none');
            submitBtn.disabled = false;
        }
    });
});

// =====================================================================
// 3. Loading State para Botones de Navegación Externa
// =====================================================================
document.querySelectorAll('a.btn-primary').forEach(btn => {
    btn.addEventListener('click', function (e) {
        const href = this.getAttribute('href');
        // Solo aplicar a navegación externa, no a anclas
        if (href && !href.startsWith('#')) {
            // Guardar contenido original de forma segura
            const originalHTML = this.innerHTML;

            // Crear spinner de forma segura (DOM API en vez de innerHTML)
            this.textContent = '';
            const spinner = document.createElement('span');
            spinner.className = 'spinner-border spinner-border-sm me-2';
            spinner.setAttribute('role', 'status');
            spinner.setAttribute('aria-hidden', 'true');
            spinner.style.borderWidth = '0.15em';
            this.appendChild(spinner);
            this.appendChild(document.createTextNode(' Cargando...'));

            this.style.opacity = '0.8';
            this.style.pointerEvents = 'none';

            // Restaurar si la navegación tarda demasiado
            setTimeout(() => {
                this.innerHTML = originalHTML;
                this.style.opacity = '1';
                this.style.pointerEvents = 'auto';
            }, 8000);
        }
    });
});

// =====================================================================
// 4. Auto-formato de RUT Chileno
// =====================================================================
document.addEventListener('DOMContentLoaded', () => {
    const rutInput = document.getElementById('rut');
    if (!rutInput) return;

    rutInput.addEventListener('input', function () {
        // Limitar caracteres permitidos
        let value = this.value.replace(/[^0-9kK]/g, '').toUpperCase();

        // Limitar longitud máxima (9 chars: 8 dígitos + 1 DV)
        if (value.length > 9) {
            value = value.slice(0, 9);
        }

        if (value.length > 0) {
            const body = value.slice(0, -1);
            const dv = value.slice(-1);

            // Formatear cuerpo con puntos
            let formattedBody = '';
            for (let i = body.length - 1, j = 0; i >= 0; i--, j++) {
                formattedBody = body.charAt(i) + formattedBody;
                if (j === 2 && i !== 0) {
                    formattedBody = '.' + formattedBody;
                    j = -1;
                }
            }

            this.value = body.length > 0 ? formattedBody + '-' + dv : dv;
        } else {
            this.value = '';
        }
    });
});

// =====================================================================
// 5. Hero Background Slideshow (Sync para todas las instancias)
// =====================================================================
document.addEventListener('DOMContentLoaded', () => {
    const slideshows = document.querySelectorAll('.hero-slideshow');
    if (slideshows.length === 0) return;

    const numSlides = slideshows[0].querySelectorAll('.hero-slide').length;
    if (numSlides === 0) return;

    let currentSlide = 0;

    // Respetar preferencia de movimiento reducido
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (prefersReducedMotion) return;

    setInterval(() => {
        slideshows.forEach(show => {
            const slides = show.querySelectorAll('.hero-slide');
            if (slides[currentSlide]) slides[currentSlide].classList.remove('active');
        });

        currentSlide = (currentSlide + 1) % numSlides;

        slideshows.forEach(show => {
            const slides = show.querySelectorAll('.hero-slide');
            if (slides[currentSlide]) slides[currentSlide].classList.add('active');
        });
    }, 5000);
});