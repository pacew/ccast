try {
    var sock = new WebSocket (wss_url, "ccast");
} catch (e) {
    console.log (["websocket failed", e]);
}

sock.onerror = function (ev) {
    console.log (["sock onerror", ev]);
    foo = ev;
}
sock.onopen = function (ev) {
    console.log (["sock onopen", ev]);
}




