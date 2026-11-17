const { test } = require('node:test');
const assert = require('node:assert/strict');
const handler = require('../api/contact');

function response() {
  return {
    headers: {}, setHeader(key, value) { this.headers[key] = value; },
    status(code) { this.code = code; return this; },
    json(body) { this.body = body; return this; }
  };
}

test('rejects GET without contacting SMTP', async () => {
  const res = response();
  await handler({ method: 'GET' }, res);
  assert.equal(res.code, 405);
  assert.equal(res.headers.Allow, 'POST');
});
test('validates required fields', async () => {
  const res = response();
  await handler({ method: 'POST', body: {} }, res);
  assert.equal(res.code, 422);
});
test('honeypot accepts silently', async () => {
  const res = response();
  await handler({ method: 'POST', body: { website: 'spam' } }, res);
  assert.equal(res.code, 200);
  assert.equal(res.body.ok, true);
});
test('rejects header injection', async () => {
  const res = response();
  await handler({ method: 'POST', body: {
    name: 'Test', email: 'a@example.com\r\nBcc: b@example.com', message: 'A long enough message'
  } }, res);
  assert.equal(res.code, 422);
});
test('accepts URL-encoded requests and fails safely without credentials', async () => {
  const res = response();
  await handler({ method: 'POST', body: 'name=Test&email=test%40example.com&message=This+is+a+test+message' }, res);
  assert.equal(res.code, 503);
  assert.equal(res.body.ok, false);
});
