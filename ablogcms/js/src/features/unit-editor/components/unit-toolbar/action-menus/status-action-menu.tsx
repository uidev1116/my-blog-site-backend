import { useCallback } from 'react';
import { Icon } from '@components/icon';
import { MenuItem } from '../../../../../components/dropdown-menu';
import { useUnitToolbarProps } from '../store';
import type { UnitToolbarActionMenuProps } from '../types';

const StatusActionMenu = (props: UnitToolbarActionMenuProps) => {
  const { editor, unit } = useUnitToolbarProps();

  const handleSelect = useCallback(() => {
    editor.commands.setUnitStatus(unit.id, unit.status === 'open' ? 'close' : 'open');
  }, [unit, editor.commands]);

  return (
    <MenuItem
      icon={<Icon name={unit.status === 'open' ? 'visibility_off' : 'visibility'} />}
      shortcut={['Mod', 'Shift', 'H']}
      onSelect={handleSelect}
      {...props}
    >
      {unit.status === 'open' ? '非表示' : '表示'}
    </MenuItem>
  );
};

export default StatusActionMenu;
