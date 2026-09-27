import { after, before, test } from 'node:test';
import assert from 'node:assert/strict';
import { createRealtimeServer } from '../src/realtime-hub.js';
import { signJwt, verifyJwt } from '../src/jwt.util.js';
import { OPCODE, decodeFrames, encodeClientFrame, encodeFrame } from '../src/websocket-frame.util.js';

const SECRET = 'test-secret';
const API_KEY = 'test-key';
let server;
let baseUrl;

before(async () => {
  server = createRealtimeServer({ jwtSecret: SECRET, apiKey: API_KEY, log: {} });
  await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
  baseUrl = `127.0.0.1:${server.address().port}`;
});

after(() => server.close());

function tokenFor(sub, exp = Math.floor(Date.now() / 1000) + 60) {
  return signJwt({ sub, email: `${sub}@test`, exp }, SECRET);
}

/** Opens a socket and resolves with it plus a queue of received events. */
function connect(token) {
  return new Promise((resolve, reject) => {
    const socket = new WebSocket(`ws://${baseUrl}/ws?token=${encodeURIComponent(token)}`);
    const events = [];
    const waiters = [];
    socket.addEventListener('message', (message) => {
      const event = JSON.parse(message.data);
      const waiter = waiters.shift();
      if (waiter) waiter(event);
      else events.push(event);
    });
    socket.addEventListener('error', () => reject(new Error('connection failed')));
    const next = () =>
      events.length ? Promise.resolve(events.shift()) : new Promise((done) => waiters.push(done));
    socket.addEventListener('open', async () => {
      assert.equal((await next()).event, 'connected');
      resolve({ socket, next });
    });
  });
}

function publish(body, apiKey = API_KEY) {
  return fetch(`http://${baseUrl}/publish`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Realtime-Api-Key': apiKey },
    body: JSON.stringify(body),
  });
}

test('verifyJwt accepts a valid token and rejects forged, expired or subject-less ones', () => {
  assert.equal(verifyJwt(tokenFor('u1'), SECRET).sub, 'u1');
  assert.equal(verifyJwt(tokenFor('u1'), 'other-secret'), null);
  assert.equal(verifyJwt(tokenFor('u1', 10), SECRET), null);
  assert.equal(verifyJwt(signJwt({ exp: 9999999999 }, SECRET), SECRET), null);
  assert.equal(verifyJwt('not.a.jwt', SECRET), null);
});

test('frames round-trip, including 16-bit lengths and partial buffers', () => {
  const big = 'x'.repeat(300);
  const encoded = Buffer.concat([encodeFrame(OPCODE.text, 'hi'), encodeFrame(OPCODE.text, big)]);

  const partial = decodeFrames(encoded.subarray(0, encoded.length - 10));
  assert.equal(partial.frames.length, 1);
  assert.equal(partial.frames[0].payload.toString(), 'hi');

  const { frames, rest } = decodeFrames(encoded);
  assert.equal(frames[1].payload.toString(), big);
  assert.equal(rest.length, 0);

  const [clientFrame] = decodeFrames(encodeClientFrame(OPCODE.text, '{"type":"ping"}')).frames;
  assert.equal(clientFrame.payload.toString(), '{"type":"ping"}');
});

test('delivers published events only to the sockets of that user', async () => {
  const alice = await connect(tokenFor('alice'));
  const bob = await connect(tokenFor('bob'));

  const response = await publish({ userId: 'alice', event: 'message.created', data: { text: 'olá' } });
  assert.equal(response.status, 202);
  assert.deepEqual(await response.json(), { delivered: 1 });
  assert.deepEqual(await alice.next(), { event: 'message.created', data: { text: 'olá' } });

  await publish({ userId: 'bob', event: 'monitor.updated', data: { id: 1 } });
  assert.deepEqual(await bob.next(), { event: 'monitor.updated', data: { id: 1 } });

  alice.socket.close();
  bob.socket.close();
});

test('answers application pings', async () => {
  const client = await connect(tokenFor('carol'));
  client.socket.send(JSON.stringify({ type: 'ping' }));
  assert.equal((await client.next()).event, 'pong');
  client.socket.close();
});

test('rejects publishers without the api key and sockets without a valid token', async () => {
  assert.equal((await publish({ userId: 'alice', event: 'x' }, 'wrong')).status, 401);
  assert.equal((await publish({ event: 'x' })).status, 422);
  await assert.rejects(connect(tokenFor('alice', 10)));
});
