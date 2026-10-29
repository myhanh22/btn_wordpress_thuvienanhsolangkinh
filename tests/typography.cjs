// Read-only browser audit. Temporary probes only change this browser's DOM.
const { chromium } = require(process.env.LK_PLAYWRIGHT_PATH || "playwright");
const fs = require("node:fs");
const assert = require("node:assert/strict");
const samples = [
  "Giữ một khoảnh khắc",
  "Kể ngàn câu chuyện",
  "Lối về bình yên",
  "Ảnh nghệ thuật",
  "Chủ đề",
  "Tác giả",
];
const selectors = [
  "#hero-title",
  "#hero-title em",
  ".lk-hero-caption strong",
  ".lk-section-heading h2",
  ".lk-card-info h3",
  ".lk-album h3",
  ".lk-strip p",
  ".lk-strip i",
  ".lk-card-number",
  ".lk-about h2",
  "#lk-lightbox-title",
];
(async () => {
  const browser = await chromium.launch({
    executablePath: process.env.LK_BROWSER || "/usr/bin/microsoft-edge",
    headless: true,
  });
  try {
    const page = await browser.newPage();
    const cdp = await page.context().newCDPSession(page);
    await cdp.send("DOM.enable");
    await cdp.send("CSS.enable");
    await cdp.send("Network.enable");
    await cdp.send("Network.setCacheDisabled", { cacheDisabled: true });
    const report = {
      platform: process.platform,
      browser: await browser.version(),
      mode: process.env.LK_EXPECT_FONT ? "verify" : "diagnose",
      fontResponses: [],
      views: [],
    };
    page.on("response", (r) => {
      if (/\.woff2(?:\?|$)/.test(r.url()))
        report.fontResponses.push({
          url: r.url(),
          status: r.status(),
          mime: r.headers()["content-type"],
        });
    });
    async function inspect(selector) {
      const nodeId = (
        await cdp.send("DOM.querySelector", {
          nodeId: (await cdp.send("DOM.getDocument")).root.nodeId,
          selector,
        })
      ).nodeId;
      const styles = await page
        .locator(selector)
        .first()
        .evaluate((el) => {
          const s = getComputedStyle(el),
            text = el.textContent;
          return {
            text,
            nfc: text === text.normalize("NFC"),
            combining: [...text].filter((c) => /\p{M}/u.test(c)),
            unusualSpaces: [...text].filter((c) =>
              /[\u00a0\u2000-\u200b\u202f\ufeff]/u.test(c),
            ),
            ...Object.fromEntries(
              [
                "fontFamily",
                "fontSize",
                "fontWeight",
                "fontStyle",
                "fontSynthesis",
                "letterSpacing",
                "lineHeight",
              ].map((k) => [k, s[k]]),
            ),
          };
        });
      const fonts = (await cdp.send("CSS.getPlatformFontsForNode", { nodeId }))
        .fonts;
      if (process.env.LK_EXPECT_FONT) {
        assert.ok(fonts.length, `No rendered fonts: ${selector}`);
        assert.ok(
          fonts.every(
            (f) =>
              f.isCustomFont &&
              f.familyName.includes(process.env.LK_EXPECT_FONT),
          ),
          `Unexpected fallback: ${selector}: ${JSON.stringify(fonts)}`,
        );
      }
      return { selector, ...styles, fonts };
    }
    for (const width of [1440, 768, 390, 320]) {
      await page.setViewportSize({ width, height: 900 });
      const response = await page.goto(
        process.env.LK_TEST_URL || "http://baitapnhom1.local",
        { waitUntil: "networkidle" },
      );
      await page.evaluate(() => document.fonts.ready);
      const view = {
        width,
        httpCharset: response.headers()["content-type"],
        charset: await page.evaluate(() => document.characterSet),
        nodes: [],
      };
      for (const selector of selectors.slice(0, -1))
        view.nodes.push(await inspect(selector));
      view.overflow = await page.evaluate(
        () => document.documentElement.scrollWidth > innerWidth,
      );
      assert.equal(view.overflow, false, `Horizontal overflow at ${width}`);
      await page.locator(".lk-photo").first().click();
      await page.locator(".lk-lightbox[open]").waitFor();
      view.nodes.push(await inspect("#lk-lightbox-title"));
      await page.locator("#lk-close").click();
      // All requested strings, normal/italic and NFC/NFD, at heading size.
      await page.evaluate((samples) => {
        const box = document.createElement("div");
        box.id = "lk-font-probes";
        for (const text of samples)
          for (const form of ["NFC", "NFD"])
            for (const italic of [false, true]) {
              const h = document.createElement("h2");
              const span = document.createElement(italic ? "em" : "span");
              span.textContent = text.normalize(form);
              span.id = "lk-probe-" + box.children.length;
              h.append(span);
              box.append(h);
            }
        document.body.append(box);
      }, samples);
      await page.evaluate(() => document.fonts.ready);
      view.probes = [];
      for (let i = 0; i < samples.length * 4; i++)
        view.probes.push(await inspect("#lk-probe-" + i));
      await page.locator("#lk-font-probes").evaluate((el) => el.remove());
      if (process.env.LK_SCREENSHOT_DIR) {
        await page.evaluate(() => scrollTo(0, 0));
        await page.screenshot({
          path: `${process.env.LK_SCREENSHOT_DIR}/typography-${width}.png`,
        });
      }
      report.views.push(view);
    }
    if (process.env.LK_EXPECT_FONT) {
      assert.ok(report.fontResponses.length >= 2, "Both font styles must load");
      assert.ok(
        report.fontResponses.every(
          (f) =>
            f.status === 200 &&
            /font\/woff2|application\/(?:font-woff|octet-stream)/.test(f.mime),
        ),
        "Font HTTP/MIME failure",
      );
    }
    fs.writeFileSync(
      process.env.LK_REPORT || "/tmp/lk-typography.json",
      JSON.stringify(report, null, 2) + "\n",
    );
    console.log(
      `PASS: ${report.mode}, 4 viewports, 24 NFC/NFD normal/italic probes per viewport; ${report.fontResponses.length} font responses`,
    );
  } finally {
    await browser.close();
  }
})().catch((e) => {
  console.error(e);
  process.exitCode = 1;
});
