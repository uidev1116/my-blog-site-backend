import { useMemo } from 'react';
import { useSettings } from '@features/unit-editor/stores/settings';
import { UnitStatus } from '../components';
import { useUnitToolbarProps } from '../store';

const AlignStatus = () => {
  const { editor, unit } = useUnitToolbarProps();
  const { align } = useSettings();

  const unitDef = editor.findUnitDef(unit.type);

  const value = useMemo(() => {
    if (unitDef === null) {
      return '';
    }
    if (unitDef.supports?.align === undefined) {
      return '';
    }
    const alignOptions =
      typeof unitDef.supports.align === 'function' ? unitDef.supports.align(align.version) : unitDef.supports.align;

    return alignOptions.find((align) => align.value === unit.align)?.label ?? '';
  }, [unitDef, unit.align, align]);

  if (value === '') {
    return null;
  }

  return <UnitStatus label="配置" value={value} />;
};

export default AlignStatus;
