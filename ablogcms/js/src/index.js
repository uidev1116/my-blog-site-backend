import './lib/polyfill';

import i18next from 'i18next';
import HttpApi from 'i18next-http-backend';

import qs from 'qs';

import AcmsSyncLoader from './lib/sync-loader';
import loadList from './built-in/load-list';
import library from './built-in/library';
import dispatch from './built-in/dispatch';
import utility from './built-in/dispatch-utility';
import entryAdd from './built-in/dispatch-entry-add';
import { parseQuery } from './utils';
import { cacheBusting } from './config';

const underscore = require('underscore');

const doc = window.document;
const nav = window.navigator;
const loc = window.location;

// parse query
const elm = doc.getElementById('acms-js');
let query = {};
if (elm) {
  const s = elm.src.split('?');
  if (s.length > 1) {
    query = qs.parse(s[1]);
  }
}
query.searchEngineKeyword = '';
if (
  doc.referrer.match(
    /^http:\/\/www\.google\..*(?:\?|&)q=([^&]+).*$|^http:\/\/search\.yahoo\.co\.jp.*(?:\?|&)p=([^&]+).*$|^http:\/\/www\.bing\.com.*(?:\?|&)q=([^&]+).*$/
  )
) {
  query.searchEngineKeyword = decodeURIComponent(RegExp.$1 || RegExp.$2 || RegExp.$3).replace(/\+/g, ' ');
}
query.root = '/';
if (query.offset) {
  query.root += query.offset;
}
query.jsRoot = query.root + query.jsDir;
query.hash = loc.hash;
query.Gmap = { sensor: nav.userAgent.match(/iPhone|Android/) ? 'true' : 'false' };

// Set CSRF Token
window.csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

const path = query.jsRoot; // include path
const Dispatch = () => {};
const Library = () => {};
const { ACMS } = window;
const Config = function (key, value) {
  if (!key) {
    return '';
  }
  if (typeof key === 'string') {
    if (!value) {
      return typeof this.Config[key] !== 'undefined' ? this.Config[key] : '';
    }
    this.Config[key] = value;
    return true;
  }
  for (const prop in key) {
    // eslint-disable-next-line no-restricted-properties
    arguments.callee[prop] = key[prop];
  }
};

//----------
// register
ACMS.Config = Config;
ACMS.Dispatch = Dispatch;
ACMS.Library = Library;
ACMS.SyncLoader = AcmsSyncLoader;
window.ACMS = ACMS;
window._ = underscore;

for (const prop in query) {
  ACMS.Config[prop] = query[prop];
}

__webpack_public_path__ = ACMS.Config.root; // eslint-disable-line

const offset = ACMS.Config.offset || '';
const loader = new AcmsSyncLoader()
  .next(() => {
    const lng = navigator.languages ? navigator.languages[0] : navigator.language || navigator.userLanguage;
    const res = new Promise((resolve) => {
      i18next.use(HttpApi).init(
        {
          lng,
          debug: false,
          load: 'languageOnly',
          fallbackLng: 'ja',
          backend: {
            loadPath: `/${offset}js/locales/{{lng}}/{{ns}}.json${cacheBusting}`,
          },
        },
        (err, t) => {
          ACMS.i18n = t;
          ACMS.i18n.lng = lng;
          resolve(t);
        }
      );
    });
    return res;
  })
  .next(`${path}config.js${cacheBusting}`)
  .next(() => {
    ACMS.dispatchEvent('configLoad');
  });

if (typeof jQuery === 'undefined') {
  loader.next(`${path}library/jquery/jquery-${query.jQuery}.min.js${cacheBusting}`);
}
if (query.jQueryMigrate !== 'off') {
  loader.next(`${path}library/jquery/jquery-${query.jQueryMigrate}.min.js${cacheBusting}`);
}
loader
  .next(`${path}library/jquery/ui_1.13.2/jquery-ui.min.js${cacheBusting}`)
  .next(`${path}library/jquery/jquery-ui-touch-punch/jquery.ui.touch-punch.min.js${cacheBusting}`)
  .next(() => {
    library();
  })
  .next(`${path}dispatch.js${cacheBusting}`)
  .next(() => {
    utility();
  })
  .next(() => {
    Library.parseQuery = parseQuery;
    loadList(path);
    ACMS.Dispatch.Edit._add = entryAdd;
    ACMS.Dispatch2 = dispatch;
  })
  .load(() => {
    $(() => {
      $.ajaxSetup({
        beforeSend(xhr, settings) {
          try {
            // リクエスト先のURLを解析
            const requestUrl = new URL(settings.url, window.location.href);

            // 現在のページのドメインと同じ場合だけヘッダーを追加
            if (requestUrl.origin === window.location.origin) {
              if (window.csrfToken) {
                // デフォルトで、jQueryのAjaxリクエストにCSRFトークンを付与
                xhr.setRequestHeader('X-Csrf-Token', window.csrfToken);
              }
            }
          } catch (e) {
            // URLの解析に失敗した場合は何もしない
            console.warn('Invalid URL in ajaxSetup:', settings.url); // eslint-disable-line no-console
          }
        },
      });

      jQuery.expr.filters.text = function (elem) {
        let attr;
        let type;
        // @see https://github.com/jquery/sizzle/issues/66
        // IE6 and 7 will map elem.type to 'text' for new HTML5 types (search, etc)
        // use getAttribute instead to test this case
        const res =
          elem.nodeName.toLowerCase() === 'input' &&
          ((type = elem.type), (attr = elem.getAttribute('type')), type === 'text') &&
          (attr === type || attr === null);
        return res;
      };

      if (!$.isFunction($.parseHTML)) {
        jQuery.parseHTML = function (data) {
          return data;
        };
      }

      ACMS.dispatchEvent('acmsReady');
      ACMS.Dispatch(document);
    });
  });
