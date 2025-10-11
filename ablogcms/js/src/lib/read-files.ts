export type ExtendedFile =
  | {
      file: File;
      filetype: 'image';
      preview: string;
      blob?: Blob | null;
    }
  | {
      file: File;
      filetype: 'file';
    };

export default function readFiles(files: File[] | FileList): Promise<ExtendedFile[]> {
  const promises: Promise<ExtendedFile>[] = [];
  Array.from(files).forEach((f) => {
    const promise = new Promise<ExtendedFile>((resolve, reject) => {
      const objFileReader = new FileReader();
      if (/(heic|heif)/i.test(f.type)) {
        let convertedBlob: Blob | null = null;
        objFileReader.onload = () => {
          resolve({
            file: f,
            filetype: 'image',
            preview: objFileReader.result as string,
            blob: convertedBlob,
          });
        };
        (async () => {
          const { heicTo } = await import(/* webpackChunkName: "heic-to" */ 'heic-to');
          const blob = await heicTo({
            blob: f,
            type: 'image/jpeg',
          });
          if (blob instanceof Blob) {
            convertedBlob = blob;
            objFileReader.readAsDataURL(blob);
          }
        })();
      } else if (f.type.match('image.*')) {
        objFileReader.onload = () => {
          resolve({
            file: f,
            filetype: 'image',
            preview: objFileReader.result as string,
          });
        };
        objFileReader.readAsDataURL(f);
      } else {
        objFileReader.onload = () => {
          resolve({
            file: f,
            filetype: 'file',
          });
        };
        objFileReader.readAsDataURL(f);
      }
      objFileReader.onerror = () => {
        reject(new Error(`Failed to read file: ${f.name}`));
      };
    });
    promises.push(promise);
  });
  return Promise.all(promises);
}
