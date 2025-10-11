import { useCallback } from 'react';
import { Icon } from '@components/icon';
import { ToolbarButton } from '../../ui/toolbar';
import { useUnitToolbarProps } from '../store';
import type { UnitToolbarActionProps } from '../types';

const DeleteAction = (props: UnitToolbarActionProps) => {
  const { editor, unit } = useUnitToolbarProps();

  const handleDelete = useCallback(async () => {
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
    <ToolbarButton {...props} label="削除" commands={['Mod', 'Shift', 'Backspace']} onClick={handleDelete}>
      <Icon name="delete" />
    </ToolbarButton>
  );
};

export default DeleteAction;
