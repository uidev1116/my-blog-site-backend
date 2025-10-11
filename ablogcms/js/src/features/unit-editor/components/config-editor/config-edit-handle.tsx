import DraggableButton from '@components/draggable-button/draggable-button';
import type { HandleProps } from '@features/unit-editor/core/types/unit';

interface ConfigEditHandleProps {
  handleProps: HandleProps;
}

const ConfigEditHandle = ({ handleProps }: ConfigEditHandleProps) => {
  return (
    <div className="acms-admin-unit-config-edit-handle">
      <DraggableButton className="acms-admin-unit-config-edit-handle" {...handleProps} />
    </div>
  );
};

export default ConfigEditHandle;
