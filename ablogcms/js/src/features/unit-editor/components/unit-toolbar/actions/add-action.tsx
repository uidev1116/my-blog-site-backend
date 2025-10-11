import { useCallback } from 'react';
import type { UnitMenuItem } from '@features/unit-editor/core/types/unit';
import { Icon } from '@components/icon';
import { ToolbarButton } from '../../ui/toolbar';
import UnitMenu from '../../unit-menu';
import { useUnitToolbarProps } from '../store';
import type { UnitToolbarActionProps } from '../types';

const AddAction = (props: UnitToolbarActionProps) => {
  const { editor, unit } = useUnitToolbarProps();

  const handleSelect = useCallback(
    async (menuItem: UnitMenuItem) => {
      const newUnits = menuItem.units.map((unit) => {
        const { id, ...options } = unit;
        return editor.createUnit(id, options);
      });
      editor.commands.insertAfterUnit(unit.id, newUnits);
    },
    [unit, editor]
  );

  return (
    <UnitMenu
      editor={editor}
      renderTrigger={({ MenuTrigger }) => (
        <MenuTrigger asChild>
          <ToolbarButton {...props} label="追加" commands={['Mod', 'Shift', 'A']}>
            <Icon name="add" />
          </ToolbarButton>
        </MenuTrigger>
      )}
      onSelect={handleSelect}
    />
  );
};

export default AddAction;
