import { useCallback } from 'react';
import { type UnitAlign } from '@features/unit-editor/core';
import { useSettings } from '@features/unit-editor/stores/settings';
import {
  MenuGroup,
  MenuItemRadioGroup,
  MenuItemRadio,
  type MenuItemValueChangeEvent,
} from '../../../../../components/dropdown-menu';
import { useUnitToolbarProps } from '../store';
import type { UnitToolbarActionMenuProps } from '../types';

const AlignmentActionMenu = (props: UnitToolbarActionMenuProps) => {
  const { editor, unit } = useUnitToolbarProps();
  const { align } = useSettings();

  const handleValueChange = useCallback(
    (event: MenuItemValueChangeEvent) => {
      editor.commands.setUnitAlign(unit.id, event.detail.value as UnitAlign);
    },
    [editor, unit]
  );

  if (!editor.selectors.canAlignUnit(unit.type, align.version)) {
    return null;
  }

  return (
    <MenuGroup title="配置" {...props}>
      <MenuItemRadioGroup defaultValue={unit.align} onValueChange={handleValueChange}>
        {editor.selectors.getAlignOptions(unit.type, align.version).map((align) => (
          <MenuItemRadio
            key={align.value}
            icon={
              <span className="material-symbols-outlined" aria-hidden="true">
                {align.value === 'auto' ? 'instant_mix' : `format_align_${align.value}`}
              </span>
            }
            value={align.value}
          >
            {align.label}
          </MenuItemRadio>
        ))}
      </MenuItemRadioGroup>
    </MenuGroup>
  );
};

export default AlignmentActionMenu;
