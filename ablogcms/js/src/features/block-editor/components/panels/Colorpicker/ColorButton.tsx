import { memo, useCallback } from 'react';
import { cn } from '../../../lib/utils';

export type ColorButtonProps = {
  color?: string;
  active?: boolean;
  onColorChange?: (color: string) => void; // eslint-disable-line no-unused-vars
};

export const ColorButton = memo(({ color, active, onColorChange }: ColorButtonProps) => {
  const wrapperClassName = cn('acms-admin-block-editor-text-menu-color-picker-palette-item', active && 'active');
  const bubbleClassName = cn('acms-admin-block-editor-text-menu-color-picker-palette-bubble', active && `active`);

  const handleClick = useCallback(() => {
    if (onColorChange) {
      onColorChange(color || '');
    }
  }, [onColorChange, color]);

  return (
    <button type="button" onClick={handleClick} className={wrapperClassName} aria-label={`select ${color}`}>
      <div style={{ backgroundColor: color, color }} className={bubbleClassName} />
    </button>
  );
});

ColorButton.displayName = 'ColorButton';
