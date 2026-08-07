'use strict';

const { chromium, firefox, webkit } = require(
    require.resolve('playwright', { paths: [process.cwd()] })
);

const baseUrl = process.env.PLAYWRIGHT_BASE_URL || 'https://awamotos.com';
const widths = [768, 945, 991];
const engines = { chromium, firefox, webkit };

function assert(condition, message, data) {
    if (!condition) {
        throw new Error(`${message}: ${JSON.stringify(data)}`);
    }
}

async function capture(browserType, engineName, width) {
    const launchOptions = {};

    if (
        engineName === 'chromium'
        && process.env.AWA_CHROMIUM_EXECUTABLE_PATH
    ) {
        launchOptions.executablePath =
            process.env.AWA_CHROMIUM_EXECUTABLE_PATH;
        launchOptions.args = [
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu',
        ];
    }

    const browser = await browserType.launch({
        headless: true,
        ...launchOptions,
    });

    try {
        const page = await browser.newPage({
            viewport: { width, height: 900 },
        });

        await page.addInitScript(() => {
            window.__awaHeaderTabletFrames = [];

            const observer = new MutationObserver(() => {
                const main = document.querySelector(
                    '.awa-main-header .header-main > .container'
                );
                const nav = document.querySelector(
                    '[data-awa-header-nav="true"]'
                );
                const navContainer = nav?.querySelector(':scope > .container');
                const navInner = navContainer?.querySelector(
                    '.awa-nav-bar__inner'
                );

                if (!main || !nav || !navContainer || !navInner) {
                    return;
                }

                observer.disconnect();

                const box = (element) => {
                    if (!element) {
                        return null;
                    }

                    const rect = element.getBoundingClientRect();

                    return {
                        x: Math.round(rect.x),
                        y: Math.round(rect.y),
                        width: Math.round(rect.width),
                        height: Math.round(rect.height),
                    };
                };

                const captureFrame = (reason) => {
                    window.__awaHeaderTabletFrames.push({
                        reason,
                        gap: getComputedStyle(navContainer).gap,
                        main: box(main),
                        nav: box(nav),
                        navContainer: box(navContainer),
                        navInner: box(navInner),
                        minicart: box(
                            document.querySelector(
                                '.minicart-wrapper .showcart'
                            )
                        ),
                    });
                };

                requestAnimationFrame(() => {
                    captureFrame('first-frame');
                    requestAnimationFrame(() =>
                        captureFrame('second-frame')
                    );
                });
            });

            observer.observe(document, { childList: true, subtree: true });
        });

        await page.goto(baseUrl, {
            waitUntil: 'domcontentloaded',
            timeout: 60_000,
        });
        await page.waitForFunction(
            () => (window.__awaHeaderTabletFrames?.length || 0) >= 2,
            undefined,
            { timeout: 30_000 }
        );

        const [first, second] = await page.evaluate(
            () => window.__awaHeaderTabletFrames
        );

        assert(
            JSON.stringify(first.main) === JSON.stringify(second.main),
            `${engineName}/${width}px main header shifted`,
            { first: first.main, second: second.main }
        );
        assert(
            JSON.stringify(first.nav) === JSON.stringify(second.nav),
            `${engineName}/${width}px navigation shifted`,
            { first: first.nav, second: second.nav }
        );
        assert(
            JSON.stringify(first.minicart) === JSON.stringify(second.minicart),
            `${engineName}/${width}px minicart shifted`,
            { first: first.minicart, second: second.minicart }
        );
        assert(
            first.main?.height === 88 && first.nav?.height === 48,
            `${engineName}/${width}px header heights are invalid`,
            first
        );
        assert(
            first.gap === '0px' && second.gap === '0px',
            `${engineName}/${width}px structural rail gap returned`,
            { first: first.gap, second: second.gap }
        );
        assert(
            first.navInner?.x === first.navContainer?.x
                && first.navInner?.width === first.navContainer?.width,
            `${engineName}/${width}px nav rail is inset`,
            {
                container: first.navContainer,
                inner: first.navInner,
            }
        );
        assert(
            first.minicart?.width === 44 && first.minicart?.height === 44,
            `${engineName}/${width}px minicart touch target is invalid`,
            first.minicart
        );

        console.log(
            `PASS ${engineName} ${width}px: header 88px, nav 48px, rail gap 0, minicart 44px`
        );
    } finally {
        await browser.close();
    }
}

(async () => {
    for (const [engineName, browserType] of Object.entries(engines)) {
        for (const width of widths) {
            await capture(browserType, engineName, width);
        }
    }
})().catch((error) => {
    console.error(error);
    process.exit(1);
});
