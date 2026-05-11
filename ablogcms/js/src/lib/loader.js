import AcmsSyncLoader from './sync-loader';
import { cacheBusting } from '../config';

/**
 * 名前からオブジェクトへの参照を取得する
 *
 * @param {String} name
 */
const getObjectPointerFromName = (name) => {
  const nameTokens = name.split('.');
  const iz = nameTokens.length;
  let objPointer = global;
  let i = 0;
  let token;
  let parentPointer;

  for (; i < iz; i++) {
    token = nameTokens[i];
    if (objPointer[token] === undefined) {
      objPointer[token] = {};
    }

    // 直前のポインタ（親オブジェクト）を残す
    parentPointer = objPointer;

    // ポインタを更新する
    objPointer = objPointer[token];
  }

  return {
    current: objPointer,
    token,
    parent: parentPointer,
  };
};

/**
 * loadClosureFactory
 *
 * @param {String}   url        ロードするリソースのURL
 * @param {String}   [charset]  文字コード指定
 * @param {Function} [pre]      ロード前実行
 * @param {Function} [post]     ロード後実行
 * @param {Function} [loaded]
 */
const loadClosureFactory = (url, charset, pre, post, loaded) => {
  url = typeof url === 'string' ? url : '';
  url += cacheBusting;
  charset = typeof charset === 'string' ? charset : '';
  pre = $.isFunction(pre) ? pre : function () {};
  post = $.isFunction(post) ? post : function () {};
  loaded = $.isFunction(loaded) ? loaded : function () {};

  /**
   * loadClosure
   */
  const loadClosureFunction = function (callSpecifiedFunction) {
    if (!$.isFunction(callSpecifiedFunction)) {
      callSpecifiedFunction = function () {};
    }

    if (!loadClosureFunction.executed) {
      loadClosureFunction.executed = true;
      loadClosureFunction.stack = [callSpecifiedFunction];

      new AcmsSyncLoader()
        .next(pre)
        .next(url)
        .next(loaded)
        .next(() => {
          while (loadClosureFunction.stack.length) {
            loadClosureFunction.stack.shift()();
          }
        })
        .load(() => {
          post();
          ACMS.dispatchEvent(`${url.match('.+/(.+?).[a-z]+([?#;].*)?$')[1]}Ready`);
        });
    } else {
      loadClosureFunction.stack.push(callSpecifiedFunction);
      return true;
    }
  };
  return loadClosureFunction;
};

/**
 * assignLoadClosure
 *
 * @param {String}   name        オブジェクト名
 * @param {Function} loadClosure スクリプトロードのクロージャ
 * @param {Boolean}  [del]       削除フラグ
 */
const assignLoadClosure = (name, loadClosure, del) => {
  const pointerInfo = getObjectPointerFromName(name);
  const parentPointer = pointerInfo.parent;
  const { token } = pointerInfo;

  //-------
  // proxy
  //
  // 変数にするとただの参照代入になるのでプロパティとしてアクセス
  // @example
  // ACMS.Dispatch['Edit'] = function() {...};
  const placeholderFunction = function (...args) {
    const scope = this;

    if (del) {
      global[name.replace(/\..*$/, '')] = undefined;
    }

    /**
     * loadClosure <- callSpecifiedFunction
     */
    return loadClosure(() => {
      let func;

      try {
        func = getObjectPointerFromName(name).current;
      } catch (e) {
        return false;
      }

      if (typeof func !== 'function') {
        return false;
      }
      if (func === placeholderFunction) {
        return false;
      }

      //--------------------
      // take over property
      let key;
      let _key;

      for (key in placeholderFunction) {
        if (func[key]) {
          for (_key in placeholderFunction[key]) {
            func[key][_key] = placeholderFunction[key][_key];
          }
        } else {
          func[key] = placeholderFunction[key];
        }
      }
      return func.apply(scope, args);
    });
  };
  parentPointer[token] = placeholderFunction;
};

/**
 * cssのロード
 *
 * @param {string} url
 * @param {?string} charset
 * @param {?boolean} prepend
 * @return {boolean}
 */
const loadClosureFactoryCss = (url, charset = '', prepend = false) => {
  if (!url) {
    return false;
  }
  url += cacheBusting;

  const link = window.document.createElement('link');
  link.type = 'text/css';
  link.rel = 'stylesheet';
  if (charset) {
    link.charset = charset;
  }
  link.href = url;

  const head = window.document.getElementsByTagName('head')[0];

  if (prepend && head.firstChild) {
    head.insertBefore(link, head.firstChild);
  } else {
    head.appendChild(link);
  }
  return true;
};

export { loadClosureFactory, loadClosureFactoryCss, assignLoadClosure };
