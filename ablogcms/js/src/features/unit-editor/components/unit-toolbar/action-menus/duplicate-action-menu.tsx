import { useCallback } from 'react';
import { Icon } from '@components/icon';
import { MenuItem } from '../../../../../components/dropdown-menu';
import { useUnitToolbarProps } from '../store';
import type { UnitToolbarActionMenuProps } from '../types';

const DuplicateActionMenu = (props: UnitToolbarActionMenuProps) => {
  const { unit, editor } = useUnitToolbarProps();

  const handleSelect = useCallback(async () => {
    editor.commands.duplicateUnit(unit.id);
  }, [unit, editor.commands]);

  return (
    <MenuItem
      icon={<Icon name="library_add" />}
      shortcut={['Mod', 'Shift', 'D']}
      onSelect={handleSelect}
      isDisabled={!editor.selectors.canDuplicateUnit(unit.id)}
      {...props}
    >
      複製
    </MenuItem>
  );
};

export default DuplicateActionMenu;
