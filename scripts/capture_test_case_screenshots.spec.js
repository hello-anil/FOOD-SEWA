const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const baseUrl = 'http://localhost/foodmandu';
const outDir = path.resolve(__dirname, '..', 'screenshots', 'system-tests');
const password = 'Test@12345';
const userEmail = 'screenshot.user@foodmandu.test';
const adminEmail = 'screenshot.admin@foodmandu.test';

const ids = {
  category: '6',
  st001: '7',
  st002: '8',
  st004: '10',
};

async function save(page, name) {
  await page.screenshot({
    path: path.join(outDir, name),
    fullPage: true,
    animations: 'disabled',
  });
}

async function visible(locator) {
  await locator.waitFor({ state: 'visible', timeout: 15000 });
}

async function loginUser(page) {
  await page.goto(`${baseUrl}/user/auth.php?mode=login`);
  await page.getByPlaceholder('Email').fill(userEmail);
  await page.getByPlaceholder('Password').fill(password);
  await page.getByRole('button', { name: 'Login' }).click();
  await visible(page.getByText('Recent Orders'));
}

async function loginAdmin(page) {
  await page.goto(`${baseUrl}/admin/auth.php`);
  await page.getByPlaceholder('Email').fill(adminEmail);
  await page.getByPlaceholder('Password').fill(password);
  await page.getByRole('button', { name: 'Login' }).click();
  await visible(page.getByText('Total Orders'));
}

async function main() {
  fs.mkdirSync(outDir, { recursive: true });
  const browser = await chromium.launch({
    channel: 'msedge',
    headless: true,
  });

  const publicContext = await browser.newContext();
  publicContext.setDefaultTimeout(15000);
  publicContext.setDefaultNavigationTimeout(30000);
  const publicPage = await publicContext.newPage();
  await publicPage.setViewportSize({ width: 1440, height: 1000 });
  await publicPage.goto(`${baseUrl}/user/auth.php?mode=signup`);
  await save(publicPage, 'ST001_01_customer_registration_form.png');
  await publicContext.close();

  const userContext = await browser.newContext();
  userContext.setDefaultTimeout(15000);
  userContext.setDefaultNavigationTimeout(30000);
  const userPage = await userContext.newPage();
  await userPage.setViewportSize({ width: 1440, height: 1000 });
  await loginUser(userPage);

  await userPage.goto(`${baseUrl}/user/foods.php?category=${ids.category}`);
  await visible(userPage.locator('main').getByText('ST006 Evidence Burger').first());
  await save(userPage, 'ST001_02_customer_browses_foods.png');

  const foodCard = userPage.locator('.card').filter({ hasText: 'ST006 Evidence Burger' }).first();
  await foodCard.getByRole('button', { name: 'Add' }).click();
  await visible(userPage.getByText(/Added to cart|Item added to cart/));
  await userPage.goto(`${baseUrl}/user/cart.php`);
  await visible(userPage.locator('main table').getByText('ST006 Evidence Burger').first());
  await save(userPage, 'ST001_03_customer_cart_contains_item.png');

  await userPage.goto(`${baseUrl}/user/order.php?order_code=ORDST001PASS`);
  await visible(userPage.getByText('Order ORDST001PASS'));
  await save(userPage, 'ST001_04_customer_order_visible.png');

  await userPage.goto(`${baseUrl}/user/order.php?order_code=ORDST002PASS`);
  await visible(userPage.getByText('Delivery: CONFIRMED'));
  await save(userPage, 'ST002_02_customer_status_reflected.png');

  await userPage.goto(`${baseUrl}/track.php?tracking_number=TRKST003PASS`);
  await visible(userPage.getByText('Order ORDST003PASS'));
  await userPage.getByRole('button', { name: 'Track Live Location' }).click();
  await visible(userPage.getByText('Live Delivery Route'));
  await userPage.waitForTimeout(2500);
  await save(userPage, 'ST003_02_customer_live_tracking.png');

  await userPage.goto(`${baseUrl}/user/order.php?order_code=ORDST004PASS`);
  await visible(userPage.getByText('Delivery: CANCELLED'));
  await visible(userPage.getByText('Refund required'));
  await save(userPage, 'ST004_02_customer_cancelled_refund_notice.png');

  await userPage.goto(`${baseUrl}/user/foods.php?category=${ids.category}`);
  await visible(userPage.locator('main').getByText('ST006 Evidence Burger').first());
  await save(userPage, 'ST006_02_customer_menu_item_orderable.png');
  await userContext.close();

  const adminContext = await browser.newContext();
  adminContext.setDefaultTimeout(15000);
  adminContext.setDefaultNavigationTimeout(30000);
  const adminPage = await adminContext.newPage();
  await adminPage.setViewportSize({ width: 1440, height: 1000 });
  await loginAdmin(adminPage);

  await adminPage.goto(`${baseUrl}/admin/orders.php?id=${ids.st001}`);
  await visible(adminPage.getByText('Order ORDST001PASS'));
  await save(adminPage, 'ST001_05_admin_order_visible.png');

  await adminPage.goto(`${baseUrl}/admin/orders.php?id=${ids.st002}`);
  await visible(adminPage.getByText('Order ORDST002PASS'));
  await save(adminPage, 'ST002_01_admin_order_confirmed.png');

  await adminPage.goto(`${baseUrl}/admin/delivery_management.php`);
  await visible(adminPage.getByText('ORDST003PASS'));
  await visible(adminPage.getByText('OUT_FOR_DELIVERY').first());
  await save(adminPage, 'ST003_01_admin_delivery_management.png');

  await adminPage.goto(`${baseUrl}/admin/orders.php?id=${ids.st004}`);
  await visible(adminPage.getByText('Order ORDST004PASS'));
  await visible(adminPage.getByText('Refund required'));
  await save(adminPage, 'ST004_01_admin_cancelled_refund_required.png');

  await adminPage.goto(`${baseUrl}/admin/foods.php`);
  await visible(adminPage.locator('main table').getByText('ST006 Evidence Burger').first());
  await save(adminPage, 'ST006_01_admin_menu_item_created.png');

  await adminContext.close();
  await browser.close();
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
