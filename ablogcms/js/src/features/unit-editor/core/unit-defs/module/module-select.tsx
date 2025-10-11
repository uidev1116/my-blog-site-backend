import useEffectOnce from '@hooks/use-effect-once';
import { useCallback, useRef, useState } from 'react';
import useUpdateEffect from '@hooks/use-update-effect';
import type { ModuleAttributes } from './types';

interface ModuleSelectProps {
  value: ModuleAttributes;
  onChange: (value: ModuleAttributes) => void;
}

const ModuleSelect = ({ value, onChange }: ModuleSelectProps) => {
  const [html, setHtml] = useState<string>('');

  useEffectOnce(() => {
    (async () => {
      const url = ACMS.Library.acmsLink(
        {
          tpl: 'ajax/module/view.html',
          searchParams: value,
        },
        {
          inherit: false,
          ajaxCacheBusting: false,
        }
      );
      const response = await fetch(url);
      const html = await response.text();
      setHtml(decodeURIComponent(html));
    })();
  });

  const handleClick = useCallback(() => {
    const Dialog = new ACMS.Dispatch.ModuleDialog('index', (res: string, mid: string, tpl: string) => {
      setHtml(res);
      onChange({ mid: parseInt(mid, 10), tpl });
    });

    Dialog.show(value.mid, value.tpl);
  }, [value, onChange]);

  const ref = useRef<HTMLDivElement>(null);

  useUpdateEffect(() => {
    if (ref.current) {
      ACMS.Dispatch(ref.current);
    }
  }, [html]);
  return (
    <div className="acms-admin-overflow-auto">
      <div className="acms-admin-my-3">
        <button type="button" className="acms-admin-btn-admin acms-admin-btn-small" onClick={handleClick}>
          <span className="acms-admin-icon acms-admin-icon-module" aria-hidden="true" />
          モジュール
        </button>
      </div>
      <div
        ref={ref}
        // eslint-disable-next-line react/no-danger
        dangerouslySetInnerHTML={{ __html: html }}
      />
    </div>
  );
};

export default ModuleSelect;
