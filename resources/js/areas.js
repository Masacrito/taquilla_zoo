        (function () {
            const soportaModal = typeof HTMLDialogElement === 'function'
                && typeof HTMLDialogElement.prototype.showModal === 'function';

            if (!soportaModal) return;

            const fichas = document.querySelectorAll('dialog.ficha');
            if (!fichas.length) return;

            document.documentElement.setAttribute('data-fichas', '');

            const abrir = (ficha) => {
                if (!ficha || ficha.open) return;
                ficha.showModal();
            };


            const vigilante = new MutationObserver(() => {
                const hayAbierta = [...fichas].some((ficha) => ficha.open);
                document.body.style.overflow = hayAbierta ? 'hidden' : '';
            });

            fichas.forEach((ficha) => {
                vigilante.observe(ficha, { attributes: true, attributeFilter: ['open'] });
            });

            document.querySelectorAll('[data-ficha]').forEach((enlace) => {
                enlace.addEventListener('click', (evento) => {
                    const ficha = document.getElementById(enlace.dataset.ficha);
                    if (!ficha) return;   // sin ficha, el ancla se queda como estaba
                    evento.preventDefault();
                    abrir(ficha);
                });
            });

            fichas.forEach((ficha) => {
                // Clic fuera del contenido: el <dialog> es el blanco cuando se
                // toca el área oscurecida, porque su relleno es cero y el
                // contenido va dentro de un <article>.
                ficha.addEventListener('click', (evento) => {
                    if (evento.target === ficha) ficha.close();
                });

                ficha.querySelector('[data-cerrar]')?.addEventListener('click', () => ficha.close());
            });

            // Enlace directo: zoomat.chiapas.gob.mx/#ficha-aviarios abre esa
            // ficha de una vez. Sirve para compartir por WhatsApp un recorrido
            // en concreto.
            if (window.location.hash.length > 1) {
                // Por id y no armando un selector con el fragmento: un hash
                // cualquiera («#1234») produce un selector inválido y tira
                // una excepción.
                const objetivo = document.getElementById(window.location.hash.slice(1));
                if (objetivo && objetivo.matches('dialog.ficha')) abrir(objetivo);
            }
        })();

