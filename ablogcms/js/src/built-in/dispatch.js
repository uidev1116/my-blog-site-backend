import DocumentOutliner from 'document-outliner';
import { FocusedImage } from 'image-focus';
import ScrollHint from 'scroll-hint';
import axiosLib from '../lib/axios';
import lazyLoad from '../lib/lazy-load';
import dispatchScrollAnimation from '../dispatch/dispatch-scroll-animation';
import { addClass, removeClass } from '../lib/dom';
import { contrastColor, rgb2hex } from '../utils';
import dispatchFlatpicker from '../dispatch/dispatch-flatpicker';

/**
 * 組み込みJS の dispatch 関数
 * @param {Element | Document} context コンテキスト
 * @returns {void}
 */
export default (context) => {
  //----------
  // htmx
  (async () => {
    const existsHtmx = context.querySelector(ACMS.Config.htmxMark);
    if (existsHtmx) {
      const { default: dispatchHtmx } = await import(/* webpackChunkName: "htmx" */ '../dispatch/dispatch-htmx');
      dispatchHtmx(context instanceof Document ? context.body : context, ACMS.Config.htmxConfig);
    }
  })();

  //------------------------
  // 会員限定記事の表示・非表示
  (async (context) => {
    const membersOnlyEntryDom = context.querySelector(ACMS.Config.membersOnlyEntryMark);

    if (!membersOnlyEntryDom) {
      return;
    }
    if (membersOnlyEntryDom.classList.contains('loaded')) {
      return;
    }
    const display = membersOnlyEntryDom.getAttribute('data-display') || 'block';
    const data = new FormData();
    data.append('ACMS_POST_Login_Check', 'exec');
    data.append('formToken', window.csrfToken);
    const response = await axiosLib.post(location.href, data);
    const isLogin = response.data && response.data.isLogin;

    if (isLogin) {
      // ログイン中
      const eid = membersOnlyEntryDom.getAttribute('data-eid');
      const page = parseInt(membersOnlyEntryDom.getAttribute('data-page'), 10) || 1;

      let query = [];
      const s = location.href.split('?');
      if (s.length > 1) {
        query = ACMS.Library.parseQuery(s[1]);
      }
      query.eid = eid;

      const data = new FormData();
      data.append('ACMS_POST_NotProcess', 'exec');
      data.append('formToken', window.csrfToken);
      const url = ACMS.Library.acmsLink({
        eid,
        tpl: 'ajax/members-only-content.html',
        page,
        Query: query,
      });
      const response = await axiosLib.post(url, data);
      if (response && response.status === 200) {
        // ログインしていて、会員限定記事が閲覧できる
        membersOnlyEntryDom.innerHTML = response.data;
        membersOnlyEntryDom.style.display = display;
        membersOnlyEntryDom.classList.add('loaded');
        ACMS.Dispatch(membersOnlyEntryDom);
      }
    } else {
      // ログアウト中
      membersOnlyEntryDom.style.display = display;
    }
  })(context);

  //----------------------------
  // ログイン状態による表示・非表示
  (async (context) => {
    const loginHiddenDoms = context.querySelectorAll(ACMS.Config.loginHiddenMark); // ログイン状態の時、非表示にする
    const loginShowDoms = context.querySelectorAll(ACMS.Config.loginShowMark); // ログイン状態の時、表示する
    const logoutHiddenDoms = context.querySelectorAll(ACMS.Config.logoutHiddenMark); // ログアウト状態の時、非表示にする
    const logoutShowDoms = context.querySelectorAll(ACMS.Config.logoutShowMark); // ログアウト状態の時、表示する

    if (
      (!loginHiddenDoms || loginHiddenDoms.length === 0) &&
      (!loginShowDoms || loginShowDoms.length === 0) &&
      (!logoutHiddenDoms || logoutHiddenDoms.length === 0) &&
      (!logoutShowDoms || logoutShowDoms.length === 0)
    ) {
      return;
    }

    const data = new FormData();
    data.append('ACMS_POST_Login_Check', 'exec');
    data.append('formToken', window.csrfToken);
    const response = await axiosLib.post(location.href, data);
    const isLogin = response.data && response.data.isLogin;

    // ログイン状態の時、非表示にする
    [].forEach.call(loginHiddenDoms, (elm) => {
      if (isLogin) {
        elm.style.display = 'none';
      }
    });
    // ログイン状態の時、表示する
    [].forEach.call(loginShowDoms, (elm) => {
      if (isLogin) {
        const display = elm.getAttribute('data-display') || 'block';
        elm.style.display = display;
      }
    });
    // ログアウト状態の時、非表示にする
    [].forEach.call(logoutHiddenDoms, (elm) => {
      if (!isLogin) {
        elm.style.display = 'none';
      }
    });
    // ログアウト状態の時、表示する
    [].forEach.call(logoutShowDoms, (elm) => {
      if (!isLogin) {
        const display = elm.getAttribute('data-display') || 'block';
        elm.style.display = display;
      }
    });
  })(context);

  //-------------
  // scroll hint
  (async (context) => {
    const tableUnitScrollHintMark = '.js-table-unit-scroll-hint';
    const tables = context.querySelectorAll(ACMS.Config.scrollHintMark);
    const tableUnits = context.querySelectorAll(tableUnitScrollHintMark);
    if (tableUnits.length > 0 || tables.length > 0) {
      import(/* webpackChunkName: "scroll-hint-css" */ 'scroll-hint/css/scroll-hint.css').then(() => {
        // build in js
        new ScrollHint(tables, ACMS.Config.scrollHintConfig); // eslint-disable-line no-new

        // table unit
        new ScrollHint(tableUnits, { ...ACMS.Config.scrollHintConfig, applyToParents: true }); // eslint-disable-line no-new
      });
    }
  })(context);

  //--------------------
  // scroll animation
  dispatchScrollAnimation(context, ACMS.Config.scrollAnimationMark, ACMS.Config.scrollAnimationConfig);

  //------------
  // lazy load
  ACMS.Library.LazyLoad(ACMS.Config.lazyLoadMark, ACMS.Config.lazyLoadConfig);

  //---------
  // in-view
  lazyLoad(
    ACMS.Config.lazyContentsMark,
    () => true,
    (item) => {
      const type = item.getAttribute('data-type');
      if (!type) {
        return;
      }
      const script = document.createElement(type);
      [...item.attributes].forEach((data) => {
        const matches = data.name.match(/^data-(.*)/);
        if (matches && matches[1] !== 'type') {
          script[matches[1]] = data.value;
        }
      });
      item.appendChild(script);
    }
  );

  //--------------
  // focus image
  [].forEach.call(context.querySelectorAll('.js-focused-image'), (image) => {
    image.style.visibility = 'visible';
    new FocusedImage(image); // eslint-disable-line no-new
  });

  //-------------
  // pdf viewer
  const { pdfPreviewConfig } = ACMS.Config;
  lazyLoad(
    pdfPreviewConfig.mark,
    (wrapper) => {
      const elm = wrapper.querySelector(pdfPreviewConfig.previewMark);
      if (elm) {
        return elm.getAttribute(pdfPreviewConfig.lazyAttr) === '1'; // lazy-load 判定
      }
      return false;
    },
    (wrapper) => {
      const elm = wrapper.querySelector(pdfPreviewConfig.previewMark);
      if (!elm) {
        return;
      }
      const url = elm.getAttribute(pdfPreviewConfig.pdfAttr);
      if (!url) {
        return;
      }
      const page = parseInt(elm.getAttribute(pdfPreviewConfig.pageAttr), 10) || 1;
      const imageWidth = parseInt(elm.getAttribute(pdfPreviewConfig.widthAttr), 10) || elm.clientWidth;

      import(/* webpackChunkName: "pdf2image" */ '../lib/pdf2image').then(async ({ default: Pdf2Image }) => {
        const pdf2Image = new Pdf2Image(url);
        const prevButton = wrapper.querySelector(pdfPreviewConfig.prevBtnMark);
        const nextButton = wrapper.querySelector(pdfPreviewConfig.nextBtnMark);
        const showClass = pdfPreviewConfig.showBtnClass;
        const image = await pdf2Image.getPageImage(page, imageWidth);
        if (image) {
          elm.src = image;
        }
        const checkButton = async () => {
          if (prevButton) {
            if (await pdf2Image.hasPrevPage()) {
              addClass(prevButton, showClass);
            } else {
              removeClass(prevButton, showClass);
            }
          }
          if (nextButton) {
            if (await pdf2Image.hasNextPage()) {
              addClass(nextButton, showClass);
            } else {
              removeClass(nextButton, showClass);
            }
          }
        };
        checkButton();
        if (prevButton) {
          prevButton.addEventListener('click', async (e) => {
            e.preventDefault();
            const prevImage = await pdf2Image.getPrevImage(imageWidth);
            if (prevImage) {
              elm.src = prevImage;
            }
            checkButton();
          });
        }
        if (nextButton) {
          nextButton.addEventListener('click', async (e) => {
            e.preventDefault();
            const nextImage = await pdf2Image.getNextImage(imageWidth);
            if (nextImage) {
              elm.src = nextImage;
            }
            checkButton();
          });
        }
      });
    }
  );

  //---------------
  // OpenStreetMap
  lazyLoad(
    ACMS.Config.openStreetMapMark,
    (elm) => elm.getAttribute('data-lazy') === 'true',
    (item) => {
      import(/* webpackChunkName: "open-street-map" */ '../lib/open-street-map/open-street-map').then(
        ({ default: openStreetMap }) => {
          openStreetMap(item);
        }
      );
    }
  );

  //-------------
  // Google Maps
  lazyLoad(
    ACMS.Config.s2dReadyMark,
    (element) => element.getAttribute('data-lazy') === 'true',
    (element) => {
      import(/* webpackChunkName: "google-maps" */ '../lib/google-maps/google-maps').then(
        ({ default: setupGoogleMaps }) => {
          setupGoogleMaps(element);
        }
      );
    }
  );
  lazyLoad(
    ACMS.Config.s2dMark,
    (element) => element.getAttribute('data-lazy') === 'true',
    (element) => {
      const handleClick = () => {
        import(/* webpackChunkName: "google-maps" */ '../lib/google-maps/google-maps').then(
          ({ default: setupGoogleMaps }) => {
            setupGoogleMaps(element);
          }
        );
      };
      element.addEventListener('click', handleClick);
    }
  );

  //---------------
  // StreetView
  lazyLoad(
    ACMS.Config.streetViewMark,
    (element) => element.getAttribute('data-lazy') === 'true',
    (element) => {
      import(/* webpackChunkName: "open-street-map" */ '../lib/google-maps/street-view').then(
        ({ default: streetView }) => {
          streetView(element);
        }
      );
    }
  );

  //---------
  // preview
  ACMS.Dispatch.Preview = async () => {
    // React をバンドルに含めないため、動的インポート
    const { default: dispatchInlinePreview } = await import(
      /* webpackChunkName: "preview-share" */ '../dispatch/dispatch-inline-preview'
    );
    dispatchInlinePreview(document);
  };
  if (window.parent !== window && location.href) {
    window.parent.postMessage({ task: 'preview', url: location.href }, '*');
  }

  $(ACMS.Config.externalFormSubmitButton, context).each((index, button) => {
    ACMS.Library.deprecated(ACMS.i18n('deprecated.feature.external_form_submit_button.name'), {
      since: '3.2.0',
      alternative: ACMS.i18n('deprecated.feature.external_form_submit_button.alternative'),
    });
    $(button).click((e) => {
      e.preventDefault();
      const target = $(button).data('target');
      if (!target) {
        return;
      }
      const name = $(button).attr('name');
      if (name && name.match(/^ACMS_POST/)) {
        $(target).append(`<input type="hidden" name="${name}" value="true" />`);
      }
      $(target).submit();
    });
  });

  //-----------------------------
  //  WYSIWYG Editor (trumbowyg)
  ACMS.Dispatch.wysiwyg = {
    async init(elm) {
      const { default: wysiwyg } = await import(/* webpackChunkName: "wysiwyg" */ '../lib/wysiwyg');
      wysiwyg(elm, ACMS.Config.wysiwygConfig);
    },
    dispatch(ctx) {
      const editors = ctx.querySelectorAll(ACMS.Config.wysiwygMark);
      if (editors && editors.length > 0) {
        [].forEach.call(editors, (elm) => {
          this.init(elm);
        });
      }
    },
    isAdapted(elm) {
      return elm.classList.contains('trumbowyg-textarea');
    },
    getHtml(elm) {
      return $(elm).trumbowyg('html');
    },
    setHtml(elm, html) {
      $(elm).trumbowyg('html', html);
    },
    empty(elm) {
      $(elm).trumbowyg('empty');
    },
    destroy(elm) {
      $(elm).trumbowyg('destroy');
    },
    disable(elm, disable) {
      if (disable) {
        $(elm).trumbowyg('disable');
      } else {
        $(elm).trumbowyg('enable');
      }
    },
  };
  ACMS.Dispatch.wysiwyg.dispatch(context);
  ACMS.addListener('acmsAddCustomFieldGroup', (event) => {
    ACMS.Dispatch.wysiwyg.dispatch(event.target);
  });

  /**
   * Flatpicker
   */
  dispatchFlatpicker(context);
  ACMS.addListener('acmsAddCustomFieldGroup', (event) => {
    dispatchFlatpicker(event.target);
  });

  //-------------------
  // contrast color
  ((context) => {
    const contrastColorTarget = context.querySelectorAll(ACMS.Config.contrastColorTarget);
    if (contrastColorTarget && contrastColorTarget.length) {
      [].forEach.call(contrastColorTarget, (item) => {
        const black = item.getAttribute('data-black-color') || '#000000';
        const white = item.getAttribute('data-white-color') || '#ffffff';
        let bgColor = item.getAttribute('data-bg-color');
        if (!bgColor) {
          const style = window.getComputedStyle(item);
          if (style) {
            bgColor = rgb2hex(style.backgroundColor);
          }
        }
        if (bgColor) {
          item.style.color = contrastColor(bgColor, black, white);
        }
      });
    }
  })(context);

  /**
   * Password strength checker
   */
  ((context) => {
    const passwordStrength = context.querySelectorAll(ACMS.Config.passwordStrengthMark);
    if (passwordStrength.length > 0) {
      import(/* webpackChunkName: "zxcvbn" */ '../lib/zxcvbn').then(({ default: zxcvbn }) => {
        [].forEach.call(passwordStrength, (item) => {
          zxcvbn(item);
        });
      });
    }
  })(context);
  //-------------------
  // document-outliner
  ((context) => {
    const outlineTarget = context.querySelectorAll(ACMS.Config.documentOutlinerMark);
    if (outlineTarget && outlineTarget.length) {
      [].forEach.call(outlineTarget, (item) => {
        requestAnimationFrame(() => {
          const target = item.getAttribute('data-target');
          if (!target || !document.querySelector(target)) {
            return;
          }
          const outline = new DocumentOutliner(item);
          const overrideConfig = {};
          Object.keys(ACMS.Config.documentOutlinerConfig).forEach((key) => {
            let value = item.getAttribute(`data-${key}`);
            if (value) {
              if (isNaN(value) === false) {
                value = parseInt(value, 10);
              }
              if (value === 'true' || value === 'false') {
                value = value === 'true';
              }
              overrideConfig[key] = value;
            }
          });
          const config = { ...ACMS.Config.documentOutlinerConfig, ...overrideConfig };

          outline.makeList(target, config);
          [].forEach.call(context.querySelectorAll(ACMS.Config.scrollToMark), (anchor) => {
            ACMS.Dispatch.scrollto(anchor);
          });
        });
      });
    }
  })(context);

  //-----------
  // validator
  ACMS.Dispatch.validator = async function (form, options = {}) {
    const validator = await ACMS.Library.validator(form, {
      ...ACMS.Config.validatorOptions,
      // 互換性のために追加
      resultClassName: ACMS.Config.validatorResultClass || ACMS.Config.validatorOptions.resultClassName,
      okClassName: ACMS.Config.validatorOkClass || ACMS.Config.validatorOptions.okClassName,
      ngClassName: ACMS.Config.validatorNgClass || ACMS.Config.validatorOptions.ngClassName,
      // サーバーサイドのテンプレートエンジンでバリデーションをしている場合に送信時のバリデーションを有効化すると、エラーメッセージが表示されず、どこでエラーが発生したかわからなくなるので、無効化する
      ...(ACMS.Config.admin ? { shouldValidateOnSubmit: false } : {}),
      ...options,
    });
    return validator;
  };
};
