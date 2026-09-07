/**
 * Lector de QR para el módulo de accesos.
 *
 * Se empaqueta con Vite y se sirve desde este servidor, NO desde un CDN: el
 * módulo de accesos tiene que funcionar aunque el zoológico se quede sin
 * internet (brief §8, modo contingencia).
 *
 * Se expone en window porque la vista de escaneo lo usa desde un script
 * suelto, sin módulos.
 */
import jsQR from 'jsqr';

window.jsQR = jsQR;
