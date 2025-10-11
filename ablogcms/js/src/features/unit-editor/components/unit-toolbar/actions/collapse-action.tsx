import { useCallback } from 'react';
import { Icon } from '@components/icon';
import { ToolbarButton } from '../../ui/toolbar';
import { useUnitToolbarProps } from '../store';
import type { UnitToolbarActionProps } from '../types';

const CollapseAction = (props: UnitToolbarActionProps) => {
  const { unit, editor } = useUnitToolbarProps();

  const handleToggleCollapsed = useCallback(() => {
    editor.commands.toggleUnitCollapsed(unit.id);
  }, [unit, editor.commands]);

  return (
    <ToolbarButton {...props} label={unit.collapsed ? '開く' : '閉じる'} onClick={handleToggleCollapsed}>
      <Icon name={unit.collapsed ? 'open_in_full' : 'close_fullscreen'} />
    </ToolbarButton>
  );
};

export default CollapseAction;
