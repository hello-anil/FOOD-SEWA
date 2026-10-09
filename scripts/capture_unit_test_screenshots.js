const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const baseUrl = 'http://localhost/foodmandu';
const outDir = path.resolve(__dirname, '..', 'screenshots', 'unit-tests');
const password = 'Test@12345';
const adminEmail = 'screenshot.admin@foodmandu.test';
const loginEmail = 'screenshot.user@foodmandu.test';
const foodImage = path.resolve(__dirname, '..', 'uploads', 'foods', '1cb2396bb797d2b3f7436e0d0ac827a3.png');

async function visible(locator) {
  await locator.waitFor({ state: 'visible', timeout: 15000 });
}

async function save(page, name) {
  await page.screenshot({
    path: path.join(outDir, name),
    fullPage: true,
    animations: 'disabled',
  });
}

async function loginAdmin(page) {
  await page.goto(`${baseUrl}/admin/auth.php`);
  await page.getByPlaceholder('Email').fill(adminEmail);
  await page.getByPlaceholder('Password').fill(password);
  await page.getByRole('button', { name: 'Login' }).click();
  await visible(page.getByText('Total Orders'));
}

async function loginUser(page, email = loginEmail) {
  await page.goto(`${baseUrl}/user/auth.php?mode=login`);
  await page.getByPlaceholder('Email').fill(email);
  await page.getByPlaceholder('Password').fill(password);
  await page.getByRole('button', { name: 'Login' }).click();
  await visible(page.getByText('Recent Orders'));
}

async function main() {
  fs.mkdirSync(outDir, { recursive: true });

  const stamp = Date.now();
  const registeredEmail = `unit.customer.${stamp}@foodmandu.test`;
  const menuItem = `TC003 Unit Menu ${stamp}`;

  const browser = await chromium.launch({ channel: 'msedge', headless: true });

  const registrationContext = await browser.newContext();
  registrationContext.setDefaultTimeout(15000);
  registrationContext.setDefaultNavigationTimeout(30000);
  const registrationPage = await registrationContext.newPage();
  await registrationPage.setViewportSize({ width: 1440, height: 1000 });
  await registrationPage.goto(`${baseUrl}/user/auth.php?mode=signup`);
  await registrationPage.getByPlaceholder('Full name').fill('Unit Customer');
  await registrationPage.getByPlaceholder('Email').fill(registeredEmail);
  await registrationPage.getByPlaceholder('Phone').fill('9840012345');
  await registrationPage.getByPlaceholder('Password').fill(password);
  await registrationPage.getByRole('button', { name: 'Create Account' }).click();
  await visible(registrationPage.getByText('Registration successful'));
  await visible(registrationPage.getByText('Recent Orders'));
  await save(registrationPage, 'TC001_customer_registration_success.png');
  await registrationContext.close();

  const loginContext = await browser.newContext();
  loginContext.setDefaultTimeout(15000);
  loginContext.setDefaultNavigationTimeout(30000);
  const loginPage = await loginContext.newPage();
  await loginPage.setViewportSize({ width: 1440, height: 1000 });
  await loginUser(loginPage, registeredEmail);
  await visible(loginPage.getByText('Welcome back'));
  await save(loginPage, 'TC002_customer_login_success.png');
  await loginContext.close();

  const adminContext = await browser.newContext();
  adminContext.setDefaultTimeout(15000);
  adminContext.setDefaultNavigationTimeout(30000);
  const adminPage = await adminContext.newPage();
  await adminPage.setViewportSize({ width: 1440, height: 1000 });
  await loginAdmin(adminPage);
  await adminPage.goto(`${baseUrl}/admin/foods.php`);
  await adminPage.locator('select[name="category_id"]').selectOption({ label: 'ST Evidence' });
  await adminPage.locator('input[name="name"]').fill(menuItem);
  await adminPage.locator('textarea[name="description"]').fill('Unit testing screenshot menu item.');
  await adminPage.locator('input[name="price"]').fill('399');
  await adminPage.locator('input[name="stock_qty"]').fill('12');
  await adminPage.locator('input[name="image"]').setInputFiles(foodImage);
  await adminPage.locator('select[name="status"]').selectOption('ACTIVE');
  await adminPage.getByRole('button', { name: 'Create' }).click();
  await visible(adminPage.getByText('Food created'));
  await visible(adminPage.locator('main table').getByText(menuItem).first());
  await save(adminPage, 'TC003_admin_menu_item_created.png');
  await adminContext.close();

  const orderContext = await browser.newContext();
  orderContext.setDefaultTimeout(15000);
  orderContext.setDefaultNavigationTimeout(30000);
  const orderPage = await orderContext.newPage();
  await orderPage.setViewportSize({ width: 1440, height: 1000 });
  await loginUser(orderPage, registeredEmail);
  await orderPage.goto(`${baseUrl}/user/addresses.php`);
  await orderPage.locator('select[name="address_name"]').selectOption('HOME');
  await orderPage.locator('input[name="address_line_1"]').fill('Unit Test Street');
  await orderPage.locator('input[name="address_line_2"]').fill('Near unit test landmark');
  await orderPage.locator('input[name="city"]').fill('Kathmandu');
  await orderPage.locator('input[name="zone"]').fill('Bagmati');
  await orderPage.locator('input[name="is_default"]').check();
  await orderPage.getByRole('button', { name: 'Save' }).click();
  await visible(orderPage.getByText('Address added'));

  await orderPage.goto(`${baseUrl}/user/foods.php?category=6`);
  const card = orderPage.locator('.card').filter({ hasText: menuItem }).first();
  await visible(card);
  await card.locator('input[name="qty"]').fill('1');
  await card.getByRole('button', { name: 'Add' }).click();
  await visible(orderPage.getByText(/Added to cart|Item added to cart/));

  await orderPage.goto(`${baseUrl}/user/checkout.php`);
  await orderPage.locator('select[name="gateway"]').selectOption('STRIPE');
  await orderPage.getByRole('button', { name: 'Place Order and Pay' }).click({ noWaitAfter: true });
  await orderPage.waitForTimeout(3000);

  await orderPage.goto(`${baseUrl}/user/dashboard.php`);
  const orderCode = await orderPage.evaluate(async () => {
    const response = await fetch('/foodmandu/user/dashboard.php', { credentials: 'same-origin' });
    const html = await response.text();
    const match = html.match(/ORD[A-Z0-9]{10}/);
    return match ? match[0] : '';
  });

  if (orderCode) {
    await orderPage.goto(`${baseUrl}/user/order.php?order_code=${orderCode}`);
  } else if (!/\/user\/order\.php/.test(orderPage.url())) {
    await orderPage.goto(`${baseUrl}/user/order.php`);
  }
  await visible(orderPage.getByText(/Order ORD|Your Orders/).first());
  await save(orderPage, 'TC004_order_created_unique_number.png');
  await orderContext.close();

  await browser.close();
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
