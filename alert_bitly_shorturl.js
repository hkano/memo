javascript: var d = document, t = d.title, w = window, l = location, e = encodeURIComponent;
if (t.length > 20) t = t.substr(0, 20) + "...";
w.callback = function(response) {
    if(response.error_message) { alert("An error occured: " + response.error_message);
} else {
    alert(" | " + t + " " + response.results[location.href]['shortUrl']);
    w.open(f, '_blank');
}};
var s = document.createElement("script");
s.src = "http://api.bit.ly/shorten?version=2.0.1&format=json&callback=callback&login=**login**&apiKey=**apiKey**&longUrl=" + encodeURIComponent(window.location.href);
void(document.body.appendChild(s));
