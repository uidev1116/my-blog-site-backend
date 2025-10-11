import { useCallback } from 'react';
import { useSettings } from '@features/unit-editor/stores/settings';
import {
  MenuGroup,
  MenuItemRadioGroup,
  MenuItemRadio,
  MenuItemValueChangeEvent,
} from '../../../../../components/dropdown-menu';
import { useUnitToolbarProps } from '../store';
import type { UnitToolbarActionMenuProps } from '../types';

const GroupActionMenu = (props: UnitToolbarActionMenuProps) => {
  const { editor, unit } = useUnitToolbarProps();
  const { unitGroup } = useSettings();

  const handleValueChange = useCallback(
    (event: MenuItemValueChangeEvent) => {
      editor.commands.setUnitGroup(unit.id, event.detail.value);
    },
    [editor, unit]
  );

  if (!unitGroup.enable) {
    return null;
  }

  return (
    <MenuGroup title="グループ" {...props}>
      <MenuItemRadioGroup defaultValue={unit.group || ''} onValueChange={handleValueChange}>
        {unitGroup.options.map((group) => (
          <MenuItemRadio key={group.value} value={group.value}>
            {group.label}
          </MenuItemRadio>
        ))}
      </MenuItemRadioGroup>
    </MenuGroup>
  );
};

export default GroupActionMenu;
