import classnames from 'classnames';
import { FC, useState, useRef, ReactNode, isValidElement, Children, useCallback } from 'react';
import useUpdateEffect from '../../hooks/use-update-effect';

interface TabsProps {
  children: ReactNode;
  onChange?: (index: number) => void;
  defaultIndex?: number;
  index?: number;
}

interface PanelProps extends React.HTMLAttributes<HTMLDivElement> {
  id: string;
  label: string;
  children: ReactNode;
}
export const TabPanel = ({ children }: PanelProps) => {
  return children;
};

export const Tabs: FC<TabsProps> = ({ children, onChange, defaultIndex = 0, index }) => {
  const [activeTabIndex, setActiveTabIndex] = useState(index || defaultIndex);
  const tabRefs = useRef<HTMLButtonElement[]>([]);

  const handleTabListKeyDown = useCallback(
    (e: React.KeyboardEvent<HTMLUListElement>) => {
      if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
        const tabs = Children.toArray(children);
        if (tabs.length > 0) {
          if (activeTabIndex >= 0) {
            let nextIndex;
            if (e.key === 'ArrowRight') {
              nextIndex = activeTabIndex + 1 < tabs.length ? activeTabIndex + 1 : 0;
            } else {
              nextIndex = activeTabIndex - 1 >= 0 ? activeTabIndex - 1 : tabs.length - 1;
            }
            setActiveTabIndex(nextIndex);
          }
        }
      }
    },
    [children, activeTabIndex]
  );

  useUpdateEffect(() => {
    if (tabRefs.current[activeTabIndex]) {
      tabRefs.current[activeTabIndex].focus();
    }
  }, [activeTabIndex]);

  useUpdateEffect(() => {
    if (onChange) {
      onChange(activeTabIndex);
    }
    // onChangeをdepsにいれると不要なレンダリングが発生するため。
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [activeTabIndex]);

  return (
    <div className="acms-admin-tabs">
      {/* eslint-disable-next-line jsx-a11y/interactive-supports-focus */}
      <ul className="acms-admin-tabs-inner" role="tablist" onKeyDown={handleTabListKeyDown}>
        {Children.map(children, (child, index) => {
          if (isValidElement<PanelProps>(child)) {
            return (
              <li role="presentation" key={child.props.id}>
                <button
                  ref={(node: HTMLButtonElement) => {
                    tabRefs.current[index] = node;
                  }}
                  id={`${child.props.id}-tab`}
                  role="tab"
                  aria-controls={child.props.id}
                  aria-selected={index === activeTabIndex}
                  tabIndex={index === activeTabIndex ? 0 : -1}
                  type="button"
                  onClick={() => setActiveTabIndex(index)}
                  className={classnames({
                    'acms-admin-tab-active': index === activeTabIndex,
                  })}
                >
                  {child.props.label}
                </button>
              </li>
            );
          }
          return null;
        })}
      </ul>
      {Children.map(children, (child, index) => {
        if (isValidElement(child)) {
          // eslint-disable-next-line @typescript-eslint/no-unused-vars
          const { label: _, ...rest } = child.props;
          return (
            <div
              key={child.props.id}
              role="tabpanel"
              aria-hidden={activeTabIndex !== index}
              className="acms-admin-tabs-panel"
              style={{ display: activeTabIndex === index ? 'block' : 'none' }}
              tabIndex={0}
              aria-labelledby={`${child.props.id}-tab`}
              {...rest}
            >
              {child}
            </div>
          );
        }
        return null;
      })}
    </div>
  );
};
