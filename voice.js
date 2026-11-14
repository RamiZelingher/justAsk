// Voice search: fills the topic search box with what was said and sends it (Web Speech API).
// Chrome, Edge and Safari support it; elsewhere the microphone button stays hidden.
(function () {
  var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (!SR) return;
  document.querySelectorAll('.tsearch .mic').forEach(function (btn) {
    var form = btn.closest('form'), input = form.querySelector('input[name=q]'), status = form.querySelector('.ts-status');
    var rec = null, heard = false;
    btn.hidden = false;
    function stop() { if (rec) { try { rec.stop(); } catch (e) {} } }
    btn.addEventListener('click', function () {
      if (rec) { stop(); return; }
      rec = new SR();
      rec.lang = btn.dataset.lang;
      rec.interimResults = true;
      rec.maxAlternatives = 1;
      heard = false;
      rec.onstart = function () { btn.classList.add('on'); status.textContent = btn.dataset.listening; };
      rec.onresult = function (ev) {
        var text = '', final = false;
        for (var i = 0; i < ev.results.length; i++) { text += ev.results[i][0].transcript; if (ev.results[i].isFinal) final = true; }
        input.value = text.trim();
        heard = input.value !== '';
        if (final && heard) { status.textContent = ''; stop(); form.submit(); }
      };
      rec.onerror = function (ev) {
        status.textContent = (ev.error === 'not-allowed' || ev.error === 'service-not-allowed') ? btn.dataset.denied : btn.dataset.nothing;
      };
      rec.onend = function () {
        btn.classList.remove('on');
        if (!heard && status.textContent === btn.dataset.listening) status.textContent = btn.dataset.nothing;
        rec = null;
      };
      try { rec.start(); } catch (e) { rec = null; }
    });
  });
})();
