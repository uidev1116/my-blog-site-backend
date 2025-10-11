import InnerUnits from '@features/unit-editor/components/inner-units';
import { ToolbarButtonGroup, ToolbarButton, ToolbarVr } from '@features/unit-editor/components/ui/toolbar';
import {
  UnitToolbar,
  MoveAction,
  DragAction,
  UnitMeta,
  StatusAction,
  AddAction,
  CollapseAction,
  DuplicateAction,
  DeleteAction,
  AddBeforeActionMenu,
  AddAfterActionMenu,
  DeleteActionMenu,
  StatusActionMenu,
  CollapseActionMenu,
  DuplicateActionMenu,
  AnkerEditor,
  UnwrapActionMenu,
  WrapActionMenu,
} from '@features/unit-editor/components/unit-toolbar';
import type { HandleProps, UnitEditProps, UnitTreeNode } from '@features/unit-editor/core/types/unit';
import { useCallback } from 'react';
import { useSettings } from '@features/unit-editor/stores/settings';
import VisuallyHidden from '@components/visually-hidden';
import useBreakpointValue from '@hooks/use-breakpoint-value';
import { Icon } from '@components/icon';
import { Editor } from '../..';
import {
  Menu,
  MenuTrigger,
  MenuPopover,
  MenuGroup,
  MenuList,
  MenuDivider,
  MenuItemRadioGroup,
  MenuItemRadio,
  MenuItemValueChangeEvent,
} from '../../../../../components/dropdown-menu';
import { Spacer } from '../../../../../components/spacer';
import { GroupAttributes } from './types';

interface ToolbarProps {
  editor: Editor;
  unit: UnitTreeNode<GroupAttributes>;
  handleProps: HandleProps;
}

const Toolbar = ({ editor, unit, handleProps }: ToolbarProps) => {
  const { groupUnit } = useSettings();
  const handleClassSelectChange = useCallback(
    (event: React.ChangeEvent<HTMLSelectElement>) => {
      editor.commands.setUnitAttributes(unit.id, {
        class: event.target.value,
      });
    },
    [editor.commands, unit.id]
  );

  const handleTagMenuChange = useCallback(
    (event: MenuItemValueChangeEvent) => {
      editor.commands.setUnitAttributes(unit.id, {
        tag: event.detail.value,
      });
    },
    [editor.commands, unit.id]
  );

  const handleClassMenuChange = useCallback(
    (event: MenuItemValueChangeEvent) => {
      editor.commands.setUnitAttributes(unit.id, {
        class: event.detail.value,
      });
    },
    [editor.commands, unit.id]
  );

  const isMobileDown = useBreakpointValue({ xs: true, sm: false });
  const isMobileUp = useBreakpointValue({ xs: false, sm: true });
  const isTabletUp = useBreakpointValue({ sm: false, md: true });

  return (
    <>
      <UnitToolbar editor={editor} unit={unit} handleProps={handleProps}>
        {/* 情報セクション */}
        <div className="acms-admin-unit-toolbar-info">
          <div>
            <DragAction />
          </div>
          <ToolbarVr />
          <div>
            <Spacer size={12} />
            <UnitMeta />
          </div>
          {isTabletUp && (
            <div>
              <Spacer size={4} />
              <VisuallyHidden asChild>
                <label htmlFor={`${unit.id}-group-class`}>スタイル</label>
              </VisuallyHidden>
              <select id={`${unit.id}-group-class`} value={unit.attributes.class} onChange={handleClassSelectChange}>
                <option value="">未選択</option>
                {groupUnit.classOptions.map((option) => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
              <Spacer size={12} />
            </div>
          )}
          <ToolbarVr />
          <div>
            <MoveAction />
          </div>
        </div>

        {/* メニューセクション */}
        <div className="acms-admin-unit-toolbar-menus">
          <div>
            <ToolbarButtonGroup>
              {isMobileDown && <AddAction />}
              {isMobileUp && <CollapseAction />}
              {isMobileUp && <StatusAction />}
              {isMobileUp && <DuplicateAction />}
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
                    {isMobileDown && <DuplicateActionMenu />}
                    {isMobileDown && <DeleteActionMenu />}
                    {isMobileDown && <StatusActionMenu />}
                    {isMobileDown && <CollapseActionMenu />}
                    <WrapActionMenu />
                    <UnwrapActionMenu />
                  </MenuGroup>
                  <MenuDivider />
                  {!isTabletUp && (
                    <MenuGroup title="グループ">
                      <MenuItemRadioGroup
                        value={unit.attributes.class}
                        onValueChange={handleClassMenuChange}
                        closeOnSelect={false}
                      >
                        <MenuItemRadio value="">未選択</MenuItemRadio>
                        {groupUnit.classOptions.map((option) => (
                          <MenuItemRadio key={option.value} value={option.value}>
                            {option.label}
                          </MenuItemRadio>
                        ))}
                      </MenuItemRadioGroup>
                    </MenuGroup>
                  )}
                  <MenuGroup title="タグ">
                    <MenuItemRadioGroup
                      value={unit.attributes.tag || groupUnit.tagOptions[0].value}
                      onValueChange={handleTagMenuChange}
                      closeOnSelect={false}
                    >
                      {groupUnit.tagOptions.map((option) => (
                        <MenuItemRadio key={option.value} value={option.value}>
                          {option.label}
                        </MenuItemRadio>
                      ))}
                    </MenuItemRadioGroup>
                  </MenuGroup>
                  <MenuDivider />
                  <AnkerEditor />
                </MenuList>
              </MenuPopover>
            </Menu>
          </div>
        </div>
      </UnitToolbar>
      <input
        key={`group-class-${unit.id}-${unit.attributes.class}`}
        type="hidden"
        name={`group_class_${unit.id}`}
        defaultValue={unit.attributes.class}
      />
      <input
        key={`group-tag-${unit.id}-${unit.attributes.tag || groupUnit.tagOptions[0].value}`}
        type="hidden"
        name={`group_tag_${unit.id}`}
        defaultValue={unit.attributes.tag || groupUnit.tagOptions[0].value}
      />
    </>
  );
};

const Edit = ({ editor, unit, handleProps }: UnitEditProps<GroupAttributes>): JSX.Element => {
  return (
    <div className="acms-admin-group-unit acms-admin-group-unit-level1">
      <div className="acms-admin-group-unit-start">
        <Toolbar editor={editor} unit={unit} handleProps={handleProps} />
      </div>
      <div className="acms-admin-group-unit-content" style={{ display: unit.collapsed ? 'none' : 'block' }}>
        <InnerUnits editor={editor} unit={unit} />
      </div>
      <div className="acms-admin-group-unit-end">
        <UnitToolbar editor={editor} unit={unit} handleProps={handleProps}>
          <div className="acms-admin-unit-toolbar-info">
            <div>
              <UnitMeta />
            </div>
          </div>
        </UnitToolbar>
      </div>
    </div>
  );
};

export default Edit;
