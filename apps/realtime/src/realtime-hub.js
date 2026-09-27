import { createServer } from 'node:http';
import { timingSafeEqual } from 'node:crypto';
import { verifyJwt } from './jwt.util.js';
import { OPCODE, acceptKey, decodeFrames, encodeFrame } from './websocket-frame.util.js';

const HEARTBEAT_MS = 30_000;
const MAX_PUBLISH_BYTES = 1024 * 1024;

/**
 * Realtime hub.
 *
 *  - Browsers connect to `GET /ws?token=<user JWT>` (WebSocket) and receive `{event, data}` JSON
 *    messages for their own user only.
 *  - The backend pushes events with `POST /publish {userId, event, data}` and the
 *    `X-Realtime-Api-Key` header.
 *
 * It keeps no state besides the open connections: events of users with no open tab are dropped.
 */
export function createRealtimeServer({ jwtSecret, apiKey, log = console }) {
  /** @type {Map<string, Set<import('node:net').Socket>>} */
  const clientsByUser = new Map();

  function send(socket, message) {
    if (!socket.destroyed) socket.write(encodeFrame(OPCODE.text, JSON.stringify(message)));
  }

  function publish(userId, message) {
    const sockets = clientsByUser.get(userId);
    if (!sockets) return 0;
    for (const socket of sockets) send(socket, message);
    return sockets.size;
  }

  function isAuthorizedPublisher(request) {
    const provided = Buffer.from(String(request.headers['x-realtime-api-key'] ?? ''));
    const expected = Buffer.from(apiKey ?? '');
    return expected.length > 0 && provided.length === expected.length && timingSafeEqual(provided, expected);
  }

  function reply(response, status, body) {
    response.writeHead(status, { 'Content-Type': 'application/json' });
    response.end(JSON.stringify(body));
  }

  const server = createServer((request, response) => {
    const { pathname } = new URL(request.url ?? '/', 'http://hub');

    if (request.method === 'GET' && pathname === '/health') {
      const connections = [...clientsByUser.values()].reduce((total, sockets) => total + sockets.size, 0);
      return reply(response, 200, { status: 'ok', connections });
    }

    if (request.method !== 'POST' || pathname !== '/publish') {
      return reply(response, 404, { error: 'not_found' });
    }

    if (!isAuthorizedPublisher(request)) {
      return reply(response, 401, { error: 'invalid_api_key' });
    }

    const chunks = [];
    let size = 0;
    request.on('data', (chunk) => {
      size += chunk.length;
      if (size > MAX_PUBLISH_BYTES) request.destroy();
      else chunks.push(chunk);
    });
    request.on('end', () => {
      let body;
      try {
        body = JSON.parse(Buffer.concat(chunks).toString('utf8'));
      } catch {
        return reply(response, 400, { error: 'invalid_json' });
      }
      if (typeof body?.userId !== 'string' || typeof body?.event !== 'string') {
        return reply(response, 422, { error: 'userId_and_event_required' });
      }
      const delivered = publish(body.userId, { event: body.event, data: body.data ?? null });
      reply(response, 202, { delivered });
    });
  });

  server.on('upgrade', (request, socket) => {
    const url = new URL(request.url ?? '/', 'http://hub');
    const key = request.headers['sec-websocket-key'];
    const payload = verifyJwt(url.searchParams.get('token'), jwtSecret);

    if (url.pathname !== '/ws' || typeof key !== 'string' || request.headers.upgrade?.toLowerCase() !== 'websocket') {
      socket.end('HTTP/1.1 400 Bad Request\r\n\r\n');
      return;
    }
    if (!payload) {
      socket.end('HTTP/1.1 401 Unauthorized\r\n\r\n');
      return;
    }

    socket.write(
      'HTTP/1.1 101 Switching Protocols\r\n' +
        'Upgrade: websocket\r\n' +
        'Connection: Upgrade\r\n' +
        `Sec-WebSocket-Accept: ${acceptKey(key)}\r\n\r\n`,
    );
    socket.setNoDelay(true);

    const userId = payload.sub;
    if (!clientsByUser.has(userId)) clientsByUser.set(userId, new Set());
    clientsByUser.get(userId).add(socket);

    let pending = Buffer.alloc(0);
    let alive = true;

    const heartbeat = setInterval(() => {
      if (!alive) return socket.destroy();
      alive = false;
      socket.write(encodeFrame(OPCODE.ping));
    }, HEARTBEAT_MS);

    const cleanup = () => {
      clearInterval(heartbeat);
      const sockets = clientsByUser.get(userId);
      sockets?.delete(socket);
      if (sockets?.size === 0) clientsByUser.delete(userId);
    };

    socket.on('data', (chunk) => {
      const { frames, rest } = decodeFrames(Buffer.concat([pending, chunk]));
      pending = Buffer.from(rest);

      for (const frame of frames) {
        if (frame.opcode === OPCODE.close) {
          socket.end(encodeFrame(OPCODE.close));
          return;
        }
        if (frame.opcode === OPCODE.ping) socket.write(encodeFrame(OPCODE.pong, frame.payload));
        if (frame.opcode === OPCODE.pong) alive = true;
        // Text frames: the browser only sends {"type":"ping"} keep-alives; any traffic proves it is alive.
        if (frame.opcode === OPCODE.text) {
          alive = true;
          if (frame.payload.toString('utf8').includes('"ping"')) send(socket, { event: 'pong', data: null });
        }
      }
    });
    socket.on('close', cleanup);
    socket.on('error', (error) => {
      log.warn?.(`socket error for user ${userId}: ${error.message}`);
      cleanup();
    });

    send(socket, { event: 'connected', data: { userId } });
  });

  return server;
}
