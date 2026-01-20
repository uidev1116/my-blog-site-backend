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
import { useCallback, useMemo, useState } from 'react';
import { useSettings } from '@features/unit-editor/stores/settings';
import VisuallyHidden from '@components/visually-hidden';
import useBreakpointValue from '@hooks/use-breakpoint-value';
import { Icon } from '@components/icon';
import { contrastColor, mix } from '@utils/color';
import { getCSSVariable } from '@utils/css';
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

/**
 * 階層レベルに応じた背景色を取得する
 * @param level 階層レベル（1から開始）
 * @param element 取得元の要素（.acms-admin-group-unit要素）
 * @returns HEXカラーコード
 *
 * 仕組み：
 * - レベルに対応するCSS変数（--acms-admin-group-unit-color-level{N}）が存在すればそれを使用
 * - CSS変数が存在しない場合はグラデーション計算に切り替え
 * - この方式により、SCSS側でCSS変数を追加するだけで固定レベルを増やせる
 */
const getLevelColor = (level: number, element?: Element | null): string => {
  // レベルに対応するCSS変数を試す
  const variableName = `--acms-admin-group-unit-color-level${level}`;
  const levelColor = getCSSVariable(variableName, '', element);

  // CSS変数が存在する場合はその値を返す
  if (levelColor) {
    return levelColor;
  }

  // CSS変数が存在しない場合はグラデーション計算
  // 最後に定義されている固定レベルを探す（連続していると仮定）
  let lastDefinedLevel = 0;
  for (let i = 1; i < level; i += 1) {
    const testVar = `--acms-admin-group-unit-color-level${i}`;
    const testColor = getCSSVariable(testVar, '', element);
    if (testColor) {
      lastDefinedLevel = i;
    } else {
      break; // CSS変数は連続していると仮定
    }
  }

  // グラデーション生成
  const start = getCSSVariable('--acms-admin-group-unit-gradient-start', '#bcbcc5', element);
  const end = getCSSVariable('--acms-admin-group-unit-gradient-end', '#e2e2e8', element);

  const step = 0.15; // 1階層あたりの進み
  const maxT = 0.9; // 深い階層の上限
  const t = Math.min((level - lastDefinedLevel) * step, maxT);

  return mix(start, end, t);
};

/**
 * テキストカラーの定義（CSS変数から取得）
 * @param element 取得元の要素（.acms-admin-group-unit要素）
 */
const getTextColors = (element?: Element | null) => ({
  light: getCSSVariable('--acms-admin-group-unit-text-light', '#ffffff', element),
  dark: getCSSVariable('--acms-admin-group-unit-text-dark', '#595963', element),
});

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
  const [groupUnitElement, setGroupUnitElement] = useState<HTMLDivElement | null>(null);

  // callback refで要素の変更を検知
  const groupUnitRef = useCallback((element: HTMLDivElement | null) => {
    setGroupUnitElement(element);
  }, []);

  // 階層レベルに応じた背景色とテキストカラーを計算
  const levelStyles = useMemo(() => {
    if (groupUnitElement === null) {
      return {};
    }
    const level = editor.selectors.getGroupUnitLevel(unit.id);
    // 階層レベルが0以下の場合は1にフォールバック（念のため）
    const safeLevel = Math.max(level, 1);
    const levelColor = getLevelColor(safeLevel, groupUnitElement);
    const textColors = getTextColors(groupUnitElement);
    const textColor = contrastColor(levelColor, textColors.dark, textColors.light);
    // depthは0始まり（level - 1）
    const depth = Math.max(safeLevel - 1, 0);

    return {
      '--acms-admin-group-unit-level-color': levelColor,
      '--acms-admin-group-unit-text-color': textColor,
      '--acms-admin-group-unit-depth': depth,
    } as React.CSSProperties;
  }, [editor.selectors, unit.id, groupUnitElement]);

  // Sticky Scroll用のスタイル（z-indexを含む）
  const stickyStyles = useMemo(() => {
    const level = editor.selectors.getGroupUnitLevel(unit.id);
    const depth = Math.max(level - 1, 0);
    const maxDepth = editor.selectors.getMaxGroupUnitDepth();
    // 階層が深いほどz-indexを小さくする（最小値は1）
    const zIndex = Math.max(maxDepth - depth + 1, 1);

    return {
      zIndex,
      position: 'sticky',
      top: 'calc(var(--acms-admin-group-unit-depth) * var(--acms-admin-group-unit-sticky-header-height) + var(--acms-admin-unit-editor-offset))', // 追従ヘッダーの高さをプラス
    } as React.CSSProperties;
  }, [editor.selectors, unit.id]);

  return (
    <div ref={groupUnitRef} className="acms-admin-group-unit" style={levelStyles}>
      <div className="acms-admin-group-unit-start" style={stickyStyles}>
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
