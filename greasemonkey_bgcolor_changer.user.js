// ==UserScript==
// @name           BGColor Changer
// @namespace      http://xxx.com
// @include        http://hostname/*
// @include        http://hostname2/*
// ==/UserScript==

window.addEventListener('load', function(e) {
    if (document.getElementsByTagName("body")[0].style.backgroundColor === "") {
        GM_addStyle("body { background-color: #aaa !important }");
    }
}, true);
