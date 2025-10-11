import { UnitToolbarFeatures } from '../types';
import CommonUnitToolbar from './common-toolbar';

interface InplaceToolbarProps
  extends Omit<React.ComponentPropsWithoutRef<typeof CommonUnitToolbar>, 'children' | 'features' | 'handleProps'> {
  features?: Partial<Pick<UnitToolbarFeatures, 'anker' | 'align'>>;
}

const InplaceToolbar = ({ editor, unit, features = {}, ...props }: InplaceToolbarProps) => {
  return (
    <CommonUnitToolbar
      editor={editor}
      unit={unit}
      features={{
        align: true,
        anker: true,
        ...features,
        insert: false,
        collapse: false,
        status: true,
        duplicate: false,
        delete: false,
        move: false,
        drag: false,
        group: false,
        meta: true,
        wrap: false,
        unwrap: false,
      }}
      {...props}
    />
  );
};

export default InplaceToolbar;
