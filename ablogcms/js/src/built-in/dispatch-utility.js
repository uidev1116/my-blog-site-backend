import { isOldIE, getBrowser, random } from '../utils';

export default () => {
  //------------------
  // Dispatch.Utility
  ACMS.Dispatch.Utility = function (context) {
    const { Config } = ACMS;

    //------------------------------------------
    // unitgroup to make all of uniform height
    if (Config.unitGroupAlign) {
      let timer;

      $(window)
        .resize(() => {
          const $unitGroup = $(Config.unitGroupAlignMark);
          const containerWidth = $unitGroup.parent().width();
          let currentWidth = 0;
          let count = 0;

          clearTimeout(timer);
          timer = setTimeout(() => {
            _.each($unitGroup, (v) => {
              const $unit = $(v);
              const unitW = $unit.outerWidth(true) - 1;

              $unit.css({
                clear: 'none',
              });
              if (!$unit.prev().hasClass(Config.unitGroupAlignMark.substring(1))) {
                currentWidth = 0;
                count = 0;
              }
              if (1 && count > 0 && containerWidth - (currentWidth + unitW) < -1) {
                $unit.css({
                  clear: 'both',
                });
                currentWidth = unitW;
                count = 1;
              } else {
                currentWidth += unitW;
                count++;
              }
            });
          }, Config.unitGroupAlignInterval);
        })
        .trigger('resize');
    }

    //------------
    // highlight
    if (Config.keyword && !Config.admin) {
      let searchKeywordTag = Config.searchKeywordMatchTag;
      if (!searchKeywordTag) {
        searchKeywordTag = 'mark';
      }
      $.each(Config.keyword.split(' '), function (j) {
        const word = this;
        $(Config.searchKeywordHighlightMark, context)
          .find('*')
          .addBack()
          .contents()
          .filter(
            /* eslint-disable array-callback-return */
            function () {
              if (this.nodeType === 3) {
                const elm = this;
                let text = elm.nodeValue;
                text = text.replace(
                  new RegExp(`(${word})`, 'ig'),
                  `<${searchKeywordTag} class="${Config.searchKeywordMatchClass}${
                    parseInt(j, 10) + 1
                  }">$1</${searchKeywordTag}>`
                );
                $(elm).before($.parseHTML(text));
                $(elm).remove();
              }
            }
            /* eslint-enable array-callback-return */
          );
      });
    }

    //--------
    // toggle
    ((context) => {
      const $toggleHead = $(`[class*=${Config.toggleHeadClassSuffix}]`, context);
      const $toggleBody = $(`[class*="${Config.toggleBodyClassSuffix}"]`, context);
      if ($toggleHead.length === 0) {
        return;
      }
      if ($toggleBody.length === 0) {
        return;
      }
      ACMS.Library.deprecated(ACMS.i18n('deprecated.feature.toggle.name'), {
        since: '3.2.0',
        alternative: ACMS.i18n('deprecated.feature.toggle.alternative'),
      });
      $toggleHead.css('cursor', 'pointer').on('click', function () {
        if (!new RegExp(`([^\\s]*)${Config.toggleHeadClassSuffix}`).test(this.className)) return false;
        const mark = RegExp.$1;
        const $target = $(`.${mark}${Config.toggleBodyClassSuffix}`);
        if (!$target.size()) return false;
        $target.slideToggle();
        return false;
      });
      $toggleBody.hide();
    })(context);

    //------
    // fade
    ((context) => {
      const $fadeHead = $(`[class*=${Config.fadeHeadClassSuffix}]`, context);
      const $fadeBody = $(`[class*="${Config.fadeBodyClassSuffix}"]`, context);
      if ($fadeHead.length === 0) {
        return;
      }
      if ($fadeBody.length === 0) {
        return;
      }
      ACMS.Library.deprecated(ACMS.i18n('deprecated.feature.fade.name'), {
        since: '3.2.0',
        alternative: ACMS.i18n('deprecated.feature.fade.alternative'),
      });
      $fadeHead.css('cursor', 'pointer').on('click', function (e) {
        const $headTarget = $(e.target);
        if (!new RegExp(`([^\\s]*)${Config.fadeHeadClassSuffix}`).test(this.className)) return false;
        const mark = RegExp.$1;
        const $bodyTarget = $(`.${mark}${Config.fadeBodyClassSuffix}`);
        if (!$bodyTarget.size()) return false;
        $bodyTarget.css('display') === 'none' ? $bodyTarget.fadeIn() : $bodyTarget.fadeOut(); // eslint-disable-line no-unused-expressions
        if ($headTarget.data('fade-replace')) {
          const fadeCurrentTxt = $headTarget.text();
          const fadeReplaceTxt = $headTarget.data('fade-replace');
          $headTarget.text(fadeReplaceTxt);
          $headTarget.data('fade-replace', fadeCurrentTxt);
        }
        return false;
      });
      $fadeBody.hide();
    })(context);

    //-------------------
    // styleswitch ready
    const $link = $(Config.styleSwitchStyleMark, context);
    if ($link.size()) {
      const styleName = $.cookie('styleName');
      if (styleName) {
        ACMS.Library.switchStyle(styleName, $link);
      }
    }

    //-----------
    // styleswitch
    $(Config.styleSwitchMark, context).click(function () {
      ACMS.Library.switchStyle(this.rel, $(Config.styleSwitchStyleMark));
      return false;
    });

    //----------
    // comment
    $(Config.commentCookieMark, context).each(function () {
      if (!$.cookie('acms_comment_name')) return true;
      $('input:text[name=name]', this).val($.cookie('acms_comment_name'));
      $('input:text[name=mail]', this).val($.cookie('acms_comment_mail'));
      $('input:text[name=url]', this).val($.cookie('acms_comment_url'));
      $('input:password[name=pass]', this).val($.cookie('acms_comment_pass'));
      $('input:checkbox[name=persistent]', this).attr('checked', 'checked');
    });
    $(Config.commentCookieUserMark, context).each(function () {
      if (!$.cookie('acms_user_name')) return true;
      let name = $.cookie('acms_user_name');
      let mail = $.cookie('acms_user_mail');
      let url = $.cookie('acms_user_url');
      if (!name) {
        name = '';
      }
      if (!mail) {
        mail = '';
      }
      if (!url) {
        url = '';
      }
      $('input:text[name=name]', this).replaceWith(
        `<strong>${name}</strong><input type="hidden" name="name" value="${name}" />`
      );
      $('input:text[name=mail]', this).replaceWith(
        `<strong>${mail}</strong><input type="hidden" name="mail" value="${mail}" />`
      );
      $('input:text[name=url]', this).replaceWith(
        `<strong>${url}</strong><input type="hidden" name="url" value="${url}" />`
      );
    });

    //-------------
    // ready focus
    $(Config.readyFocusMark, context).focus();
  };

  ACMS.Dispatch.Utility.getBrowser = getBrowser;
  ACMS.Dispatch.Utility.isOldIE = isOldIE;
  ACMS.Dispatch.Utility.random = random;

  ACMS.Dispatch.Utility.browser = function () {
    const _ua = (function () {
      const browser = ACMS.Dispatch.Utility.getBrowser();
      let IE6 = false;
      let IE7 = false;
      let IE8 = false;
      let IE9 = false;

      if (browser === 'ie9') {
        IE9 = true;
      } else if (browser === 'ie8') {
        IE9 = true;
        IE8 = true;
      } else if (browser === 'ie7') {
        IE9 = true;
        IE8 = true;
        IE7 = true;
      } else if (browser === 'ie6') {
        IE9 = true;
        IE8 = true;
        IE7 = true;
        IE6 = true;
      }

      return {
        ltIE6: IE6,
        ltIE7: IE7,
        ltIE8: IE8,
        ltIE9: IE9,
        mobile: /^(.+iPhone.+AppleWebKit.+Mobile.+|^.+Android.+AppleWebKit.+Mobile.+)$/i.test(
          navigator.userAgent.toLowerCase()
        ),
        tablet: /^(.+iPad;.+AppleWebKit.+Mobile.+|.+Android.+AppleWebKit.+)$/i.test(navigator.userAgent.toLowerCase()),
      };
    })();
    return _ua;
  };

  ACMS.Dispatch.Utility.unloadAlert = (context, selector, force = false) => {
    if (selector) {
      selector = `.js-admin_unload_alert, ${selector}`;
    }

    const $adminForm = $(selector, context);
    if (!$adminForm.length) {
      return false;
    }
    const adminForm = $adminForm.get(0);

    const unload = function () {
      const onBeforeunloadHandler = function (e) {
        e.returnValue = ACMS.i18n('unload.message1');
      };
      window.addEventListener('beforeunload', onBeforeunloadHandler, false);

      if (adminForm) {
        adminForm.addEventListener('submit', () => {
          window.removeEventListener('beforeunload', onBeforeunloadHandler, false);
        });
      }
    };

    if (force) {
      unload();
    } else {
      $adminForm.bind('input', () => {
        unload();
      });
    }
  };
};
