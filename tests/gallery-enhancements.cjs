// Run after browser.cjs on the disposable installation. Never use real accounts.
const { chromium } = require(process.env.LK_PLAYWRIGHT_PATH || "playwright");
const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const base = process.env.LK_TEST_URL;
if (
  process.env.LK_DISPOSABLE_WP !== "1" ||
  !base ||
  !process.env.LK_TEST_PASSWORD
)
  throw Error("Disposable installation required");
(async () => {
  const browser = await chromium.launch({
    executablePath: process.env.LK_BROWSER || "/usr/bin/microsoft-edge",
    headless: true,
    args: ["--no-sandbox"],
  });
  const context = await browser.newContext();
  const page = await context.newPage();
  page.setDefaultNavigationTimeout(60000);
  await context.route("**/*", (route) =>
    new URL(route.request().url()).origin === new URL(base).origin
      ? route.continue()
      : route.abort(),
  );
  const check = (ok, message) => {
    assert.ok(ok, message);
    console.log("PASS: " + message);
  };
  const errors = [];
  page.on("pageerror", (e) => errors.push(e.message));
  await page.goto(base + "/");
  const visibleTopics = await page
    .locator("[data-topic]")
    .evaluateAll((es) =>
      es.map((e) => e.dataset.topic).filter((s) => s !== "all"),
    );
  await page.goto(base + "/wp-login.php");
  await page.fill("#user_login", "lk_fixture_author");
  await page.fill("#user_pass", process.env.LK_TEST_PASSWORD);
  await Promise.all([page.waitForNavigation(), page.click("#wp-submit")]);
  await page.goto(base + "/dang-tac-pham/");
  const choices = await page
    .locator("#lk-photo-topic option")
    .allTextContents();
  for (const name of [
    "Đời sống",
    "Chân dung",
    "Phong cảnh",
    "Thiên nhiên",
    "Động vật",
    "Tĩnh vật",
  ])
    check(choices.includes(name), "Form offers existing " + name + " topic");
  check(
    !(await page.locator("#lk-new-topic-field").isVisible()),
    "Custom topic field initially hidden",
  );
  const image = path.resolve(
    __dirname,
    "../astra-child/assets/demo/hoa-sen-26.jpg",
  );
  async function submit(title, choice, newName) {
    await page.goto(base + "/dang-tac-pham/");
    await page.fill("#lk-photo-title", title);
    await page.selectOption("#lk-photo-topic", choice);
    if (choice === "other") {
      check(
        await page.locator("#lk-new-topic").isVisible(),
        "Other reveals new topic field",
      );
      check(
        (await page.locator("#lk-new-topic").getAttribute("required")) !== null,
        "New topic required when Other selected",
      );
      await page.fill("#lk-new-topic", newName);
    }
    await page.setInputFiles("#lk-photo-file", image);
    await Promise.all([
      page.waitForNavigation(),
      page.getByRole("button", { name: "Gửi tác phẩm", exact: true }).click(),
    ]);
    check(
      await page.locator(".lk-upload-message--success").count(),
      "Submission saved: " + title,
    );
    return new URL(page.url()).searchParams.get("lk_upload_id");
  }
  const landscape = await page
    .locator("#lk-photo-topic option")
    .filter({ hasText: "Phong cảnh" })
    .getAttribute("value");
  const idDefault = await submit("Topic default fixture", "0");
  const idExisting = await submit("Topic existing fixture", landscape);
  const idNew = await submit(
    "Topic new fixture",
    "other",
    "Góc nhìn thành phố",
  );
  const idReused = await submit(
    "Topic reused fixture",
    "other",
    "Góc nhìn thành phố",
  );
  await page.goto(base + "/dang-tac-pham/");
  check(
    (await page
      .locator("#lk-photo-topic option")
      .filter({ hasText: "Góc nhìn thành phố" })
      .count()) === 1,
    "New topic appears once and is reusable",
  );
  const nonce = await page.inputValue("[name=lk_upload_nonce]");
  for (const [choice, name, expected] of [
    ["99999999", "", "Chủ đề không hợp lệ"],
    ["other", "", "Hãy nhập tên chủ đề mới"],
  ]) {
    const r = await context.request.post(base + "/dang-tac-pham/", {
      multipart: {
        lk_upload_submit: "1",
        lk_upload_nonce: nonce,
        lk_photo_title: "Invalid topic fixture",
        lk_topic: choice,
        lk_new_topic: name,
        lk_photo_file: {
          name: "topic.jpg",
          mimeType: "image/jpeg",
          buffer: fs.readFileSync(image),
        },
      },
    });
    check(
      (await r.text()).includes(expected),
      "Server rejects invalid/empty topic",
    );
  }
  await page.goto(base + "/wp-admin/post.php?post=" + idNew + "&action=edit");
  const apiNonce = await page.evaluate(
    () =>
      window.wpApiSettings?.nonce ||
      window.wp?.apiFetch?.nonceMiddleware?.nonce,
  );
  async function api(endpoint) {
    return page.evaluate(
      async ({ u, n }) => {
        const r = await fetch(u, { headers: { "X-WP-Nonce": n } });
        return { status: r.status, data: await r.json() };
      },
      { u: base + "/wp-json/wp/v2/" + endpoint, n: apiNonce },
    );
  }
  const allTerms = await api("lk_topic?per_page=100");
  for (const slug of visibleTopics)
    check(
      allTerms.data.some((t) => t.slug === slug),
      "Public topic is available in taxonomy: " + slug,
    );
  const free = allTerms.data.find((t) => t.slug === "tu-do");
  const custom = allTerms.data.find((t) => t.name === "Góc nhìn thành phố");
  for (const [id, topic] of [
    [idDefault, free.id],
    [idExisting, Number(landscape)],
    [idNew, custom.id],
    [idReused, custom.id],
  ]) {
    const result = await api("lk_photo/" + id + "?context=edit");
    check(
      result.status === 200 &&
        result.data.status === "pending" &&
        result.data.lk_topic.includes(topic),
      "Pending work has correct persisted topic: " + id,
    );
  }
  for (const width of [1440, 1024, 768, 390, 320]) {
    await page.setViewportSize({ width, height: 900 });
    await page.goto(base + "/");
    check(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
      "No horizontal overflow at " + width + "px",
    );
    await page.fill("#lk-search", "hoa");
    const metrics = await page.locator("#lk-search").evaluate((e) => {
      const outer = e.closest(".lk-search");
      const a = e.getBoundingClientRect(),
        b = outer.getBoundingClientRect();
      return {
        outline: getComputedStyle(e).outlineStyle,
        inside:
          a.left >= b.left &&
          a.right <= b.right &&
          a.top >= b.top &&
          a.bottom <= b.bottom,
        outerFocus: getComputedStyle(outer).outlineStyle,
      };
    });
    check(
      metrics.outline === "none" &&
        metrics.inside &&
        metrics.outerFocus === "solid",
      "Search focus stays on outer frame at " + width + "px",
    );
    check(
      await page
        .locator(".lk-card:visible")
        .nth(1)
        .evaluate((e) => e.classList.contains("lk-card-tall")),
      "Filtered gallery retains visual rhythm at " + width + "px",
    );
    await page.locator(".lk-photo:visible").first().click();
    await page.waitForFunction(() => {
      const i = document.getElementById("lk-lightbox-img");
      return i.complete && i.naturalWidth > 0 && !i.hidden;
    });
    const first = await page.locator("#lk-lightbox-img").getAttribute("src");
    check(
      first.includes("/demo-watermarked/"),
      "Lightbox uses embedded watermarked photo",
    );
    check(
      await page
        .locator(".lk-lightbox button")
        .evaluateAll((es) =>
          es.every((e) => getComputedStyle(e).borderTopWidth === "0px"),
        ),
      "Lightbox controls have no square borders at " + width + "px",
    );
    await page.click("#lk-next");
    check(
      (await page.locator("#lk-lightbox-img").getAttribute("src")) !== first,
      "Next image navigation works",
    );
    await page.keyboard.press("ArrowLeft");
    check(
      (await page.locator("#lk-lightbox-img").getAttribute("src")) === first,
      "Keyboard navigation works",
    );
    await page.click("#lk-close");
    check(
      !(await page.locator(".lk-lightbox").isVisible()),
      "Close button works",
    );
    check(
      await page
        .locator(".lk-photo:visible")
        .first()
        .evaluate((e) => document.activeElement === e),
      "Lightbox restores focus to opener",
    );
  }
  check(errors.length === 0, "No frontend JavaScript errors");
  await browser.close();
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
