(function () {
  var ACMS = function () {};
  ACMS.Admin = {};
  ACMS.eventPool = [];

  ACMS.addListener = function (name, listener, options = false) {
    document.addEventListener(name, listener, options);
    // dispatch from pool
    const events = ACMS.eventPool[name];
    if (events && events.length > 0) {
      events.forEach(function (item) {
        listener(item.event);
      });
    }
  };

  ACMS.dispatchEvent = function (name, dom = document, detail = {}, options = {}) {
    const event = new CustomEvent(name, {
      detail: detail,
      ...options,
      bubbles: true,
      cancelable: false,
    });
    event.obj = detail; // Ver.3.2.0以前との互換性を保つために追加
    dom.dispatchEvent(event);
    if (!ACMS.eventPool[name]) {
      ACMS.eventPool[name] = [];
    }
    ACMS.eventPool[name].push({
      event: event,
    });
  };

  ACMS.Ready = function (listener) {
    ACMS.addListener('acmsReady', listener);
  };
  ACMS.Loaded = function (listener) {
    if (document.readyState === 'complete') {
      listener();
    } else {
      window.addEventListener('load', listener);
    }
  };
  window.ACMS = ACMS;
})();
