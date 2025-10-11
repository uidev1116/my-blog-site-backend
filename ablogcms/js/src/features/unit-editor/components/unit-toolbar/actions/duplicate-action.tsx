import { useCallback } from 'react';
import { Icon } from '@components/icon';
import { ToolbarButton } from '../../ui/toolbar';
import type { UnitToolbarActionProps } from '../types';
import { useUnitToolbarProps } from '../store';

const DuplicateAction = ({ disabled, ...props }: UnitToolbarActionProps) => {
  const { editor, unit } = useUnitToolbarProps();
  const handleDuplicate = useCallback(async () => {
    editor.commands.duplicateUnit(unit.id);
  }, [editor.commands, unit.id]);

  return (
    <ToolbarButton
      label="複製"
      commands={['Mod', 'Shift', 'D']}
      onClick={handleDuplicate}
      disabled={disabled || !editor.selectors.canDuplicateUnit(unit.id)}
      {...props}
    >
      <Icon name="library_add" />
    </ToolbarButton>
  );
};

export default DuplicateAction;
