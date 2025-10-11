/**
 * @typedef {Object} EditorData
 * @property {string} mode - エディタのモード ('html' | 'markdown')
 * @property {string} type - エディタのタイプ
 * @property {boolean} hideBtns - ボタンを非表示にするかどうか
 * @property {boolean} showSource - ソース表示モードかどうか
 * @property {boolean} disableEditorMode - エディタモードを無効化するかどうか
 * @property {string} value - エディタの値
 * @property {string} extendValue - 拡張タグの値
 */

/**
 * @typedef {Object} EditorOption
 * @property {string} value - オプションの値
 * @property {string} label - オプションのラベル
 * @property {string} [extendLabel] - 拡張ラベル
 * @property {Function} onSelect - 選択時のコールバック関数
 */

/**
 * @typedef {Object} EditorConfig
 * @property {EditorOption[]} selectOptions - セレクトオプションの配列
 * @property {string} selectedOption - 選択されたオプション
 * @property {string} selectName - セレクトの名前
 * @property {string} extendValue - 拡張タグの値
 * @property {boolean} sourceFirst - ソース表示を優先するかどうか
 * @property {string} mode - エディタのモード
 * @property {Array} btnOptions - ボタンオプションの配列
 */

/**
 * カーソルを要素の末尾に移動する
 * @param {HTMLElement} el - 対象の要素
 * @returns {void}
 */
const moveCursorToEnd = (el) => {
  if (!el) return;

  let range;
  let selection;
  if (document.createRange) {
    range = document.createRange();
    range.selectNodeContents(el);
    range.collapse(false);
    selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(range);
  } else if (document.selection) {
    range = document.body.createTextRange();
    range.moveToElementText(el);
    range.collapse(false);
    range.select();
  }
};

const displayStates = new Map();

function hide(element) {
  const { display } = getComputedStyle(element);

  if (display !== 'none') {
    displayStates.set(element, display);
    element.style.display = 'none';
  }
}

function show(element) {
  const { display } = getComputedStyle(element);

  if (display === 'none') {
    element.style.display = displayStates.get(element) || 'block';
  }
}

/**
 * タグタイプごとのハンドラー
 * @type {Object.<string, {condition: (tag: string) => boolean, handler: (self: LiteEditor, tag: string) => void}>}
 */
const tagHandlers = {
  wysiwyg: {
    condition: (tag) => tag === 'wysiwyg',
    handler: (self) => {
      self.data.mode = 'html';
      self.data.type = 'wysiwyg';
      self.data.hideBtns = true;
      self.data.showSource = true;
      self.data.disableEditorMode = true;
      self.update();
      ACMS.Dispatch.wysiwyg.init(self._getElementByQuery('[data-selector="lite-editor-source"]'));
    },
  },
  markdown: {
    condition: (tag) => tag === 'markdown',
    handler: (self) => {
      const textarea = self._getElementByQuery('[data-selector="lite-editor-source"]');
      if (!textarea) {
        return;
      }

      self.data.mode = 'markdown';
      self.data.type = 'markdown';
      self.data.disableEditorMode = true;
      self.data.hideBtns = false;
      if (textarea && ACMS.Dispatch.wysiwyg.isAdapted(textarea)) {
        self.data.value = ACMS.Dispatch.wysiwyg.getHtml(textarea);
        ACMS.Dispatch.wysiwyg.destroy(textarea);
        self.data.showSource = true;
        self.update();
      } else if (!self.data.showSource) {
        self.data.showSource = true;
        self.update();
        show(textarea);
      }
    },
  },
  sourceMode: {
    condition: (tag) => tag.match(ACMS.Config.LiteEditorSourceModeTags),
    handler: (self, tag) => {
      const textarea = self._getElementByQuery('[data-selector="lite-editor-source"]');
      if (!textarea) {
        return;
      }

      self.data.mode = 'html';
      self.data.type = tag;
      self.data.disableEditorMode = true;
      self.data.hideBtns = false;
      if (!self.data.showSource) {
        self.data.showSource = true;
        self.update();
        show(textarea);
      }
    },
  },
  default: {
    condition: () => true,
    handler: (self, tag) => {
      const textarea = self._getElementByQuery('[data-selector="lite-editor-source"]');
      if (!textarea) {
        return;
      }

      self.data.showSource = false;
      self.data.type = tag;
      self.data.mode = 'html';
      self.data.disableEditorMode = false;
      self.data.hideBtns = false;
      self.update();
      const editor = self._getElementByQuery('[data-selector="lite-editor"]');
      if (editor) {
        editor.innerHTML = self.data.value;
        show(editor);
      }
    },
  },
};

/**
 * セレクトオプションを作成する
 * @param {HTMLSelectElement} tagSelect - タグセレクト要素
 * @returns {EditorOption[]} セレクトオプションの配列
 */
const createSelectOptions = (tagSelect) => {
  if (!tagSelect) {
    return [];
  }

  const selectOptions = [];
  Array.from(tagSelect.options).forEach((option) => {
    const tag = option.value;
    const opt = {
      value: tag,
      label: option.text,
      extendLabel: option.getAttribute('data-tag_extend'),
    };

    opt.onSelect = (self) => {
      const handler = Object.values(tagHandlers).find(({ condition }) => condition(tag));
      handler.handler(self, tag);
    };

    selectOptions.push(opt);
  });
  return selectOptions;
};

/**
 * イベントリスナーをセットアップする
 * @param {LiteEditor[]} editorInstances - エディタインスタンスの配列
 * @param {HTMLElement} element - ルート要素
 * @returns {void}
 */
const setupEventListeners = (editorInstances, element) => {
  if (!editorInstances || !element) {
    return;
  }

  editorInstances.forEach((editor, _, instances) => {
    const editable = editor._getElementByQuery('[data-selector="lite-editor"]');
    if (!editable) {
      return;
    }

    // ダイレクト編集時の処理
    const editInplace = element.closest('#js-edit_inplace-box');
    if (editInplace) {
      // エディタにフォーカスする
      editable.focus();
      // カーソルを末尾に移動する
      moveCursorToEnd(editable);
      // ダイレクト編集時のEnterキー押下時の処理
      const keydownHandler = function (e) {
        if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) {
          const submitButton = editInplace.querySelector('#js-edit_inplace-submit');
          if (submitButton) {
            submitButton.click();
            e.preventDefault();
          }
        }
      };
      editable.addEventListener('keydown', keydownHandler);
    }

    // 多言語ユニット時に複数のエディタがある場合に、それぞれのエディタのセレクトボックスの変更を監視し同期する
    const selects = element.querySelectorAll('.lite-editor-select');
    if (selects.length > 0) {
      const changeHandler = function () {
        instances.forEach((instance) => {
          if (instance === editor) {
            // 自分のエディタの場合は何もしない
            return;
          }
          instance.e = {
            target: {
              value: this.value,
            },
          };
          instance.changeOption();
          instance.update();
        });
      };
      selects.forEach((select) => {
        select.addEventListener('change', changeHandler);
      });
    }

    // 多言語ユニット時に複数のエディタがある場合に、それぞれのエディタの拡張タグの入力ボックスの変更を監視し同期する
    const extendInputs = element.querySelectorAll('.lite-editor-extend-input');
    if (extendInputs.length > 0) {
      const changeHandler = function () {
        instances.forEach((instance) => {
          if (instance === editor) {
            return;
          }
          instance.data.extendValue = this.value;
          instance.update();
        });
      };
      extendInputs.forEach((input) => {
        input.addEventListener('change', changeHandler);
      });
    }
  });
};

/**
 * 絵文字ピッカーを追加するかどうか
 * @type {boolean}
 */
const isEmojiPickerAvailable = () => {
  const isMobile =
    (navigator.userAgent.indexOf('iPhone') > 0 && navigator.userAgent.indexOf('iPad') === -1) ||
    navigator.userAgent.indexOf('iPod') > 0 ||
    navigator.userAgent.indexOf('Android') > 0;
  return ACMS.Config.LiteEditorUseEmojiPicker && ACMS.Config.dbCharset === 'utf8mb4' && !isMobile;
};

/**
 * ライトエディタをセットアップする
 * @deprecated LiteEditor は非推奨です
 * @param {HTMLElement} element - 親要素
 * @param {Object} options - オプション
 * @returns {Promise<LiteEditor[]>} LiteEditor インスタンスの配列
 * @throws {Error} 必要な要素が見つからない場合
 */
export default async function setupLiteEditor(element, options = {}) {
  if (!element) {
    throw new Error('Element not found');
  }

  const tagSelect = element.querySelector('[name^="text_tag"]');
  if (!tagSelect) {
    return [];
  }

  const extendTagInput = element.querySelector('[name^="text_extend_tag"]');
  if (!extendTagInput) {
    return [];
  }

  const selectedOption = tagSelect.value;
  const selectName = tagSelect.getAttribute('name');
  const extendValue = extendTagInput.value;

  const selectOptions = createSelectOptions(tagSelect);

  const textareas = element.querySelectorAll('textarea');

  if (textareas.length === 0) {
    return [];
  }

  // 必要なモジュールを先にインポート
  import(/* webpackChunkName: "lite-editor-css" */ 'lite-editor/css/lite-editor.css');
  const { default: LiteEditor } = await import(/* webpackChunkName: "lite-editor" */ 'lite-editor');

  // 絵文字ピッカーのインポートを先に行う
  let LiteEditorEmojiPicker;
  if (isEmojiPickerAvailable()) {
    import(
      /* webpackChunkName: "lite-editor-emoji-picker-plugin-css" */ 'lite-editor-emoji-picker-plugin/css/lite-editor-emoji-picker.css'
    );
    const module = await import(
      /* webpackChunkName: "lite-editor-emoji-picker-plugin" */ 'lite-editor-emoji-picker-plugin'
    );
    LiteEditorEmojiPicker = module.default;
  }

  const editorInstances = [];

  try {
    // 各テキストエリアのエディタインスタンス作成を並列処理
    textareas.forEach((textarea) => {
      if (textarea.LiteEditor instanceof LiteEditor) {
        return null;
      }

      const tag = tagSelect.value;
      const sourceFirst = !!(tag && tag.match(ACMS.Config.LiteEditorSourceModeTags));
      textarea.setAttribute('rows', '1');

      const btnOptions = [...ACMS.Config.LiteEditorConf.btnOptions];
      if (LiteEditorEmojiPicker) {
        btnOptions.push(
          new LiteEditorEmojiPicker({
            label: ACMS.Config.LiteEditorEmojiPickerLabel,
          })
        );
      }

      const editorOption = {
        ...ACMS.Config.LiteEditorConf,
        selectOptions,
        selectedOption,
        selectName,
        extendValue,
        sourceFirst,
        mode: selectedOption === 'markdown' ? 'markdown' : 'html',
        btnOptions,
        ...options,
      };

      try {
        const editor = new LiteEditor(textarea, editorOption);
        if (sourceFirst) {
          editor.deactivateEditorMode();
        }
        textarea.LiteEditor = editor;
        textarea.focus();
        editorInstances.push(editor);
      } catch (error) {
        console.error('Failed to setup editor for textarea:', error); // eslint-disable-line no-console
        return null;
      }
    });

    setupEventListeners(editorInstances, element);
    tagSelect.remove();
    extendTagInput.remove();

    return editorInstances;
  } catch (error) {
    console.error('Failed to setup lite editor:', error); // eslint-disable-line no-console
    throw error;
  }
}
