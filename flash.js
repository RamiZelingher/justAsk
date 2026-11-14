/* Flash animations from the old site, played by Ruffle (https://ruffle.rs).
   Content marks each movie as <div class="swf" data-swf data-w data-h data-xml>.
   A movie is started only when it is visible (and its <details> is open). */
(function () {
  var boxes = document.querySelectorAll('.swf[data-swf]');
  if (!boxes.length) return;
  var src = document.currentScript && document.currentScript.dataset.ruffle;
  window.RufflePlayer = window.RufflePlayer || {};
  window.RufflePlayer.config = {
    autoplay: 'on', unmuteOverlay: 'hidden', splashScreen: false,
    letterbox: 'on', contextMenu: 'off', logLevel: 'error', publicPath: src ? src.replace(/[^\/]*$/, '') : undefined
  };
  var ready = null;
  function loadRuffle() {
    if (!ready) ready = new Promise(function (ok, fail) {
      var s = document.createElement('script');
      s.src = src || 'https://unpkg.com/@ruffle-rs/ruffle';
      s.onload = ok; s.onerror = fail;
      document.head.appendChild(s);
    });
    return ready;
  }
  function start(box) {
    if (box.dataset.started) return;
    box.dataset.started = '1';
    var w = +box.dataset.w || 400, h = +box.dataset.h || 300;
    box.style.maxWidth = w + 'px';
    box.style.aspectRatio = w + ' / ' + h;
    loadRuffle().then(function () {
      var player = window.RufflePlayer.newest().createPlayer();
      player.style.width = '100%'; player.style.height = '100%';
      box.appendChild(player);
      var params = {};
      if (box.dataset.xml) params.inputXML = box.dataset.xml;
      return player.ruffle().load({ url: 'flash/' + box.dataset.swf, parameters: params });
    }).catch(function () { box.classList.add('swf-failed'); });
  }
  function visible(box) {
    for (var d = box.closest('details'); d; d = d.parentElement && d.parentElement.closest('details'))
      if (!d.open) return false;
    return box.offsetParent !== null;
  }
  var io = 'IntersectionObserver' in window ? new IntersectionObserver(function (es) {
    es.forEach(function (e) { if (e.isIntersecting && visible(e.target)) { io.unobserve(e.target); start(e.target); } });
  }, { rootMargin: '200px' }) : null;
  boxes.forEach(function (b) {
    var w = +b.dataset.w || 400, h = +b.dataset.h || 300;
    b.style.maxWidth = w + 'px'; b.style.aspectRatio = w + ' / ' + h;
    if (io) io.observe(b); else start(b);
  });
  // a movie inside a folded <details> (or a hidden tab) starts when it is shown
  document.addEventListener('toggle', function (e) {
    if (e.target.open) e.target.querySelectorAll('.swf[data-swf]').forEach(function (b) {
      if (visible(b)) start(b);
    });
  }, true);
  document.addEventListener('click', function () {
    setTimeout(function () {
      boxes.forEach(function (b) { if (!b.dataset.started && visible(b) && b.getBoundingClientRect().top < innerHeight + 200) start(b); });
    }, 50);
  });
})();
