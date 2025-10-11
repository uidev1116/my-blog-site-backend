import { Menu, MenuGroup, MenuList, MenuPopover, MenuTrigger } from '@components/dropdown-menu';
import { Icon } from '@components/icon';
import { ToolbarButton, ToolbarButtonGroup, ToolbarVr } from '@features/unit-editor/components/ui/toolbar';
import {
  AddAction,
  AddAfterActionMenu,
  AddBeforeActionMenu,
  DeleteAction,
  DeleteActionMenu,
  DragAction,
  MoveAction,
  UnitToolbar,
} from '@features/unit-editor/components/unit-toolbar';
import type { UnitEditProps } from '@features/unit-editor/core/types/unit';
import useBreakpointValue from '@hooks/use-breakpoint-value';
import { useEffect, useState } from 'react';

const Edit = ({ editor, unit, handleProps }: UnitEditProps) => {
  const [type, setType] = useState<'normal' | 'members_only'>('normal');

  useEffect(() => {
    const checkboxes = document.querySelectorAll<HTMLInputElement>('input[type="checkbox"][name="members_only"]');
    const handleChange = (event: Event) => {
      const checkbox = event.target as HTMLInputElement;
      setType(checkbox.checked ? 'members_only' : 'normal');
    };
    checkboxes.forEach((checkbox) => {
      setType(checkbox.checked ? 'members_only' : 'normal');
    });
    checkboxes.forEach((checkbox) => {
      checkbox.addEventListener('change', handleChange);
    });

    return () => {
      checkboxes.forEach((checkbox) => {
        checkbox.removeEventListener('change', handleChange);
      });
    };
  }, []);

  const isMobileDown = useBreakpointValue({ xs: true, sm: false });
  const isMobileUp = useBreakpointValue({ xs: false, sm: true });
  return (
    <div id="more" className="acms-admin-unit-more">
      <UnitToolbar editor={editor} unit={unit} handleProps={handleProps}>
        {/* 情報セクション */}
        <div className="acms-admin-unit-toolbar-info">
          <div>
            <DragAction />
          </div>
          <ToolbarVr />
          <div>
            <MoveAction />
          </div>
        </div>
        <div className="acms-admin-unit-more-content">
          以下のユニットが
          {type === 'normal' ? (
            <>
              <span className="acms-admin-d-none acms-admin-d-md-inline">一覧表示時に</span>
              「続きを読む」
            </>
          ) : (
            <>「会員限定」</>
          )}
          <span className="acms-admin-d-none acms-admin-d-md-inline">になります</span>
        </div>
        <div className="acms-admin-unit-toolbar-menus">
          <div>
            <ToolbarButtonGroup>
              {isMobileDown && <AddAction />}
              {isMobileUp && <DeleteAction />}
            </ToolbarButtonGroup>
          </div>
          <ToolbarVr />
          <div>
            <Menu>
              <MenuTrigger asChild>
                <ToolbarButton label="その他">
                  <Icon name="more_vert" />
                </ToolbarButton>
              </MenuTrigger>
              <MenuPopover>
                <MenuList>
                  <MenuGroup title="ユニット">
                    <AddBeforeActionMenu />
                    <AddAfterActionMenu />
                    {isMobileDown && <DeleteActionMenu />}
                  </MenuGroup>
                </MenuList>
              </MenuPopover>
            </Menu>
          </div>
        </div>
      </UnitToolbar>
    </div>
  );
};

export default Edit;
