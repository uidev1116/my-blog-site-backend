import { Suspense, lazy } from 'react';
import Spinner from '@components/spinner/spinner';
import { render } from '../utils/react';
import { BannerItem } from '../features/banner-editor/types';

const dispatchBannerEditor = async (context: Element | Document) => {
  const element = context.querySelector<HTMLElement>(ACMS.Config.bannerEditMark);
  if (!element) {
    return;
  }
  const { AllHtmlEntities: Entities } = await import(/* webpackChunkName: "html-entities" */ 'html-entities');
  const BannerEditor = lazy(
    () => import(/* webpackChunkName: "banner-editor" */ '../features/banner-editor/components/banner-editor')
  );
  $('.js-banner-ie').remove();
  const json = document.querySelector<HTMLScriptElement>('#js-banner-json')?.innerHTML;
  if (!json) {
    throw new Error('Not found JSON data.');
  }
  const decoded = Entities.decode(json);
  const replaced = decoded.replace(/\\\//g, '/');
  const items: BannerItem[] = JSON.parse(replaced);
  const attr1 = document.querySelector<HTMLInputElement>('.js-label-attr1')?.value || '';
  const attr2 = document.querySelector<HTMLInputElement>('.js-label-attr2')?.value || '';
  const hide1 = document.querySelector<HTMLInputElement>('.js-hide-attr1')?.value || '';
  const hide2 = document.querySelector<HTMLInputElement>('.js-hide-attr2')?.value || '';
  const tooltip1 = document.querySelector<HTMLInputElement>('.js-tooltip-attr1')?.value || '';
  const tooltip2 = document.querySelector<HTMLInputElement>('.js-tooltip-attr2')?.value || '';
  render(
    <Suspense
      fallback={
        <div className="acms-admin-d-flex acms-admin-justify-content-center acms-admin-align-items-center">
          <Spinner size={20} />
        </div>
      }
    >
      <BannerEditor
        attr1={attr1}
        attr2={attr2}
        hide1={hide1}
        hide2={hide2}
        tooltip1={tooltip1}
        tooltip2={tooltip2}
        items={items}
      />
    </Suspense>,
    element
  );
};

export default dispatchBannerEditor;
