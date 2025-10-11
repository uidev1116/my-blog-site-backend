import { useCallback, useRef } from 'react';
import { Icon } from '@components/icon';
import { ToolbarButton, ToolbarButtonGroup } from '../../ui/toolbar';
import { useUnitToolbarProps } from '../store';
import type { UnitToolbarActionProps } from '../types';

function scrollToPointer(element: HTMLElement, prevY: number) {
  const rect = element.getBoundingClientRect();
  const newY = rect.top + rect.height / 2;

  const offset = newY - prevY;

  window.scrollBy({ top: offset, behavior: 'auto' });
}

const MoveAction = (props: UnitToolbarActionProps) => {
  const { editor, unit } = useUnitToolbarProps();
  const moveUpButtonRef = useRef<HTMLButtonElement>(null);
  const moveDownButtonRef = useRef<HTMLButtonElement>(null);
  const moveUpPointerYRef = useRef<number | null>(null);
  const moveDownPointerYRef = useRef<number | null>(null);

  const handleMoveUp = useCallback(() => {
    const prevY = moveUpPointerYRef.current;
    editor.commands.moveUpUnit(unit.id);
    requestAnimationFrame(() => {
      if (moveUpButtonRef.current !== null && prevY !== null) {
        scrollToPointer(moveUpButtonRef.current, prevY);
      }
    });
  }, [unit, editor.commands]);

  const handleMoveDown = useCallback(() => {
    const prevY = moveDownPointerYRef.current;
    editor.commands.moveDownUnit(unit.id);
    requestAnimationFrame(() => {
      if (moveDownButtonRef.current !== null && prevY !== null) {
        scrollToPointer(moveDownButtonRef.current, prevY);
      }
    });
  }, [unit, editor.commands]);

  const handleMoveUpPointerDown = (event: React.PointerEvent) => {
    moveUpPointerYRef.current = event.clientY;
  };

  const handleMoveDownPointerDown = (event: React.PointerEvent) => {
    moveDownPointerYRef.current = event.clientY;
  };
  return (
    <ToolbarButtonGroup {...props}>
      <ToolbarButton
        ref={moveUpButtonRef}
        size="large"
        label="上に移動"
        commands={['Mod', 'Shift', 'ArrowUp']}
        onClick={handleMoveUp}
        onPointerDown={handleMoveUpPointerDown}
        disabled={editor.selectors.isFirstIndexUnit(unit.id)}
      >
        <Icon name="keyboard_arrow_up" />
      </ToolbarButton>
      <ToolbarButton
        ref={moveDownButtonRef}
        size="large"
        label="下に移動"
        commands={['Mod', 'Shift', 'ArrowDown']}
        onClick={handleMoveDown}
        disabled={editor.selectors.isLastIndexUnit(unit.id)}
        onPointerDown={handleMoveDownPointerDown}
      >
        <Icon name="keyboard_arrow_down" />
      </ToolbarButton>
    </ToolbarButtonGroup>
  );
};

export default MoveAction;
