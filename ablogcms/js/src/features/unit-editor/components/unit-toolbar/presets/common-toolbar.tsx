import { useSettings } from '@features/unit-editor/stores/settings';
import { Fragment, useMemo } from 'react';
import useBreakpointValue from '@hooks/use-breakpoint-value';
import { Icon } from '@components/icon';
import { ToolbarButtonGroup, ToolbarButton, ToolbarVr } from '../../ui/toolbar';
import UnitMeta from '../components/unit-meta';
import {
  DragAction,
  MoveAction,
  AddAction,
  CollapseAction,
  StatusAction,
  DuplicateAction,
  DeleteAction,
} from '../actions';
import {
  Menu,
  MenuDivider,
  MenuGroup,
  MenuList,
  MenuPopover,
  MenuTrigger,
} from '../../../../../components/dropdown-menu';
import {
  AddAfterActionMenu,
  AlignmentActionMenu,
  DuplicateActionMenu,
  CollapseActionMenu,
  StatusActionMenu,
  DeleteActionMenu,
  AddBeforeActionMenu,
  GroupActionMenu,
  WrapActionMenu,
  UnwrapActionMenu,
} from '../action-menus';
import UnitToolbar from '../unit-toolbar';
import { Spacer } from '../../../../../components/spacer';
import AnkerEditor from '../anker-editor';
import type { UnitToolbarFeatures } from '../types';
import { AlignStatus, GroupStatus } from '../statuses';
import UnitStatusGroup from '../components/unit-status-group';

// デフォルトfeatures
const defaultFeatures: UnitToolbarFeatures = {
  insert: true,
  collapse: true,
  status: true,
  duplicate: true,
  delete: true,
  move: true,
  drag: true,
  align: true,
  group: true,
  anker: true,
  meta: true,
  wrap: true,
  unwrap: true,
};

interface CommonUnitToolbarProps extends Omit<React.ComponentPropsWithoutRef<typeof UnitToolbar>, 'children'> {
  features?: Partial<UnitToolbarFeatures>;
}

const CommonUnitToolbar = ({ editor, unit, features: featuresProp = {}, ...props }: CommonUnitToolbarProps) => {
  const settings = useSettings();

  // featuresのマージ
  const features = { ...defaultFeatures, ...featuresProp };

  const enableGroupFeature = useMemo(() => {
    if (!settings.unitGroup.enable) {
      return false;
    }
    if (!features.group) {
      return false;
    }
    return true;
  }, [settings.unitGroup.enable, features.group]);

  const enableAlignFeature = useMemo(() => {
    if (!features.align) {
      return false;
    }
    if (!editor.selectors.canAlignUnit(unit.type, settings.align.version)) {
      return false;
    }
    return true;
  }, [editor, unit.type, settings.align.version, features.align]);

  const isMobileDown = useBreakpointValue({ xs: true, sm: false });
  const isMobileUp = useBreakpointValue({ xs: false, sm: true });

  const infoFeatures = [
    ...(features.drag ? [{ id: 'drag', component: <DragAction /> }] : []),
    ...(features.meta
      ? [
          {
            id: 'meta',
            component: (
              <>
                <Spacer size={12} />
                <UnitMeta />
                <Spacer size={12} />
              </>
            ),
          },
        ]
      : []),
    ...(features.move ? [{ id: 'move', component: <MoveAction /> }] : []),
  ];

  const statusFeatures = [
    ...(enableAlignFeature ? [{ id: 'align', component: <AlignStatus /> }] : []),
    ...(enableGroupFeature ? [{ id: 'group', component: <GroupStatus /> }] : []),
  ];

  // アクションボタン群
  const buttonActions = [
    ...(isMobileDown ? [{ id: 'add', component: <AddAction disabled={!features.insert} /> }] : []),
    ...(isMobileUp ? [{ id: 'collapse', component: <CollapseAction disabled={!features.collapse} /> }] : []),
    ...(isMobileUp ? [{ id: 'status', component: <StatusAction disabled={!features.status} /> }] : []),
    ...(isMobileUp ? [{ id: 'duplicate', component: <DuplicateAction disabled={!features.duplicate} /> }] : []),
    ...(isMobileUp ? [{ id: 'delete', component: <DeleteAction disabled={!features.delete} /> }] : []),
  ];

  // メニュー内のユニットグループ
  const menuUnitActions = [
    ...(features.insert ? [{ id: 'addBefore', component: <AddBeforeActionMenu /> }] : []),
    ...(features.insert ? [{ id: 'addAfter', component: <AddAfterActionMenu /> }] : []),
    ...(features.duplicate && isMobileDown
      ? [
          {
            id: 'duplicate',
            component: <DuplicateActionMenu />,
          },
        ]
      : []),
    ...(features.delete && isMobileDown
      ? [
          {
            id: 'delete',
            component: <DeleteActionMenu />,
          },
        ]
      : []),
    ...(features.status && isMobileDown
      ? [
          {
            id: 'status',
            component: <StatusActionMenu />,
          },
        ]
      : []),
    ...(features.collapse && isMobileDown
      ? [
          {
            id: 'collapse',
            component: <CollapseActionMenu />,
          },
        ]
      : []),
    ...(features.wrap
      ? [
          {
            id: 'wrap',
            component: <WrapActionMenu />,
          },
        ]
      : []),
    ...(features.unwrap
      ? [
          {
            id: 'unwrap',
            component: <UnwrapActionMenu />,
          },
        ]
      : []),
  ];

  const menuActionGroups = [
    ...(menuUnitActions.length > 0
      ? [
          {
            id: 'unit',
            component: (
              <MenuGroup title="ユニット">
                {menuUnitActions.map((action) => {
                  return <Fragment key={action.id}>{action.component}</Fragment>;
                })}
              </MenuGroup>
            ),
          },
        ]
      : []),
    ...(enableAlignFeature
      ? [
          {
            id: 'align',
            component: <AlignmentActionMenu />,
          },
        ]
      : []),
    ...(enableGroupFeature
      ? [
          {
            id: 'group',
            component: <GroupActionMenu />,
          },
        ]
      : []),
    ...(features.anker ? [{ id: 'anker', component: <AnkerEditor /> }] : []),
  ];

  const menus = [
    ...(buttonActions.length > 0
      ? [
          {
            id: 'button',
            component: (
              <ToolbarButtonGroup>
                {buttonActions.map((feature) => {
                  return <Fragment key={feature.id}>{feature.component}</Fragment>;
                })}
              </ToolbarButtonGroup>
            ),
          },
        ]
      : []),
    ...(menuActionGroups.length > 0
      ? [
          {
            id: 'menu',
            component: (
              <Menu>
                <MenuTrigger asChild>
                  <ToolbarButton label="その他">
                    <Icon name="more_vert" />
                  </ToolbarButton>
                </MenuTrigger>
                <MenuPopover>
                  <MenuList>
                    {menuActionGroups.map((group, index) => {
                      return (
                        <Fragment key={group.id}>
                          {index > 0 && <MenuDivider />}
                          {group.component}
                        </Fragment>
                      );
                    })}
                  </MenuList>
                </MenuPopover>
              </Menu>
            ),
          },
        ]
      : []),
  ];

  return (
    <UnitToolbar editor={editor} unit={unit} {...props}>
      {/* 情報セクション */}
      {infoFeatures.length > 0 && (
        <div className="acms-admin-unit-toolbar-info">
          {infoFeatures.map((feature, index) => {
            return (
              <Fragment key={feature.id}>
                {index > 0 && <ToolbarVr />}
                <div>{feature.component}</div>
              </Fragment>
            );
          })}
        </div>
      )}

      {/* ステータスセクション */}
      {statusFeatures.length > 0 && (
        <div className="acms-admin-unit-toolbar-status">
          <UnitStatusGroup>
            {statusFeatures.map((feature) => {
              return <Fragment key={feature.id}>{feature.component}</Fragment>;
            })}
          </UnitStatusGroup>
        </div>
      )}

      {/* メニューセクション */}
      {menus.length > 0 && (
        <div className="acms-admin-unit-toolbar-menus">
          {menus.map((menu, index) => {
            return (
              <Fragment key={menu.id}>
                {index > 0 && <ToolbarVr />}
                <div>{menu.component}</div>
              </Fragment>
            );
          })}
        </div>
      )}
    </UnitToolbar>
  );
};

export default CommonUnitToolbar;
