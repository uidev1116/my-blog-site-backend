import punycode from 'punycode';
import dayjs from 'dayjs';
import lozad from 'lozad';
import PrettyScroll from 'pretty-scroll';
import { parse, serialize } from 'cookie';
import ResizeImage from '../lib/resize-image/resize-image';
import { setDropArea, getParameterByName, PerfectFormData, triggerEvent } from '../utils';
import createAcmsPathHelper from '../lib/acmsPath/createAcmsPathHelper';
import acmsLink from './lib/acmsLink';
import createAcmsContextFromFormData from './lib/createAcmsContextFromFormData';
import validator from './lib/validator';
import deprecated from '../lib/deprecated';
import tab from './lib/tab';

export default () => {
  // Polyfill for jquery.cookie.js using npm's cookie package
  $.cookie = function (name, value, options = {}) {
    // Cookie 設定時
    if (arguments.length > 1 && typeof value !== 'object') {
      let expires;
      // 値が null または undefined の場合、期限を過去に設定して削除
      if (value === null || value === undefined) {
        expires = new Date(0);
      } else if (typeof options.expires === 'number') {
        // オプションの expires を日数で指定している場合
        expires = new Date(Date.now() + options.expires * 864e5); // 864e5 は 1日(ミリ秒)
      }
      // Cookie を設定
      document.cookie = serialize(name, String(value), {
        path: options.path || '/',
        expires,
        secure: options.secure || ACMS.Config.fulltimeSSL === '1',
        sameSite: options.sameSite || 'Lax',
        httpOnly: options.httpOnly || false,
      });
      return;
    }
    // Cookie 取得時
    const cookies = parse(document.cookie);
    return name ? cookies[name] || null : cookies;
  };

  ACMS.Library.PerfectFormData = PerfectFormData;

  ACMS.Library.PrettyScroll = PrettyScroll;

  ACMS.Library.lozad = lozad;

  ACMS.Library.LazyLoad = (selector, config) => {
    const observer = lozad(selector, config);
    observer.observe();
  };

  ACMS.Library.ResizeImage = (elm) => {
    const resizeImage = new ResizeImage(elm);
    resizeImage.resize();
    return resizeImage;
  };

  ACMS.Library.geolocation = (successCallable, errorCallable) => {
    if (!navigator.geolocation) {
      errorCallable(ACMS.i18n('geo_location.not_supported'));
      return;
    }
    window.navigator.geolocation.getCurrentPosition(
      (position) => {
        successCallable(position.coords.latitude, position.coords.longitude);
      },
      (error) => {
        const errorMessage = {
          0: ACMS.i18n('geo_location.unknown_error'),
          1: ACMS.i18n('geo_location.user_denied'),
          2: ACMS.i18n('geo_location.information_error'),
          3: ACMS.i18n('geo_location.timed_out'),
        };
        errorCallable(errorMessage[error.code]);
      },
      {
        enableHighAccuracy: true,
        timeout: 30000,
        maximumAge: 10000,
      }
    );
  };

  ACMS.Library.Dayjs = (input, format) => dayjs(input).format(format);

  ACMS.Library.SmartPhoto = async (elements) => {
    import(/* webpackChunkName: "smartphoto-css" */ 'smartphoto/css/smartphoto.min.css');
    const { default: SmartPhoto } = await import(/* webpackChunkName: "smartphoto" */ 'smartphoto');
    return new SmartPhoto(elements, ACMS.Config.SmartPhotoConfig); // eslint-disable-line no-new
  };

  //-------------
  // modalVideo
  ACMS.Library.modalVideo = async (elements) => {
    import(/* webpackChunkName: "modal-video-css" */ 'modal-video/css/modal-video.min.css');
    const { default: ModalVideo } = await import(/* webpackChunkName: "modal-video" */ 'modal-video');
    return new ModalVideo(elements, ACMS.Config.modalVideoConfig); // eslint-disable-line no-new
  };

  ACMS.Library.decodeEntities = (text) => {
    const entitiesArray = [
      ['amp', '&'],
      ['apos', "'"],
      ['#x27', "'"],
      ['#x2F', '/'],
      ['#39', "'"],
      ['#47', '/'],
      ['lt', '<'],
      ['gt', '>'],
      ['nbsp', ' '],
      ['quot', '"'],
    ];

    for (let i = 0, max = entitiesArray.length; i < max; i += 1) {
      text = text.replace(new RegExp(`&${entitiesArray[i][0]};`, 'g'), entitiesArray[i][1]);
    }

    return text;
  };

  //------------------
  // punycode encode
  ACMS.Library.punycodeEncode = function (domain) {
    if (typeof domain === 'object' && domain.baseVal) {
      domain = $('<a>').attr('href', domain.baseVal).get(0).href;
    }
    let punycodeString = '';
    let tmp = '';
    let isMultiByte = false;
    let matched = false;
    if (typeof domain !== 'string') {
      return punycodeString;
    }
    matched = domain.match(/^[httpsfile]+:\/{2,3}([^\/]+)/i); // eslint-disable-line no-useless-escape
    if (matched) {
      domain = matched[1]; // eslint-disable-line prefer-destructuring
    }

    for (let i = 0; i < domain.length; i++) {
      const code = domain.charCodeAt(i);
      if (code >= 256) {
        isMultiByte = true;
        tmp += String.fromCharCode(code);
      } else {
        if (tmp.length > 0) {
          punycodeString += punycode.encode(tmp);
          tmp = '';
        }
        punycodeString += String.fromCharCode(code);
      }
    }

    if (isMultiByte) {
      punycodeString = `xn--${punycodeString}`;
    }

    return punycodeString;
  };

  //---------
  // locales
  ACMS.Library.lang = function (list, fallback) {
    let lang =
      (window.navigator.languages && window.navigator.languages[0]) ||
      window.navigator.language ||
      window.navigator.userLanguage ||
      window.navigator.browserLanguage;

    lang = lang.replace(/-(.*)$/g, '');

    list = list || ['ja', 'en'];
    fallback = fallback || 'ja';

    if (_.indexOf(list, lang) === -1) {
      lang = fallback;
    }

    return lang;
  };

  //----------
  // scrollTo
  ACMS.Library.scrollTo = function (x, y, m, k, offset, callback) {
    y += offset;
    callback = callback || function () {};

    const left = Math.floor(document.body.scrollLeft || document.documentElement.scrollLeft);
    const top = Math.floor(document.body.scrollTop || document.documentElement.scrollTop);
    let remainX = x - left;
    let remainY = y - top;

    const calc = function () {
      const h = parseInt(x - remainX, 10);
      const v = parseInt(y - remainY, 10);
      remainX *= 1 - k;
      remainY *= 1 - k;
      if (parseInt(remainX, 10) !== 0 || parseInt(remainY, 10) !== 0) {
        window.scrollTo(h, v);
        setTimeout(calc, m);
      } else {
        window.scrollTo(x, y);
        callback();
      }
    };
    setTimeout(calc, m);
  };

  //-------------
  // scrollToElm
  ACMS.Library.scrollToElm = function (elm, setting) {
    let xy;
    if (elm && $(elm).size()) {
      xy = $(elm).offset();
    } else {
      xy = { left: 0, top: 0 };
    }

    setting = $.extend(
      {
        x: xy.left,
        y: xy.top,
        m: ACMS.Config.scrollToI,
        k: ACMS.Config.scrollToV,
        offset: 0,
        callback: null,
      },
      setting
    );

    ACMS.Library.scrollTo(setting.x, setting.y, setting.m, setting.k, setting.offset, setting.callback);
  };

  //-------------
  // switchStyle
  ACMS.Library.switchStyle = function (styleName, $link) {
    ACMS.Library.deprecated(ACMS.i18n('deprecated.feature.switch_style.name'), {
      since: '3.2.0',
    });
    $link.each(function () {
      this.disabled = true;
      if (styleName === this.title) {
        this.disabled = false;
        $.cookie('styleName', styleName, { path: '/' });
      }
    });
  };

  //-------------
  // getPostData
  ACMS.Library.getPostData = function (context) {
    const data = {};
    const cnt = {};

    $(':input:not(disabled):not(:radio:not(:checked)):not(:checkbox:not(:checked))', context).each(function () {
      const name = this.name.replace(/\[\]$/, '');
      const isAry = name !== this.name;
      const val = $(this).val();

      if (isAry && typeof cnt[name] === 'undefined') {
        cnt[name] = 0;
      }
      if (typeof val === 'string') {
        if (isAry) {
          data[`${name}[${cnt[name]++}]`] = val;
        } else {
          data[name] = val;
        }
      } else {
        for (const i in val) {
          data[`${name}[${cnt[name]++}]`] = val[i];
        }
      }
    });

    return data;
  };

  //--------------------
  // getParameterByName
  ACMS.Library.getParameterByName = getParameterByName;

  //----------------------
  // Syntax Highlighter
  ACMS.Library.highlight = async (context) => {
    const preElements = context.querySelectorAll(ACMS.Config.highlightMark);
    if (preElements.length === 0) {
      return;
    }
    const highlight = (await import(/* webpackChunkName: "lib-highlightjs" */ './lib/highlight')).default;
    highlight(
      context,
      ACMS.Config.highlightMark,
      ACMS.Config.highlightConfig.languages,
      ACMS.Config.highlightConfig.theme,
      ACMS.Config.root
    );
  };

  const { acmsPath, parseAcmsPath } = createAcmsPathHelper(ACMS.Config.segments);
  /**
   * parseAcmsPath
   */
  ACMS.Library.parseAcmsPath = parseAcmsPath;

  /**
   * acmsPath
   */
  ACMS.Library.acmsPath = acmsPath;

  /**
   * acmsLink
   */
  ACMS.Library.acmsLink = acmsLink;

  /**
   * createAcmsContextFromFormData
   */
  ACMS.Library.createAcmsContextFromFormData = createAcmsContextFromFormData;

  ACMS.Library.queryToObj = function (str) {
    str = str || location.search;
    const result = {};
    const param = str.substring(str.indexOf('?') + 1).split('&');
    let hash;

    for (let i = 0; i < param.length; i++) {
      hash = param[i].split('=');
      result[hash[0]] = hash[1]; // eslint-disable-line prefer-destructuring
    }
    return result;
  };

  ACMS.Library.triggerEvent = triggerEvent;

  ACMS.Library.setDropArea = setDropArea;

  ACMS.Library.fileiconPath = function (extension) {
    return `${ACMS.Config.fileiconDir}${extension}.svg`;
  };

  ACMS.Library.validator = validator;

  ACMS.Library.isDebugMode = function () {
    return ACMS.Config.isDebugMode === '1';
  };

  ACMS.Library.deprecated = deprecated;

  ACMS.Library.tab = tab;
};
