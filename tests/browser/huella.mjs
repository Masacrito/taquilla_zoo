import { chromium } from '@playwright/test';
import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';

const base = process.env.ZOOMAT_URL || 'http://127.0.0.1:8000';
const out = 'output/huella-web'; await mkdir(out, { recursive: true });
const browser = await chromium.launch({ channel: 'chrome', headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
const errors = []; page.on('pageerror', e => errors.push(e.message));
try {
    const response = await page.goto(base); assert.equal(response.status(), 200);
    await page.waitForSelector('[data-scene="ready"]', { timeout: 60000 });
    await page.waitForTimeout(1800);
    const metrics = () => page.locator('[data-sculpture-journey]').evaluate(el => el.sculptureMetrics);
    assert.equal((await metrics()).parts, 5);
    await page.screenshot({ path: `${out}/desktop-hero.png` });
    await page.mouse.move(1080, 410); await page.waitForTimeout(1000);
    const hover = await metrics(); assert.ok(hover.maxDisplacement > .001, 'Hover should move nearby pieces');
    await page.mouse.move(20, 20); await page.waitForTimeout(2200);
    assert.ok((await metrics()).maxDisplacement < .002, 'Leaving restores rest');
    await page.locator('[data-explode]').click(); await page.waitForTimeout(1200);
    assert.ok((await metrics()).maxDisplacement > .7);
    await page.screenshot({ path: `${out}/desktop-exploded.png` });
    await page.locator('[data-explode]').click(); await page.mouse.move(20, 20); await page.waitForTimeout(2000);
    assert.ok((await metrics()).maxDisplacement < .002);
    await page.locator('[data-pause]').click(); assert.equal(await page.locator('[data-pause]').getAttribute('aria-pressed'), 'true');
    await page.locator('[data-pause]').click();
    await page.mouse.move(1070, 430); await page.mouse.down(); await page.mouse.move(1150, 455, { steps: 8 }); await page.mouse.up();
    assert.equal(await page.locator('[data-explode]').getAttribute('aria-pressed'), 'false', 'Drag must not toggle explosion');
    await page.locator('[data-reset]').click();
    for (const id of ['visitantes', 'origen', 'fecha', 'pago', 'acceso']) {
        await page.locator(`#${id}`).evaluate(el => window.scrollTo(0, el.offsetTop - 84));
        await page.mouse.move(20, 20); await page.waitForTimeout(1400);
        await page.screenshot({ path: `${out}/${id}.png` });
    }
    const final = await metrics(); assert.ok(final.progress > .9); assert.ok(final.maxDisplacement < .002);
    const performance = await page.evaluate(async () => {
        const canvas = document.querySelector('[data-canvas]');
        const gl = canvas.getContext('webgl2');
        const ext = gl.getExtension('WEBGL_debug_renderer_info');
        const timings = [];
        await new Promise(resolve => {
            let last;
            function sample(t) { if (last) timings.push(t - last); last = t; if (timings.length < 90) requestAnimationFrame(sample); else resolve(); }
            requestAnimationFrame(sample);
        });
        timings.sort((a,b) => a-b);
        return { renderer: ext ? gl.getParameter(ext.UNMASKED_RENDERER_WEBGL) : gl.getParameter(gl.RENDERER), medianFrameMs: timings[45], p95FrameMs: timings[85], userAgent: navigator.userAgent };
    });
    await page.locator('.zoo-header a[href="#areas"]').click();
    assert.equal(await page.locator('#areas [data-ficha]').count(), 6);
    await page.screenshot({ path: `${out}/areas-desktop.png` });
    for (const link of await page.locator('#areas [data-ficha]').all()) {
        const id = await link.getAttribute('data-ficha');
        await link.click(); assert.equal(await page.locator(`#${id}`).evaluate(el => el.open), true);
        await page.keyboard.press('Escape');
        assert.equal(await page.locator(`#${id}`).evaluate(el => el.open), false);
    }
    assert.equal(await page.evaluate(() => document.body.style.overflow), '');
    const buy = page.locator('#acceso a.zoo-button'); assert.match(await buy.getAttribute('href'), /\/comprar$/);
    await buy.click(); await page.waitForLoadState(); assert.match(page.url(), /comprar/);

    const mobile = await browser.newPage({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
    const modelRequests = []; mobile.on('request', r => { if (r.url().endsWith('.glb')) modelRequests.push(r.url()); });
    await mobile.goto(base); await mobile.waitForTimeout(1300);
    assert.equal(modelRequests.length, 0); assert.ok(await mobile.locator('[data-poster]').isVisible());
    assert.equal(await mobile.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    await mobile.screenshot({ path: `${out}/mobile.png` }); await mobile.locator('.zoo-header a[href="#areas"]').click();
    await mobile.screenshot({ path: `${out}/areas-mobile.png` });
    await mobile.locator('#areas [data-ficha]').first().click();
    assert.equal(await mobile.locator('#ficha-vivario').evaluate(el => el.open), true);
    await mobile.locator('#ficha-vivario [data-cerrar]').click();
    assert.equal(await mobile.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    await mobile.close();

    const reduced = await browser.newPage({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' });
    let reducedLoads = 0; reduced.on('request', r => { if (r.url().endsWith('.glb')) reducedLoads++; });
    await reduced.goto(base); await reduced.waitForTimeout(700); assert.equal(reducedLoads, 0); await reduced.close();
    const failed = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
    await failed.route('**/huella.glb', r => r.abort()); await failed.goto(base);
    await failed.waitForSelector('[data-scene="fallback"]'); assert.ok(await failed.locator('[data-poster]').isVisible()); await failed.close();
    const nojs = await browser.newPage({ javaScriptEnabled: false }); await nojs.goto(base);
    assert.ok(await nojs.locator('h1').isVisible()); assert.ok(await nojs.locator('#acceso').textContent()); await nojs.locator('[data-ficha="ficha-vivario"]').click();
    assert.ok(await nojs.locator('#ficha-vivario').isVisible()); await nojs.close();
    assert.deepEqual(errors, []);
    await writeFile(`${out}/verification.json`, JSON.stringify({ passed: true, hero: hover, final, performance, errors, checks: ['hover', 'rest', 'explode', 'assemble', 'pause', 'drag', 'scroll', 'purchase link', 'mobile', 'reduced motion', 'asset failure', 'no JS'] }, null, 2));
    console.log('PASS', JSON.stringify(final));
} finally { await browser.close(); }
