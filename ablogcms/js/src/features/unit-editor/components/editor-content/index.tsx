import {
  DndContext,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  type DragStartEvent,
  DragOverlay,
  type DragOverEvent,
  closestCorners,
} from '@dnd-kit/core';
import { sortableKeyboardCoordinates } from '@dnd-kit/sortable';
import { UnitPosition, type Editor, type UnitTreeNode } from '@features/unit-editor/core';
import { useCallback, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { Icon } from '@components/icon';
import UnitAppender from '../unit-appender';
import { Tooltip } from '../../../../components/tooltip';
import UnitList from '../unit-list';
import { Spacer } from '../../../../components/spacer';
import useShortcuts from '../../hooks/use-shortcuts';

interface EditorContentProps {
  editor: Editor;
}

interface DragableCloneProps {
  unit: UnitTreeNode;
}

const DraggableClone = ({ unit }: DragableCloneProps) => {
  return (
    <div className="acms-admin-unit-draggable-clone">
      <div>
        <span className="acms-admin-btn-draggable">
          <Icon name="drag_indicator" />
        </span>
      </div>
      <div className="acms-admin-unit-draggable-clone-vr" />
      <Spacer size={8} />
      <div>
        <div className="acms-admin-unit-draggable-clone-meta">
          {/* <span>{unit.sort}</span> */}
          {unit.status === 'close' && <span>非表示</span>}
          <span>{unit.name}</span>
        </div>
      </div>
    </div>
  );
};

const EditorContent = ({ editor }: EditorContentProps): JSX.Element => {
  const elementRef = useRef<HTMLDivElement>(null);
  const [clonedItems, setClonedItems] = useState<UnitTreeNode[] | null>(null);

  /**
   * dnd-kit の DragOver は高頻度で発火し、そこで state/editor の更新をすると
   * レンダリングが追いつかずループ的な挙動になりやすい。
   *
   * 特に「グループ内の最後の1ユニットを外へ移動」などの境界ケースで
   * move が何度も実行されると、ユニットの再マウントが連打されて
   * SSR/フォームの状態が競合しやすくなるため、ここで move を合流・抑制する。
   */
  const moveTimeoutRef = useRef<number | null>(null);

  /**
   * 直前に予約/実行しようとしていた move。
   * DragOver 連打で同じ move が何度も積まれるのを防ぐために使用する。
   */
  const lastMoveRef = useRef<{
    activeId: UnitTreeNode['id'];
    rootId: UnitTreeNode['id'] | undefined;
    index: number;
  } | null>(null);

  useEffect(() => {
    editor.setOptions({
      element: elementRef.current,
    });
  }, [editor]);

  // ショートカットハンドラーを初期化
  useShortcuts({ editor });

  // ドラッグ&ドロップのセンサー設定
  const sensors = useSensors(
    useSensor(PointerSensor),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates,
    })
  );

  const [activeId, setActiveId] = useState<UnitTreeNode['id'] | null>(null);

  const handleDragStart = useCallback(
    (event: DragStartEvent) => {
      setActiveId(event.active.id as UnitTreeNode['id']);
      setClonedItems(editor.state.units);
    },
    [editor.state.units]
  );

  /**
   * 以下のCodeSandboxのコードを参考にしている
   * @see https://codesandbox.io/p/sandbox/stoic-bohr-fosy2f?file=%2Fsrc%2Fdata.ts
   */
  const handleDragOver = useCallback(
    (event: DragOverEvent) => {
      const { active, over } = event;

      if (active === null || over === null) {
        return false;
      }

      const activeId = active.id as UnitTreeNode['id'];
      const overId =
        over.data.current?.type === 'droppable' && over.data.current.parentId !== null
          ? (over.data.current.parentId as UnitTreeNode['id'])
          : (over.id as UnitTreeNode['id']);

      let newPosition: UnitPosition | null = null;

      const overParentId = over.data.current?.parentId as UnitTreeNode['id'] | null;
      const activeParentUnit = editor.selectors.findParentUnit(activeId);
      const activeParentId = activeParentUnit?.id ?? null;
      const activeUnit = editor.selectors.findUnitById(activeId);

      if (activeUnit === null) {
        // Dnd で新規ユニットを挿入する機能を追加する場合は個々に処理を追加
        // 現状は新規ユニットを挿入する機能はないため、何もしない
        return;
      }

      if (over.data.current?.type === 'droppable') {
        // 空の子階層を許可するユニットにドロップした場合
        newPosition = {
          index: 0,
          rootId: overParentId ?? undefined,
        };
      } else if (activeParentId === null && overParentId === null) {
        // ルート階層にあるユニットをルート階層に移動する
        const index = editor.selectors.findUnitIndex(overId);
        if (index === null) {
          return;
        }

        newPosition = {
          index,
          rootId: undefined,
        };
      } else {
        const overParentUnit = overParentId ? editor.selectors.findUnitById(overParentId) : null;
        const siblingUnits = overParentUnit ? overParentUnit.children : editor.state.units;
        // 同じ親の中にすでに存在しており、かつドロップ先が自分自身の場合は、何も処理しない
        // （ドラッグ元とドラッグ先が同一であるため、ソートも不要）
        if (siblingUnits.find((unit) => unit.id === activeId) !== undefined && activeId === overId) {
          return;
        }

        if (overParentId === null) {
          // 移動元がルート階層ではない and 移動先がルート階層
          // つまり、子階層のユニットをルート階層に移動する

          const overIndex = editor.state.units.findIndex((unit) => unit.id === overId);
          if (overIndex === -1) {
            return;
          }

          newPosition = {
            index: overIndex,
            rootId: undefined,
          };
        } else {
          const overUnit = editor.selectors.findUnitById(overParentId);
          if (overUnit === null) {
            // 移動先の親ユニットが存在しない場合は何もしない
            return;
          }

          const overIndex = overUnit.children.findIndex((child) => child.id === overId);
          if (overIndex === -1) {
            // 移動先の親ユニットの中に移動先のユニットが存在しない場合は何もしない
            return;
          }

          newPosition = {
            index: overIndex,
            rootId: overParentId,
          };
        }
      }

      if (newPosition !== null && activeId !== newPosition.rootId) {
        // DragOver は同一座標でも繰り返し発火するため、すでに同じ位置なら何もしない
        const currentPosition = editor.selectors.findUnitPosition(activeId);
        if (
          currentPosition &&
          currentPosition.index === newPosition.index &&
          (currentPosition.rootId ?? undefined) === (newPosition.rootId ?? undefined)
        ) {
          return;
        }

        // 直前と同じ move 指示ならスキップ（setTimeout キューが積まれて move が連打されるのを防ぐ）
        if (
          lastMoveRef.current &&
          lastMoveRef.current.activeId === activeId &&
          lastMoveRef.current.index === newPosition.index &&
          (lastMoveRef.current.rootId ?? undefined) === (newPosition.rootId ?? undefined)
        ) {
          return;
        }
        lastMoveRef.current = { activeId, index: newPosition.index, rootId: newPosition.rootId };

        /**
         * Reactの Maximum update depth exceeded エラーを回避するために setTimeout を使用
         * see: https://github.com/clauderic/dnd-kit/issues/496
         */
        if (moveTimeoutRef.current !== null) {
          window.clearTimeout(moveTimeoutRef.current);
        }
        // 最新の move だけ残す（DragOver で古い move が後から実行されると状態が揺れる）
        moveTimeoutRef.current = window.setTimeout(() => {
          editor.commands.moveUnitToPosition(activeId, newPosition);
        }, 0);
      }
    },
    [editor.commands, editor.selectors, editor.state.units]
  );

  const handleDragEnd = useCallback(() => {
    setActiveId(null);
    lastMoveRef.current = null;
    if (moveTimeoutRef.current !== null) {
      window.clearTimeout(moveTimeoutRef.current);
      moveTimeoutRef.current = null;
    }
  }, []);

  const handleDragCancel = () => {
    if (clonedItems) {
      // Reset items to their original state in case items have been
      // Dragged across containers
      editor.commands.setUnits(clonedItems);
    }
    setActiveId(null);
    setClonedItems(null);
    lastMoveRef.current = null;
    if (moveTimeoutRef.current !== null) {
      window.clearTimeout(moveTimeoutRef.current);
      moveTimeoutRef.current = null;
    }
  };

  const activeUnit = activeId !== null ? editor.selectors.findUnitById(activeId) : null;

  return (
    <div ref={elementRef} className="acms-admin-unit-editor-content">
      <div>
        {/*
          collisionDetection は全て試した結果、closestCorners が最適だった
          おそらく
        */}
        <DndContext
          sensors={sensors}
          /**
           * @description
           * ネストされたコンテナの処理を改善するために closestCorners 衝突検出アルゴリズムを使用しています。
           * 一般的にソート可能なリストには closestCenter が推奨されますが、コンテナが重なっている場合、
           * closestCorners はより直感的な動作を提供し、下層のコンテナの検出に関する問題を防ぎます。
           *
           * @see https://docs.dndkit.com/api-documentation/context-provider/collision-detection-algorithms#when-should-i-use-the-closest-corners-algorithm-instead-of-closest-center
           */
          collisionDetection={closestCorners}
          onDragStart={handleDragStart}
          onDragOver={handleDragOver}
          onDragEnd={handleDragEnd}
          onDragCancel={handleDragCancel}
        >
          <UnitList editor={editor} units={editor.state.units} />
          {createPortal(<DragOverlay>{activeUnit && <DraggableClone unit={activeUnit} />}</DragOverlay>, document.body)}
        </DndContext>
      </div>
      <div>
        <UnitAppender editor={editor} />
      </div>

      {/* Tooltip component */}
      <Tooltip id="unit-editor-tooltip" />
    </div>
  );
};

export default EditorContent;
