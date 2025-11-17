import './lib/polyfill';

import dispatchSystemUpdate from './features/system-update';
import dispatchBackup from './features/backup';
import dispatchStaticExport from './features/static-export';
import dispatchCsvImport from './features/csv-import';
import dispatchWxrExport from './features/wxr-export';
import dispatchWebhookEventSelect from './dispatch/dispatch-webhook-event-select';
import dispatchPreviewMode from './dispatch/dispatch-preview-mode';
import dispatchTimeMachineMode from './dispatch/dispatch-timemachine-mode';
import dispatchInlinePreview from './dispatch/dispatch-inline-preview';
import dispatchAdminMenuEditor from './dispatch/dispatch-admin-menu-editor';
import dispatchBannerEditor from './dispatch/dispatch-banner-editor';
import dispatchThemeColorPicker from './dispatch/dispatch-theme-color-picker';
import dispatchSelect2 from './dispatch/dispatch-select2';
import dispatchCustomFieldMaker from './dispatch/dispatch-custom-field-maker';
import dispatchAuditLogDetailModal from './dispatch/dispatch-audit-log-detail-modal';
import dispatchQuickSearch from './dispatch/dispatch-quick-search';
import dispatchEntryLockModal from './dispatch/dispatch-entry-lock-modal';
import dispatchKeyboardShortcutModal from './dispatch/dispatch-keyboard-shortcut-modal';
import dispatchNavigationEditor from './dispatch/dispatch-navigation-editor';
import dispatchMediaAdmin from './dispatch/media/dispatch-media-admin';
import dispatchMediaField from './dispatch/media/dispatch-media-field';
import dispatchCategorySelect from './dispatch/dispatch-category-select';
import dispatchTagSelect from './dispatch/dispatch-tag-select';
import dispatchSubCategorySelect from './dispatch/dispatch-sub-category-select';
import dispatchAtableField from './dispatch/dispatch-a-table-field';
import dispatchRichEditor from './dispatch/dispatch-rich-editor';
import dispatchBlockEditor from './dispatch/dispatch-block-editor';
import dispatchRelatedEntry from './dispatch/dispatch-related-entry';
import dispatchLiteEditorField from './dispatch/dispatch-lite-editor-field';
import dispatchEntryAdmin from './dispatch/dispatch-entry-admin';
import dispatchEntryBulkChangeSelect from './dispatch/dispatch-entry-bulk-change-select';
import dispatchModuleAdmin from './dispatch/dispatch-module-admin';
import dispatchNotify from './dispatch/dispatch-notify';
import dispatchUnitEditor from './dispatch/dispatch-unit-editor';
import dispatchUnitInplaceEditor from './dispatch/dispatch-unit-inplace-editor';
import dispatchPending from './dispatch/dispatch-pending';
import dispatchTooltip from './dispatch/dispatch-tooltip';
import dispatchGoogleMapsPicker from './dispatch/dispatch-google-maps-picker';
import dispatchOpenStreetMapPicker from './dispatch/dispatch-open-street-map-picker';
import dispatchModal from './dispatch/dispatch-modal';
import dispatchResizeImageCF from './dispatch/dispatch-resize-image-cf';
import dispatchDialog from './dispatch/dispatch-dialog';
import dispatchUnitConfigEditor from './dispatch/dispatch-unit-config-editor';

ACMS.Ready(() => {
  __webpack_public_path__ = ACMS.Config.root; // eslint-disable-line

  /**
   * Delayed contents event
   */
  ACMS.addListener('acmsAdminSelectTab', (e) => {
    ACMS.dispatchEvent('acmsAdminDelayedContents', e.target, e.detail);
  });
  ACMS.addListener('acmsAdminShowTabPanel', (e) => {
    ACMS.dispatchEvent('acmsAdminDelayedContents', e.target, e.detail);
  });
  ACMS.addListener('acmsDialogOpened', (e) => {
    ACMS.dispatchEvent('acmsAdminDelayedContents', e.target, e.detail);
  });
  ACMS.addListener('acmsAddCustomFieldGroup', (e) => {
    ACMS.dispatchEvent('acmsAdminDelayedContents', e.target, e.detail);
  });

  /**
   * モーダルないのいらない情報を削除
   */
  ACMS.addListener('acmsDialogOpened', (e) => {
    $('.js-hide-on-modal', e.target).remove();
  });

  /**
   * 通知
   */
  dispatchNotify();

  /**
   * ダイアログ
   */
  dispatchDialog();

  /**
   * ツールチップ
   */
  dispatchTooltip();

  /*
   * ペンディング
   */
  dispatchPending();

  /**
   * モーダル
   */
  dispatchModal();

  /**
   * Unit Editor
   */
  dispatchUnitEditor(document);

  /**
   * Unit Inplace Editor
   */
  ACMS.addListener('acmsInplaceDialogOpen', (event) => {
    dispatchUnitInplaceEditor(event.target);
  });

  /**
   * カテゴリー選択
   */
  dispatchCategorySelect(document);
  ACMS.addListener('acmsAdminDelayedContents', (event) => {
    const context = event.obj.item || event.target;
    dispatchCategorySelect(context);
  });

  /**
   * タグ選択
   */
  dispatchTagSelect(document);
  ACMS.addListener('acmsAdminDelayedContents', (event) => {
    const context = event.obj.item || event.target;
    dispatchTagSelect(context);
  });

  /**
   * サブカテゴリー選択
   */
  dispatchSubCategorySelect(document);
  ACMS.addListener('acmsAdminDelayedContents', (event) => {
    const context = event.obj.item || event.target;
    dispatchSubCategorySelect(context);
  });

  /**
   * a-table field
   */
  dispatchAtableField(document);
  ACMS.addListener('acmsAdminDelayedContents', () => {
    dispatchAtableField(document);
  });
  ACMS.addListener('acmsCustomFieldMakerPreview', (event) => {
    dispatchAtableField(event.target);
  });

  /**
   * BlockEditor, Rich Editor
   */
  dispatchBlockEditor(document);
  dispatchRichEditor(document);
  ACMS.addListener('acmsAdminDelayedContents', (e) => {
    const context = e.obj.item || e.target;
    dispatchBlockEditor(context);
    dispatchRichEditor(context);
  });
  ACMS.addListener('acmsCustomFieldMakerPreview', (event) => {
    dispatchBlockEditor(event.target);
    dispatchRichEditor(event.target);
  });

  /**
   * 関連エントリー
   */
  dispatchRelatedEntry(document);
  ACMS.addListener('acmsAdminDelayedContents', (event) => {
    const context = event.obj.item || event.target;
    dispatchRelatedEntry(context);
  });

  /**
   * Lite Editor Field
   */
  dispatchLiteEditorField(document);
  ACMS.addListener('acmsAdminDelayedContents', (e) => {
    const ctx = e.target || document;
    dispatchLiteEditorField(ctx);
  });
  ACMS.addListener('acmsAddCustomFieldGroup', (event) => {
    const { item } = event.obj;
    dispatchLiteEditorField(item);
  });
  ACMS.addListener('acmsCustomFieldMakerPreview', (event) => {
    dispatchLiteEditorField(event.target);
  });

  /**
   * カスタムフィールドメーカー
   */
  dispatchCustomFieldMaker(document);

  /**
   * Google Map Picker
   */
  dispatchGoogleMapsPicker(document);
  ACMS.addListener('acmsAddCustomFieldGroup', (e) => {
    dispatchGoogleMapsPicker(e.obj.item);
  });

  ACMS.addListener('acmsDialogOpened', (e) => {
    dispatchGoogleMapsPicker(e.target);
  });

  dispatchOpenStreetMapPicker(document);
  ACMS.addListener('acmsAddCustomFieldGroup', (e) => {
    dispatchOpenStreetMapPicker(e.obj.item);
  });

  ACMS.addListener('acmsDialogOpened', (e) => {
    dispatchOpenStreetMapPicker(e.target);
  });

  /**
   * メニューのスクロールバー（IE以外）
   */
  (async () => {
    if (!/^ie/.test(ACMS.Dispatch.Utility.getBrowser())) {
      const { default: PerfectScrollbar } = await import(
        /* webpackChunkName: "perfect-scrollbar" */ 'perfect-scrollbar'
      );
      await import(/* webpackChunkName: "perfect-scrollbar-css" */ 'perfect-scrollbar/css/perfect-scrollbar.css');
      const psDom = document.querySelector('.js-scroll-contents');
      if (psDom) {
        const ps = new PerfectScrollbar(psDom, {
          wheelSpeed: 1,
          wheelPropagation: true,
          minScrollbarLength: 20,
        });
        ps.update();
      }
    }
  })();

  /**
   * API管理画面のAPI KEY生成
   */
  (() => {
    const target = document.querySelector('.js-x-api-key');
    const generateUuid = () => {
      // https://github.com/GoogleChrome/chrome-platform-analytics/blob/master/src/internal/identifier.js
      // const FORMAT: string = "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx";
      const chars = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.split('');
      for (let i = 0, len = chars.length; i < len; i++) {
        // eslint-disable-next-line default-case
        switch (chars[i]) {
          case 'x':
            chars[i] = Math.floor(Math.random() * 16).toString(16);
            break;
          case 'y':
            chars[i] = (Math.floor(Math.random() * 4) + 8).toString(16);
            break;
        }
      }
      return chars.join('');
    };
    if (target) {
      const input = target.querySelector('.js-key-input');
      const updateButton = target.querySelector('.js-update-key');
      if (input && !input.value) {
        input.value = generateUuid();
      }
      if (updateButton) {
        updateButton.addEventListener('click', (e) => {
          e.preventDefault();
          if (window.confirm('本当にキーを更新してもよろしいですか？保存するまでは変更されません。')) {
            input.value = generateUuid();
          }
        });
      }
    }
  })();

  /**
   * 監査ログページ
   */
  dispatchAuditLogDetailModal(document);

  /**
   * Webhook 管理ページ
   */
  dispatchWebhookEventSelect(document);

  /**
   * Theme color picker
   */
  dispatchThemeColorPicker(document);
  ACMS.addListener('acmsAdminDelayedContents', (event) => {
    const context = event.obj.item || event.target;
    dispatchThemeColorPicker(context);
  });

  /**
   * クイックサーチ
   */
  dispatchQuickSearch(document);

  /**
   * エントリーロック
   */
  if (ACMS.Config.edition === 'professional' || ACMS.Config.edition === 'enterprise') {
    dispatchEntryLockModal(document);
  }

  /**
   * キーボードショートカットモーダル
   */
  dispatchKeyboardShortcutModal(document);

  /**
   * プレビューモード（インライン）
   */
  dispatchInlinePreview(document);

  /**
   * プレビューモード（モーダル）
   */
  dispatchPreviewMode(document);

  /**
   * タイムマシーンモード
   */
  dispatchTimeMachineMode(document);

  /**
   * System update
   */
  dispatchSystemUpdate(document);

  /**
   * Backup
   */
  dispatchBackup(document);

  /**
   * Static export
   */
  dispatchStaticExport(document);

  /**
   * CSV Import
   */
  dispatchCsvImport(document);

  /**
   * WXR Export
   */
  dispatchWxrExport(document);

  /**
   * Select2
   */
  dispatchSelect2(document);
  ACMS.addListener('acmsAdminDelayedContents', (e) => {
    dispatchSelect2(e.target);
  });
  ACMS.addListener('acmsAddCustomFieldGroup', (e) => {
    dispatchSelect2(e.obj.item);
  });

  /**
   * Resize image cf
   */
  dispatchResizeImageCF(document);
  ACMS.addListener('acmsAddCustomFieldGroup', (event) => {
    dispatchResizeImageCF(event.target);
  });

  /**
   * Disclose password
   */
  const disclosePassword = document.querySelectorAll('.js-disclose_password');
  [].forEach.call(disclosePassword, (item) => {
    item.addEventListener('change', (event) => {
      const selector = event.target.getAttribute('data-target');
      const target = document.querySelector(selector);
      if (event.target.checked) {
        target.setAttribute('type', 'text');
      } else {
        target.setAttribute('type', 'password');
      }
    });
  });

  /**
   * AdminMenu editor
   */
  dispatchAdminMenuEditor(document);
  ACMS.addListener('acmsAdminDelayedContents', () => {
    dispatchAdminMenuEditor(document);
  });

  /**
   * Navigation editor
   */
  dispatchNavigationEditor(document);
  ACMS.addListener('acmsAdminDelayedContents', () => {
    dispatchNavigationEditor(document);
  });

  /**
   * Banner Editor
   */
  dispatchBannerEditor(document);
  ACMS.addListener('acmsAdminDelayedContents', (event) => {
    const context = event.obj.item || event.target;
    dispatchBannerEditor(context);
  });

  /**
   * Unit Config Editor
   */
  dispatchUnitConfigEditor(document);

  /**
   * メディア管理
   */
  dispatchMediaAdmin(document);
  ACMS.addListener('acmsAdminDelayedContents', (event) => {
    const context = event.obj.item || event.target;
    dispatchMediaAdmin(context);
  });

  /**
   * メディアフィールド
   */
  dispatchMediaField(document);
  ACMS.addListener('acmsDialogOpened', (event) => {
    dispatchMediaField(event.target);
  });
  ACMS.addListener('acmsAddCustomFieldGroup', (event) => {
    dispatchMediaField(event.target);
  });

  ACMS.addListener('acmsCustomFieldMakerPreview', (event) => {
    dispatchMediaField(event.target);
  });

  /**
   * エントリー管理
   */
  dispatchEntryAdmin(document);

  /**
   * エントリー一括変更選択
   */
  dispatchEntryBulkChangeSelect(document);

  /**
   * モジュール管理
   */
  dispatchModuleAdmin(document);

  /**
   * ライセンス警告
   */
  const toastToggle = document.querySelectorAll('.js-admin-toast-toggle');
  [].forEach.call(toastToggle, (item) => {
    const closeClass = 'acms-admin-toast-closed';

    item.addEventListener('mouseenter', (event) => {
      item.classList.remove(closeClass);
      event.preventDefault();
    });
    item.addEventListener('mouseleave', (event) => {
      item.classList.add(closeClass);
      event.preventDefault();
    });

    const storageKey = 'acms-license-alert';
    const time = localStorage.getItem(storageKey);
    if (!time || Date.now() > parseInt(time, 10)) {
      item.classList.remove(closeClass);
      setTimeout(() => {
        item.classList.add(closeClass);
      }, 5000);
      localStorage.setItem(storageKey, Date.now() + 60 * 60 * 12 * 1000);
    }
  });

  /**
   * Dispatch acmsAdminReady
   */
  ACMS.dispatchEvent('acmsAdminReady');

  /**
   * GeoPicker
   */
  const dispatchGeoInput = (context) => {
    const buttons = context.querySelectorAll('.js-geo-button');
    buttons.forEach((button) => {
      const form = context.querySelector(button.dataset.target);
      if (!form) {
        return;
      }
      const lat = form.querySelector('[name=geo_lat]');
      const lng = form.querySelector('[name=geo_lng]');
      const zoom = form.querySelector('[name=geo_zoom]');

      const addGeo = function () {
        const {
          adminLocationDefaultLat: defaultLat,
          adminLocationDefaultLng: defaultLng,
          adminLocationDefaultZoom: defaultZoom = '10',
        } = ACMS.Config;
        if (lat.value === '') {
          lat.value = defaultLat;
          lat.dispatchEvent(new Event('change', { bubbles: true }));
        }
        if (lng.value === '') {
          lng.value = defaultLng;
          lng.dispatchEvent(new Event('change', { bubbles: true }));
        }
        if (zoom.value === '') {
          zoom.value = defaultZoom || '10';
          zoom.dispatchEvent(new Event('change', { bubbles: true }));
        }
        form.style.display = 'block';
        button.textContent = ACMS.i18n('geo.message1');
        button.setAttribute('data-type', 'add');
        ACMS.dispatchEvent('onGeoInfoAdded', form.parentElement);
      };

      const deleteGeo = function () {
        form.style.display = 'none';
        button.textContent = ACMS.i18n('geo.message2');
        button.setAttribute('data-type', 'delete');
        lat.value = '';
        lat.dispatchEvent(new Event('change', { bubbles: true }));
        lng.value = '';
        lng.dispatchEvent(new Event('change', { bubbles: true }));
        zoom.value = '';
        zoom.dispatchEvent(new Event('change', { bubbles: true }));
      };

      const handleClick = () => {
        const { type } = button.dataset;
        if (type === 'add') {
          deleteGeo();
        } else if (type === 'delete') {
          addGeo();
        }
      };

      button.addEventListener('click', handleClick);
      handleClick(); // Initial trigger
    });
  };
  dispatchGeoInput(document);
  ACMS.addListener('acmsDialogOpened', (event) => {
    dispatchGeoInput(event.obj.item);
  });

  /**
   * 編集画面のラベル変更
   */
  const changeEntryLabels = () => {
    const EditorJson = document.getElementById('entry-labels');
    if (!EditorJson) {
      return;
    }
    try {
      const json = JSON.parse(EditorJson.innerHTML);
      Object.keys(json).forEach((item) => {
        const target = document.getElementById(item);
        if (target && json[item]) {
          target.innerHTML = json[item];
        }
      });
    } catch (e) {
      console.log('JSONのparseに失敗しました。'); // eslint-disable-line no-console
    }
    const details = document.getElementById('js-entry-details');
    if (!details) {
      return;
    }
    const tableSelector = details.dataset.target;
    if (!tableSelector) {
      return;
    }
    const table = details.querySelector(tableSelector);
    if (!table) {
      return;
    }
    let flag = false;
    const trs = table.querySelectorAll('tbody > tr');
    [].forEach.call(trs, (tr) => {
      const style = window.getComputedStyle(tr);
      if (style && style.display !== 'none') {
        flag = true;
      }
    });
    if (!flag) {
      details.style.display = 'none';
    }
  };

  changeEntryLabels();
  ACMS.addListener('acmsAdminDelayedContents', changeEntryLabels);
});
