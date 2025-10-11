import { useCallback } from 'react';
import { Icon } from '@components/icon';
import { useSettings } from '@features/unit-editor/stores/settings';
import { MenuItem } from '../../../../../components/dropdown-menu';
import { useUnitToolbarProps } from '../store';
import type { UnitToolbarActionMenuProps } from '../types';

const WrapActionMenu = (props: UnitToolbarActionMenuProps) => {
  const { unit, editor } = useUnitToolbarProps();
  const { unitGroup } = useSettings();

  const handleSelect = useCallback(async () => {
    const groupUnit = editor.createUnit('group');
    editor.commands.wrapUnits(groupUnit, [unit.id]);
  }, [unit, editor]);

  if (unitGroup.enable) {
    // ユニットグループが有効な場合は、グループ化メニューを表示しない
    return null;
  }

  return (
    <MenuItem
      icon={<Icon name="move_group" />}
      onSelect={handleSelect}
      isDisabled={!editor.selectors.canWrapUnit([unit.id])}
      {...props}
    >
      グループ化
    </MenuItem>
  );
};

export default WrapActionMenu;
