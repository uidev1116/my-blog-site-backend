import { useRef, useState } from 'react';
import loadRichEditorModulesAsync from '@features/rich-editor/loadModulesAsync';
import setupExpand from '@features/rich-editor/utils/setupExpand';
import type RichEditor from '@features/rich-editor/components/rich-editor/rich-editor';
import useEffectOnce from '@hooks/use-effect-once';

const Expandable = ({ children }: { children: React.ReactNode }) => {
  const ref = useRef<HTMLDivElement>(null);
  useEffectOnce(() => {
    if (ref.current) {
      setupExpand(ref.current);
    }
  });
  return (
    <div ref={ref}>
      <div className="js-expand js-acms-expand">
        <div className="js-acms-expand-inner">
          <button className="js-expand-btn js-acms-expand-btn" type="button" aria-label="リッチエディタを拡大表示する">
            <i className="acms-admin-icon acms-admin-icon-expand-arrow js-expand-icon" aria-hidden="true" />
          </button>
          {children}
        </div>
      </div>
    </div>
  );
};

export type AsyncRichEditorProps = Partial<React.ComponentPropsWithoutRef<typeof RichEditor>>;

const AsyncRichEditor = (props: AsyncRichEditorProps) => {
  const componentRef = useRef<((props: AsyncRichEditorProps) => JSX.Element) | null>(null);
  const [isReady, setIsReady] = useState(false);

  useEffectOnce(() => {
    const loadModules = async () => {
      const { createProps, RichEditor } = await loadRichEditorModulesAsync();
      const component = (props: AsyncRichEditorProps) => {
        return (
          <Expandable>
            <RichEditor {...createProps(document.createElement('div'))} {...props} />
          </Expandable>
        ) as JSX.Element;
      };
      componentRef.current = component;
      setIsReady(true);
    };
    loadModules();
  });

  if (!isReady) {
    return null;
  }

  if (!componentRef.current) {
    return null;
  }

  const RichEditor = componentRef.current;

  return <RichEditor {...props} />;
};

export default AsyncRichEditor;
