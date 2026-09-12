const fs = require('fs');
const path = require('path');
const vm = require('vm');

const projectRoot = path.resolve(__dirname, '..');
const script = fs.readFileSync(
  path.join(projectRoot, 'information', 'assets', 'js', 'main.js'),
  'utf8'
);

const pages = [
  'home',
  'basic',
  'general',
  'direction',
  'performance',
  'personnel',
  'students',
  'academic',
  'budget',
  'awards',
  'downloads'
];
const roles = ['guest', 'students', 'academic', 'personnel', 'budget', 'general', 'plan', 'admin'];
const expectedRoleByPage = {
  students: 'students',
  academic: 'academic',
  personnel: 'personnel',
  budget: 'budget',
  general: 'general',
  performance: 'plan'
};

function payloadFor(url, role, status) {
  if (url.includes('/api/admin/me.php')) {
    if (role === 'guest') {
      return { ok: false, status: 401, json: async () => ({ success: false, logged_in: false }) };
    }
    return {
      ok: true,
      status: 200,
      json: async () => ({
        success: true,
        logged_in: true,
        data: { id: 1, username: `test-${role}`, role, status }
      })
    };
  }

  return { ok: true, status: 200, json: async () => ({ success: true, data: {} }) };
}

async function render(page, role, status = 'active') {
  let init = null;
  let toolbar = null;
  const content = {
    querySelector: () => null,
    prepend: (value) => { toolbar = value; }
  };
  const menuTarget = { innerHTML: '' };
  const document = {
    body: { dataset: { page } },
    addEventListener: (event, handler) => { if (event === 'DOMContentLoaded') init = handler; },
    dispatchEvent: () => {},
    getElementById: () => null,
    createElement: () => ({ className: '', dataset: {}, innerHTML: '' }),
    querySelector: (selector) => selector === '.page-content > .container-fluid' ? content : null,
    querySelectorAll: (selector) => selector === '.js-sidebar-menu, .js-offcanvas-menu' ? [menuTarget] : []
  };
  const window = {
    location: { pathname: page === 'home' ? '/information/index.html' : `/information/pages/${page}.html` },
    bootstrap: null
  };
  const context = vm.createContext({
    window,
    document,
    fetch: async (url) => payloadFor(String(url), role, status),
    CustomEvent: function CustomEvent() {},
    console,
    setTimeout,
    clearTimeout
  });

  vm.runInContext(script, context, { filename: 'information/assets/js/main.js' });
  if (typeof init !== 'function') throw new Error('DOMContentLoaded initializer was not registered');
  await init();
  await new Promise((resolve) => setImmediate(resolve));

  return { toolbar, menuMarkup: menuTarget.innerHTML };
}

function expectedBadge(page, role) {
  if (role === 'guest') return false;
  if (role === 'admin') return true;
  return expectedRoleByPage[page] === role;
}

function localTargetExists(href) {
  const cleanPath = href.split('?')[0].replace(/^\//, '');
  const target = path.join(projectRoot, cleanPath);
  if (!fs.existsSync(target)) return false;
  if (fs.statSync(target).isDirectory()) {
    return fs.existsSync(path.join(target, 'index.php')) || fs.existsSync(path.join(target, 'index.html'));
  }
  return true;
}

(async () => {
  const failures = [];
  let checks = 0;

  for (const page of pages) {
    for (const role of roles) {
      const result = await render(page, role);
      const visible = Boolean(result.toolbar);
      const expected = expectedBadge(page, role);
      checks += 1;

      if (visible !== expected) failures.push(`${role} on ${page}: badge=${visible}, expected=${expected}`);
      if (!result.menuMarkup.includes('general.html') || !result.menuMarkup.includes('ข้อมูลทั่วไป')) {
        failures.push(`${role} on ${page}: general menu link missing`);
      }

      if (result.toolbar) {
        const markup = result.toolbar.innerHTML;
        if (!markup.includes('จัดการข้อมูล')) failures.push(`${role} on ${page}: badge label missing`);
        if (page === 'general') {
          if (!markup.includes('disabled') || !markup.includes('อยู่ระหว่างพัฒนา') || markup.includes('href=')) {
            failures.push(`${role} on ${page}: general badge must be disabled without a link`);
          }
        } else {
          const href = (markup.match(/href="([^"]+)"/) || [])[1];
          if (!href || !localTargetExists(href)) failures.push(`${role} on ${page}: invalid management link ${href || '(missing)'}`);
        }
      }
    }
  }

  const inactive = await render('students', 'students', 'inactive');
  checks += 1;
  if (inactive.toolbar) failures.push('inactive students must not render a badge');

  const generalFile = path.join(projectRoot, 'information', 'pages', 'general.html');
  checks += 1;
  if (!fs.existsSync(generalFile)) failures.push('general.html does not exist');

  if (failures.length > 0) {
    console.error(JSON.stringify({ success: false, checks, failures }, null, 2));
    process.exitCode = 1;
    return;
  }

  console.log(JSON.stringify({ success: true, checks, rolePageChecks: pages.length * roles.length }, null, 2));
})();
