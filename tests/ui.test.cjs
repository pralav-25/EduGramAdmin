const test = require('node:test');
const assert = require('node:assert/strict');
const { escapeHtml, requestJson } = require('../dashboard/ui.js');
test('student names, subjects and descriptions remain plain text', () => {
  const value = `<img src=x onerror="alert(1)"> & 'score'`;
  assert.equal(escapeHtml(value), '&lt;img src=x onerror=&quot;alert(1)&quot;&gt; &amp; &#39;score&#39;');
  assert.equal(escapeHtml(null), ''); assert.equal(escapeHtml(0), '0');
});
test('failed HTTP and malformed responses do not masquerade as directory data', async () => {
  const original = global.fetch;
  try {
    global.fetch = async () => ({ ok: false, status: 500, json: async () => ({ error: 'Database unavailable' }) });
    await assert.rejects(requestJson('/api'), /Database unavailable/);
    global.fetch = async () => ({ ok: true, json: async () => { throw new SyntaxError(); } });
    await assert.rejects(requestJson('/api'), /unreadable response/);
    global.fetch = async () => ({ ok: true, json: async () => ({ data: [], total: 0 }) });
    assert.deepEqual(await requestJson('/api'), { data: [], total: 0 });
  } finally { global.fetch = original; }
});

test('directory CSV keeps Unicode, quotes, newlines and zero; escapes formulas', () => {
  const { directoryCsv } = require('../dashboard/ui.js');
  assert.equal(directoryCsv([{ name: 'ଓଡ଼ିଆ, "student"', score: 0 },
    { name: '=SUM(1,2)', score: null }, { name: 'line\nbreak', score: 5 }], ['name', 'score']),
    '"name","score"\r\n"ଓଡ଼ିଆ, ""student""","0"\r\n"\'=SUM(1,2)",""\r\n"line\nbreak","5"\r\n');
  assert.equal(directoryCsv([], ['name']), '"name"\r\n');
  for (const name of [' +formula', '-formula', '@formula', '\tvalue', '\rvalue'])
    assert.ok(directoryCsv([{ name }], ['name']).includes('"\'' + name + '"'));
});
