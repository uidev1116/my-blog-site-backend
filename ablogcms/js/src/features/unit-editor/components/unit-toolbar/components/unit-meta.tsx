import classnames from 'classnames';
import { useMemo } from 'react';
import { UnitTreeNode } from '@features/unit-editor/core';
import { useUnitToolbarProps } from '../store';

// eslint-disable-next-line @typescript-eslint/no-empty-object-type
interface UnitMetaProps extends React.HTMLAttributes<HTMLDivElement> {}

const UnitMeta = ({ className, ...props }: UnitMetaProps) => {
  const { unit, editor } = useUnitToolbarProps();

  const sort = useMemo(() => {
    const traverse = (unit: UnitTreeNode): string => {
      const index = editor.selectors.findUnitIndex(unit.id)! + 1;
      const parentUnit = editor.selectors.findParentUnit(unit.id);

      if (parentUnit) {
        return `${traverse(parentUnit)}-${index}`;
      }

      return String(index);
    };

    return traverse(unit);
  }, [editor.selectors, unit]);

  return (
    <div className={classnames('acms-admin-unit-toolbar-meta', className)} {...props}>
      <span>{sort}</span>
      {unit.status === 'close' && <span>非表示</span>}
      <span>{unit.name}</span>
    </div>
  );
};

export default UnitMeta;
