import htmx from 'htmx.org';
import '../lib/htmx/geolocation';

declare global {
  interface Window {
    htmx: typeof htmx;
  }

  interface HtmxConfigRequestEvent extends Event {
    detail: {
      headers: Record<string, string>;
    };
  }

  interface HtmxBeforeHistoryUpdateEvent extends Event {
    detail: {
      history: {
        path: string;
      };
    };
  }
}

export const dispatchHtmx = (element: HTMLElement, customConfig: Partial<typeof htmx.config>): void => {
  if (window.htmx) {
    htmx.process(element);
    return;
  }
  window.htmx = htmx;

  Object.assign(htmx.config, {
    ...customConfig,
    allowEval: false,
  });

  window.addEventListener('htmx:configRequest', (event: Event) => {
    const csrfTokenEl = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');
    if (csrfTokenEl && 'detail' in event) {
      (event as HtmxConfigRequestEvent).detail.headers['X-CSRF-Token'] = csrfTokenEl.content;
    }
  });

  window.addEventListener('htmx:afterSwap', (event: Event) => {
    if (event.target instanceof HTMLElement) {
      ACMS.Dispatch(event.target);
    }
  });

  document.addEventListener('click', (event: Event) => {
    const target = event.target as HTMLElement;
    const anchor = target.closest('a[hx-get]') as HTMLAnchorElement | null;
    if (anchor) {
      event.preventDefault(); // ← これが遷移を防ぐ
    }
  });

  htmx.process(element);
};

export default dispatchHtmx;
