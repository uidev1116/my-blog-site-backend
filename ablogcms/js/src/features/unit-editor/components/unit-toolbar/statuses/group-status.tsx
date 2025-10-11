import { useMemo } from 'react';
import { useSettings } from '@features/unit-editor/stores/settings';
import { UnitStatus } from '../components';
import { useUnitToolbarProps } from '../store';

const GroupStatus = () => {
  const { editor, unit } = useUnitToolbarProps();
  const { unitGroup } = useSettings();

  const unitDef = editor.findUnitDef(unit.type);

  const value = useMemo(() => {
    if (unitDef === null) {
      return '';
    }
    return unitGroup.options.find((group) => group.value === unit.group)?.label ?? '';
  }, [unitDef, unit.group, unitGroup.options]);

  if (value === '') {
    return null;
  }

  return <UnitStatus label="グループ" value={value} />;
};

export default GroupStatus;
