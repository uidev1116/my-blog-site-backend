import { useCallback } from 'react';
import type { UnitMenuItem } from '@features/unit-editor/core/types/unit';
import { Icon } from '@components/icon';
import UnitMenu from '../../unit-menu';
import { useUnitToolbarProps } from '../store';
import type { UnitToolbarActionMenuProps } from '../types';

const AddBeforeActionMenu = (props: UnitToolbarActionMenuProps) => {
  const { editor, unit } = useUnitToolbarProps();

  const handleSelect = useCallback(
    async (menuItem: UnitMenuItem) => {
      const newUnits = menuItem.units.map((unit) => {
        const { id, ...options } = unit;
        return editor.createUnit(id, options);
      });
      editor.commands.insertBeforeUnit(unit.id, newUnits);
    },
    [unit, editor]
  );

  return (
    <UnitMenu
      editor={editor}
      renderTrigger={({ SubmenuTriggerItem }) => (
        <SubmenuTriggerItem {...props} icon={<Icon name="add" />}>
          前に追加
        </SubmenuTriggerItem>
      )}
      onSelect={handleSelect}
    />
  );
};

export default AddBeforeActionMenu;
