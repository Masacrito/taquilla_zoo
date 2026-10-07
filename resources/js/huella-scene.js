import * as THREE from 'three';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { DRACOLoader } from 'three/addons/loaders/DRACOLoader.js';
import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js';
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

export function mountPaw(host) {
    const canvas = host.querySelector('[data-canvas]');
    const stage = host.querySelector('[data-stage]');
    const status = host.querySelector('[data-scene-status]');
    const tools = host.querySelector('[data-scene-tools]');
    const explodeButton = host.querySelector('[data-explode]');
    const pauseButton = host.querySelector('[data-pause]');
    const abort = new AbortController();
    const on = (el, name, fn) => el.addEventListener(name, fn, { signal: abort.signal });
    let disposed = false, ready = false, paused = false, visible = true, frame = 0, lastTime = 0, time = 0;
    let exploded = false, pointerOver = false, dragging = false, down = null, dragX = 0, dragY = 0, width = 10;
    const pointer = new THREE.Vector2(2, 2);
    const raycaster = new THREE.Raycaster();
    const scene = new THREE.Scene();
    const camera = new THREE.OrthographicCamera(-5, 5, 3.2, -3.2, .1, 100);
    camera.position.set(0, 0, 15);
    const renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: true, powerPreference: 'high-performance' });
    renderer.setPixelRatio(Math.min(devicePixelRatio, 1.5));
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.25;
    const pmrem = new THREE.PMREMGenerator(renderer);
    const room = new RoomEnvironment();
    const environment = pmrem.fromScene(room, .05);
    scene.environment = environment.texture;
    scene.environmentIntensity = .5;
    room.dispose(); pmrem.dispose();
    scene.add(new THREE.HemisphereLight(0xf4e3c5, 0x083e32, 2));
    const key = new THREE.DirectionalLight(0xffe2b0, 3.5); key.position.set(-4, 6, 7); scene.add(key);
    const rim = new THREE.DirectionalLight(0xffe4aa, 3); rim.position.set(4, 3, -3); scene.add(rim);
    const lower = new THREE.PointLight(0x57d8b4, 6, 12); lower.position.set(1, -3, 3); scene.add(lower);
    const rig = new THREE.Group(), focus = new THREE.Group(); scene.add(rig); rig.add(focus);
    const parts = [], meshes = [];
    const rayLocal = new THREE.Vector3(), target = new THREE.Vector3(), world = new THREE.Vector3();
    const state = { offset: .24, zoom: 1.08, yaw: -.35, pitch: .09, fx: 0, fy: 2.45, fz: 0, progress: 0 };
    // Paw-specific poses keep the five pads readable throughout the purchase journey.
    const poses = [
        { offset: .24, zoom: 1.08, yaw: -.35, pitch: .09, fx: 0, fy: 2.45, fz: 0 },
        { offset: -.26, zoom: 1.14, yaw: .25, pitch: .04, fx: 0, fy: 2.5, fz: 0 },
        { offset: .25, zoom: 1.12, yaw: -.55, pitch: -.08, fx: 0, fy: 2.5, fz: 0 },
        { offset: -.26, zoom: 1.16, yaw: .4, pitch: .1, fx: 0, fy: 2.45, fz: 0 },
        { offset: .25, zoom: 1.12, yaw: -.3, pitch: .14, fx: 0, fy: 2.5, fz: 0 },
        { offset: -.26, zoom: 1.12, yaw: 0, pitch: 0, fx: 0, fy: 2.45, fz: 0 },
    ];
    const shadow = host.querySelector('[data-shadow]');
    const timeline = gsap.timeline({ scrollTrigger: { trigger: host, start: 'top top+=84', end: 'bottom bottom', scrub: .6, invalidateOnRefresh: true } });
    poses.slice(1).forEach((pose, i) => timeline.to(state, { ...pose, progress: (i + 1) / 5, duration: 1, ease: 'sine.inOut' }));
    const links = [...host.querySelectorAll('.zoo-chapters a')];
    const chapters = [...host.querySelectorAll('[data-chapter]')];
    const triggers = chapters.map((section, index) => ScrollTrigger.create({ trigger: section, start: 'top center', end: 'bottom center', onToggle(self) {
        if (self.isActive) links.forEach((a, i) => i === index ? a.setAttribute('aria-current', 'step') : a.removeAttribute('aria-current'));
    } }));
    links[0].setAttribute('aria-current', 'step');
    const resize = () => {
        const rect = stage.getBoundingClientRect();
        width = 6.4 * rect.width / Math.max(rect.height, 1);
        camera.left = -width / 2; camera.right = width / 2; camera.updateProjectionMatrix();
        renderer.setSize(rect.width, rect.height, false);
    };
    const resizeObserver = new ResizeObserver(resize); resizeObserver.observe(stage); resize();
    const disposeObject = object => {
        const materials = new Set(), textures = new Set();
        object.traverse(o => { o.geometry?.dispose(); if (o.material) (Array.isArray(o.material) ? o.material : [o.material]).forEach(m => materials.add(m)); });
        materials.forEach(m => { Object.values(m).forEach(v => { if (v?.isTexture) textures.add(v); }); m.dispose(); });
        textures.forEach(t => t.dispose());
    };
    const decoder = new DRACOLoader(); decoder.setDecoderPath(host.dataset.draco); decoder.setWorkerLimit(2);
    const loader = new GLTFLoader(); loader.setDRACOLoader(decoder);
    status.textContent = 'Preparando tu encuentro…';
    loader.load(host.dataset.model, gltf => {
        if (disposed) { disposeObject(gltf.scene); return; }
        focus.add(gltf.scene); gltf.scene.updateMatrixWorld(true);
        gltf.scene.traverse(o => {
            if (o.name.startsWith('SOMBRA')) { o.visible = false; return; }
            if (o.isMesh) { meshes.push(o); o.frustumCulled = true; }
            if (o.userData.hover_explode === true) {
                const rest = o.position.clone();
                // Blender extras retain Z-up coordinates; glTF node transforms are already Y-up.
                const [x, y, z] = o.userData.explode_direction;
                const direction = new THREE.Vector3(x, z, -y).normalize();
                parts.push({ object: o, rest, rotation: o.quaternion.clone(), direction, value: 0, velocity: 0, influence: 0, seed: parts.length * 1.713 });
            }
        });
        ready = true; host.classList.remove('is-fallback'); host.classList.add('is-ready'); tools.hidden = false; status.textContent = '';
        host.dataset.scene = 'ready'; host.dataset.parts = String(parts.length);
        ScrollTrigger.refresh(); start();
    }, undefined, error => { if (!disposed) { fallback('La vista 3D no está disponible. Puedes continuar con tu compra.'); console.warn('Huella GLB', error); } });

    // Sparse points appear only during the deliberate full explosion.
    const dots = new Float32Array(64 * 3);
    for (let i = 0; i < 64; i++) { const a = i * 2.39996; dots[i * 3] = Math.cos(a) * (1.5 + i % 7 * .15); dots[i * 3 + 1] = (i / 64 - .5) * 4; dots[i * 3 + 2] = Math.sin(a) * 1.2; }
    const particlesGeometry = new THREE.BufferGeometry(); particlesGeometry.setAttribute('position', new THREE.BufferAttribute(dots, 3));
    const particlesMaterial = new THREE.PointsMaterial({ color: 0xddc799, size: .025, transparent: true, opacity: 0, depthWrite: false });
    const particles = new THREE.Points(particlesGeometry, particlesMaterial); scene.add(particles);
    const deltaRotation = new THREE.Quaternion(), euler = new THREE.Euler();

    function render(now) {
        frame = 0;
        if (disposed || paused || !visible || document.hidden || !ready) return;
        const dt = Math.min((now - (lastTime || now)) / 1000, .05); lastTime = now; time += dt;
        const finale = THREE.MathUtils.smoothstep(state.progress, .86, .98);
        if (finale > .5 && exploded) setExploded(false);
        explodeButton.disabled = finale > .5;
        const motion = 1 - finale;
        rig.position.set(state.offset * width, Math.sin(time * .75) * .015 * motion, 0);
        rig.scale.setScalar(Math.min(state.zoom, width * .105) * (1 + Math.sin(time * 1.2) * .003 * motion));
        rig.rotation.set(state.pitch + (dragY + (pointerOver ? pointer.y * .025 : 0)) * motion, state.yaw + (dragX + Math.sin(time * .4) * .015 + (pointerOver ? pointer.x * .025 : 0)) * motion, 0);
        focus.position.set(-state.fx, -state.fy, -state.fz);
        scene.updateMatrixWorld(true);
        let hit = null;
        if (pointerOver && !dragging && motion > 0) { raycaster.setFromCamera(pointer, camera); hit = raycaster.intersectObjects(meshes, false)[0]; }
        const steps = Math.max(1, Math.ceil(dt / (1 / 120))), step = dt / steps;
        parts.forEach(p => {
            p.influence = 0;
            if (hit) {
                rayLocal.copy(hit.point); p.object.parent.worldToLocal(rayLocal);
                p.influence = Math.exp(-p.rest.distanceToSquared(rayLocal) / .75) * .18 * motion;
            }
            const desired = exploded ? 1 : p.influence;
            for (let n = 0; n < steps; n++) { p.velocity += (desired - p.value) * 100 * step; p.velocity *= Math.exp(-13 * step); p.value += p.velocity * step; }
            if (!desired && Math.abs(p.value) < 1e-5 && Math.abs(p.velocity) < 1e-5) { p.value = 0; p.velocity = 0; }
            target.copy(p.direction).multiplyScalar(p.value * .8);
            p.object.position.copy(p.rest).add(target);
            const twist = p.value * (.14 + Math.sin(time * .6 + p.seed) * .07);
            euler.set(twist * Math.sin(p.seed), twist, twist * Math.cos(p.seed)); deltaRotation.setFromEuler(euler);
            p.object.quaternion.copy(p.rotation).multiply(deltaRotation);
        });
        particles.position.copy(rig.position); particles.rotation.y = time * .035;
        particlesMaterial.opacity = THREE.MathUtils.damp(particlesMaterial.opacity, exploded ? .45 : 0, 5, dt);
        lower.intensity = 6 + Math.sin(time * .7) * .35 * motion;
        shadow.style.left = `${50 + state.offset * 100}%`;
        renderer.render(scene, camera);
        host.dataset.exploded = String(exploded);
        // Small read-only diagnostics for device-specific verification.
        host.sculptureMetrics = { calls: renderer.info.render.calls, triangles: renderer.info.render.triangles, parts: parts.length, progress: state.progress, maxDisplacement: Math.max(...parts.map(p => p.object.position.distanceTo(p.rest))), pixelRatio: renderer.getPixelRatio() };
        frame = requestAnimationFrame(render);
    }
    function start() { if (!frame && ready && !disposed && !paused && visible && !document.hidden) { lastTime = 0; frame = requestAnimationFrame(render); } }
    function stop() { cancelAnimationFrame(frame); frame = 0; lastTime = 0; }
    function setExploded(value) { exploded = value; explodeButton.setAttribute('aria-pressed', String(value)); explodeButton.innerHTML = value ? 'Reunir <span aria-hidden="true">−</span>' : 'Explorar <span aria-hidden="true">＋</span>'; }
    function fallback(message) { stop(); ready = false; host.classList.remove('is-ready'); host.classList.add('is-fallback'); tools.hidden = true; status.textContent = message; host.dataset.scene = 'fallback'; }
    on(explodeButton, 'click', () => { setExploded(!exploded); });
    on(host.querySelector('[data-reset]'), 'click', () => { setExploded(false); dragX = dragY = 0; pointerOver = false; });
    on(pauseButton, 'click', () => { paused = !paused; pauseButton.textContent = paused ? 'Reanudar' : 'Pausar'; pauseButton.setAttribute('aria-pressed', String(paused)); if (paused) stop(); else start(); });
    function updatePointer(event) { const r = canvas.getBoundingClientRect(); pointer.set((event.clientX - r.left) / r.width * 2 - 1, -(event.clientY - r.top) / r.height * 2 + 1); }
    on(canvas, 'pointermove', event => {
        updatePointer(event); pointerOver = true;
        if (down && Math.hypot(event.clientX - down.x, event.clientY - down.y) > 5) dragging = true;
        if (dragging) { dragX = THREE.MathUtils.clamp(down.rx + (event.clientX - down.x) * .006, -Math.PI, Math.PI); dragY = THREE.MathUtils.clamp(down.ry + (event.clientY - down.y) * .003, -.4, .4); }
    });
    on(canvas, 'pointerdown', event => { if (event.button !== 0) return; down = { x: event.clientX, y: event.clientY, rx: dragX, ry: dragY }; canvas.setPointerCapture(event.pointerId); });
    const endDrag = event => { if (canvas.hasPointerCapture(event.pointerId)) canvas.releasePointerCapture(event.pointerId); down = null; dragging = false; };
    on(canvas, 'pointerup', endDrag); on(canvas, 'pointercancel', endDrag); on(canvas, 'pointerleave', () => { pointerOver = false; });
    on(document, 'visibilitychange', () => document.hidden ? stop() : start());
    const observer = new IntersectionObserver(([entry]) => { visible = entry.isIntersecting; if (visible) start(); else stop(); }); observer.observe(host);
    on(canvas, 'webglcontextlost', event => { event.preventDefault(); fallback('La vista 3D se pausó. El contenido y la compra siguen disponibles.'); });
    on(canvas, 'webglcontextrestored', () => { location.reload(); });
    return () => {
        disposed = true; stop(); abort.abort(); observer.disconnect(); resizeObserver.disconnect();
        timeline.scrollTrigger?.kill(); timeline.kill(); triggers.forEach(t => t.kill()); decoder.dispose();
        disposeObject(scene); environment.dispose(); renderer.dispose();
        host.classList.remove('is-ready'); tools.hidden = true; status.textContent = ''; delete host.sculptureMetrics;
    };
}
