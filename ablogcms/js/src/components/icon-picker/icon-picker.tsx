import { useState, useEffect, useRef, useCallback } from 'react';
import styled from 'styled-components';
import defaultIcons from '../../lib/icons';

const IconListWrap = styled.div`
  position: fixed;
  top: 10px;
  left: 0;
  z-index: 9999;
  max-width: 240px;

  &::before,
  &::after {
    position: absolute;
    bottom: calc(100% - 1px);
    left: 20px;
    width: 0;
    height: 0;
    pointer-events: none;
    content: ' ';
    border: solid transparent;
  }

  &::after {
    margin-left: -10px;
    border-color: rgb(255 255 255 / 0%);
    border-width: 10px;
    border-bottom-color: #fff;
  }

  &::before {
    margin-left: -11px;
    border-color: rgb(204 204 204 / 0%);
    border-width: 11px;
    border-bottom-color: #ccc;
  }
`;

const IconListInner = styled.div`
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  max-height: 200px;
  overflow-y: scroll;
  border: 1px solid #ccc;
`;

const IconList = styled.ul`
  padding: 10px;
  margin: 0;
  white-space: normal;
  list-style: none;
  background: #fff;

  li {
    display: inline-block;
    margin: 0;
  }
`;

const IconButton = styled.button`
  padding: 8px;
  font-size: 18px;
  color: #333;
  cursor: pointer;
  background-color: #fff;
  border: 1px solid #fff;
  border-radius: 4px;
  transition: background-color linear 0.15s;

  &:hover {
    text-decoration: none;
    background: #bae2f3;
  }
`;

const Preview = styled.div`
  box-sizing: border-box;
  display: inline-block;
  width: 50px;
  padding: 6px 8px;
  margin-right: 0;
  line-height: 1;
  color: #333;
  text-align: center;
  text-decoration: none;
  vertical-align: middle;
  background-color: #f7f7f7;
  border: 1px solid rgb(0 0 0 / 20%);
  border-radius: 0;
  border-top-left-radius: 3px;
  border-bottom-left-radius: 3px;
`;

interface IconPickerProps {
  icons?: string[];
  defaultValue?: string;
  onChange?(icon: string): void;
}

const IconPicker = ({ icons = defaultIcons, defaultValue = '', onChange = () => {} }: IconPickerProps) => {
  const [icon, setIcon] = useState(defaultValue);
  const [isOpen, setIsOpen] = useState(false);
  const [position, setPosition] = useState({ top: 0, left: 0 });
  const pickerRef = useRef<HTMLDivElement>(null);
  const buttonRef = useRef<HTMLButtonElement>(null);
  const iconButtonRefs = useRef<HTMLButtonElement[]>([]);

  useEffect(() => {
    const handleClick = (event: MouseEvent) => {
      if (buttonRef.current && buttonRef.current.contains(event.target as Node)) {
        return;
      }
      if (pickerRef.current && pickerRef.current.contains(event.target as Node)) {
        return;
      }
      setIsOpen(false);
    };
    window.addEventListener('click', handleClick, { capture: true });
    return () => {
      window.removeEventListener('click', handleClick, { capture: true });
    };
  }, []);

  const selectIcon = useCallback(
    (icon: string) => {
      setIcon(icon);
      setIsOpen(false);
      onChange(icon);
    },
    [onChange]
  );

  const handleKeyDown = useCallback((event: React.KeyboardEvent<HTMLDivElement>) => {
    if (event.code === 'Escape') {
      event.stopPropagation();
      setIsOpen(false);
    }
  }, []);

  const handleClick = () => {
    const clientRect = buttonRef.current?.getBoundingClientRect();
    if (clientRect) {
      setPosition({
        top: clientRect.top + 45,
        left: clientRect.left,
      });
      setIsOpen((prev) => !prev);
    }
  };

  useEffect(() => {
    if (isOpen) {
      iconButtonRefs.current[0]?.focus();
    } else {
      buttonRef.current?.focus();
    }
  }, [isOpen]);

  return (
    <>
      <div className="acms-admin-btn-group">
        <Preview>
          <span className={icon} />
        </Preview>
        <button
          type="button"
          className="acms-admin-btn"
          onClick={handleClick}
          ref={buttonRef}
          aria-label={ACMS.i18n('icon_picker.open')}
        >
          <span className="acms-admin-icon-arrow-small-down" />
        </button>
      </div>
      {isOpen && (
        <div ref={pickerRef} style={{ position: 'relative' }}>
          <IconListWrap
            ref={pickerRef}
            style={{ top: `${position.top}px`, left: `${position.left}px` }}
            onKeyDown={handleKeyDown}
          >
            <IconListInner>
              <IconList>
                {icons.map((iconName) => (
                  <li key={iconName}>
                    <IconButton
                      type="button"
                      onClick={() => selectIcon(iconName)}
                      aria-label={ACMS.i18n('icon_picker.pick')}
                      ref={(node: HTMLButtonElement) => {
                        iconButtonRefs.current.push(node);
                      }}
                    >
                      <span className={iconName} aria-hidden />
                    </IconButton>
                  </li>
                ))}
              </IconList>
            </IconListInner>
          </IconListWrap>
        </div>
      )}
    </>
  );
};

export default IconPicker;
