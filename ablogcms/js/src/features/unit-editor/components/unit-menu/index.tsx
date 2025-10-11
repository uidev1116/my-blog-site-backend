import type { UnitMenuItem } from '@features/unit-editor/core/types/unit';
import { useCallback } from 'react';
import { useSettings } from '@features/unit-editor/stores/settings';
import { UnitEditorSettings } from '@features/unit-editor/types';
import { type Editor } from '@features/unit-editor/core';
import { Icon } from '@components/icon';
import {
  Menu,
  MenuGroup,
  MenuItem,
  MenuList,
  MenuTrigger,
  MenuPopover,
  MenuItemSelectEvent,
  SubmenuTriggerItem,
} from '../../../../components/dropdown-menu';
import { MaterialSymbol } from '../../../../types/material-symbols';

/**
 * ユニットタイプをカテゴリーごとにグループ化する
 */
const groupUnitDefsByCategory = (
  defs: UnitEditorSettings['unitDefs']
): Record<string, UnitEditorSettings['unitDefs']> => {
  return defs.reduce(
    (acc, def) => {
      if (!def.category) return acc;
      const category = def.category.slug;
      if (!acc[category]) {
        acc[category] = [];
      }
      acc[category].push(def);
      return acc;
    },
    {} as Record<string, UnitEditorSettings['unitDefs']>
  );
};

interface RenderTriggerProps {
  SubmenuTriggerItem: typeof SubmenuTriggerItem;
  MenuTrigger: typeof MenuTrigger;
}

interface UnitMenuProps extends React.ComponentPropsWithoutRef<typeof Menu> {
  renderTrigger: ({ SubmenuTriggerItem, MenuTrigger }: RenderTriggerProps) => React.ReactNode;
  onSelect?: (menuItem: UnitMenuItem) => void;
  editor: Editor;
}

const UnitMenu = ({ renderTrigger, onSelect, editor, ...props }: UnitMenuProps): JSX.Element => {
  const { unitDefs } = useSettings();
  const handleSelect = useCallback(
    (event: MenuItemSelectEvent) => {
      if (onSelect) {
        const unitDef = unitDefs.find((def) => def.id === event.detail.value);
        if (unitDef) {
          onSelect(unitDef);
        }
      }
    },
    [onSelect, unitDefs]
  );

  const getIcon = useCallback(
    (def: UnitMenuItem) => {
      if (!def.icon) {
        const icon = editor.findUnitDef(def.id)?.icon || 'build';
        return <Icon name={icon as MaterialSymbol} />;
      }
      if (def.icon.startsWith('acms-')) {
        return <span className={def.icon} aria-hidden="true" />;
      }
      return <Icon name={def.icon as MaterialSymbol} />;
    },
    [editor]
  );

  const getLabel = useCallback(
    (def: UnitMenuItem) => {
      return def.label || editor.findUnitDef(def.id)?.name || def.id;
    },
    [editor]
  );

  return (
    <Menu {...props}>
      {renderTrigger({ SubmenuTriggerItem, MenuTrigger })}
      <MenuPopover scrollable size="large">
        <MenuList>
          {Object.entries(groupUnitDefsByCategory(unitDefs)).map(([category, defs]) => (
            <MenuGroup key={category} title={defs[0]?.category?.name || category}>
              {defs.map((def) => (
                <MenuItem key={def.id} icon={getIcon(def)} value={def.id} onSelect={handleSelect}>
                  {getLabel(def)}
                </MenuItem>
              ))}
            </MenuGroup>
          ))}
        </MenuList>
      </MenuPopover>
    </Menu>
  );
};

export default UnitMenu;
