import type { Editor, UnitMenuItem } from '@features/unit-editor/core';
import { useCallback } from 'react';
import { Icon } from '@components/icon';
import { ButtonV2 } from '../../../../components/button-v2';
import UnitMenu from '../unit-menu';

interface UnitAppenderProps {
  editor: Editor;
}

/**
 * ユニット末尾追加ボタンコンポーネント
 */
const UnitAppender = ({ editor }: UnitAppenderProps): JSX.Element => {
  // ユニット追加ハンドラ
  const handleAddUnit = useCallback(
    async (menuItem: UnitMenuItem) => {
      const newUnits = menuItem.units.map((unit) => {
        const { id, ...options } = unit;
        return editor.createUnit(id, options);
      });
      editor.commands.insertUnit(newUnits);
    },
    [editor]
  );

  return (
    <div className="acms-admin-unit-appender">
      <div style={{ display: 'grid' }}>
        <UnitMenu
          editor={editor}
          placement="bottom"
          renderTrigger={({ MenuTrigger }) => (
            <MenuTrigger asChild>
              <ButtonV2 type="button" variant="unit-insert" size="large">
                <Icon name="add" />
                ユニットを追加
              </ButtonV2>
            </MenuTrigger>
          )}
          onSelect={handleAddUnit}
        />
      </div>
    </div>
  );
};

export default UnitAppender;
