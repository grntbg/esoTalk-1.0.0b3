Ajax.debugUpdateBackground = true;

// Override all ajax request success methods to parse debug information from the json result.
Ajax.request = function(request) {
	if (!request || this.beenLoggedOut) return false;
	if (!request.success) request.success = function() {};
	if (!request._success) request._success = request.success;
	request.success = function() {
		this._success();
		if (!Ajax.debugUpdateBackground && this.background) return;
		if (typeof this.json.queries == "undefined") return;
		getById("debugQueries").innerHTML = this.json.queries;
		getById("debugQueriesCount").innerHTML = this.json.queriesCount;
		getById("debugLoadTime").innerHTML = this.json.loadTime;
		getById("debugPost").innerHTML = this.json.debugPost;
		getById("debugGet").innerHTML = this.json.debugGet;
		getById("debugFiles").innerHTML = this.json.debugFiles;
		getById("debugSession").innerHTML = this.json.debugSession;
		getById("debugCookie").innerHTML = this.json.debugCookie;
		getById("debugHooks").innerHTML = this.json.hookedFunctions;
		if (this.json.log) Messages.showMessage("debugLog", "info", "<div style='overflow:auto;max-height:400px'>" + this.json.log + "</div>", false);
	};
	this.queue.push(request);
	this.doNextRequest();
};

// Override the disconnect function to show debug information when there's a fatal error.
Ajax.disconnect = function(request) {
	this.disconnected = true;
	request.repeat = true;
	this.disconnectedRequest = request;
	this.queue = [];
	Messages.showMessage("ajaxDisconnected", "warning", esoTalk.language["ajaxDisconnected"], false);
	Messages.showMessage("disconnectedInfo", "info", "<a href='#' onclick='Ajax.toggleDebugInfo(this);return false'>show debug info</a><div id='debugInfo' style='display:none;overflow:auto;max-height:400px'><ul class='form'>" +
		"<li><label>HTTP status code</label><div>" + Ajax.disconnectedRequest.http.status + "</div>" +
		"<li><label>Request URL</label><div>" + Ajax.disconnectedRequest.url + "</div>" +
		"<li><label>POST data</label><div>" + Ajax.disconnectedRequest.post + "</div>" +
		"<li><label>Response text</label><div>" + Ajax.disconnectedRequest.http.responseText.replace(/</g, "&lt;").replace(/>/g, "&gt;") + "</div>" +
		"</ul></div>", false);
};

// Toggle the debug information in the ajax disconnected message.
Ajax.toggleDebugInfo = function(link) {
	toggle(getById("debugInfo"), {animation: "verticalSlide"});
	link.innerHTML = !getById("debugInfo").showing ? "show debug info" : "hide debug info";
};