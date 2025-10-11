export default async function dispatchResizeImageCF(context: Element | Document) {
  const elements = context.querySelectorAll(ACMS.Config.resizeImageTargetMarkCF);
  if (elements.length === 0) {
    return;
  }

  const { default: ResizeImage } = await import(
    /* webpackChunkName: "resize-image" */ '../lib/resize-image/resize-image'
  );
  elements.forEach((element) => {
    if (!element.closest('.item-template')) {
      const resizeImg = new ResizeImage(element);
      resizeImg.resize();
    }
  });
}
