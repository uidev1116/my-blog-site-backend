import { CSS } from '@dnd-kit/utilities';
import { useSortable } from '@dnd-kit/sortable';
import classNames from 'classnames';
import type { BannerType } from '../types';
import DraggableButton from '../../../components/draggable-button/draggable-button';

const Menu = ({ id, addItem }: { id: string; addItem: (type: BannerType) => void }) => {
  const { isDragging, attributes, listeners, setNodeRef, transform, transition, setActivatorNodeRef } = useSortable({
    id,
  });

  const style = {
    transform: transform ? CSS.Transform.toString({ ...transform, scaleX: 1, scaleY: 1 }) : undefined,
    transition,
    position: 'relative' as const,
  };

  return (
    <div
      ref={setNodeRef}
      style={style}
      className={classNames(
        'acms-admin-banner-edit-item-wrap acms-admin-banner-edit-unit-box acms-admin-banner-sortable-editor',
        {
          'acms-admin-dragging': isDragging,
        }
      )}
    >
      <div className="acms-admin-banner-edit-unit-box-inner">
        <DraggableButton ref={setActivatorNodeRef} {...attributes} {...listeners} size="small" />
        <div className="acms-admin-banner-edit-menu">
          <span style={{ fontSize: '14px', verticalAlign: 'middle' }}>{ACMS.i18n('media.add')}</span>
          <div className="acms-admin-banner-edit-btn-group">
            <button className="acms-admin-btn" onClick={() => addItem('image')} type="button">
              {ACMS.i18n('media.media')}
            </button>
            <button className="acms-admin-btn" onClick={() => addItem('source')} type="button">
              {ACMS.i18n('media.source')}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

export default Menu;
