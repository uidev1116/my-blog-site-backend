export type PdfPageResult = {
  image: string | false; // dataURL (失敗時 false)
  hasPrev: boolean;
  hasNext: boolean;
  currentPage: number;
};

type Pdf2Image = typeof import('../../../../lib/pdf2image').default;

let Pdf2ImageClass: Pdf2Image | undefined;

// URLごとのインスタンスと、pageごとの in-flight 合流・結果キャッシュ
const instanceByUrl = new Map<string, InstanceType<Pdf2Image>>();
const inflightByKey = new Map<string, Promise<PdfPageResult>>();
const resultCacheByKey = new Map<string, PdfPageResult>();

const keyOf = (url: string, page: number) => `${url}|${page}`;

async function getInstance(url: string) {
  if (!Pdf2ImageClass) {
    const { default: pdf2image } = await import(/* webpackChunkName: "pdf2image" */ '../../../../lib/pdf2image');
    Pdf2ImageClass = pdf2image;
  }
  let inst = instanceByUrl.get(url);
  if (!inst) {
    inst = new Pdf2ImageClass(url);
    instanceByUrl.set(url, inst);
  }
  return inst;
}

/** 同じ url|page は1本に合流し、完了後はキャッシュから返す */
export async function fetchPdfPage(url: string, page: number): Promise<PdfPageResult> {
  const key = keyOf(url, page);

  // 完全キャッシュ
  const cached = resultCacheByKey.get(key);
  if (cached) return cached;

  // 進行中に合流
  const running = inflightByKey.get(key);
  if (running) return running;

  const p = (async () => {
    const inst = await getInstance(url);
    const image = await inst.getPageImage(page);
    const [hasPrev, hasNext] = await Promise.all([inst.hasPrevPage(), inst.hasNextPage()]);
    const res: PdfPageResult = {
      image,
      hasPrev,
      hasNext,
      currentPage: inst.currentPage,
    };
    if (image !== false) {
      resultCacheByKey.set(key, res);
    }
    return res;
  })();

  inflightByKey.set(key, p);
  try {
    return await p;
  } finally {
    inflightByKey.delete(key);
  }
}
