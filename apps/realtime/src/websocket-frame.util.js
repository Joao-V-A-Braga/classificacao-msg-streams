import { createHash } from 'node:crypto';

// RFC 6455 — just what the hub needs: server frames are never masked, client frames always are.

const HANDSHAKE_GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

export const OPCODE = { text: 0x1, close: 0x8, ping: 0x9, pong: 0xa };

export function acceptKey(secWebSocketKey) {
  return createHash('sha1').update(secWebSocketKey + HANDSHAKE_GUID).digest('base64');
}

export function encodeFrame(opcode, payload = Buffer.alloc(0)) {
  const data = Buffer.isBuffer(payload) ? payload : Buffer.from(payload, 'utf8');
  let header;

  if (data.length < 126) {
    header = Buffer.from([0x80 | opcode, data.length]);
  } else if (data.length < 65536) {
    header = Buffer.alloc(4);
    header[0] = 0x80 | opcode;
    header[1] = 126;
    header.writeUInt16BE(data.length, 2);
  } else {
    header = Buffer.alloc(10);
    header[0] = 0x80 | opcode;
    header[1] = 127;
    header.writeBigUInt64BE(BigInt(data.length), 2);
  }

  return Buffer.concat([header, data]);
}

/**
 * Splits `buffer` into complete frames. Returns the frames and the unconsumed rest (a frame that
 * has not fully arrived yet). Fragmented messages are not needed by the hub and are not reassembled.
 */
export function decodeFrames(buffer) {
  const frames = [];
  let offset = 0;

  while (buffer.length - offset >= 2) {
    const first = buffer[offset];
    const second = buffer[offset + 1];
    const masked = (second & 0x80) !== 0;
    let length = second & 0x7f;
    let cursor = offset + 2;

    if (length === 126) {
      if (buffer.length < cursor + 2) break;
      length = buffer.readUInt16BE(cursor);
      cursor += 2;
    } else if (length === 127) {
      if (buffer.length < cursor + 8) break;
      length = Number(buffer.readBigUInt64BE(cursor));
      cursor += 8;
    }

    const maskLength = masked ? 4 : 0;
    if (buffer.length < cursor + maskLength + length) break;

    const mask = masked ? buffer.subarray(cursor, cursor + 4) : null;
    cursor += maskLength;
    const payload = Buffer.from(buffer.subarray(cursor, cursor + length));
    if (mask) {
      for (let i = 0; i < payload.length; i++) payload[i] ^= mask[i % 4];
    }

    frames.push({ fin: (first & 0x80) !== 0, opcode: first & 0x0f, payload });
    offset = cursor + length;
  }

  return { frames, rest: buffer.subarray(offset) };
}

/** Test helper: a masked frame as a browser would send it. */
export function encodeClientFrame(opcode, payload) {
  const data = Buffer.from(payload, 'utf8');
  const mask = Buffer.from([1, 2, 3, 4]);
  const masked = Buffer.from(data.map((byte, i) => byte ^ mask[i % 4]));
  return Buffer.concat([Buffer.from([0x80 | opcode, 0x80 | data.length]), mask, masked]);
}
