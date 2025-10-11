import type { Editor } from '@features/unit-editor/core';
import type { HandleProps, UnitTreeNode } from '@features/unit-editor/core/types/unit';
import { Toolbar } from '../ui/toolbar';
import { UnitToolbarProvider } from './store';

interface UnitToolbarProps {
  editor: Editor;
  unit: UnitTreeNode;
  handleProps?: HandleProps;
  children?: React.ReactNode;
  className?: string;
}

/**
 * ユニットツールバーコンポーネント
 */
const UnitToolbar = ({ children, className, ...props }: UnitToolbarProps) => {
  return (
    <UnitToolbarProvider {...props}>
      <Toolbar className={className}>{children}</Toolbar>
    </UnitToolbarProvider>
  );
};

export default UnitToolbar;
