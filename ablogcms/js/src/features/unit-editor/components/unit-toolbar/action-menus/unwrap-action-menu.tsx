import { useCallback } from 'react';
import { Icon } from '@components/icon';
import { useSettings } from '@features/unit-editor/stores/settings';
import { MenuItem } from '../../../../../components/dropdown-menu';
import { useUnitToolbarProps } from '../store';
import type { UnitToolbarActionMenuProps } from '../types';

const UnwrapActionMenu = (props: UnitToolbarActionMenuProps) => {
  const { unit, editor } = useUnitToolbarProps();
  const { unitGroup } = useSettings();

  const handleSelect = useCallback(async () => {
    editor.commands.unwrapUnit(unit.id);
  }, [unit, editor.commands]);

  if (unitGroup.enable) {
    // ユニットグループが有効な場合は、グループ解除メニューを表示しない
    return null;
  }

  return (
    <MenuItem
      icon={<Icon name="ungroup" />}
      onSelect={handleSelect}
      isDisabled={!editor.selectors.canUnwrapUnit(unit.id)}
      {...props}
    >
      グループ解除
    </MenuItem>
  );
};

export default UnwrapActionMenu;
