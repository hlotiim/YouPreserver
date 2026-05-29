const http = require('http');
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const port = 3457;
const mime = {
	'.html': 'text/html; charset=utf-8',
	'.css': 'text/css; charset=utf-8',
	'.js': 'application/javascript; charset=utf-8',
	'.svg': 'image/svg+xml',
};

http.createServer((req, res) => {
	let rel = decodeURIComponent((req.url.split('?')[0] || '/'));
	if (rel === '/' || rel === '') {
		rel = '/preview/index.html';
	}
	if (rel.endsWith('/')) {
		rel += 'index.html';
	}

	const filePath = path.normalize(path.join(root, rel.replace(/^\//, '')));
	if (!filePath.startsWith(root)) {
		res.writeHead(403);
		res.end('Forbidden');
		return;
	}

	fs.readFile(filePath, (err, data) => {
		if (err) {
			res.writeHead(404);
			res.end('Not found: ' + rel);
			return;
		}
		res.writeHead(200, { 'Content-Type': mime[path.extname(filePath)] || 'text/plain' });
		res.end(data);
	});
}).listen(port, () => {
	console.log('Preview: http://localhost:' + port + '/preview/index.html');
});
