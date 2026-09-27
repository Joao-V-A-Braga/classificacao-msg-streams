import { createRealtimeServer } from './realtime-hub.js';

const port = Number(process.env.PORT ?? 8200);
const jwtSecret = process.env.JWT_SECRET ?? '';
const apiKey = process.env.REALTIME_API_KEY ?? '';

if (!jwtSecret || !apiKey) {
  console.error('JWT_SECRET and REALTIME_API_KEY are required (see apps/backend/.env.local).');
  process.exit(1);
}

const server = createRealtimeServer({ jwtSecret, apiKey });

server.listen(port, '0.0.0.0', () => {
  console.log(`Realtime hub listening on ws://0.0.0.0:${port}/ws`);
});

for (const signal of ['SIGINT', 'SIGTERM']) {
  process.on(signal, () => {
    server.close();
    process.exit(0);
  });
}
