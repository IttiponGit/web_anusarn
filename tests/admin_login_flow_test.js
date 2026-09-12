const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const adminScript = fs.readFileSync(
  path.join(__dirname, '..', 'admin', 'admin.js'),
  'utf8'
);

class MockFormData {
  constructor() {
    this.values = new Map();
  }

  append(name, value) {
    this.values.set(name, value);
  }

  get(name) {
    return this.values.get(name);
  }
}

function makeResponse(status, payload) {
  return {
    status,
    ok: status >= 200 && status < 300,
    async json() {
      return payload;
    },
  };
}

async function flushPromises() {
  await new Promise((resolve) => setImmediate(resolve));
  await new Promise((resolve) => setImmediate(resolve));
}

async function createLoginPage(responseQueue) {
  const requests = [];
  const consoleErrors = [];
  let submitHandler = null;
  let redirectedTo = null;

  const loginForm = {
    addEventListener(type, handler) {
      if (type === 'submit') {
        submitHandler = handler;
      }
    },
  };
  const loginMessage = {
    textContent: '',
    setAttribute() {},
  };
  const usernameInput = { value: '' };
  const passwordInput = { value: '' };

  const elements = {
    loginForm,
    loginMessage,
    username: usernameInput,
    password: passwordInput,
  };

  const location = {
    hostname: 'localhost',
    href: 'http://localhost/admin/login.html',
    search: '',
    replace(url) {
      redirectedTo = url;
    },
  };

  const context = {
    console: {
      error(...args) {
        consoleErrors.push(args);
      },
    },
    document: {
      getElementById(id) {
        return elements[id] || null;
      },
    },
    window: {
      location,
      setTimeout,
    },
    FormData: MockFormData,
    URL,
    URLSearchParams,
    fetch: async (url, options = {}) => {
      requests.push({ url, options });
      assert.notEqual(responseQueue.length, 0, `Unexpected fetch to ${url}`);
      return responseQueue.shift();
    },
    setTimeout,
    clearTimeout,
  };

  vm.runInNewContext(adminScript, context, { filename: 'admin/admin.js' });
  await flushPromises();

  return {
    loginMessage,
    usernameInput,
    passwordInput,
    requests,
    consoleErrors,
    getRedirect: () => redirectedTo,
    async submit() {
      assert.equal(typeof submitHandler, 'function', 'Login submit handler was not registered');
      await submitHandler({ preventDefault() {} });
      await flushPromises();
    },
  };
}

async function run() {
  const guest = await createLoginPage([
    makeResponse(401, { success: false, logged_in: false }),
  ]);
  assert.equal(guest.loginMessage.textContent, '');
  assert.equal(guest.consoleErrors.length, 0);
  assert.equal(guest.getRedirect(), null);
  assert.equal(guest.requests[0].url, '/api/admin/me.php');
  assert.equal(guest.requests[0].options.credentials, 'same-origin');

  const invalidLogin = await createLoginPage([
    makeResponse(401, { success: false, logged_in: false }),
    makeResponse(401, { success: false, error: 'Invalid username or password' }),
  ]);
  invalidLogin.usernameInput.value = 'invalid-user';
  invalidLogin.passwordInput.value = 'invalid-password';
  await invalidLogin.submit();
  assert.equal(invalidLogin.loginMessage.textContent, 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง');
  assert.equal(invalidLogin.getRedirect(), null);
  assert.equal(invalidLogin.requests[1].url, '/api/admin/auth.php');
  assert.equal(invalidLogin.requests[1].options.method, 'POST');
  assert.equal(invalidLogin.requests[1].options.credentials, 'same-origin');
  assert.equal(invalidLogin.requests[1].options.body.get('username'), 'invalid-user');
  assert.equal(invalidLogin.requests[1].options.body.get('password'), 'invalid-password');

  const validUser = {
    user_id: 2,
    username: 'test-user',
    full_name: 'Test User',
    role: 'students',
    status: 'active',
  };
  const validLogin = await createLoginPage([
    makeResponse(401, { success: false, logged_in: false }),
    makeResponse(200, { success: true, logged_in: true }),
    makeResponse(200, { success: true, logged_in: true, data: validUser }),
  ]);
  validLogin.usernameInput.value = 'test-user';
  validLogin.passwordInput.value = 'test-password';
  await validLogin.submit();
  assert.equal(validLogin.getRedirect(), 'dashboard.html');
  assert.equal(validLogin.requests[2].url, '/api/admin/me.php');
  assert.equal(validLogin.requests[2].options.credentials, 'same-origin');
  assert.equal(validLogin.consoleErrors.length, 0);

  const existingSession = await createLoginPage([
    makeResponse(200, { success: true, logged_in: true, data: validUser }),
  ]);
  assert.equal(existingSession.getRedirect(), 'dashboard.html');
  assert.equal(existingSession.loginMessage.textContent, '');
  assert.equal(existingSession.consoleErrors.length, 0);

  console.log('PASS guest: me.php 401 is silent and remains on login page');
  console.log('PASS invalid login: auth.php 401 shows the credential error');
  console.log('PASS valid login: auth.php 200, me.php 200, then dashboard redirect');
  console.log('PASS existing session: me.php 200 redirects away from login page');
}

run().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
