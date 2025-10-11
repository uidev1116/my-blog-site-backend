import classnames from 'classnames';
import type { UnitMenuItem } from '@features/unit-editor/core/types/unit';
import { type Editor } from '@features/unit-editor/core';
import { Icon } from '@components/icon';
import { ButtonV2 } from '../../../../components/button-v2';
import UnitMenu from '../unit-menu';

interface UnitInserterProps {
  editor: Editor;
  variant?: React.ComponentPropsWithoutRef<typeof ButtonV2>['variant'];
  visibility?: 'visible' | 'hidden';
  /**
   * ユニット追加ハンドラ（必須）
   */
  onInsert: (menuItem: UnitMenuItem) => void;
}

/**
 * ユニット挿入ボタンコンポーネント
 */
const UnitInserter = ({ editor, variant = 'outlined', visibility, onInsert }: UnitInserterProps): JSX.Element => {
  return (
    <div
      className={classnames('acms-admin-unit-inserter', {
        [`acms-admin-unit-inserter-${visibility}`]: visibility !== undefined,
      })}
    >
      <div className="acms-admin-unit-inserter-positioner">
        <div className="acms-admin-unit-inserter-clicable-area">
          <UnitMenu
            editor={editor}
            placement="bottom"
            renderTrigger={({ MenuTrigger }) => (
              <MenuTrigger asChild>
                <ButtonV2 type="button" variant={variant} size="small">
                  <Icon name="add" />
                  ユニットを追加
                </ButtonV2>
              </MenuTrigger>
            )}
            onSelect={onInsert}
          />
        </div>
      </div>
    </div>
  );
};

export default UnitInserter;
