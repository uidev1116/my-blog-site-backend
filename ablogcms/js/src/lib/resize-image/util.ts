interface DrawInfo {
  rad: number;
  dx: number;
  dy: number;
}

interface DestinationSize {
  width: number;
  height: number;
  resize: boolean;
}

interface DataUrlRes {
  dataUrl: string;
  resize: boolean;
}

interface BlobRes {
  blob: Blob;
  resize: boolean;
}

export default class ResizeImageUtil {
  /**
   * URLからBASE64画像でリサイズ画像を取得
   *
   * @param url
   * @param resizeType (null=long side, width, height)
   * @param resizeSize (length)
   * @param callback
   */
  async getDataUrlFromUrl(
    url: string,
    resizeType: string,
    resizeSize: number,
    callback: () => void
  ): Promise<DataUrlRes> {
    const Mime = await import(/* webpackChunkName: "mime-types" */ 'mime-types');
    const mimeType = Mime.lookup(url) || 'image/png';
    const image = new Image();
    image.crossOrigin = 'anonymous';
    image.src = url;

    const res = await this.getDataURL(image, mimeType, resizeType, resizeSize, callback);
    return res;
  }

  /**
   * URLからBLOBデータでリサイズ画像を取得
   *
   * @param url
   * @param resizeType
   * @param resizeSize
   * @param callback
   */
  async getBlobFromUrl(url: string, resizeType: string, resizeSize: number, callback: () => void): Promise<BlobRes> {
    const Mime = await import(/* webpackChunkName: "mime-types" */ 'mime-types');
    const mimeType = Mime.lookup(url) || 'image/png';
    const image = new Image();
    image.crossOrigin = 'anonymous';
    image.src = url;

    const res = await this.getBlob(image, mimeType, resizeType, resizeSize, callback);
    return res;
  }

  /**
   * FileからBASE64画像でリサイズ画像を取得
   *
   * @param file
   * @param resizeType
   * @param resizeSize
   * @param callback
   * @returns {Promise}
   */
  async getDataUrlFromFile(
    file: File,
    resizeType: string,
    resizeSize: number,
    callback: () => void
  ): Promise<DataUrlRes> {
    const mimeType = file.type || 'image/png';
    const image = new Image();
    image.src = this._createObjectURL(file);

    const res = await this.getDataURL(image, mimeType, resizeType, resizeSize, callback);
    return res;
  }

  /**
   * FileからBLOBデータでリサイズ画像を取得
   *
   * @param file
   * @param resizeType
   * @param resizeSize
   * @param callback
   * @returns {Promise}
   */
  async getBlobFromFile(file: File, resizeType: string, resizeSize: number, callback?: () => void): Promise<BlobRes> {
    const mimeType = file.type || 'image/png';
    const image = new Image();
    image.src = this._createObjectURL(file);
    const res = await this.getBlob(image, mimeType, resizeType, resizeSize, callback);
    return res;
  }

  /**
   * BASE64画像を取得
   *
   * @param image
   * @param mimeType
   * @param resizeType
   * @param resizeSize
   * @param callback
   * @returns {Promise}
   */
  getDataURL(
    image: HTMLImageElement,
    mimeType: string,
    resizeType: string,
    resizeSize: number,
    callback: (dataUrl: string, resize: boolean) => void
  ): Promise<DataUrlRes> {
    return new Promise((resolve) => {
      const onload = () => {
        const canvas = document.createElement('canvas');
        if (!resizeSize) {
          resizeSize = image.width;
        }
        const destinationSize = this._getDestinationSize(image, resizeType, resizeSize);
        const drawInfo = {
          rad: 0,
          dx: 0,
          dy: 0,
        };

        canvas.width = destinationSize.width;
        canvas.height = destinationSize.height;

        this._drawImage(image, canvas, destinationSize, drawInfo);
        const dataUrl = canvas.toDataURL(mimeType);
        resolve({
          dataUrl,
          resize: destinationSize.resize,
        });
        if (typeof callback === 'function') {
          callback(dataUrl, destinationSize.resize);
        }
      };
      if (image.width) {
        onload();
      } else {
        image.onload = onload;
      }
    });
  }

  /**
   * リサイズした上で、Blobデータを取得
   *
   * @param image
   * @param mimeType
   * @param resizeType
   * @param resizeSize
   * @param callback
   * @returns {Promise}
   */
  getBlob(
    image: HTMLImageElement,
    mimeType: string,
    resizeType: string,
    resizeSize: number,
    callback?: (blob: Blob | null, resize: boolean) => void
  ): Promise<BlobRes> {
    return new Promise((resolve, reject) => {
      const onload = () => {
        const canvas = document.createElement('canvas');
        if (!resizeSize) {
          resizeSize = image.width;
        }
        const destinationSize = this._getDestinationSize(image, resizeType, resizeSize);
        const drawInfo = {
          rad: 0,
          dx: 0,
          dy: 0,
        };
        canvas.width = destinationSize.width;
        canvas.height = destinationSize.height;

        this._drawImage(image, canvas, destinationSize, drawInfo);
        canvas.toBlob(
          (blob) => {
            if (blob === null) {
              reject(new Error('Failed to create Blob'));
              return;
            }
            resolve({
              blob,
              resize: destinationSize.resize,
            });
            if (typeof callback === 'function') {
              callback(blob, destinationSize.resize);
            }
          },
          mimeType,
          1
        );
      };
      if (image.width) {
        onload();
      } else {
        image.onload = onload;
      }
    });
  }

  /**
   * データUrlをBlobに変換
   *
   * @param dataUrl
   * @returns {*}
   */
  dataUrlToBlob(dataUrl: string): Blob {
    const arr = dataUrl.split(',');
    let mime = '';
    let bstr = '';

    if (arr.length > 1) {
      // eslint-disable-next-line prefer-destructuring
      mime = arr[0].match(/:(.*?);/)![1];
      bstr = atob(arr[1]);
    } else {
      bstr = atob(arr[0]);
    }
    let n = bstr.length;
    const u8arr = new Uint8Array(n);

    while (n--) {
      u8arr[n] = bstr.charCodeAt(n);
    }

    return new Blob([u8arr], { type: mime });
  }

  /**
   * fileからデータURL作成
   *
   * @param file
   * @returns {*}
   * @private
   */
  _createObjectURL(file: File): string {
    const createObjectURL = window.URL && window.URL.createObjectURL;
    return createObjectURL(file);
  }

  /**
   * リサイズ画像の書き出し
   *
   * @param image
   * @param canvas
   * @param destinationSize
   * @param drawInfo
   * @returns {*}
   */
  _drawImage(
    image: HTMLImageElement,
    canvas: HTMLCanvasElement,
    destinationSize: { width: number; height: number },
    drawInfo: DrawInfo
  ): HTMLCanvasElement {
    const ctx = canvas.getContext('2d')!;
    // 高品質補間を有効化
    ctx.imageSmoothingEnabled = true;
    ctx.imageSmoothingQuality = 'high';
    const { rad, dx, dy } = drawInfo;
    const diff = 0.5;

    if (image.width * diff > destinationSize.width) {
      // スムーズ処理を入れて、リサイズ
      let oc = document.createElement('canvas');
      let octx = oc.getContext('2d')!;
      // 高品質補間を有効化
      octx.imageSmoothingEnabled = true;
      octx.imageSmoothingQuality = 'high';

      // step 1 we reduce the image to half by using an off-screen canvas
      oc.width = image.width * diff;
      oc.height = image.height * diff;
      octx.drawImage(image, 0, 0, oc.width, oc.height);

      let { width } = oc;
      let { height } = oc;

      // step 2 reuses the off-screen canvas and draws the image reduced to half again
      while (width * diff > destinationSize.width) {
        const tmp = document.createElement('canvas');
        tmp.width = Math.max(Math.floor(width * diff), destinationSize.width);
        tmp.height = Math.max(Math.floor(height * diff), destinationSize.height);

        const tmpCtx = tmp.getContext('2d')!;
        tmpCtx.imageSmoothingEnabled = true;
        tmpCtx.imageSmoothingQuality = 'high';
        tmpCtx.drawImage(oc, 0, 0, width, height, 0, 0, tmp.width, tmp.height);

        oc = tmp;
        octx = tmpCtx;
        width = oc.width;
        height = oc.height;
      }
      // step 3 we draw once more to main canvas, again reduced to half but to the final size
      if (drawInfo.rad !== 0) {
        ctx.setTransform(Math.cos(rad), Math.sin(rad), -Math.sin(rad), Math.cos(rad), dx, dy);
      }
      ctx.drawImage(oc, 0, 0, width, height, 0, 0, destinationSize.width, destinationSize.height);
    } else {
      // 一気にリサイズ
      if (drawInfo.rad !== 0) {
        ctx.setTransform(Math.cos(rad), Math.sin(rad), -Math.sin(rad), Math.cos(rad), dx, dy);
      }
      ctx.drawImage(image, 0, 0, image.width, image.height, 0, 0, destinationSize.width, destinationSize.height);
    }
    return canvas;
  }

  /**
   * リサイズサイズの取得
   *
   * @param image
   * @param resizeType
   * @param resizeSize
   * @returns {{width: number, height: number}}
   */
  _getDestinationSize = (image: HTMLImageElement, resizeType: string, resizeSize: number): DestinationSize => {
    const destinationSize: DestinationSize = {
      width: 0,
      height: 0,
      resize: true,
    };

    // Long side
    if (!resizeType) {
      if (image.width > image.height) {
        resizeType = 'width';
      } else {
        resizeType = 'height';
      }
    }

    if (resizeType.substring(0, 1) === 'h') {
      // Portrait
      if (image.height < resizeSize) {
        destinationSize.width = image.width;
        destinationSize.height = image.height;
        destinationSize.resize = false;
      } else {
        destinationSize.width = image.width * (resizeSize / image.height);
        destinationSize.height = resizeSize;
      }

      // Landscape
    } else if (image.width < resizeSize) {
      destinationSize.width = image.width;
      destinationSize.height = image.height;
      destinationSize.resize = false;
    } else {
      destinationSize.width = resizeSize;
      destinationSize.height = image.height * (resizeSize / image.width);
    }

    return destinationSize;
  };

  /**
   * 画像のローテーション対応
   *
   * @param image
   * @param canvas
   * @param destinationSize
   * @returns {Promise}
   */
  _fixImageRotation(
    image: HTMLImageElement,
    canvas: HTMLCanvasElement,
    destinationSize: DestinationSize
  ): Promise<DrawInfo> {
    return new Promise((resolve) => {
      this._getExifData(image).then((exif) => {
        const drawInfo = {
          rad: 0,
          dx: 0,
          dy: 0,
        };

        switch (exif.Orientation) {
          case 8:
            canvas.width = destinationSize.height;
            canvas.height = destinationSize.width;
            drawInfo.rad = (-90 * Math.PI) / 180;
            drawInfo.dy = canvas.height;
            break;
          case 3:
            drawInfo.rad = (180 * Math.PI) / 180;
            drawInfo.dx = canvas.width;
            drawInfo.dy = canvas.height;
            break;
          case 6:
            canvas.width = destinationSize.height;
            canvas.height = destinationSize.width;
            drawInfo.rad = (90 * Math.PI) / 180;
            drawInfo.dx = canvas.width;
            break;
          default:
            break;
        }
        resolve(drawInfo);
      });
    });
  }

  /**
   * Exif情報の取得
   *
   * @param image
   * @returns {Promise}
   */
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  _getExifData(image: HTMLImageElement): Promise<any> {
    return new Promise((resolve) => {
      import(/* webpackChunkName: "exif-js" */ 'exif-js').then((Exif) => {
        // @ts-expect-error type definition is not correct
        Exif.getData(image, () => {
          const res = Exif.getAllTags(image);
          resolve(res);
        });
      });
    });
  }
}
