import { useCallback } from 'react';
import { Icon } from '@components/icon';
import { MenuItem } from '../../../../../components/dropdown-menu';
import { useUnitToolbarProps } from '../store';
import type { UnitToolbarActionMenuProps } from '../types';

const CollapseActionMenu = (props: UnitToolbarActionMenuProps) => {
  const { unit, editor } = useUnitToolbarProps();

  const handleSelect = useCallback(() => {
    editor.commands.toggleUnitCollapsed(unit.id);
  }, [unit, editor.commands]);

  return (
    <MenuItem
      icon={<Icon name={unit.collapsed ? 'open_in_full' : 'close_fullscreen'} />}
      onSelect={handleSelect}
      {...props}
    >
      {unit.collapsed ? '開く' : '閉じる'}
    </MenuItem>
  );
};

export default CollapseActionMenu;
