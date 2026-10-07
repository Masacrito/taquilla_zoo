import './areas.js';
const journey = document.querySelector('[data-sculpture-journey]');
const eligibility = matchMedia('(min-width: 901px) and (pointer: fine) and (prefers-reduced-motion: no-preference)');
let teardown = null;
let generation = 0;

async function refreshExperience() {
    const token = ++generation;
    teardown?.(); teardown = null;
    if (!journey) return;
    journey.classList.remove('is-ready');
    journey.querySelector('[data-scene-tools]').hidden = true;
    if (!eligibility.matches || navigator.connection?.saveData) {
        journey.classList.add('is-fallback'); return;
    }
    try {
        const { mountPaw } = await import('./huella-scene.js');
        if (token !== generation) return;
        teardown = mountPaw(journey);
    } catch (error) {
        journey.classList.add('is-fallback');
        console.warn('La escena 3D no está disponible; se conserva el acceso a la compra.', error);
    }
}
eligibility.addEventListener('change', refreshExperience);
navigator.connection?.addEventListener('change', refreshExperience);
window.addEventListener('pagehide', () => { ++generation; teardown?.(); teardown = null; });
window.addEventListener('pageshow', event => { if (event.persisted) refreshExperience(); });
refreshExperience();
