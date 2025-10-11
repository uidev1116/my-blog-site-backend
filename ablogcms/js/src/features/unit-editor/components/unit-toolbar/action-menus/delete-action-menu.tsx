import { useCallback } from 'react';
import { Icon } from '@components/icon';
import { MenuItem } from '../../../../../components/dropdown-menu';
import { useUnitToolbarProps } from '../store';
import type { UnitToolbarActionMenuProps } from '../types';

const DeleteActionMenu = (props: UnitToolbarActionMenuProps) => {
  const { editor, unit } = useUnitToolbarProps();

  const handleSelect = useCallback(async () => {
    if (
      await ACMS.Library.dialog.confirm(ACMS.i18n('unit.message1'), {
        confirmButton: {
          className: 'acms-admin-btn-danger',
        },
      })
    ) {
      editor.commands.removeUnit(unit.id);
    }
  }, [unit, editor.commands]);

  return (
    <MenuItem
      icon={<Icon name="delete" />}
      variant="danger"
      shortcut={['Mod', 'Shift', 'Backspace']}
      onSelect={handleSelect}
      {...props}
    >
      削除
    </MenuItem>
  );
};

export default DeleteActionMenu;
