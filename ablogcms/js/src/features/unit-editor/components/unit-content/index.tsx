import type { UnitTreeNode } from '@features/unit-editor/core/types/unit';

/**
 * UnitContentコンポーネントのプロパティ
 */
export interface UnitContentProps {
  /**
   * ユニット
   */
  unit: UnitTreeNode;

  children?: React.ReactNode;
}

/**
 * ユニットのコンテンツを表示するコンポーネント
 */
const UnitContent = ({ children, unit }: UnitContentProps) => {
  return (
    <div className="acms-admin-unit-content" style={{ display: unit.collapsed ? 'none' : 'block' }}>
      {children}
    </div>
  );
};

export default UnitContent;
