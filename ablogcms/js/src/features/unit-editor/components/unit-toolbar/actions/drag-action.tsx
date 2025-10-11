import DraggableButton from '../../../../../components/draggable-button/draggable-button';
import { useUnitToolbarProps } from '../store';
import type { UnitToolbarActionProps } from '../types';

const DragAction = (props: UnitToolbarActionProps) => {
  const { handleProps } = useUnitToolbarProps();
  return <DraggableButton {...props} {...handleProps} />;
};

export default DragAction;
