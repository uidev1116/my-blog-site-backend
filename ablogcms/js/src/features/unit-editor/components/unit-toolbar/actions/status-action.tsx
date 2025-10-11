import { useCallback } from 'react';
import { Icon } from '@components/icon';
import { ToolbarButton } from '../../ui/toolbar';
import { useUnitToolbarProps } from '../store';
import type { UnitToolbarActionProps } from '../types';

const StatusAction = (props: UnitToolbarActionProps) => {
  const { editor, unit } = useUnitToolbarProps();

  const handleToggleStatus = useCallback(() => {
    editor.commands.setUnitStatus(unit.id, unit.status === 'open' ? 'close' : 'open');
  }, [unit, editor.commands]);

  return (
    <ToolbarButton
      {...props}
      label={unit.status === 'open' ? '非表示' : '表示'}
      commands={['Mod', 'Shift', 'H']}
      onClick={handleToggleStatus}
    >
      <Icon name={unit.status === 'open' ? 'visibility_off' : 'visibility'} />
    </ToolbarButton>
  );
};

export default StatusAction;
