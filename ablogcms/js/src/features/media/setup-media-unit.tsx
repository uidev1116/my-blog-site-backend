import { render } from '../../utils/react';
import { triggerEvent } from '../../utils';
import { MediaItem } from './types';
import MediaUnit from './components/media-unit/media-unit';

function removeTrailingSlash(path: string) {
  return path.replace(/\/$/, '');
}

export default function setupMediaUnit(
  context: HTMLElement,
  options: Partial<React.ComponentPropsWithoutRef<typeof MediaUnit>> = {}
) {
  const elements = context.querySelectorAll<HTMLElement>('.js-media-unit');
  if (elements.length === 0) {
    return () => {};
  }
  const roots: ReturnType<typeof render>[] = [];
  elements.forEach((element) => {
    element.classList.add('done');
    const {
      id = '',
      primaryImageId = '',
      mediaSizes,
      multiUpload,
      mediaDir = '',
      active = ACMS.Config.mediaLibrary,
      enlarged,
      primary,
      thumbnail,
      type,
      pdf,
      pdfIcon,
      caption,
      text,
      alt,
      mid,
      link,
      landscape,
      lang,
      name,
      nolink,
      overrideLink = '',
      overrideAlt = '',
      overrideCaption = '',
    } = element.dataset;
    const thumbnailPath =
      type === 'file' ? `${removeTrailingSlash(ACMS.Config.root)}${thumbnail}` : `${mediaDir}${thumbnail}`;
    const item = {
      media_caption: caption,
      media_text: text,
      media_alt: alt,
      media_id: mid,
      media_link: link,
      media_landscape: landscape,
      media_thumbnail: thumbnailPath,
      media_type: type,
      media_pdf: pdf,
      media_title: name,
    } as MediaItem;
    let mediaSizesFiltered = [];
    if (mediaSizes) {
      mediaSizesFiltered = JSON.parse(mediaSizes).filter((obj: object) => {
        if (Object.keys(obj).length === 0) {
          return false;
        }
        return true;
      });
    }
    const root = render(
      <MediaUnit
        items={[item]}
        id={id}
        primaryImageId={primaryImageId}
        mediaSizes={mediaSizesFiltered}
        mediaDir={mediaDir}
        active={active}
        lang={lang}
        primary={primary as 'true' | 'false'}
        multiUpload={multiUpload === 'false' ? 'false' : 'true'}
        usePdfIcon={pdfIcon as 'yes' | 'no'}
        enlarged={enlarged as 'true' | 'false'}
        hasLink={nolink as 'true' | 'false'}
        overrideLink={overrideLink}
        overrideAlt={overrideAlt}
        overrideCaption={overrideCaption}
        {...options}
        onChange={(mediaItems) => {
          if (options.onChange) {
            options.onChange(mediaItems);
          }
          triggerEvent(element, 'acmsAdminMediaUnitChange', { bubbles: true, detail: { mediaItems } });
        }}
      />,
      element
    );
    roots.push(root);
  });

  return () => {
    roots.forEach((root) => {
      root.unmount();
    });
  };
}
