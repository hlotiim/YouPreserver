const fs = require('fs');
const path = require('path');
const zlib = require('zlib');

function crc32(buf) {
	let crc = 0xffffffff;
	for (let i = 0; i < buf.length; i += 1) {
		crc ^= buf[i];
		for (let j = 0; j < 8; j += 1) {
			crc = (crc >>> 1) ^ (crc & 1 ? 0xedb88320 : 0);
		}
	}
	return (crc ^ 0xffffffff) >>> 0;
}

function chunk(type, data) {
	const len = Buffer.alloc(4);
	len.writeUInt32BE(data.length, 0);
	const typeBuf = Buffer.from(type);
	const crcBuf = Buffer.alloc(4);
	crcBuf.writeUInt32BE(crc32(Buffer.concat([typeBuf, data])), 0);
	return Buffer.concat([len, typeBuf, data, crcBuf]);
}

function createSolidPng(size, r, g, b) {
	const signature = Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]);
	const ihdr = Buffer.alloc(13);
	ihdr.writeUInt32BE(size, 0);
	ihdr.writeUInt32BE(size, 4);
	ihdr[8] = 8;
	ihdr[9] = 2;
	ihdr[10] = 0;
	ihdr[11] = 0;
	ihdr[12] = 0;

	const row = Buffer.alloc(1 + size * 3);
	row[0] = 0;
	for (let x = 0; x < size; x += 1) {
		const offset = 1 + x * 3;
		row[offset] = r;
		row[offset + 1] = g;
		row[offset + 2] = b;
	}
	const raw = Buffer.concat(Array.from({ length: size }, () => row));
	const compressed = zlib.deflateSync(raw);

	return Buffer.concat([
		signature,
		chunk('IHDR', ihdr),
		chunk('IDAT', compressed),
		chunk('IEND', Buffer.alloc(0)),
	]);
}

const outDir = path.join(__dirname, 'icons');
fs.mkdirSync(outDir, { recursive: true });
[16, 48, 128].forEach((size) => {
	const png = createSolidPng(size, 245, 118, 0);
	fs.writeFileSync(path.join(outDir, `icon${size}.png`), png);
});
console.log('Icons created');
