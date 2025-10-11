/**
 * @typedef { import("./src/lib/acmsPath/types").AcmsContext } AcmsContext
 */

/**
 * @typedef { import("./src/components/dataview/types").GetValues } DataviewGetValues
 * @typedef { import("./src/components/dataview/types").CreateGetValuesOptions } DataviewCreateGetValuesOptions
 * @typedef { import("./src/components/dataview/types").Action } DataviewAction
 * @typedef { import("./src/components/dataview/types").BulkAction } DataviewBulkAction
 * @typedef { import("./src/components/dataview/types").Menu } DataviewMenu
 * @typedef { import("./src/components/dataview/types").Column } DataviewColumn
 * @typedef { import("./src/components/dataview/types").GetGetValue } DataviewGetGetValue
 * @typedef { import("./src/components/dataview/types").GetValues } DataviewGetValues
 * @typedef { import("./src/components/dataview/types").CreateGetValues } DataviewCreateGetValues
 * @typedef { import("./src/components/dataview/types").CreateGetValuesOptions } DataviewCreateGetValuesOptions
 */

ACMS.Config({
  //---------------------------------------------------
  // jQuery UI テーマ名 ( 下記URLでDLとカスタマイズが可能 )
  // @url http://jqueryui.com/themeroller/
  uiTheme: 'smoothness',

  //------
  // htmx
  htmxMark: 'meta[name="acms-htmx"],[data-hx-get],[data-hx-post],[hx-get],[hx-post]', // htmxを有効にする要素のセレクタ
  htmxConfig: {
    historyCacheSize: -1, // ローカルストレージにHTMLをキャッシュしない（キャッシュすると戻る・進むが正常に動作しないため）
    refreshOnHistoryMiss: true, // キャッシュがなければページを再読込
  },

  //----------------------
  // Syntax Highlighter
  highlightMark: 'pre',
  highlightConfig: {
    theme: 'atom-one-light', // テーマを指定（https://highlightjs.org/examples を参照）
    languages: ['bash', 'css', 'javascript', 'json', 'php', 'sql', 'typescript', 'xml', 'yaml', 'twig'], // ハイライトする言語を指定（https://highlightjs.org/download を参照）
  },

  //-------------------
  // WYSIWYG Editor (trumbowyg)
  // @link https://alex-d.github.io/Trumbowyg/
  wysiwygMark: 'textarea.js-wysiwyg,textarea.js-ckeditor,textarea.js-emoditor',
  wysiwygConfig: {
    lang: 'ja',
    // resetCss: true,
    autogrow: true,
    tagsToRemove: ['script'],
    btns: [
      ['viewHTML'],
      ['undo', 'redo'], // Only supported in Blink browsers
      ['formatting'],
      ['fontsize'],
      ['lineheight'],
      ['strong', 'em', 'del'],
      // ['superscript', 'subscript'],
      ['foreColor', 'backColor'],
      ['link'],
      ['justifyLeft', 'justifyCenter', 'justifyRight'],
      ['unorderedList', 'orderedList'],
      ['horizontalRule'],
      ['table', 'tableCellBackgroundColor', 'tableBorderColor'],
      ['removeformat'],
      ['fullscreen'],
    ],
    tagClasses: {
      // table: 'class-name',
    },
    semantic: {
      div: 'div',
    },
  },

  //-----------------------
  // SmartPhoto
  SmartPhotoMark: 'a[data-rel^=SmartPhoto],.js-smartphoto',
  SmartPhotoConfig: {
    classNames: {
      smartPhoto: 'smartphoto',
      smartPhotoClose: 'smartphoto-close',
      smartPhotoBody: 'smartphoto-body',
      smartPhotoInner: 'smartphoto-inner',
      smartPhotoContent: 'smartphoto-content',
      smartPhotoImg: 'smartphoto-img',
      smartPhotoImgOnMove: 'smartphoto-img-onmove',
      smartPhotoImgElasticMove: 'smartphoto-img-elasticmove',
      smartPhotoImgWrap: 'smartphoto-img-wrap',
      smartPhotoArrows: 'smartphoto-arrows',
      smartPhotoNav: 'smartphoto-nav',
      smartPhotoArrowRight: 'smartphoto-arrow-right',
      smartPhotoArrowLeft: 'smartphoto-arrow-left',
      smartPhotoImgLeft: 'smartphoto-img-left',
      smartPhotoImgRight: 'smartphoto-img-right',
      smartPhotoList: 'smartphoto-list',
      smartPhotoListOnMove: 'smartphoto-list-onmove',
      smartPhotoHeader: 'smartphoto-header',
      smartPhotoCount: 'smartphoto-count',
      smartPhotoCaption: 'smartphoto-caption',
      smartPhotoDismiss: 'smartphoto-dismiss',
      smartPhotoLoader: 'smartphoto-loader',
      smartPhotoLoaderWrap: 'smartphoto-loader-wrap',
      smartPhotoImgClone: 'smartphoto-img-clone',
    },
    message: {
      gotoNextImage: ACMS.i18n('smartphoto.goto_next_image'),
      gotoPrevImage: ACMS.i18n('smartphoto.goto_prev_image'),
      closeDialog: ACMS.i18n('smartphoto.close_the_image_dialog'),
    },
    arrows: true,
    nav: true,
    animationSpeed: 300,
    swipeOffset: 100,
    headerHeight: 60,
    footerHeight: 60,
    forceInterval: 10,
    registance: 0.5,
    resizeStyle: 'fit',
    verticalGravity: false,
    useOrientationApi: false,
    useHistoryApi: true,
    lazyAttribute: 'data-src',
  },

  //----------------
  // contrast color
  contrastColorTarget: '.js-contrast-color',

  //--------------------
  // document-outliner
  documentOutlinerMark: '.js-outline',
  documentOutlinerConfig: {
    link: true,
    listType: 'ol',
    listClassName: 'acms-ol',
    itemClassName: 'acms-ol-item',
    linkClassName: 'scrollTo',
    anchorName: 'heading-$1',
    exceptClass: 'js-except',
    levelLimit: 5,
  },

  //-----------------------
  // modal video
  modalVideoMark: '.js-modal-video',
  modalVideoConfig: {
    channel: 'youtube',
    youtube: {
      autoplay: 1,
      cc_load_policy: 1,
      color: 'red', // red | white
      controls: 1,
      disablekb: 0,
      enablejsapi: 0,
      end: null,
      fs: 1,
      h1: null,
      iv_load_policy: 1,
      loop: 0,
      modestbranding: 0,
      origin: 0,
      playsinline: 0,
      rel: 0,
      showinfo: 1,
      start: 0,
      wmode: 'transparent',
      theme: 'dark',
    },
    ratio: '16:9',
    vimeo: {
      api: false,
      autopause: true,
      autoplay: true,
      byline: true,
      callback: null,
      color: null,
      height: null,
      loop: false,
      maxheight: null,
      maxwidth: null,
      player_id: null,
      portrait: true,
      title: true,
      width: null,
      xhtml: false,
    },
    allowFullScreen: true,
    animationSpeed: 300,
    classNames: {
      modalVideo: 'modal-video',
      modalVideoClose: 'modal-video-close',
      modalVideoBody: 'modal-video-body',
      modalVideoInner: 'modal-video-inner',
      modalVideoIframeWrap: 'modal-video-movie-wrap',
      modalVideoCloseBtn: 'modal-video-close-btn',
    },
    aria: {
      openMessage: ACMS.i18n('modal_video.aria_open_msg'),
      dismissBtnMessage: ACMS.i18n('modal_video.dismiss_msg'),
    },
  },

  //-----------------------------
  // module setting popup window
  popupSettingMark: '.js-popup_setting',
  popupSettingConf: {
    width: 850,
    height: 500,
    autoclose: true,
    autoreload: true,
  },

  moduleManagementMark: '.js-module_management',
  moduleManagementReloadMsg: ACMS.i18n('module_management.reload_msg'),
  moduleUnitDirectAddMsg: ACMS.i18n('module_management.unit_direct_add_msg'),

  dialogBtnMark: '.js-dialog-btn',
  dialogTitleMark: '.js-dialog-title',
  dialogBodyMark: '.js-dialog-body',

  //------------------------
  // 会員限定記事の表示・非表示
  membersOnlyEntryMark: '.js-members-only-entry', // 非公開時の案内表示にこのクラスを付与する

  //----------------------------
  // ログイン状態による表示・非表示
  loginHiddenMark: '.js-login-hidden', // ログイン状態の時、非表示にする
  loginShowMark: '.js-login-show', // ログイン状態の時、表示する
  logoutHiddenMark: '.js-logout-hidden', // ログアウト状態の時、非表示にする
  logoutShowMark: '.js-logout-show', // ログアウト状態の時、表示する

  //-------------
  // scroll hint
  scrollHintMark: '.js-scroll-hint',
  scrollHintConfig: {
    suggestClass: 'is-active',
    scrollableClass: 'is-scrollable',
    scrollableRightClass: 'is-right-scrollable',
    scrollableLeftClass: 'is-left-scrollable',
    scrollHintClass: 'scroll-hint',
    scrollHintIconClass: 'scroll-hint-icon',
    scrollHintIconAppendClass: 'scroll-hint-icon-white',
    scrollHintIconWrapClass: 'scroll-hint-icon-wrap',
    scrollHintText: 'scroll-hint-text',
    remainingTime: -1,
    scrollHintBorderWidth: 10,
    enableOverflowScrolling: true,
    suggestiveShadow: false,
    applyToParents: false,
    offset: 0,
    i18n: {
      scrollable: ACMS.i18n('scrollhint.scrollable'),
    },
  },

  //-----------------------
  // アニメーション クラス付与
  scrollAnimationMark: '.acms-entry img',
  scrollAnimationConfig: {
    delay: 0, // 遅延時間
    animationClass: '', // アニメーションクラス名
    inViewClass: 'in-view', // 発火時に付与するクラス名
    repeat: false, // trueにすると、要素が画面内に入るたびにアニメーションが再生されます
  },

  //----------------
  // lazy-contents
  lazyContentsMark: '.js-lazy-contents',

  //-----------
  // lazy load
  lazyLoadMark: '.js-lazy-load',
  lazyLoadConfig: {
    rootMargin: '10px 0px', // syntax similar to that of CSS Margin
    threshold: 0.1, // ratio of element convergence
    loaded: function (el) {
      el.addEventListener('load', function () {
        if (el.tagName === 'IMG') {
          var img = new Image();
          img.onload = function () {
            el.classList.add('loaded');
          };
          img.src = el.getAttribute('src');
        } else {
          el.classList.add('loaded');
        }
      });
      setTimeout(function () {
        el.classList.add('loading');
      }, 100);
      ACMS.dispatchEvent('acmsLazyLoaded', el);
    },
  },

  //----------------
  // js-pdf-preview
  pdfPreviewConfig: {
    mark: '.js-pdf-viewer', // PDFプレビューの親要素につけるクラス名
    previewMark: '.js-preview', // 実際にプレビュー画像を表示する img 要素のクラス名
    prevBtnMark: '.js-prev', // 次ページのボタンにつけるクラス名
    nextBtnMark: '.js-next', // 前ページのボタンにつけるクラス名
    pdfAttr: 'data-pdf', // 対象のPDFのパスのdata属性名
    widthAttr: 'data-width', // 幅指定のdata属性名
    pageAttr: 'data-page', // 表示するページ数のdata属性名
    lazyAttr: 'data-lazy', // lazy load するかどうか（1 or 0）のdata属性名
    showBtnClass: 'acms-admin-block', // PDFのページ送りボタンがある場合につくクラス名
  },

  //---------
  // a-table
  aTableMark: '[class^=js-editable-table]',
  aTableDestMark: '.js-editable-table-dest',
  aTableFieldMark: '.js-editable-table-field',
  aTableConf: {
    align: {
      default: 'acms-cell-text-left',
      left: 'acms-cell-text-left',
      center: 'acms-cell-text-center',
      right: 'acms-cell-text-right',
    },
    btn: {
      group: 'acms-admin-btn-group acms-admin-btn-group-inline',
      item: 'acms-admin-btn',
      itemActive: 'acms-admin-btn acms-admin-btn-active',
    },
    icon: {
      alignLeft: 'acms-admin-icon-text-left',
      alignCenter: 'acms-admin-icon-text-center',
      alignRight: 'acms-admin-icon-text-right',
      undo: 'acms-admin-icon-undo',
      merge: 'acms-admin-icon-merge',
      split: 'acms-admin-icon-split',
      source: 'acms-admin-icon-source',
      td: '',
      th: '',
    },
  },
  aTableSelector: [
    { label: ACMS.i18n('a_table.not_newline'), value: 'acms-cell-text-nowrap acms-admin-cell-text-nowrap' },
    { label: ACMS.i18n('a_table.bold'), value: 'acms-cell-text-bold acms-admin-cell-text-bold' },
    { label: ACMS.i18n('a_table.top_alignment'), value: 'acms-cell-text-top acms-admin-cell-text-top' },
    { label: ACMS.i18n('a_table.center_alignment'), value: 'acms-cell-text-middle acms-admin-cell-text-middle' },
    { label: ACMS.i18n('a_table.bottom_alignment'), value: 'acms-cell-text-bottom acms-admin-cell-text-bottom' },
  ],
  // テーブル自体にクラスを付与できます
  aTableOption: [
    { label: ACMS.i18n('a_table.scrollhint_table'), value: 'js-table-unit-scroll-hint' },
    { label: ACMS.i18n('a_table.scrollable_table'), value: 'acms-table-scrollable' },
  ],
  aTableMessage: {
    mergeCells: ACMS.i18n('a_table.merge_cell'),
    splitCell: ACMS.i18n('a_table.split_cell'),
    changeToTh: ACMS.i18n('a_table.change_to_th'),
    changeToTd: ACMS.i18n('a_table.change_to_td'),
    alignLeft: ACMS.i18n('a_table.align_left'),
    alignCenter: ACMS.i18n('a_table.align_center'),
    alignRight: ACMS.i18n('a_table.align_right'),
    addColumnLeft: ACMS.i18n('a_table.add_column_left'),
    addColumnRight: ACMS.i18n('a_table.add_column_right'),
    removeColumn: ACMS.i18n('a_table.remove_column'),
    addRowTop: ACMS.i18n('a_table.add_row_top'),
    addRowBottom: ACMS.i18n('a_table.add_row_bottom'),
    removeRow: ACMS.i18n('a_table.remove_row'),
    source: ACMS.i18n('a_table.source'),
    mergeCellError1: ACMS.i18n('a_table.merge_cell_error1'),
    mergeCellConfirm1: ACMS.i18n('a_table.merge_cell_confirm1'),
    pasteError1: ACMS.i18n('a_table.paste_error1'),
    splitError1: ACMS.i18n('a_table.split_error1'),
    splitError2: ACMS.i18n('a_table.split_error2'),
    splitError3: ACMS.i18n('a_table.split_error3'),
  },
  //---------
  // navigation module language
  navigationEditMark: '#js-navigation-edit',
  navigationMessage: {
    detail: ACMS.i18n('navigation.detail'),
    add: ACMS.i18n('navigation.add'),
    open: ACMS.i18n('navigation.open'),
    close: ACMS.i18n('navigation.close'),
    attr: ACMS.i18n('navigation.attr'),
    child_attr: ACMS.i18n('navigation.child_attr'),
    remove: ACMS.i18n('navigation.remove'),
    label: ACMS.i18n('navigation.label'),
    onRemove: ACMS.i18n('navigation.on_remove'),
    onFirstUpdate: ACMS.i18n('navigation.on_first_update'),
  },

  bannerEditMark: '#js-banner-edit',

  //---------
  // admin-menu
  adminMenuEditMark: '#js-admin-menu-edit',

  //----------
  // LiteEditor
  LiteEditorUseEmojiPicker: true, //スマホの場合は強制的にfalseになります。
  LiteEditorEmojiPickerLabel: '<i class="lite-editor-emoji-font lite-editor-emoji-font-smile" aria-hidden="true"></i>',
  LiteEditorMark: '.js-lite-editor-field',
  LiteEditorFieldConf: {}, // カスタムフィールドで利用するLiteEditorの設定
  LiteEditorSourceModeTags: /^(ul|ol|dl|pre|blockquote|none|markdown|wysiwyg|table|template|div)/, //テキストユニット内でソース入力モードになります。
  LiteEditorConf: {
    minHeight: 50,
    maxHeight: 650,
    nl2br: false,
    classNames: {
      LiteEditor: 'entryFormLiteEditor',
      LiteEditorSource: 'entryFormTextarea',
      LiteEditorBtnGroup: 'acms-admin-btn-group acms-admin-btn-group-inline',
      LiteEditorBtn: 'acms-admin-btn',
      LiteEditorBtnActive: 'acms-admin-btn acms-admin-btn-active',
      LiteEditorBtnClose: '',
      LiteEditorTooltipInput: 'acms-admin-form-width-full',
    },
    btnPosition: 'bottom',
    escapeNotRegisteredTags: false,
    relAttrForTargetBlank: 'noopener noreferrer',
    message: {
      addLinkTitle: ACMS.i18n('lite_editor.add_link_title'),
      updateLinkTitle: ACMS.i18n('lite_editor.update_link_title'),
      addLink: ACMS.i18n('lite_editor.add_link'),
      updateLink: ACMS.i18n('lite_editor.update_link'),
      removeLink: ACMS.i18n('lite_editor.remove_link'),
      linkUrl: ACMS.i18n('lite_editor.link_url'),
      linkLabel: ACMS.i18n('lite_editor.link_label'),
      targetBlank: ACMS.i18n('lite_editor.target'),
      targetBlankLabel: ACMS.i18n('lite_editor.target_label'),
    },
    btnOptions: [
      {
        label: ACMS.i18n('lite_editor.link'),
        tag: 'a',
        className: '',
        sampleText: ACMS.i18n('lite_editor.link_sample_txt'),
      },
      { label: ACMS.i18n('lite_editor.em'), tag: 'em', className: '', sampleText: ' ' },
      { label: ACMS.i18n('lite_editor.strong'), tag: 'strong', className: '', sampleText: ' ' },
      // { label: '下線', tag: 'u', className: '', sampleText: ' '},
    ],
  },

  //----------
  // PaperEditor
  SmartBlockHeadingConf: function (Extensions, target, icons) {
    var headingStart = target.getAttribute('data-heading-start') || 2;
    var headingEnd = target.getAttribute('data-heading-end') || 3;
    var headings = [];
    for (var num = headingStart; num <= headingEnd; num++) {
      var name = 'H' + num;
      headings.push(
        new Extensions['Heading' + num]({
          tagName: name,
          customName: name,
          icon: icons['Heading' + num + 'Icon'],
        })
      );
    }
    return headings;
  },
  SmartBlockUnitMinHeight: 300,
  SmartBlockUnitMaxHeight: 650,
  SmartBlockTitlePlaceholder: ACMS.i18n('rich_editor.titlePlaceholder'),
  SmartBlockConf: function (Extensions, target, icons) {
    return [].concat([new Extensions.Paragraph()], ACMS.Config.SmartBlockHeadingConf(Extensions, target, icons), [
      new Extensions.ListItem(),
      new Extensions.BulletList(),
      new Extensions.OrderedList(),
      new Extensions.Blockquote(),
      new Extensions.Media({
        className: 'column-media-center',
        imgClassName: 'columnImageCenter',
        imgFullClassName: 'columnImage',
        captionClassName: 'caption',
      }),
      new Extensions.Emphasis({
        schema: {
          group: 'mark',
          parseDOM: [{ tag: 'strong' }],
          toDOM: function () {
            return ['strong', 0];
          },
        },
      }),
      new Extensions.Underline(),
      new Extensions.Strike(),
      new Extensions.Link(),
      new Extensions.MoveDown(),
      new Extensions.MoveUp(),
      new Extensions.Trash(),
      new Extensions.DefaultKeys(),
      new Extensions.DefaultPlugins({
        placeholder: ACMS.i18n('rich_editor.placeholder'),
      }),
    ]);
  },
  SmartBlockReplace: function (Extensions) {
    // eg.
    // return [
    //  new Extensions.Paragraph({
    //     className: "hoge"
    //   })
    // ];
    return [];
  },
  SmartBlockRemoves: [], // eg.["Underline", "Link"],
  SmartBlockAdds: function (Extensions) {
    // eg.
    // return [
    //   new Extensions.Table(), // テーブルブロックを表示
    //   new Extensions.Code(), // コードブロックを表示
    // ]
    return [];
  },
  SmartBlockMark: '.js-smartblock,.js-paper-editor',
  SmartBlockTitleMark: '.js-smartblock-title,.js-paper-editor-title',
  SmartBlockBodyMark: '.js-smartblock-body,.js-paper-editor-body',
  SmartBlockEditMark: '.js-smartblock-edit,.js-paper-editor-edit',

  //--------------
  // Block Editor
  blockEditorMark: '.js-block-editor',
  blockEditorConfig: {
    setMainImageMark: '.js-block-editor-set-main-image',
    tableScrollableWrapperClass: 'acms-table-scrollable',
    tableScrollableClass: 'js-table-unit-scroll-hint',
    /**
     * BlockEditorコンポーネントに渡されるprops
     */
    editorProps: {
      editorProps: {
        attributes: {
          /**
           * @description この値を変更するときは、system/src/scss/global/_variables.scss の$entry-classも変更すること
           */
          class: 'acms-entry',
        },
      },
    },
  },

  //--------------
  // Unit Editor
  unitEditorMark: '#js-unit-editor',
  unitInplaceEditorMark: '#js-unit-inplace-editor',

  //--------------
  // Unit Form Editor
  unitFormEditorMark: '#js-unit-form-editor',
  unitFormEditorItemMark: '.acms-admin-unit',
  unitFormEditorItemHeadMark: '.acms-admin-unit-toolbar',
  unitFormEditorItemBodyMark: '.acms-admin-unit-content',

  //---------
  mediaAdminMark: '#js-media-edit',
  mediaFieldMark: '.js-media-field',
  mediaShowAltAndCaptionOnModal: true,
  mediaCropSizes: [
    [16, 9],
    [4, 3],
    [3, 4],
    [1, 1],
  ],

  //--------
  // Entry Admin

  /**
   * @typedef {import("./src/features/entry/types").EntryType } EntryType
   * @typedef {import("./src/features/entry/hooks/use-entry-admin-actions").GetActionsContext} EntryAdminGetActionsContext
   * @typedef {import("./src/features/entry/hooks/use-entry-admin-bulk-actions").GetBulkActionsContext} EntryAdminGetBulkActionsContext
   * @typedef {import("./src/features/entry/hooks/use-entry-admin-menus").GetMenusContext} EntryAdminGetMenusContext
   */

  /**
   * エントリー管理画面の設定オブジェクト
   * @typedef {Object} EntryAdminConfig
   * @property {number} linkMaxLength - リンク先URLの最大表示文字数
   * @property {function(DataviewGetValues<EntryType>): DataviewGetValues<EntryType>} getValues - カスタムカラムの値を取得する関数をカスタマイズする関数
   * @property {DataviewGetValuesOptions<EntryType>} getValuesOptions - カスタムカラムの値を取得する関数のオプション
   * @property {function(DataviewAction<EntryType>[], EntryAdminGetActionsContext): DataviewAction<EntryType>[]} getActions - 各行のアクションをカスタマイズする関数
   * @property {function(DataviewBulkAction<EntryType>[], EntryAdminGetBulkActionsContext): DataviewBulkAction<EntryType>[]} getBulkActions - 一括操作のアクションをカスタマイズする関数
   * @property {function(DataviewMenu<EntryType>[], EntryAdminGetMenusContext): DataviewMenu<EntryType>[]} getMenus - メニューをカスタマイズする関数
   * @property {function(DataviewColumn<EntryType>[]): DataviewColumn<EntryType>[]} getColumns - 表示するカラムをカスタマイズする関数
   */

  /**
   * エントリー管理画面のHTML要素と紐づけるCSSセレクター
   * @type {string}
   */
  entryAdminMark: '#js-entry-admin',

  /**
   * エントリー一括変更選択画面のHTML要素と紐づけるCSSセレクター
   * @type {string}
   */
  entryBulkChangeSelectMark: '#js-entry-bulk-change-select',

  /**
   * @type {EntryAdminConfig}
   */
  entryAdminConfig: {
    linkMaxLength: 25,
    // getValues: function (getValues) {
    //   return {
    //     ...getValues,
    //     text: function (info) {
    //       // override text getValues
    //     }
    //   }
    // },
    getValuesOptions: {
      // formatText: (value) => {
      //   // something to text format
      // },
      // formatTextarea: (value) => {
      //   // something to textarea format
      // },
      // formatNumber: (value) => {
      //   // something to number format
      // },
      // formatDate: (value) => {
      //   // something to date format
      // },
      // formatFileName: (value) => {
      //   // something to file name format
      // },
    },
    getActions: function (actions) {
      return actions;
    },
    getBulkActions: function (bulkActions) {
      return bulkActions;
    },
    getMenus: function (menus) {
      return menus;
    },
    getColumns: function (columns) {
      return columns;
    },
  },

  //--------
  // Module Admin

  /**
   * @typedef { import("./src/features/module/types").ModuleType } ModuleType
   * @typedef {import("./src/features/module/hooks/use-module-admin-actions").GetActionsContext} ModuleAdminGetActionsContext
   * @typedef {import("./src/features/module/hooks/use-module-admin-bulk-actions").GetBulkActionsContext} ModuleAdminGetBulkActionsContext
   * @typedef {import("./src/features/module/hooks/use-module-admin-menus").GetMenusContext} ModuleAdminGetMenusContext
   */

  /**
   * モジュール管理画面のHTML要素と紐づけるCSSセレクター
   * @type {string}
   */
  moduleAdminMark: '#js-module-admin',
  /**
   * モジュール管理画面の設定オブジェクト
   * @typedef {Object} ModuleAdminConfig
   * @property {function(DataviewAction<ModuleType>[], ModuleAdminGetActionsContext): DataviewAction<ModuleType>[]} getActions - 各行のアクションをカスタマイズする関数
   * @property {function(DataviewBulkAction<ModuleType>[], ModuleAdminGetBulkActionsContext): DataviewBulkAction<ModuleType>[]} getBulkActions - 一括操作のアクションをカスタマイズする関数
   * @property {function(DataviewMenu<ModuleType>[], ModuleAdminGetMenusContext): DataviewMenu<ModuleType>[]} getMenus - メニューをカスタマイズする関数
   * @property {function(DataviewColumn<ModuleType>[]): DataviewColumn<ModuleType>[]} getColumns - 表示するカラムをカスタマイズする関数
   */

  /**
   * @type {ModuleAdminConfig}
   */
  moduleAdminConfig: {
    getActions: function (actions) {
      return actions;
    },
    getBulkActions: function (bulkActions) {
      return bulkActions;
    },
    getMenus: function (menus) {
      return menus;
    },
    getColumns: function (columns) {
      return columns;
    },
  },

  //--------
  // select2
  select2Mark: '.js-select2',
  select2Threshold: 10,
  select2Config: {}, // https://select2.org/configuration/options-api

  quickSearchFeature: true,
  quickSearchCommand: ['command + k', 'ctrl + k'],

  //-------------
  // autoHeightR
  autoHeightRMark: '.js-autoheight-r',
  autoHeightRConf: {
    style: 'height',
    element: '', // 高さのスタイルを適応するクラス（空の場合はautoHeightRMarkの要素に適応）
    offset: 0,
    parent: 'parent', // parent : autoHeightRMarkクラスの一個上の要素 or 指定した要素
    list: '', // 実際に並んでいる要素のクラスを指定（指定してない場合、autoHeightRMarkの一個上の要素）
  },
  autoHeightRArray: [
    //    {
    //        'mark'    : '',
    //        'config'  : {}
    //    }
  ],

  //--------------------
  // 日付選択カレンダー
  dpicMark: '.js-datepicker', // セレクタの指し示す要素をクリックで日付選択カレンダーを利用出来ます
  dpicConfig: {
    closeText: ACMS.i18n('datepic.close'),
    prevText: ACMS.i18n('datepic.prev'),
    nextText: ACMS.i18n('datepic.next'),
    currentText: ACMS.i18n('datepic.current'),
    monthNames: [
      ACMS.i18n('datepic.month.jan'),
      ACMS.i18n('datepic.month.feb'),
      ACMS.i18n('datepic.month.mar'),
      ACMS.i18n('datepic.month.apr'),
      ACMS.i18n('datepic.month.may'),
      ACMS.i18n('datepic.month.jun'),
      ACMS.i18n('datepic.month.jul'),
      ACMS.i18n('datepic.month.aug'),
      ACMS.i18n('datepic.month.sep'),
      ACMS.i18n('datepic.month.oct'),
      ACMS.i18n('datepic.month.nov'),
      ACMS.i18n('datepic.month.dec'),
    ],
    monthNamesShort: [
      ACMS.i18n('datepic.month.jan'),
      ACMS.i18n('datepic.month.feb'),
      ACMS.i18n('datepic.month.mar'),
      ACMS.i18n('datepic.month.apr'),
      ACMS.i18n('datepic.month.may'),
      ACMS.i18n('datepic.month.jun'),
      ACMS.i18n('datepic.month.jul'),
      ACMS.i18n('datepic.month.aug'),
      ACMS.i18n('datepic.month.sep'),
      ACMS.i18n('datepic.month.oct'),
      ACMS.i18n('datepic.month.nov'),
      ACMS.i18n('datepic.month.dec'),
    ],
    dayNames: [
      ACMS.i18n('datepic.week.sun'),
      ACMS.i18n('datepic.week.mon'),
      ACMS.i18n('datepic.week.tue'),
      ACMS.i18n('datepic.week.wed'),
      ACMS.i18n('datepic.week.thu'),
      ACMS.i18n('datepic.week.fri'),
      ACMS.i18n('datepic.week.sat'),
    ],
    dayNamesShort: [
      ACMS.i18n('datepic.week_short.sun'),
      ACMS.i18n('datepic.week_short.mon'),
      ACMS.i18n('datepic.week_short.tue'),
      ACMS.i18n('datepic.week_short.wed'),
      ACMS.i18n('datepic.week_short.thu'),
      ACMS.i18n('datepic.week_short.fri'),
      ACMS.i18n('datepic.week_short.sat'),
    ],
    dayNamesMin: [
      ACMS.i18n('datepic.week_min.sun'),
      ACMS.i18n('datepic.week_min.mon'),
      ACMS.i18n('datepic.week_min.tue'),
      ACMS.i18n('datepic.week_min.wed'),
      ACMS.i18n('datepic.week_min.thu'),
      ACMS.i18n('datepic.week_min.fri'),
      ACMS.i18n('datepic.week_min.sat'),
    ],
    dateFormat: 'yy-mm-dd',
    firstDay: 0,
    isRTL: false,
    constrainInput: false,
  },
  dpicArray: [
    //    {
    //        'mark'    : '',
    //        'config'  : {}
    //    }
  ],
  flatDatePicker: '.js-datepicker2',
  flatDatePickerConfig: {
    allowInput: true,
    dateFormat: 'Y-m-d',
  },
  flatTimePicker: '.js-timepicker',
  flatTimePickerConfig: {
    allowInput: true,
    enableTime: true,
    noCalendar: true,
    dateFormat: 'H:i:S',
    time_24hr: true,
  },
  //-----------
  // accordion
  accordionMark: '.js-accordion',
  accordionConfig: {
    active: null,
    animated: 'slide', // ( 'slide' | 'fade' | '' )
    heightStyle: 'content',
    collapsible: true,
  },
  accordionArray: [
    //    {
    //        'mark'    : '',
    //        'config'  : {}
    //    }
  ],

  //------
  // tabs
  tabsMark: '.js-tabs',
  tabsConfig: {
    collapsible: false,
    cookie: null,
    fx: {
      //opacity : 'toggle', // クロスフェード
      //height  : 'toggle', // 縦スライド
      //duration: 'fast' // ( 'fast' | 'normal' | 'slow' | '' )
    },
  },
  tabsArray: [
    //    {
    //        'mark'    : '',
    //        'config'  : {}
    //    }
  ],

  //----------
  // acms tabs
  acmsTabsMark: '.js-acms_tabs',
  acmsTabsConfig: {
    tabClass: 'js-acms_tab',
    activeClass: 'js-acms_tab-active',
    readyMark: '.js-ready-acms_tabs', // e.g. window.document.location.hash
  },
  acmsTabsArray: [
    //    {
    //        'mark'    : '',
    //        'config'  : {}
    //    }
  ],

  //------------------
  // acms alert close
  acmsAlertCloseMark: '.js-acms-alert-close',
  acmsAlertCloseConfig: {
    target: '.acms-admin-alert, .acms-alert',
  },
  acmsAlertCloseArray: [
    //    {
    //        'mark'    : '',
    //        'config'  : {}
    //    }
  ],

  //-------
  // fader
  faderMark: '.js-fader',
  faderConfig: {
    initial: 'hide', // ( 'hide' | 'show' )
    effect: 'fade', // ( 'fade' | 'slide' | '' )
    speed: 'fast', // ( 'fast' | 'slow' )
    activeClass: 'js-fader-active',
    readyMark: '.js-ready-fader', // e.g. window.document.location.hash
  },
  faderArray: [
    //    {
    //        'mark'    : '',
    //        'config'  : {}
    //    }
  ],

  //-------
  // admin fader
  adminFaderMark: '.js-admin-fader',
  adminFaderConfig: {
    initial: 'hide', // ( 'hide' | 'show' )
    effect: 'fade', // ( 'fade' | 'slide' | '' )
    speed: 'fast', // ( 'fast' | 'slow' )
    activeClass: 'js-admin-fader-active',
  },

  externalFormSubmitButton: '.js-external-form-btn',

  //-----------------------
  // タイトルの編集
  editInplateTitleMark: '.entryTitle,.entry-title',

  //-----------------------------------
  // 位置情報編集時のデフォルト
  adminLocationDefaultLat: '35.185574',
  adminLocationDefaultLng: '136.899066',
  adminLocationDefaultZoom: '10',

  //----------------
  // OpenStreetMap
  openStreetMapMark: '.js-open-street-map',
  openStreetMapTileLayer: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',

  //----------------------------
  // 静的グーグルマップの動的化
  s2dMark: '[class^="column-map-"]>img:not(.js-s2d-ready)',
  s2dReadyMark: 'img.js-s2d-ready',
  s2dRegion: 'JP',
  s2dStyle: [
    // {
    //     stylers: [
    //         { hue: "#00ffe6" },
    //         { saturation: -20 }
    //     ]
    // },{
    //     featureType: "road",
    //     elementType: "geometry",
    //     stylers: [
    //         { lightness: 100 },
    //         { visibility: "simplified" }
    //     ]
    // },{
    //     featureType: "road",
    //     elementType: "labels",
    //     stylers: [
    //         { visibility: "off" }
    //     ]
    // }
  ],
  streetViewMark: '.js-street-view',

  //--------------
  // 離脱時アラート
  unloadAlertMark: '.js-unload_alert',

  //--------------------
  // スタイルの切り替え
  styleSwitchMark: 'a.styleswitch',
  styleSwitchStyleMark: 'link.styleswitch',
  // リンク要素のタイトル属性を利用してスタイルシートを切り替えることが出来ます。
  // 例)
  // <link rel="stylesheet" type="text/css" href="xxx.css" title="a" class="styleswitch" />
  // <link rel="stylesheet" type="text/css" href="yyy.css" title="b" class="styleswitch" />
  //
  // <a href="#" class="styleswitch" rel="a">スタイルを[a]に切り替える</a>
  // <a href="#" class="styleswitch" rel="b">スタイルを[b]に切り替える</a>

  //------------
  // スクロール
  scrollToMark: 'a.scrollTo', // セレクタのアンカーをクリックするとhref属性のフラグに指定された要素へスクロールします。
  scrollToI: 40, // 間隔(i)msec
  scrollToV: 0.5, // 0 < 移動量(v) < 1
  // 例)
  // <a name="a"></a>
  // <div id="b"></div>
  //
  // <a href="#a" class="scrollTo" />
  // <a href="#b" class="scrollTo" />
  // <a href="#" class="scrollTo" /> ※フラグ名が指定されない（特定出来ない）場合はページの最上部へスクロールします。
  //--------------------
  // オフキャンバス
  offcanvas: {
    fixedHeaderMark: '.js-offcanvas-header',
    openBtnMark: '.js-offcanvas-btn', //offcanvasを開くボタンのクラス
    openBtnRMark: '.js-offcanvas-btn-r', //offcanvasを右方向に開くボタンのクラス
    openBtnLMark: '.js-offcanvas-btn-l', //offcanvasを左方向に開くボタンのクラス
    closeBtnMark: '.js-offcanvas-close', //offcanvasを閉じるボタンのクラス
    offcanvasMark: '.js-offcanvas', //offcanvasが適応されるエリアのクラス
    breakpoint: 767, //max-widthで指定,'all'を指定すると全画面,
    throttleTime: 100,
  },
  //--------------------
  // スクロール時の追随
  //--------------------
  prettyScrollMark: '.js-pretty-scroll',
  prettyScrollConfig: {
    container: '.js-pretty-scroll-container',
    offsetTop: 20,
    offsetBottom: 20,
    breakpoint: 767,
    condition: function () {
      return true;
    },
  },
  //--------------------
  // プレビュー機能の設定
  //--------------------
  previewDeviceHistoryKey: {
    preview: 'acms-preview-history-device', // プレビュー時
    approval: 'acms-approval-preview-history-device', // 承認プレビュー時
    timemachine: 'acms-timemachine-preview-history-device', // タイムマシン時
  },
  previewDevices: [
    {
      name: 'iPhone 16',
      ua: 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1',
      width: 393,
      height: 852,
      resizable: false,
      hasFrame: true,
    },
    {
      name: 'iPhone 16 Pro Max',
      ua: 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1',
      width: 440,
      height: 956,
      resizable: false,
      hasFrame: true,
    },
    {
      name: 'iPhone 13 mini',
      ua: 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1',
      width: 375,
      height: 812,
      resizable: false,
      hasFrame: true,
    },
    {
      name: 'iPhone SE',
      ua: 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1',
      width: 375,
      height: 667,
      resizable: false,
      hasFrame: true,
    },
    {
      name: 'Android FHD+',
      ua: 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Mobile Safari/537.36',
      width: 412,
      height: 917,
      resizable: false,
      hasFrame: true,
    },
    {
      name: 'iPad',
      ua: 'Mozilla/5.0 (iPad; CPU OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1',
      width: 820,
      height: 1180,
      resizable: false,
      hasFrame: true,
    },
    {
      name: 'iPad mini',
      ua: 'Mozilla/5.0 (iPad; CPU OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1',
      width: 744,
      height: 1133,
      resizable: false,
      hasFrame: true,
    },
    {
      name: 'iPad Pro 13',
      ua: 'Mozilla/5.0 (iPad; CPU OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1',
      width: 1032,
      height: 1376,
      resizable: false,
      hasFrame: true,
    },
    {
      name: 'PC',
      ua: 'none',
      width: 1400,
      height: 900,
      resizable: true,
      hasFrame: false,
    },
  ],
  // テキストの自動選択
  clickSelectionInputTextMark: ':text.url, textarea.js-click-selection, :text.js-click-selection', // セレクタの示す要素をクリックするとテキストが選択状態になります。

  //--------------------------
  // イメージのロールオーバー
  rolloverImgMark: 'img.js-rollover, img.imgover', // セレクタの示す要素をホバーするとイメージがロールオーバーします。
  rolloverImgSuffix: '_o', // ロールオーバー時のファイル名に付けられる接尾辞です。例) banner.jpg -> banner_o.jpg

  //--------------------
  // ユニットグループの整列
  unitGroupAlign: true,
  unitGroupAlignMark: '.js-unit_group-align',
  unitGroupAlignInterval: 400,

  //------------------------
  // 検索ワードのハイライト
  searchKeywordHighlightMark: '.entry, .entryColumn, .entryTitle', // セレクタ要素内に検索ワードが含まれる時、該当箇所がハイライトされます。
  searchKeywordMatchTag: 'mark',
  searchKeywordMatchClass: 'highlight',
  // 例)
  // 検索対象:<div.entry><p>いろはにほへと</p></div>
  // 検索語:「いろ　ほへ」
  // 結果:<div.entry><p><span class="highlight1">いろ</span>はに<span class="highlight2">ほへ</span>と</p></div>

  //---------
  // トグル
  toggleHeadClassSuffix: 'toggle-head', // 切り替える際にクリックする要素
  toggleBodyClassSuffix: 'toggle-body', // 切り替え表示対象の要素
  // 要素の表示非表示をアニメーションで切り替えます ( 初期状態は非表示となります )
  // 例）
  // <h4 class="continue-toggle-head">続きを読む</h4>
  // <p class="continue-toggle-body">本文</p>

  //----------
  // フェード
  fadeHeadClassSuffix: 'fade-head', // 切り替える際にクリックする要素
  fadeBodyClassSuffix: 'fade-body', // 切り替え表示対象の要素
  // 要素の表示非表示を切り替えますフェードのアニメーションで切り替えます ( 初期状態は非表示となります )
  // 例）
  // <h4 class="continue-fade-head">続きを読む</h4>
  // <p class="continue-fade-body">本文</p>

  //-----------
  // validator
  validatorFormMark: 'form.js-validator',
  validatorOptions: {
    resultClassName: 'validator-result-',
    okClassName: 'valid',
    ngClassName: 'invalid',
    shouldScrollOnSubmitFailed: true,
    shouldFocusOnSubmitFailed: true,
    onInvalid: (results, element) => {
      ACMS.dispatchEvent('acmsFormInvalid', element, { results });
    },
    onValid: (results, element) => {
      ACMS.dispatchEvent('acmsFormValid', element, { results });
    },
    onValidated: (results, element) => {
      ACMS.dispatchEvent('acmsFormValidated', element, { results });
    },
    onSubmitFailed: (results, form) => {
      ACMS.Dispatch.Utility.unloadAlert(window.document, ACMS.Config.unloadAlertMark, true);
      ACMS.dispatchEvent('acmsValidateFailed', form, { results });
    },
    shouldValidate: 'onBlur',
    shouldRevalidate: 'onChange',
    shouldValidateOnSubmit: true,
    formnovalidateAttr: 'data-acms-formnovalidate',
    customRules: {},
  },

  //--------------
  // resize image
  resizeImage: 'on',
  resizeImageTargetMark: '.js-img_resize',
  resizeImageTargetMarkCF: '.js-img_resize_cf',
  resizeImageInputMark: '.js-img_resize_input',
  resizeImagePreviewMark: '.js-img_resize_preview',

  //-------------------
  // password strength
  passwordStrengthMark: '.js-password_strength',
  passwordStrengthInputMark: '.js-input',
  passwordStrengthMeterMark: '.js-meter',
  passwordStrengthLabelMark: '.js-label',
  passwordStrengthMessage: {
    0: ACMS.i18n('password_strength_meter.worst'),
    1: ACMS.i18n('password_strength_meter.bad'),
    2: ACMS.i18n('password_strength_meter.weak'),
    3: ACMS.i18n('password_strength_meter.good'),
    4: ACMS.i18n('password_strength_meter.strong'),
  },

  //--------------
  // post include
  postIncludeOnsubmitMark: '.js-post_include',
  postIncludeOnreadyMark: '.js-post_include-ready',
  postIncludeOnBottomMark: '.js-post_include-bottom',
  postIncludeOnIntervalMark: '.js-post_include-interval',
  postIncludeMethod: 'replace', // ( 'swap' | 'replace' )
  postIncludeEffect: 'slide', // ( 'slide' | 'fade' | '' )
  postIncludeEffectSpeed: 'slow', // ( 'slow' | 'fast' )
  postIncludeOffset: 60,
  postIncludeReadyDelay: 0,
  postIncludeIntervalTime: 20000,
  postIncludeArray: [
    {
      //        'mark'           : '.js-post_include-original',
      //        'type'           : 'submit',
      //        'method'         : 'swap',
      //        'effect'         : 'slide',
      //        'effectSpeed'    : 'slow'
    },
  ],

  //---------------------
  // link match location
  linkMatchLocationMark: '.js-link_match_location',
  linkMatchLocationFullMark: '.js-link_match_location-full',
  linkMatchLocationBlogMark: '.js-link_match_location-blog',
  linkMatchLocationCategoryMark: '.js-link_match_location-category',
  linkMatchLocationEntryMark: '.js-link_match_location-entry',
  linkMatchLocationContainMark: '.js-link_match_location-contain',
  linkMatchLocationClass: 'stay',
  linkMatchLocationFullClass: 'stay',
  linkMatchLocationBlogClass: 'stay',
  linkMatchLocationCategoryClass: 'stay',
  linkMatchLocationEntryClass: 'stay',
  linkMatchLocationContainClass: 'stay',
  linkMatchLocationArray: [
    {
      //        'mark'  : '.js-link_match_location-original',
      //        'type'  : 'part', //( 'part' | 'full' | 'blog' | 'category' | 'entry' )
      //        'class' : 'current'
    },
  ],

  //--------------------
  // link outside blank
  linkOutsideBlankMark: 'a:not([target]):not([href^="javascript"]):not([href^="tel"])', // 外部リンクを新しいウィンドウで開きます。このセレクタで指定される要素に対してのみ処理対象となります
  linkOutsideAppendAttr: 'noopener noreferrer', //外部リンクに付与する属性

  //--------------------
  // adminTableSortable
  adminTableSortableMark: '.js-admin_table-sortable',

  //--------------------
  // fieldgroupSortable
  fieldgroupSortableMark: '.js-fieldgroup-sortable',
  fieldgroupSortableItemMark: '.sortable-item', // fieldgroupSortableMarkの指し示す要素の子要素である必要があります。
  fieldgroupSortableItemHandleMark: '.item-handle', // fieldgroupSortableItemMarkの指し示す要素の子要素である必要があります。
  fieldgroupSortableItemDeleteMark: '.item-delete', // fieldgroupSortableItemMarkの指し示す要素の子要素である必要があります。
  fieldgroupSortableItemTemplateMark: '.item-template', // fieldgroupSortableMarkの指し示す要素の子要素である必要があります。
  fieldgroupSortableItemInsertMark: '.item-insert', // fieldgroupSortableMarkの指し示す要素の子要素である必要があります。
  fieldgroupSortableItemMaxMark: '.item-max', // fieldgroupSortableMarkの指し示す要素の子要素である必要があります。
  fieldgroupSortableItemDeleteMessage: ACMS.i18n('field_group_sortable.delete_msg'), // 空文字 ('') にした場合は確認せずに削除します。
  fieldgroupSortableItemOverflowMessage1: ACMS.i18n('field_group_sortable.overflow_msg1'), // 最大登録数を超えた時のメッセージの前半。（前半と後半の間に最大数が入ります）
  fieldgroupSortableItemOverflowMessage2: ACMS.i18n('field_group_sortable.overflow_msg2'), // 最大登録数を超えた時のメッセージの後半。（前半と後半の間に最大数が入ります）

  //--------------
  // web storage
  webStorage: 'on',
  webStorageType: 'local', // local or session
  webStorageCapacity: 'limitless', // one or limitless
  webStorageInterval: 2000,

  //-------
  // ready
  readyFocusMark: ':input.js-ready-focus',

  //-------
  // hover
  hoverMark: '.js-hover',
  hoverClass: 'hover',

  //-------
  // zebra
  zebraMark: '.js-zebra',
  zebraOddClass: 'odd',
  zebraEvenClass: 'even',

  //--------------------
  // incremental search
  incrementalSearchMark: '.js-incremental-search',
  incrementalSearchBoxMark: '.js-search-box',
  incrementalSearchElementMark: '.js-search-element',

  //----------------
  // comment cookie
  commentCookieMark: 'form#comment-form.apply',
  commentCookieUserMark: 'form#comment-form.apply, form#comment-form.reapply',

  //-----------
  // input count up
  countupMark: '.js-countup',
  countupMarkOver: 'acms-admin-text-danger',
});

//--------------
// Config.Admin
ACMS.Config.Admin = {
  //--------------
  // arg guidance
  argGuidance: {
    Entry_Body: ['bid', 'uid', 'cid', 'eid', 'keyword', 'tag', 'field_', 'start', 'end', 'page', 'order'],
    Entry_List: ['bid', 'uid', 'cid', 'eid', 'keyword', 'tag', 'field_', 'start', 'end', 'page', 'order'],
    Entry_Photo: ['bid', 'uid', 'cid', 'eid', 'keyword', 'tag', 'field_', 'start', 'end', 'page', 'order'],
    Entry_Headline: ['bid', 'uid', 'cid', 'eid', 'keyword', 'tag', 'field_', 'start', 'end', 'page', 'order'],
    Entry_Summary: ['bid', 'uid', 'cid', 'eid', 'keyword', 'tag', 'field_', 'start', 'end', 'page', 'order'],
    Entry_ArchiveList: ['bid', 'cid', 'keyword', 'tag', 'field_'],
    Entry_TagRelational: ['bid', 'cid', 'eid', 'keyword', 'field_', 'start', 'end'],
    Entry_Continue: ['eid'],
    Entry_Field: ['eid'],
    Entry_Calendar: ['bid', 'cid', 'start'],

    Entry_GeoList: ['bid', 'uid', 'cid', 'eid', 'keyword', 'tag', 'field_', 'start', 'end', 'page'],

    Admin_Entry_Autocomplete: ['bid', 'uid', 'cid', 'keyword', 'tag', 'field_', 'start', 'end'],

    Unit_List: ['bid', 'uid', 'cid', 'eid', 'keyword', 'tag', 'field_', 'start', 'end', 'page', 'order'],

    Category_List: ['bid', 'cid', 'keyword', 'field_', 'start', 'end'],
    Category_EntryList: ['bid', 'uid', 'cid', 'keyword', 'tag', 'field_', 'start', 'end'],
    Category_GeoList: ['bid', 'cid', 'keyword', 'field_'],
    Category_EntrySummary: ['bid', 'uid', 'cid', 'keyword', 'tag', 'field_', 'start', 'end'],
    Category_Field: ['cid'],

    User_Profile: ['bid', 'uid'],
    User_Field: ['uid'],
    User_Search: ['bid', 'uid', 'keyword', 'field_', 'page'],
    User_GeoList: ['bid', 'uid', 'keyword', 'field_', 'page'],

    Blog_Field: ['bid'],
    Blog_ChildList: ['bid', 'keyword', 'field_'],
    Blog_GeoList: ['bid', 'keyword', 'field_'],

    Tag_Cloud: ['bid', 'cid', 'eid', 'field_', 'start', 'end'],
    Tag_Filter: ['bid', 'cid', 'field_', 'tag'],

    Calendar_Month: ['bid', 'cid', 'start', 'end'],
    Calendar_Year: ['bid', 'cid', 'start'],

    Links: [],
    Banner: [],
    Media_Banner: [],
    Navigation: [],
    Topicpath: ['bid', 'cid', 'eid'],

    Comment_Body: ['eid'],
    Comment_List: ['bid'],

    Json_2Tpl: [],
    Feed_Rss2: ['bid', 'uid', 'cid', 'eid', 'keyword', 'tag', 'start', 'end'],
    Feed_ExList: [],
    Sitemap: [''],
    Ogp: [],

    Shop_Cart_List: [],
    Case_Time: [],

    Alias_List: ['bid'],

    Field_ValueList: ['bid', 'field_'],

    Form2_Unit: ['eid'],

    Plugin_Schedule: ['bid'],
    Schedule: ['bid'],

    V2_Entry_Summary: ['bid', 'uid', 'cid', 'eid', 'keyword', 'tag', 'field_', 'start', 'end', 'page', 'order'],
    V2_Entry_Body: ['bid', 'uid', 'cid', 'eid', 'keyword', 'tag', 'field_', 'start', 'end', 'page', 'order'],
    V2_Entry_ArchiveList: ['bid', 'cid', 'keyword', 'tag', 'field_'],
    V2_Entry_TagRelational: ['bid', 'cid', 'eid', 'keyword', 'field_', 'start', 'end'],
    V2_Entry_MoreContent: ['eid'],
    V2_Entry_UnitList: ['bid', 'uid', 'cid', 'eid', 'keyword', 'tag', 'field_', 'start', 'end', 'page', 'order'],
    V2_Entry_GeoList: ['bid', 'uid', 'cid', 'eid', 'keyword', 'tag', 'field_', 'start', 'end', 'page'],
    V2_Category_Tree: ['bid', 'cid', 'keyword', 'field_', 'start', 'end', 'order'],
    V2_Category_EntrySummary: ['bid', 'uid', 'cid', 'keyword', 'tag', 'field_', 'start', 'end'],
    V2_Category_GeoList: ['bid', 'cid', 'keyword', 'field_', 'page'],
    V2_Media_Banner: [],
    V2_Blog_Field: ['bid'],
    V2_Entry_Field: ['eid'],
    V2_Category_Field: ['cid'],
    V2_User_Field: ['uid'],
    V2_Module_Field: [],
    V2_Field_ValueList: ['bid', 'field_'],
    V2_Links: [],
    V2_Navigation: [],
    V2_Tag_Filter: ['bid', 'cid', 'field_', 'tag'],
    V2_Tag_Cloud: ['bid', 'cid', 'eid', 'field_', 'start', 'end'],
    V2_Topicpath: ['bid', 'cid', 'eid'],
    V2_User_Search: ['bid', 'uid', 'keyword', 'field_', 'page'],
    V2_User_GeoList: ['bid', 'uid', 'keyword', 'field_', 'page'],
    V2_Blog_ChildList: ['bid', 'keyword', 'field_'],
    V2_Blog_GeoList: ['bid', 'keyword', 'field_'],
    V2_Json2Tpl: [],
    V2_Ogp: [],
    V2_Sitemap: [],
    V2_Calendar: ['bid', 'cid', 'start'],
    V2_CalendarYear: ['bid', 'cid', 'start'],
    V2_Schedule: ['bid', 'start'],
    V2_GlobalVars: [],
  },

  //--------------
  // axis guidance
  axisGuidance: {
    Entry_Body: ['bid_axis', 'cid_axis'],
    Entry_List: ['bid_axis', 'cid_axis'],
    Entry_Photo: ['bid_axis', 'cid_axis'],
    Entry_Headline: ['bid_axis', 'cid_axis'],
    Entry_Summary: ['bid_axis', 'cid_axis'],
    Entry_ArchiveList: ['bid_axis', 'cid_axis'],
    Entry_TagRelational: ['bid_axis', 'cid_axis'],
    Entry_Continue: [],
    Entry_Field: [],
    Entry_Calendar: ['bid_axis', 'cid_axis'],

    Entry_GeoList: ['bid_axis', 'cid_axis'],

    Admin_Entry_Autocomplete: ['bid_axis', 'cid_axis'],

    Unit_List: ['bid_axis', 'cid_axis'],

    Category_List: ['cid_axis'],
    Category_EntryList: [],
    Category_EntrySummary: ['bid_axis', 'cid_axis'],
    Category_Field: [],
    Category_GeoList: [],

    User_Profile: [],
    User_Field: [],
    User_Search: ['bid_axis'],
    User_GeoList: [],

    Blog_Field: [],
    Blog_ChildList: [],
    Blog_GeoList: [],

    Tag_Cloud: ['bid_axis', 'cid_axis'],
    Tag_Filter: ['bid_axis', 'cid_axis'],

    Calendar_Month: ['bid_axis', 'cid_axis'],
    Calendar_Year: ['bid_axis', 'cid_axis'],

    Links: [],
    Banner: [],
    Media_Banner: [],
    Navigation: [],
    Topicpath: ['bid_axis', 'cid_axis'],

    Comment_Body: [],
    Comment_List: [],

    Json_2Tpl: [],
    Feed_Rss2: ['bid_axis', 'cid_axis'],
    Feed_ExList: [],
    Sitemap: ['bid_axis', 'cid_axis'],
    Ogp: [],

    Shop_Cart_List: [],
    Case_Time: [],

    Alias_List: [],

    Field_ValueList: ['bid_axis'],

    Form2_Unit: [],

    Plugin_Schedule: [],
    Schedule: [],

    V2_Entry_Summary: ['bid_axis', 'cid_axis'],
    V2_Entry_Body: ['bid_axis', 'cid_axis'],
    V2_Entry_ArchiveList: ['bid_axis', 'cid_axis'],
    V2_Entry_TagRelational: ['bid_axis', 'cid_axis'],
    V2_Entry_MoreContent: [],
    V2_Entry_UnitList: ['bid_axis', 'cid_axis'],
    V2_Entry_GeoList: ['bid_axis', 'cid_axis'],
    V2_Category_Tree: ['cid_axis'],
    V2_Category_EntrySummary: ['cid_axis'],
    V2_Category_GeoList: [],
    V2_Media_Banner: [],
    V2_Blog_Field: [],
    V2_Entry_Field: [],
    V2_Category_Field: [],
    V2_User_Field: [],
    V2_Module_Field: [],
    V2_Field_ValueList: ['bid_axis'],
    V2_Links: [],
    V2_Navigation: [],
    V2_Tag_Filter: ['bid_axis', 'cid_axis'],
    V2_Tag_Cloud: ['bid_axis', 'cid_axis'],
    V2_Topicpath: ['bid_axis', 'cid_axis'],
    V2_User_Search: ['bid_axis'],
    User_GeoList: [],
    V2_Blog_ChildList: [],
    V2_Blog_GeoList: [],
    V2_Json2Tpl: [],
    V2_Ogp: [],
    V2_Sitemap: ['bid_axis', 'cid_axis'],
    V2_Calendar: ['bid_axis', 'cid_axis'],
    V2_CalendarYear: ['bid_axis', 'cid_axis'],
    V2_Schedule: [],
    V2_GlobalVars: [],
  },

  //--------------------
  // multi arg guidance
  multiArgGuidance: {
    Entry_Body: ['bid', 'uid', 'cid', 'eid'],
    Entry_List: ['bid', 'uid', 'cid', 'eid'],
    Entry_Photo: ['bid', 'uid', 'cid', 'eid'],
    Entry_Headline: ['bid', 'uid', 'cid', 'eid'],
    Entry_Summary: ['bid', 'uid', 'cid', 'eid'],
    Entry_ArchiveList: [],
    Entry_TagRelational: ['cid'],
    Entry_Continue: [],
    Entry_Field: ['bid', 'uid', 'cid', 'eid'],
    Entry_Calendar: [],

    Entry_GeoList: ['bid', 'uid', 'cid'],

    Admin_Entry_Autocomplete: ['bid', 'uid', 'cid', 'eid'],

    Unit_List: [],

    Category_List: [],
    Category_EntryList: [],
    Category_EntrySummary: [],
    Category_Field: [],
    Category_GeoList: [],

    User_Profile: [],
    User_Field: [],
    User_Search: [],
    User_GeoList: [],

    Blog_Field: [],
    Blog_ChildList: [],
    Blog_GeoList: [],

    Tag_Cloud: ['bid', 'cid'],
    Tag_Filter: ['bid', 'cid'],

    Calendar_Month: [],
    Calendar_Year: [],

    Links: [],
    Banner: [],
    Media_Banner: [],
    Navigation: [],
    Topicpath: [],

    Comment_Body: [],
    Comment_List: [],

    Json_2Tpl: [],
    Feed_Rss2: [],
    Feed_ExList: [],
    Sitemap: [],
    Ogp: [],

    Shop_Cart_List: [],
    Case_Time: [],

    Alias_List: [],

    Field_ValueList: [],

    Form2_Unit: [],

    Plugin_Schedule: [],
    Schedule: [],

    V2_Entry_Summary: ['bid', 'uid', 'cid', 'eid'],
    V2_Entry_Body: ['bid', 'uid', 'cid', 'eid'],
    V2_Entry_ArchiveList: [],
    V2_Entry_TagRelational: ['cid'],
    V2_Entry_MoreContent: [],
    V2_Entry_UnitList: [],
    V2_Entry_GeoList: ['bid', 'uid', 'cid'],
    V2_Category_Tree: [],
    V2_Category_EntrySummary: [],
    V2_Category_GeoList: [],
    V2_Media_Banner: [],
    V2_Blog_Field: [],
    V2_Entry_Field: [],
    V2_Category_Field: [],
    V2_User_Field: [],
    V2_Module_Field: [],
    V2_Field_ValueList: [],
    V2_Links: [],
    V2_Navigation: [],
    V2_Tag_Filter: ['bid', 'cid'],
    V2_Tag_Cloud: ['bid', 'cid'],
    V2_Topicpath: [],
    V2_User_Search: [],
    V2_User_GeoList: [],
    V2_Blog_ChildList: [],
    V2_Blog_GeoList: [],
    V2_Json2Tpl: [],
    V2_Ogp: [],
    V2_Sitemap: [],
    V2_Calendar: [],
    V2_CalendarYear: [],
    V2_Schedule: [],
    V2_GlobalVars: [],
  },
};
