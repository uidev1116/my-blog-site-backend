import Util from './util';

export default class ResizeImage {
  /**
   * Constructor
   *
   * @param elm
   */
  constructor(elm) {
    this.elm = elm;
    this.dropAreaMark = '.js-drop_area';
    this.inputMark = ACMS.Config.resizeImageInputMark;
    this.previewMark = ACMS.Config.resizeImagePreviewMark;
    this.targetMark = ACMS.Config.resizeImageTargetMark;
    this.targetMarkCF = ACMS.Config.resizeImageTargetMarkCF;
    this.dropSelect = false;
    this.dragging = 0;
    this.previewOnly = ACMS.Config.resizeImage !== 'on';
    this.util = new Util();
    this.eventListeners = [];
  }

  /**
   * リサイズ処理の初期化
   */
  resize() {
    const targetAry = this.elm.querySelectorAll(this.targetMark);

    if (targetAry.length > 0) {
      [].forEach.call(targetAry, (input) => {
        this.exec(input);
      });
    } else if (
      1 &&
      this.elm.classList.contains(this.targetMarkCF.substr(1)) &&
      !this.elm.classList.contains('resizeImage')
    ) {
      this.elm.classList.add('resizeImage');
      this.exec(this.elm);
    }
  }

  /**
   * イベントリスナーを削除し、クリーンアップする
   */
  destroy() {
    // 登録したイベントリスナーを削除
    this.eventListeners.forEach(({ element, type, handler }) => {
      element.removeEventListener(type, handler);
    });
    this.eventListeners = [];

    // プレビュー要素を削除
    const previewElements = this.elm.querySelectorAll(this.previewMark);
    previewElements.forEach((element) => {
      element.remove();
    });

    // リサイズデータを削除
    const resizeDataElements = this.elm.querySelectorAll('.js-img_resize_data');
    resizeDataElements.forEach((element) => {
      element.remove();
    });

    // クラスを削除
    this.elm.classList.remove('resizeImage');
  }

  /**
   * targetに対してリサイズ処理イベントを登録
   *
   * @param target
   */
  exec(target) {
    const node = target.querySelector(this.previewMark);
    if (node !== null) {
      this.previewBox = node.cloneNode(true);
      target
        .querySelector(this.previewMark)
        .insertAdjacentHTML('afterend', '<div class="js-img_resize_preview_location" />');
      this.listener(target);
    }
  }

  /**
   * 画像のファイル選択イベントを登録
   *
   * @param target
   */
  listener(target) {
    const dropArea = target.querySelector(this.dropAreaMark);
    const interval = 1500;
    let lastTime = new Date().getTime() - interval;

    this.dragging = 0;
    if (dropArea && window.File && window.FileReader && !this.previewOnly) {
      this.banDrag(target);

      target.querySelector('img').getAttribute('src');

      // ドロップできることを表示
      if (!target.querySelector('img').getAttribute('src')) {
        dropArea.classList.add('drag-n-drop-hover');
        setTimeout(() => {
          const area = dropArea.querySelector('.acms-admin-drop-area');
          $(area).fadeOut(200, () => {
            dropArea.classList.remove('drag-n-drop-hover');
            dropArea.querySelector('.acms-admin-drop-area').style.display = '';
          });
        }, 800);
      }

      // ドロップ時のアクションを設定
      const dropHandler = (event) => {
        event.stopPropagation();
        event.preventDefault();
        this.dragging = 0;
        this.dropSelect = true;
        dropArea.classList.remove('drag-n-drop-hover');

        const { files } = event.dataTransfer;
        let gif = false;

        for (let i = 0; i < files.length; i++) {
          const file = files[i];
          if (file.type === 'image/gif') {
            gif = true;
            break;
          }
        }
        if (gif) {
          if (!window.confirm(ACMS.i18n('drop_select_gif_image.alert'))) {
            return false;
          }
        }
        this.readFiles(event.dataTransfer.files, target);
        return false;
      };
      dropArea.addEventListener('drop', dropHandler, false);
      this.eventListeners.push({ element: dropArea, type: 'drop', handler: dropHandler });

      // ドロップエリアにいる間
      const dragOverHandler = (event) => {
        event.stopPropagation();
        event.preventDefault();
        dropArea.classList.add('drag-n-drop-hover');
        return false;
      };
      dropArea.addEventListener('dragover', dragOverHandler, false);
      this.eventListeners.push({ element: dropArea, type: 'dragover', handler: dragOverHandler });

      // ドロップエリアに入った時
      const dragEnterHandler = (event) => {
        event.stopPropagation();
        event.preventDefault();
        this.dragging++;
        dropArea.classList.add('drag-n-drop-hover');
        return false;
      };
      dropArea.addEventListener('dragenter', dragEnterHandler, false);
      this.eventListeners.push({ element: dropArea, type: 'dragenter', handler: dragEnterHandler });

      // ドロップエリアから出て行った時
      const dragLeaveHandler = (event) => {
        event.stopPropagation();
        event.preventDefault();
        this.dragging--;
        if (this.dragging === 0) {
          dropArea.classList.remove('drag-n-drop-hover');
        }
        return false;
      };
      dropArea.addEventListener('dragleave', dragLeaveHandler, false);
      this.eventListeners.push({ element: dropArea, type: 'dragleave', handler: dragLeaveHandler });
    }

    // フォーム入力よりファイルが選択された
    const fileInputs = target.querySelectorAll(this.inputMark);
    fileInputs.forEach((fileInput) => {
      const changeHandler = (event) => {
        if (lastTime + interval <= new Date().getTime()) {
          lastTime = new Date().getTime();
          this.readFiles(event.target.files, target);
        }
      };
      fileInput.addEventListener('change', changeHandler);
      this.eventListeners.push({ element: fileInput, type: 'change', handler: changeHandler });
    });
  }

  /**
   * 画像ファイルを読み込み
   *
   * @param files
   * @param target
   * @return {boolean}
   */
  readFiles(files, target) {
    const sizeSelect = target.querySelectorAll('[name^=image_size_]');
    const bannerSize = document.querySelectorAll('.js-banner_size_large');
    const bannerSizeCriterion = document.querySelector('.js-banner_size_large_criterion');
    const rawSize = sizeSelect.length > 0 && sizeSelect[0].value.length < 2;

    // 多言語対応ユニット
    if (sizeSelect.length > 1) {
      return false;
    }

    // バナーモジュール
    if (bannerSize.length >= 1 && bannerSize.value) {
      ACMS.Config.lgImg = `${bannerSizeCriterion.value}:${bannerSize[0].value}`;
    }
    [].forEach.call(target.querySelectorAll(this.previewMark), (item) => {
      item.parentNode.removeChild(item);
    });
    [].forEach.call(target.querySelectorAll('.js-img_resize_data'), (item) => {
      item.parentNode.removeChild(item);
    });

    const lgImageSize = ACMS.Config.lgImg;
    const lgImgAry = lgImageSize.split(':');
    const lgImgSide = lgImgAry[0];
    let lgImgSize = lgImgAry[1];

    if (rawSize) {
      lgImgSize = 999999999;
    }
    for (let i = 0; i < files.length; i++) {
      const file = files[i];
      if (!file) continue;
      this.util.getDataUrlFromFile(file, lgImgSide, lgImgSize).then((data) => {
        const { dataUrl } = data;
        let { resize } = data;

        if (rawSize) {
          resize = false;
        }
        if (!this.dropSelect && file.type === 'image/gif') {
          resize = false;
        }
        if (this.previewOnly) {
          resize = false;
        }
        this.set(target, dataUrl, resize || this.dropSelect);
      });
    }
  }

  /**
   * 画像ドラッグでの失敗を抑制
   *
   * @param target
   */
  banDrag(target) {
    [].forEach.call(target.querySelectorAll('img'), (item) => {
      const mouseDownHandler = (event) => {
        event.preventDefault();
      };
      const mouseUpHandler = (event) => {
        event.preventDefault();
      };
      item.addEventListener('mousedown', mouseDownHandler);
      item.addEventListener('mouseup', mouseUpHandler);
      this.eventListeners.push(
        { element: item, type: 'mousedown', handler: mouseDownHandler },
        { element: item, type: 'mouseup', handler: mouseUpHandler }
      );
    });
  }

  /**
   * 分数を約分
   *
   * @param numerator
   * @param denominator
   */
  reduce(numerator, denominator) {
    if (numerator > denominator) {
      return Math.floor(numerator / denominator);
    }
    const numerator0 = numerator;
    const denominator0 = denominator;
    let c;
    // eslint-disable-next-line no-constant-condition
    while (1) {
      c = numerator % denominator;
      if (c === 0) break;
      denominator = numerator;
      numerator = c;
    }
    return `${Math.floor(numerator0 / numerator)}/${Math.floor(denominator0 / numerator)}`;
  }

  /**
   * リサイズした画像データをdomとして追加
   *
   * @param {HTMLElement} target 対象の要素
   * @param {string} dataUrl 画像データのURL
   * @param {boolean} resize リサイズされているかどうか
   * @return {void}
   */
  set(target, dataUrl, resize) {
    if (!this.previewBox) {
      this.previewBox = target.querySelector(this.previewMark).cloneNode(true);
      [].forEach.call(target.querySelectorAll(this.previewMark), (item) => {
        item.parentNode.removeChild(item);
      });
      [].forEach.call(target.querySelectorAll('.js-img_resize_data'), (item) => {
        item.parentNode.removeChild(item);
      });
    }

    // preview
    const clone = this.previewBox.cloneNode(true);
    clone.style.display = '';
    clone.setAttribute('src', dataUrl);
    target.querySelector('.js-img_resize_preview_location').insertAdjacentHTML('beforebegin', clone.outerHTML);
    [].forEach.call(target.querySelectorAll('.js-img_resize_preview_old'), (item) => {
      item.parentNode.removeChild(item);
    });
    const imgDataUrl = target.querySelector('.js-img_data_url');
    if (imgDataUrl) {
      imgDataUrl.setAttribute('data-src', dataUrl);
    }

    // ban drag
    this.banDrag(target);

    const input = target.querySelector(this.inputMark);

    // insert data
    if (resize) {
      const fileInput = target.querySelector(this.inputMark);
      if (fileInput) {
        fileInput.value = '';
      }
    } else {
      dataUrl = '';
    }

    const inputName = input.getAttribute('name');
    const dataForm = document.createElement('input');
    dataForm.classList.add('js-img_resize_data');
    dataForm.setAttribute('type', 'hidden');
    dataForm.setAttribute('accept', 'image/*');
    dataForm.setAttribute('type', 'hidden');
    dataForm.setAttribute('name', inputName);
    dataForm.value = dataUrl;

    const dummy = target.querySelector('.js-img_resize_dummy');
    if (dummy) {
      dummy.remove();
    }
    target.querySelector(this.inputMark).insertAdjacentHTML('afterend', dataForm.outerHTML);
  }
}
