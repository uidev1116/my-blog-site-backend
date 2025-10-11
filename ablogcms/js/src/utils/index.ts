import ResizeImage from '../lib/resize-image/util';

export const parseQuery = (query: string): { [key: string]: string } => {
  const s = query.split('&');
  const data: { [key: string]: string } = {};
  const iz = s.length;
  let i = 0;
  let param;
  let key;
  let value;

  for (; i < iz; i++) {
    param = s[i].split('=');
    if (param[0] !== undefined) {
      key = param[0]; // eslint-disable-line prefer-destructuring
      value = param[1] !== undefined ? param.slice(1).join('=') : key;
      try {
        data[key] = decodeURIComponent(value);
      } catch (e) {
        console.log(e); // eslint-disable-line no-console
      }
    }
  }
  return data;
};

export const getParameterByName = (name: string, query: string): string => {
  const search = query || location.search;
  name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]'); // eslint-disable-line no-useless-escape
  const regex = new RegExp(`[\\?&]${name}=([^&#]*)`);
  const results = regex.exec(search);
  try {
    return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
  } catch {
    return '';
  }
};

export const PerfectFormData = (dom: HTMLFormElement, dataUrlClass: string): FormData => {
  const clone = <HTMLFormElement>dom.cloneNode(true);

  // セレクトボックスの入力値の登録(cloneのbug対応)
  const selectors = dom.querySelectorAll('select');
  const cloneSelectors = clone.querySelectorAll('select');
  [].forEach.call(selectors, (select: HTMLSelectElement, i) => {
    [].forEach.call(select.options, (option, j) => {
      cloneSelectors[i].options[j].selected = select.options[j].selected;
    });
  });

  // input要素の削除とdataUrlの削除
  const inputs = clone.querySelectorAll('input');
  [].forEach.call(inputs, (input: HTMLInputElement) => {
    if (input.getAttribute('type') === 'file' || input.classList.contains(dataUrlClass)) {
      input.remove();
    }
  });

  // DOMからFromDataを作成
  const formData = new FormData(clone);
  const postModule = <HTMLInputElement>dom.querySelector('[name^=ACMS_POST_]');
  formData.append(postModule.name, postModule.value);

  // ファイル要素を追加
  const inputs2 = dom.querySelectorAll('input');
  [].forEach.call(inputs2, (input: HTMLInputElement) => {
    if (input.getAttribute('type') === 'file') {
      const name = input.getAttribute('name');
      if (name) {
        if ((input.files as FileList).length > 0) {
          [].forEach.call(input.files, (file) => {
            formData.append(name, file);
          });
        } else {
          formData.append(name, new File([''], ''));
        }
      }
    }
  });

  // dataUrlをblobに変換してFormDataに追加
  const base64Data = dom.querySelectorAll(`.${dataUrlClass}`);
  [].forEach.call(base64Data, (item: HTMLInputElement) => {
    const dataUrl = item.value;
    if (!dataUrl) return;
    const name = item.getAttribute('name');
    const resizeImage = new ResizeImage();
    const blob = resizeImage.dataUrlToBlob(dataUrl);
    if (blob && name) {
      formData.append(name, blob);
    }
  });

  return formData;
};

export const getScrollTop = () =>
  window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop || 0;

export const getScrollLeft = () =>
  window.pageXOffset || document.documentElement.scrollLeft || document.body.scrollLeft || 0;

export const getOffset = (el: Element) => {
  const rect = el.getBoundingClientRect();
  return {
    top: rect.top + getScrollTop(),
    left: rect.left + getScrollLeft(),
  };
};

/**
 * RFC 3986 に基づいてURLエンコードを行う
 * https://www.rfc-editor.org/rfc/rfc3986
 */
export const encodeUri = (str: string | number | boolean): string =>
  encodeURIComponent(str).replace(/[!'()*]/g, (match) => `%${match.charCodeAt(0).toString(16)}`);

/**
 * 要素がスクロール可能かどうかを判定する
 */
export const isScrollable = (el: Element) => el.scrollHeight > el.clientHeight || el.scrollWidth > el.clientWidth;

/**
 * iframeをリロードする
 */
export const reloadIframe = (iframe: HTMLIFrameElement): Promise<void> =>
  new Promise((resolve) => {
    iframe.onload = function () {
      resolve();
    };
    // eslint-disable-next-line no-self-assign
    iframe.src = iframe.src; // Reload the iframe by reassigning the same src
  });

export const range = (start: number, end?: number, step = 1): number[] => {
  // end が undefined の場合、start を end として扱い、start を 0 とする
  if (end === undefined) {
    end = start;
    start = 0;
  }

  // step が 0 の場合は、start を end - start の回数だけ繰り返す
  if (step === 0) {
    const length = Math.max(end - start, 0);
    return Array.from({ length }, () => start);
  }

  const length = Math.max(Math.floor((end - start) / step), 0);

  return Array.from({ length }, (_, i) => start + i * step);
};

export const getBrowser = () => {
  const ua = window.navigator.userAgent.toLowerCase();
  const ver = window.navigator.appVersion.toLowerCase();
  let name = 'unknown';

  if (ua.indexOf('msie') !== -1) {
    if (ver.indexOf('msie 6.') !== -1) {
      name = 'ie6';
    } else if (ver.indexOf('msie 7.') !== -1) {
      name = 'ie7';
    } else if (ver.indexOf('msie 8.') !== -1) {
      name = 'ie8';
    } else if (ver.indexOf('msie 9.') !== -1) {
      name = 'ie9';
    } else if (ver.indexOf('msie 10.') !== -1) {
      name = 'ie10';
    } else {
      name = 'ie';
    }
  } else if (ua.indexOf('trident/7') !== -1) {
    name = 'ie11';
  } else if (ua.indexOf('chrome') !== -1) {
    name = 'chrome';
  } else if (ua.indexOf('safari') !== -1) {
    name = 'safari';
  } else if (ua.indexOf('opera') !== -1) {
    name = 'opera';
  } else if (ua.indexOf('firefox') !== -1) {
    name = 'firefox';
  }
  return name;
};

export const isOldIE = () => {
  const browser = getBrowser();
  if (browser.indexOf('ie') !== -1) {
    if (parseInt(browser.replace(/[^0-9]/g, ''), 10) <= 10) {
      return true;
    }
  }
  return false;
};

export const contrastColor = (hex: string, black = '#000000', white = '#ffffff') => {
  const rgb = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);

  if (rgb === null) {
    return white;
  }
  const a = 1 - (0.299 * parseInt(rgb[1], 16) + 0.587 * parseInt(rgb[2], 16) + 0.114 * parseInt(rgb[3], 16)) / 255;

  if (a < 0.4) {
    // bright colors - black font
    return black;
  }
  // dark colors - white font
  return white;
};

export const rgb2hex = (rgb: string) => {
  const rgbAry = rgb.match(/^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/);
  const hexDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'a', 'b', 'c', 'd', 'e', 'f'];
  if (!rgbAry) {
    return '';
  }
  return `#${[rgbAry[1], rgbAry[2], rgbAry[3]]
    // @ts-expect-error typescript のisNaNの引数はnumber型のみだが、JavaScriptのisNaNはstring型を受け付ける
    .map((x) => (isNaN(x) ? '00' : hexDigits[(x - (x % 16)) / 16] + hexDigits[x % 16]))
    .join('')}`;
};

export const random = (length = 8) => {
  const quotient = Math.floor(length / 8);
  let remainder = length % 8;
  let txt = '';
  for (let i = 0; i < quotient; i += 1) {
    txt += Math.random().toString(36).slice(-8);
  }
  if (remainder > 0) {
    remainder *= -1;
    txt += Math.random().toString(36).slice(remainder);
  }
  return txt;
};

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export class ExpireLocalStorage<T = any> {
  save(key: string, jsonData: T, expirationSec: number) {
    if (!('localStorage' in window) || !(window.localStorage !== null)) {
      return false;
    }
    const expirationMS = expirationSec * 1000;
    const record = { value: JSON.stringify(jsonData), timestamp: new Date().getTime() + expirationMS };
    localStorage.setItem(key, JSON.stringify(record));
    return jsonData;
  }

  load(key: string): T | false {
    if (!('localStorage' in window) || !(window.localStorage !== null)) {
      return false;
    }
    const data = localStorage.getItem(key);
    if (!data) {
      return false;
    }
    const record = JSON.parse(data);
    if (!record) {
      return false;
    }
    return new Date().getTime() < record.timestamp && JSON.parse(record.value);
  }
}

export const formatBytes = (byte: string | number) => {
  const units = ['bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
  let l = 0;
  let n = typeof byte === 'number' ? byte : parseInt(byte, 10) || 0;
  while (n >= 1024 && ++l) {
    n /= 1024;
  }
  return `${n.toFixed(n >= 10 || l < 1 ? 0 : 1)} ${units[l]}`;
};

export const getExt = (name: string): string => name.split('.').pop() as string;

export const getFileName = (name: string): string => name.split('/').pop() as string;

/**
 * Content-Dispositionヘッダーからファイル名を取得する
 * @param contentDisposition Content-Dispositionヘッダーの値
 * @param defaultFileName デフォルトのファイル名
 * @returns ファイル名
 */
export const getFileNameFromContentDisposition = (
  contentDisposition: string | undefined,
  defaultFileName: string
): string => {
  if (!contentDisposition) {
    return defaultFileName;
  }

  // filename="..." または filename*=UTF-8''... の形式を解析
  const fileNameMatch = contentDisposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
  if (fileNameMatch && fileNameMatch[1]) {
    // デコードするとスペースが"+"になるのでスペースへ置換します
    return decodeURIComponent(fileNameMatch[1].replace(/['"]/g, '')).replace(/\+/g, ' ');
  }

  return defaultFileName;
};

export const setDropArea = (target: HTMLElement, dropAreaMark: string, callback: (files: FileList) => void) => {
  const $dropArea = $(dropAreaMark, target);
  const objDropArea = $dropArea.get(0);
  let dragging = 0;

  if (objDropArea && window.File && window.FileReader) {
    $('img', target).on('mousedown', (e) => {
      e.preventDefault();
    });
    $('img', target).on('mouseup', (e) => {
      e.preventDefault();
    });

    // ドロップできることを表示
    if (!$('img', target).attr('src')) {
      $dropArea.addClass('drag-n-drop-hover');
      setTimeout(() => {
        $dropArea.find('.acms-admin-drop-area').fadeOut(200, () => {
          $dropArea.removeClass('drag-n-drop-hover');
          $dropArea.find('.acms-admin-drop-area').show();
        });
      }, 800);
    }
    // ドロップ時のアクションを設定
    objDropArea.addEventListener(
      'drop',
      (event: DragEvent) => {
        event.stopPropagation();
        event.preventDefault();
        dragging = 0;
        $(objDropArea).removeClass('drag-n-drop-hover');
        if (event.dataTransfer === null) {
          return;
        }
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
        callback(files);
      },
      false
    );

    // ドロップエリアにいる間
    objDropArea.addEventListener(
      'dragover',
      (event) => {
        $(objDropArea).addClass('drag-n-drop-hover');
        event.stopPropagation();
        event.preventDefault();
        return false;
      },
      false
    );

    // ドロップエリアに入った時
    objDropArea.addEventListener(
      'dragenter',
      (event) => {
        dragging++;
        $(objDropArea).addClass('drag-n-drop-hover');
        event.stopPropagation();
        event.preventDefault();
        return false;
      },
      false
    );

    // ドロップエリアから出て行った時
    objDropArea.addEventListener(
      'dragleave',
      (event) => {
        dragging--;
        if (dragging === 0) {
          $(objDropArea).removeClass('drag-n-drop-hover');
        }
        event.stopPropagation();
        event.preventDefault();
        return false;
      },
      false
    );
  } else {
    // ブラウザが対応していない場合の処理
  }
};

export const dataURItoBlob = (dataURI: string) => {
  // convert base64 to raw binary data held in a string
  // doesn't handle URLEncoded DataURIs - see SO answer #6850276 for code that does this
  const byteString = atob(dataURI.split(',')[1]);
  // separate out the mime component
  const mimeString = dataURI.split(',')[0].split(':')[1].split(';')[0];
  // write the bytes of the string to an ArrayBuffer
  const ab = new ArrayBuffer(byteString.length);
  // create a view into the buffer
  const ia = new Uint8Array(ab);
  // set the bytes of the buffer to the correct values
  for (let i = 0; i < byteString.length; i++) {
    ia[i] = byteString.charCodeAt(i);
  }
  // write the ArrayBuffer to a blob, and you're done
  return new Blob([ab], { type: mimeString });
};

export const triggerEvent = (element: EventTarget, eventName: string, options: CustomEventInit = {}) => {
  element.dispatchEvent(new CustomEvent(eventName, options));
};

export function calcVhToPx(vh: number) {
  return (vh / 100) * window.innerHeight;
}

export function calcWidthFromRatio(ratio: number, height: number): number {
  // 横幅を計算 (横幅 = 縦幅 × 縦横比)
  return height * ratio;
}

export function calcHeightFromRatio(ratio: number, width: number): number {
  // 縦幅を計算 (縦幅 = 横幅 ÷ 縦横比)
  return width / ratio;
}

/**
 * Parse URLSearchParams to plain object.
 * @see https://github.com/jeremy-code/ts-utils#parseurlsearchparamsts
 */
export function parseUrlSearchParams(urlSearchParams: URLSearchParams) {
  return Array.from(urlSearchParams).reduce<Record<string, string | string[]>>((acc, [k, v]) => {
    if (!acc[k]) {
      // Returns array only if there are multiple values
      const values = urlSearchParams.getAll(k);
      acc[k] = values.length > 1 ? values : v;
    }
    return acc;
  }, {});
}

export function isValidUrl(url: string, validProtocols?: string[]): boolean {
  try {
    const parsedUrl = new URL(url, window.location.href);

    const protocolsToCheck = validProtocols || ['https:', 'http:', 'mailto:', 'tel:'];

    // parsedUrl.protocolがprotocolsToCheckに含まれていればtrue
    if (parsedUrl.protocol === '' || protocolsToCheck.includes(parsedUrl.protocol)) {
      return true;
    }

    return false; // その他のスキーム（例：file://, script://）は無効
  } catch {
    return false; // URL形式が不正な場合は無効
  }
}
