var isNode = typeof module !== 'undefined' && module.exports;

if (isNode) {
	process.once('message', function (code) {
		// Use Function constructor instead of eval for slightly better isolation
		var fn = new Function(JSON.parse(code).data);
		fn();
	});
} else {
	self.onmessage = function (code) {
		// Use Function constructor instead of eval for slightly better isolation
		// Note: This is still a security risk if code.data comes from untrusted sources
		var fn = new Function(code.data);
		fn();
	};
}