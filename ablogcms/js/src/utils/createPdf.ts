export default function createPdf(file: File | Blob, page = 1): Promise<string> {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = (event) => {
      import(/* webpackChunkName: "pdf2image" */ '../lib/pdf2image').then(async ({ default: Pdf2Image }) => {
        if (!(event.target?.result instanceof ArrayBuffer)) {
          resolve('');
        }
        const pdf2Image = new Pdf2Image(new Uint8Array(event.target?.result as ArrayBuffer));
        pdf2Image
          .getPageImage(page)
          .then((image) => {
            resolve(image as string);
          })
          .catch((reason) => {
            reject(new Error(reason));
          });
      });
    };
    reader.readAsArrayBuffer(file);
  });
}
