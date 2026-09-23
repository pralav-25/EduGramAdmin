const test = require('node:test');
const assert = require('node:assert/strict');
const { setImmediate: settle } = require('node:timers/promises');
const { initDirectory } = require('../dashboard/ui.js');

function element() {
  return {
    children: [], events: {}, value: '', textContent: '', disabled: false,
    appendChild(child) { this.children.push(child); },
    replaceChildren(...children) { this.children = children; },
    addEventListener(type, listener) { this.events[type] = listener; },
  };
}
function fixture(t) {
  const search = element(), table = element(), status = element(), pagination = element();
  const requests = [];
  const responses = [];
  t.mock.method(global, 'fetch', async url => {
    requests.push(new URL(url, 'https://test/dashboard/').searchParams);
    const next = responses.shift();
    if (next instanceof Error) throw next;
    return { ok: true, json: async () => next };
  });
  const original = global.document;
  global.document = {
    getElementById: id => ({ search, pagination, 'directory-status': status })[id],
    querySelector: () => table,
    createElement: element,
  };
  t.after(() => { global.document = original; });
  return { search, table, status, pagination, requests, responses };
}
const page = name => ({ data: [{ name }], total: 60 });

test('Retry repeats the failed next page while preserving the displayed rows', async t => {
  const f = fixture(t);
  f.responses.push(page('Alice'));
  initDirectory('students', ['name']);
  await settle();
  const [, next, retry] = f.pagination.children;
  f.responses.push(new Error('Temporarily unavailable'));
  next.onclick();
  await settle();
  assert.equal(f.table.children[0].children[1].textContent, 'Alice');
  assert.equal(retry.hidden, false);
  f.responses.push(page('Bob'));
  retry.onclick();
  await settle();
  assert.deepEqual(f.requests.map(query => query.get('page')), ['1', '2', '2']);
  assert.equal(f.table.children[0].children[1].textContent, 'Bob');
  assert.match(f.status.textContent, /Page 2/);
});

test('a failed new search cannot restore the old query page or stale rows', async t => {
  t.mock.timers.enable({ apis: ['setTimeout'] });
  const f = fixture(t);
  f.responses.push(page('Page one'));
  initDirectory('students', ['name']);
  await settle();
  const [prev, next, retry] = f.pagination.children;
  f.responses.push(page('Page two'));
  next.onclick();
  await settle();
  f.search.value = 'Zoe';
  f.responses.push(new Error('Connection interrupted'));
  f.search.events.input();
  t.mock.timers.tick(200);
  await settle();
  assert.equal(f.table.children.length, 0);
  assert.equal(prev.disabled, true);
  f.responses.push(page('Zoe'));
  retry.onclick();
  await settle();
  assert.deepEqual(f.requests.slice(-2).map(query => [query.get('page'), query.get('q')]),
    [['1', 'Zoe'], ['1', 'Zoe']]);
  assert.equal(f.table.children[0].children[0].textContent, '1');
});
