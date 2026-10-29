// Run against the disposable fixture installation only. See tests/README.md.
const { chromium } = require(process.env.LK_PLAYWRIGHT_PATH || "playwright");
const assert = require("node:assert/strict");
const path = require("node:path");
const base = process.env.LK_TEST_URL;
if (
  process.env.LK_DISPOSABLE_WP !== "1" ||
  !base ||
  !process.env.LK_TEST_PASSWORD
)
  throw Error("Disposable WordPress environment required");
(async () => {
  const browser = await chromium.launch({
    executablePath: process.env.LK_BROWSER || "/usr/bin/microsoft-edge",
    headless: true,
    args: ["--no-sandbox"],
  });
  const context = await browser.newContext();
  await context.route("**/*", (route) =>
    new URL(route.request().url()).origin === new URL(base).origin
      ? route.continue()
      : route.abort(),
  );
  const page = await context.newPage();
  page.setDefaultNavigationTimeout(60000);
  const check = (v, message) => {
    assert.ok(v, message);
    console.log("PASS: " + message);
  };
  async function login(role) {
    await context.clearCookies();
    await page.goto(base + "/wp-login.php");
    await page.locator("#user_login").fill("lk_fixture_" + role);
    await page.locator("#user_pass").fill(process.env.LK_TEST_PASSWORD);
    await Promise.all([
      page.waitForURL((u) => !u.pathname.endsWith("wp-login.php"), {
        waitUntil: "domcontentloaded",
        timeout: 60000,
      }),
      page.locator("#wp-submit").click(),
    ]);
  }
  await page.goto(base + "/dang-tac-pham/");
  check(
    await page.getByRole("link", { name: "Đăng nhập", exact: true }).count(),
    "Guest sees login link",
  );
  check(
    !(await page.locator("form[enctype]").count()),
    "Guest has no upload form",
  );
  await login("subscriber");
  await page.goto(base + "/dang-tac-pham/");
  check(
    (await page.textContent("body")).includes("chưa được cấp quyền"),
    "Subscriber cannot upload",
  );
  await login("author");
  await page.goto(base + "/dang-tac-pham/?lk_upload=pending");
  check(
    !(await page.locator(".lk-upload-message--success").count()),
    "Forged pending query cannot show success",
  );
  async function submit(title, file) {
    await page.locator("#lk-photo-title").fill(title);
    await page.locator("#lk-photo-file").setInputFiles(file);
    await Promise.all([
      page.waitForNavigation(),
      page.getByRole("button", { name: "Gửi tác phẩm", exact: true }).click(),
    ]);
  }
  const image = path.resolve(
    __dirname,
    "../astra-child/assets/demo/hoa-sen-26.jpg",
  );
  await submit("Ảnh thử lần một", image);
  check(
    await page.locator(".lk-upload-message--success").count(),
    "First image stored and receipt shown",
  );
  const first = new URL(page.url()).searchParams.get("lk_upload_id");
  const firstURL = page.url();
  check(
    (await page.locator("form[enctype]").getAttribute("action")) ===
      base + "/dang-tac-pham/",
    "Form action is clean",
  );
  await submit("Ảnh thử lần hai", image);
  const second = new URL(page.url()).searchParams.get("lk_upload_id");
  check(
    first && second && first !== second,
    "Consecutive uploads create two different works",
  );
  check(
    await page.locator(".lk-upload-message--success").count(),
    "Second image receives success receipt",
  );
  await page.goto(firstURL);
  // POST directly to the old success URL to verify server handling, independently of form action.
  const nonce = await page.locator("[name=lk_upload_nonce]").inputValue();
  const invalidNonce = await context.request.post(firstURL, {
    multipart: {
      lk_upload_submit: "1",
      lk_upload_nonce: "invalid",
      lk_photo_title: "Bad nonce",
      lk_photo_file: {
        name: "image.jpg",
        mimeType: "image/jpeg",
        buffer: require("node:fs").readFileSync(image),
      },
    },
  });
  check(
    (await invalidNonce.text()).includes("Phiên gửi biểu mẫu đã hết hạn"),
    "POST on receipt URL rejects invalid nonce",
  );
  const invalidFile = await context.request.post(firstURL, {
    multipart: {
      lk_upload_submit: "1",
      lk_upload_nonce: nonce,
      lk_photo_title: "Bad file",
      lk_photo_file: {
        name: "fake.jpg",
        mimeType: "image/jpeg",
        buffer: Buffer.from("This is not an image"),
      },
    },
  });
  check(
    (await invalidFile.text()).includes("Chỉ chấp nhận ảnh"),
    "Fake JPEG is refused",
  );
  const oversized = await context.request.post(firstURL, {
    multipart: {
      lk_upload_submit: "1",
      lk_upload_nonce: nonce,
      lk_photo_title: "Oversized image",
      lk_photo_file: {
        name: "oversized.jpg",
        mimeType: "image/jpeg",
        buffer: Buffer.alloc(13 * 1024 * 1024),
      },
    },
  });
  const oversizedText = await oversized.text();
  check(
    oversizedText.includes("Ảnh vượt quá giới hạn") ||
      oversizedText.includes("Không tải được tệp") ||
      oversizedText.includes("Hãy nhập tên tác phẩm"),
    "Oversized upload is refused by form or PHP limit",
  );
  check(
    !oversizedText.includes("lk-upload-message--success"),
    "Oversized upload never reports success",
  );
  for (const failure of ["insert", "thumbnail"]) {
    const response = await context.request.post(firstURL, {
      headers: { "X-LK-Test-Failure": failure },
      multipart: {
        lk_upload_submit: "1",
        lk_upload_nonce: nonce,
        lk_photo_title: "Must be cleaned " + failure,
        lk_photo_file: {
          name: "cleanup-fixture.jpg",
          mimeType: "image/jpeg",
          buffer: require("node:fs").readFileSync(image),
        },
      },
    });
    const text = await response.text();
    check(
      text.includes(
        failure === "insert" ? "Không lưu được tác phẩm" : "Không gắn được ảnh",
      ),
      "Injected " + failure + " failure displays an error",
    );
    check(
      !text.includes("lk-upload-message--success"),
      "Injected " + failure + " failure never reports success",
    );
  }
  await page.goto(base + "/wp-admin/post.php?post=" + first + "&action=edit");
  // Standard wp-admin save endpoint must not let Author set publish.
  const editNonce = await page.locator("#_wpnonce").inputValue();
  await context.request.post(base + "/wp-admin/post.php", {
    form: {
      action: "editpost",
      post_ID: first,
      post_type: "lk_photo",
      _wpnonce: editNonce,
      post_title: "Ảnh thử lần một",
      post_status: "publish",
    },
  });
  await login("editor");
  await page.goto(base + "/wp-admin/post.php?post=" + first + "&action=edit");
  const apiNonce = await page.evaluate(
    () =>
      window.wpApiSettings?.nonce ||
      window.wp?.apiFetch?.nonceMiddleware?.nonce,
  );
  check(apiNonce, "Editor has authenticated REST nonce");
  async function api(url, method = "GET", body) {
    return page.evaluate(
      async ({ url, method, body, nonce }) => {
        const r = await fetch(url, {
          method,
          headers: { "X-WP-Nonce": nonce, "Content-Type": "application/json" },
          body: body ? JSON.stringify(body) : undefined,
        });
        return { status: r.status, data: await r.json() };
      },
      { url: base + "/wp-json/wp/v2/" + url, method, body, nonce: apiNonce },
    );
  }
  for (const id of [first, second]) {
    const work = await api("lk_photo/" + id + "?context=edit");
    check(
      work.status === 200 &&
        work.data.status === "pending" &&
        work.data.featured_media > 0,
      "Stored work " + id + " is pending with thumbnail",
    );
  }
  const failedWorks = await api(
    "lk_photo?context=edit&status=pending&search=Must%20be%20cleaned",
  );
  const failedMedia = await api("media?context=edit&search=cleanup-fixture");
  check(
    failedWorks.status === 200 &&
      failedWorks.data.length === 0 &&
      failedMedia.status === 200 &&
      failedMedia.data.length === 0,
    "Failure cleanup leaves no work or attachment",
  );
  check(
    (await api("lk_photo/" + first, "POST", { status: "publish" })).status ===
      200,
    "Editor approves work",
  );
  await page.goto(base + "/");
  check(
    (await page.locator(".lk-card").count()) === 31,
    "One published work plus all 30 demos",
  );
  check(
    (await page.locator(".lk-card").first().getAttribute("data-title")) ===
      "Ảnh thử lần một",
    "Real work comes before demos",
  );
  check(
    await page
      .getByRole("link", { name: "Đăng tác phẩm", exact: true })
      .isVisible(),
    "Upload link visible on homepage",
  );
  await page.locator("#lk-search").fill("anh thu lan mot");
  check(
    (await page.locator(".lk-card:visible").count()) === 1,
    "Accent-insensitive search finds real image",
  );
  await page.locator(".lk-card:visible .lk-photo").click();
  check(await page.locator(".lk-lightbox").isVisible(), "Lightbox opens");
  await page.keyboard.press("Escape");
  await page.locator("#lk-search").fill("");
  await page.locator("#lk-author").selectOption("Bộ sưu tập mẫu");
  check(
    (await page.locator(".lk-card:visible").count()) === 30,
    "Author filter finds all demos",
  );
  await page.locator('[data-album="thien-nhien"]').click();
  check(
    (await page.locator(".lk-card:visible").count()) > 0 &&
      (await page.locator("#lk-author").inputValue()) === "",
    "Album resets search/author and filters topic",
  );
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(base + "/");
  check(
    await page.evaluate(
      () => document.documentElement.scrollWidth <= innerWidth,
    ),
    "Mobile homepage has no horizontal overflow",
  );
  check(
    await page
      .getByRole("link", { name: "Đăng tác phẩm", exact: true })
      .isVisible(),
    "Upload link visible on mobile",
  );
  await page.goto(base + "/dang-tac-pham/");
  check(
    await page.evaluate(
      () => document.documentElement.scrollWidth <= innerWidth,
    ),
    "Mobile form has no horizontal overflow",
  );
  await browser.close();
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
