import { describe, it, expect } from 'vitest';
import type { UnitPosition, UnitTreeNode } from '../types';
import {
  insertUnit,
  removeUnit,
  updateUnit,
  setUnitStatus,
  moveUnitToPosition,
  moveUpUnit,
  moveDownUnit,
  setUnitAlign,
  setUnitGroup,
  insertAfterUnit,
  insertBeforeUnit,
  setUnitCollapsed,
  toggleUnitCollapsed,
  duplicateUnit,
  selectUnit,
  deselectUnit,
  deselectAll,
  selectNextUnit,
  selectPreviousUnit,
  wrapUnits,
  unwrapUnit,
} from './commands';
import { createMockEditor, createMockUnit, createMockUnitDef } from './test-utils';

describe('unit-operations', () => {
  describe('insertUnit', () => {
    it('should add a unit at the end when no position is specified', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '1' })];
      const newUnit = createMockUnit({ id: 'new-unit' });
      const result = insertUnit(editor, newUnit);
      expect(result.units).toHaveLength(2);
      expect(result.units[1].id).toBe('new-unit');
    });

    it('should add a unit at the specified position', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '1' }), createMockUnit({ id: '2' })];
      const newUnit = createMockUnit({ id: 'new-unit' });
      const newPosition: UnitPosition = { index: 1 };
      const result = insertUnit(editor, newUnit, newPosition);
      expect(result.units).toHaveLength(3);
      expect(result.units[1].id).toBe('new-unit');
    });

    it('should add multiple units at once', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '1' }), createMockUnit({ id: '2' })];
      const newUnits = [createMockUnit({ id: 'new-unit-1' }), createMockUnit({ id: 'new-unit-2' })];
      const newPosition: UnitPosition = { index: 1 };
      const result = insertUnit(editor, newUnits, newPosition);
      expect(result.units).toHaveLength(4);
      expect(result.units[1].id).toBe('new-unit-1');
      expect(result.units[2].id).toBe('new-unit-2');
    });

    it('should handle empty units array', () => {
      const editor = createMockEditor();
      const newUnit = createMockUnit({ id: 'new-unit' });
      const result = insertUnit(editor, newUnit);
      expect(result.units).toHaveLength(1);
      expect(result.units[0].id).toBe('new-unit');
    });

    it('should throw error when index is negative', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '1' })];
      const newUnit = createMockUnit({ id: 'new-unit' });
      const newPosition: UnitPosition = { index: -1 };
      expect(() => insertUnit(editor, newUnit, newPosition)).toThrow(RangeError);
    });

    it('should throw error when index is greater than array length', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '1' })];
      const newUnit = createMockUnit({ id: 'new-unit' });
      const newPosition: UnitPosition = { index: 5 };
      expect(() => insertUnit(editor, newUnit, newPosition)).toThrow(RangeError);
    });

    it('should insert unit into nested structure when rootId is specified', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '1', children: [createMockUnit({ id: '2' })] })];
      const newUnit = createMockUnit({ id: 'new-unit' });
      const newPosition: UnitPosition = { index: 1, rootId: '1' };
      const result = insertUnit(editor, newUnit, newPosition);
      expect(result.units[0].children).toHaveLength(2);
      expect(result.units[0].children?.[1].id).toBe('new-unit');
    });

    it('should insert multiple units into nested structure', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '1', children: [createMockUnit({ id: '2' })] })];
      const newUnits = [createMockUnit({ id: 'new-unit-1' }), createMockUnit({ id: 'new-unit-2' })];
      const newPosition: UnitPosition = { index: 1, rootId: '1' };
      const result = insertUnit(editor, newUnits, newPosition);
      expect(result.units[0].children).toHaveLength(3);
      expect(result.units[0].children?.[1].id).toBe('new-unit-1');
      expect(result.units[0].children?.[2].id).toBe('new-unit-2');
    });

    it('should handle deeply nested structures', () => {
      const editor = createMockEditor({
        units: [
          createMockUnit({ id: '1', children: [createMockUnit({ id: '2', children: [createMockUnit({ id: '3' })] })] }),
        ],
      });
      const newUnit = createMockUnit({ id: '4' });
      const newPosition: UnitPosition = { index: 1, rootId: '2' };
      const result = insertUnit(editor, newUnit, newPosition);
      expect(result.units[0].children?.[0].children).toHaveLength(2);
      expect(result.units[0].children?.[0].children?.[1].id).toBe('4');
    });

    it('should not modify structure when rootId is not found', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '1' })];
      const newUnit = createMockUnit({ id: '2' });
      const newPosition: UnitPosition = { index: 0, rootId: 'non-existent' };
      const result = insertUnit(editor, newUnit, newPosition);
      expect(result.units).toEqual(editor.state.units);
    });
  });

  describe('insertUnit with validation rules', () => {
    it('should not allow nested unit when supports.nested is false', () => {
      const editor = createMockEditor();
      const mock = createMockUnitDef({ supports: { nested: false } });
      editor.registerUnitDefinition('mock', mock);
      editor.state.units = [createMockUnit({ id: '1', children: [createMockUnit({ id: '2' })] })];
      const newUnit = createMockUnit({ id: '3' });
      const newPosition: UnitPosition = { index: 1, rootId: '1' };

      const result = insertUnit(editor, newUnit, newPosition);
      expect(result).toEqual(editor.state); // Should not change when validation fails
    });

    it('should allow non-nested unit when supports.nested is false', () => {
      const editor = createMockEditor();
      const mock = createMockUnitDef({ supports: { nested: false } });
      editor.registerUnitDefinition('mock', mock);
      editor.state.units = [createMockUnit({ id: '1' })];
      const newUnit = createMockUnit({ id: '2' });
      const newPosition: UnitPosition = { index: 1 };

      const result = insertUnit(editor, newUnit, newPosition);
      expect(result.units).toHaveLength(2);
      expect(result.units[1].id).toBe('2');
    });

    it('should not allow multiple units when supports.multiple is false', () => {
      const editor = createMockEditor();
      const mock = createMockUnitDef({ supports: { multiple: false } });
      editor.registerUnitDefinition('mock', mock);
      editor.state.units = [createMockUnit({ id: '1' })];
      const newUnit = createMockUnit({ id: '2' });

      const result = insertUnit(editor, newUnit);
      expect(result.units).toEqual(editor.state.units); // Should not change when validation fails
    });

    it('should allow single unit when supports.multiple is false and no existing unit', () => {
      const editor = createMockEditor();
      const mock = createMockUnitDef({ supports: { multiple: false } });
      editor.registerUnitDefinition('mock', mock);
      editor.state.units = [];
      const newUnit = createMockUnit({ id: '1' });

      const result = insertUnit(editor, newUnit);
      expect(result.units).toHaveLength(1);
      expect(result.units[0].id).toBe('1');
    });
  });

  describe('removeUnit', () => {
    it('should remove a unit by id', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '1' }), createMockUnit({ id: '2' })];
      const result = removeUnit(editor, '1');
      expect(result.units).toHaveLength(1);
      expect(result.units[0].id).toBe('2');
    });

    it('should remove a unit and its children', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '1', children: [createMockUnit({ id: '2' })] })];
      const result = removeUnit(editor, '1');
      expect(result.units).toHaveLength(0);
    });
  });

  describe('updateUnit', () => {
    it('should update a unit by id', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '1' })];
      const result = updateUnit(editor, '1', { type: 'group' });
      expect(result.units[0].type).toBe('group');
    });
  });

  describe('setUnitStatus', () => {
    it('should update unit status', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '1', status: 'open' })];
      const result = setUnitStatus(editor, '1', 'close');
      expect(result.units[0].status).toBe('close');
    });

    it('should update unit status back to open', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '1', status: 'close' })];
      const result = setUnitStatus(editor, '1', 'open');
      expect(result.units[0].status).toBe('open');
    });
  });

  describe('moveUnitToPosition', () => {
    it('should change unit order', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '1' }), createMockUnit({ id: '2' })];
      const newPosition: UnitPosition = { index: 1 };
      const result = moveUnitToPosition(editor, '1', newPosition);
      expect(result.units[0].id).toBe('2');
      expect(result.units[1].id).toBe('1');
    });

    it('should throw error when newPosition is negative', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '1' }), createMockUnit({ id: '2' })];
      const newPosition: UnitPosition = { index: -1 };
      expect(() => moveUnitToPosition(editor, '1', newPosition)).toThrow(RangeError);
    });

    it('should throw error when newPosition is greater than the number of units', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '1' }), createMockUnit({ id: '2' })];
      const newPosition: UnitPosition = { index: 3 };
      expect(() => moveUnitToPosition(editor, '1', newPosition)).toThrow(RangeError);
    });

    it('should not change unit order when newPosition is the same as the current position', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '1' }), createMockUnit({ id: '2' })];
      const newPosition: UnitPosition = { index: 0 };
      const result = moveUnitToPosition(editor, '1', newPosition);
      expect(result.units).toEqual(editor.state.units);
    });

    it('should move unit to the end of the array', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '1' }), createMockUnit({ id: '2' })];
      const newPosition: UnitPosition = { index: editor.state.units.length - 1 };
      const result = moveUnitToPosition(editor, '1', newPosition);
      expect(result.units[0].id).toBe('2');
      expect(result.units[1].id).toBe('1');
    });

    it('should move unit to the beginning of the array', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '1' }), createMockUnit({ id: '2' })];
      const newPosition: UnitPosition = { index: 0 };
      const result = moveUnitToPosition(editor, '1', newPosition);
      expect(result.units[0].id).toBe('1');
      expect(result.units[1].id).toBe('2');
    });
    it('should move unit within nested structure', () => {
      const editor = createMockEditor();
      editor.state.units = [
        createMockUnit({
          id: '1',
          children: [createMockUnit({ id: '2' }), createMockUnit({ id: '3' }), createMockUnit({ id: '4' })],
        }),
      ];
      const newPosition: UnitPosition = { index: 2, rootId: '1' };
      const result = moveUnitToPosition(editor, '2', newPosition);

      expect(result.units[0].children?.[0].id).toBe('3');
      expect(result.units[0].children?.[1].id).toBe('4');
      expect(result.units[0].children?.[2].id).toBe('2');
    });

    it('should not affect other nested units when moving unit within specific parent', () => {
      const editor = createMockEditor({
        units: [
          createMockUnit({ id: '1', children: [createMockUnit({ id: '2' }), createMockUnit({ id: '3' })] }),
          createMockUnit({ id: '4', children: [createMockUnit({ id: '5' }), createMockUnit({ id: '6' })] }),
        ],
      });
      const newPosition: UnitPosition = { index: 1, rootId: '1' };
      const result = moveUnitToPosition(editor, '2', newPosition);

      expect(result.units[0].children?.[0].id).toBe('3');
      expect(result.units[0].children?.[1].id).toBe('2');
      expect(result.units[1].children?.[0].id).toBe('5');
      expect(result.units[1].children?.[1].id).toBe('6');
    });

    it('should move unit from parent A to parent B', () => {
      const editor = createMockEditor({
        units: [
          createMockUnit({ id: '1', children: [createMockUnit({ id: '2' }), createMockUnit({ id: '3' })] }),
          createMockUnit({ id: '4', children: [createMockUnit({ id: '5' }), createMockUnit({ id: '6' })] }),
        ],
      });
      const newPosition: UnitPosition = { index: 1, rootId: '4' };
      const result = moveUnitToPosition(editor, '2', newPosition);

      expect(result.units[0].children).toHaveLength(1);
      expect(result.units[0].children?.[0].id).toBe('3');
      expect(result.units[1].children).toHaveLength(3);
      expect(result.units[1].children?.[1].id).toBe('2');
    });

    it('should move unit from nested structure to root level', () => {
      const editor = createMockEditor({
        units: [
          createMockUnit({ id: '1', children: [createMockUnit({ id: '2' }), createMockUnit({ id: '3' })] }),
          createMockUnit({ id: '4' }),
        ],
      });
      const newPosition: UnitPosition = { index: 1 };
      const result = moveUnitToPosition(editor, '2', newPosition);

      expect(result.units).toHaveLength(3);
      expect(result.units[0].children).toHaveLength(1);
      expect(result.units[0].children?.[0].id).toBe('3');
      expect(result.units[1].id).toBe('2');
      expect(result.units[2].id).toBe('4');
    });

    it('should move unit from third level to second level', () => {
      const editor = createMockEditor({
        units: [
          createMockUnit({
            id: '1',
            children: [
              createMockUnit({ id: '2', children: [createMockUnit({ id: '3' }), createMockUnit({ id: '4' })] }),
            ],
          }),
        ],
      });
      const newPosition: UnitPosition = { index: 1, rootId: '1' };
      const result = moveUnitToPosition(editor, '3', newPosition);

      expect(result.units[0].children).toHaveLength(2);
      expect(result.units[0].children?.[0].id).toBe('2');
      expect(result.units[0].children?.[0].children).toHaveLength(1);
      expect(result.units[0].children?.[0].children?.[0].id).toBe('4');
      expect(result.units[0].children?.[1].id).toBe('3');
    });

    it('should not allow moving unit to nested position when supports.nested is false', () => {
      const editor = createMockEditor({
        units: [createMockUnit({ id: '1' }), createMockUnit({ id: '2', children: [createMockUnit({ id: '3' })] })],
        unitDefs: {
          mock: createMockUnitDef({ supports: { nested: false } }),
        },
      });
      const newPosition: UnitPosition = { index: 1, rootId: '2' };

      const result = moveUnitToPosition(editor, '1', newPosition);
      expect(result.units).toEqual(editor.state.units); // Should not change when validation fails
    });

    it('should allow moving unit to non-nested position when supports.nested is false', () => {
      const editor = createMockEditor({
        units: [createMockUnit({ id: '1', children: [createMockUnit({ id: '2' })] }), createMockUnit({ id: '3' })],
        unitDefs: {
          mock: createMockUnitDef({ supports: { nested: false } }),
        },
      });
      const newPosition: UnitPosition = { index: 1 };

      const result = moveUnitToPosition(editor, '2', newPosition);
      expect(result.units[1].id).toBe('2'); // Unit should be moved to root level
    });

    it('should allow moving nested unit to root level when supports.nested is false', () => {
      const editor = createMockEditor({
        units: [createMockUnit({ id: '1', children: [createMockUnit({ id: '2' })] })],
        unitDefs: {
          mock: createMockUnitDef({ supports: { nested: false } }),
        },
      });
      const newPosition: UnitPosition = { index: 1 };

      const result = moveUnitToPosition(editor, '2', newPosition);
      expect(result.units).toHaveLength(2);
      expect(result.units[1].id).toBe('2'); // Unit should be moved to root level
    });
  });

  describe('moveUpUnit', () => {
    it('should move unit up', () => {
      const editor = createMockEditor({
        units: [createMockUnit({ id: '1' }), createMockUnit({ id: '2' })],
      });
      const result = moveUpUnit(editor, '2');
      expect(result.units[0].id).toBe('2');
      expect(result.units[1].id).toBe('1');
    });
  });

  describe('moveDownUnit', () => {
    it('should move unit down', () => {
      const editor = createMockEditor({
        units: [createMockUnit({ id: '1' }), createMockUnit({ id: '2' })],
      });
      const result = moveDownUnit(editor, '1');
      expect(result.units[0].id).toBe('2');
      expect(result.units[1].id).toBe('1');
    });
  });

  describe('setUnitAlign', () => {
    it('should update unit alignment', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '1' })];
      const result = setUnitAlign(editor, '1', 'center');
      expect(result.units[0].align).toBe('center');
    });
  });

  describe('setUnitGroup', () => {
    it('should update unit group', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '1' })];
      const result = setUnitGroup(editor, '1', 'header');
      expect(result.units[0].group).toBe('header');
    });
  });

  describe('insertAfterUnit', () => {
    it('should insert unit after specified unit in top level', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '1' }), createMockUnit({ id: '3' })];
      const newUnit = createMockUnit({ id: '2' });
      const result = insertAfterUnit(editor, '1', newUnit);
      expect(result.units).toHaveLength(3);
      expect(result.units[1].id).toBe('2');
    });

    it('should insert unit after specified unit in nested structure', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '1', children: [createMockUnit({ id: '2' })] })];
      const newUnit = createMockUnit({ id: '3' });
      const result = insertAfterUnit(editor, '2', newUnit);
      expect(result.units[0].children).toHaveLength(2);
      expect(result.units[0].children?.[1].id).toBe('3');
    });

    it('should insert multiple units after specified unit', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '1' }), createMockUnit({ id: '4' })];
      const newUnits = [createMockUnit({ id: '2' }), createMockUnit({ id: '3' })];
      const result = insertAfterUnit(editor, '1', newUnits);
      expect(result.units).toHaveLength(4);
      expect(result.units[1].id).toBe('2');
      expect(result.units[2].id).toBe('3');
    });

    it('should not modify units when target unit is not found', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '1' })];
      const newUnit = createMockUnit({ id: '2' });
      const result = insertAfterUnit(editor, 'non-existent', newUnit);
      expect(result).toEqual(editor.state);
    });

    it('should insert unit after specified unit in deeply nested structure', () => {
      const editor = createMockEditor();
      editor.state.units = [
        createMockUnit({ id: '1', children: [createMockUnit({ id: '2', children: [createMockUnit({ id: '3' })] })] }),
      ];
      const newUnit = createMockUnit({ id: '4' });
      const result = insertAfterUnit(editor, '3', newUnit);
      expect(result.units[0].children?.[0].children).toHaveLength(2);
      expect(result.units[0].children?.[0].children?.[1].id).toBe('4');
    });

    it('should insert unit after specified unit in three level nested structure', () => {
      const editor = createMockEditor();
      editor.state.units = [
        createMockUnit({
          id: '1',
          children: [
            createMockUnit({
              id: '2',
              children: [createMockUnit({ id: '3', children: [createMockUnit({ id: '4' })] })],
            }),
          ],
        }),
      ];
      const newUnit = createMockUnit({ id: '5' });
      const result = insertAfterUnit(editor, '4', newUnit);
      expect(result.units[0].children?.[0].children?.[0].children).toHaveLength(2);
      expect(result.units[0].children?.[0].children?.[0].children?.[1].id).toBe('5');
    });
  });

  describe('insertBeforeUnit', () => {
    it('should insert unit before specified unit in top level', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '2' }), createMockUnit({ id: '3' })];
      const newUnit = createMockUnit({ id: '1' });
      const result = insertBeforeUnit(editor, '2', newUnit);
      expect(result.units).toHaveLength(3);
      expect(result.units[0].id).toBe('1');
    });

    it('should insert unit before specified unit in nested structure', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '1', children: [createMockUnit({ id: '2' })] })];
      const newUnit = createMockUnit({ id: '3' });
      const result = insertBeforeUnit(editor, '2', newUnit);
      expect(result.units[0].children).toHaveLength(2);
      expect(result.units[0].children?.[0].id).toBe('3');
    });

    it('should insert multiple units before specified unit', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '3' }), createMockUnit({ id: '4' })];
      const newUnits = [createMockUnit({ id: '1' }), createMockUnit({ id: '2' })];
      const result = insertBeforeUnit(editor, '3', newUnits);
      expect(result.units).toHaveLength(4);
      expect(result.units[0].id).toBe('1');
      expect(result.units[1].id).toBe('2');
    });

    it('should not modify units when target unit is not found', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '1' })];
      const newUnit = createMockUnit({ id: '2' });
      const result = insertBeforeUnit(editor, 'non-existent', newUnit);
      expect(result.units).toEqual(editor.state.units);
    });

    it('should insert unit before specified unit in deeply nested structure', () => {
      const editor = createMockEditor();
      editor.state.units = [
        createMockUnit({ id: '1', children: [createMockUnit({ id: '2', children: [createMockUnit({ id: '3' })] })] }),
      ];
      const newUnit = createMockUnit({ id: '4' });
      const result = insertBeforeUnit(editor, '3', newUnit);
      expect(result.units[0].children?.[0].children).toHaveLength(2);
      expect(result.units[0].children?.[0].children?.[0].id).toBe('4');
    });

    it('should insert unit before specified unit in three level nested structure', () => {
      const editor = createMockEditor();
      editor.state.units = [
        createMockUnit({
          id: '1',
          children: [
            createMockUnit({
              id: '2',
              children: [createMockUnit({ id: '3', children: [createMockUnit({ id: '4' })] })],
            }),
          ],
        }),
      ];
      const newUnit = createMockUnit({ id: '5' });
      const result = insertBeforeUnit(editor, '4', newUnit);
      expect(result.units[0].children?.[0].children?.[0].children).toHaveLength(2);
      expect(result.units[0].children?.[0].children?.[0].children?.[0].id).toBe('5');
    });
  });

  describe('setUnitCollapsed', () => {
    it('should update unit collapsed', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '1' })];
      const result = setUnitCollapsed(editor, '1', true);
      expect(result.units[0].collapsed).toBe(true);
    });

    it('should update unit collapsed back to false', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '1', status: 'close' })];
      const result = setUnitCollapsed(editor, '1', false);
      expect(result.units[0].collapsed).toBe(false);
    });
  });

  describe('toggleUnitCollapsed', () => {
    it('should toggle unit collapsed', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '1' })];
      const result = toggleUnitCollapsed(editor, '1');
      expect(result.units[0].collapsed).toBe(true);
    });

    it('should toggle unit collapsed back to false', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '1', status: 'close', collapsed: true })];
      const result = toggleUnitCollapsed(editor, '1');
      expect(result.units[0].collapsed).toBe(false);
    });
  });

  describe('duplicateUnit', () => {
    it('should duplicate a unit', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '1' })];
      const result = duplicateUnit(editor, '1');
      expect(result.units).toHaveLength(2);
      expect(result.units[0].id).toBe('1');
      expect(result.units[1].id).not.toBe('1');
    });

    it('should duplicate a unit with children', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '1', children: [createMockUnit({ id: '2' })] })];
      const result = duplicateUnit(editor, '1');
      expect(result.units).toHaveLength(2);
      expect(result.units[0].children).toHaveLength(1);
      expect(result.units[1].children).toHaveLength(1);
      expect(result.units[0].children?.[0].id).toBe('2');
      expect(result.units[1].children?.[0].id).not.toBe('2');
    });

    it('should duplicate a nested unit', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '1', children: [createMockUnit({ id: '2' })] })];
      const result = duplicateUnit(editor, '2');
      expect(result.units[0].children).toHaveLength(2);
      expect(result.units[0].children?.[0].id).toBe('2');
      expect(result.units[0].children?.[1].id).not.toBe('2');
    });

    it('should not duplicate unit when supports.duplicate is false', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '1' })];
      editor.registerUnitDefinition('mock', createMockUnitDef({ supports: { duplicate: false } }));
      const result = duplicateUnit(editor, '1');
      expect(result.units).toEqual(editor.state.units); // Should not change when validation fails
    });

    it('should not duplicate unit when child has supports.duplicate is false', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '1', children: [createMockUnit({ id: '2' })] })];
      editor.registerUnitDefinition('mock', createMockUnitDef({ supports: { duplicate: false } }));
      const result = duplicateUnit(editor, '1');
      expect(result.units).toEqual(editor.state.units); // Should not change when validation fails
    });

    it('should throw error when unit not found', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '1' })];
      expect(() => duplicateUnit(editor, 'non-existent')).toThrow('Duplicate unit failed because unit not found');
    });

    it('should update attributes with new IDs when duplicating', () => {
      const originalUnit = createMockUnit({ id: 'original-id' });
      originalUnit.attributes = {
        'field-original-id': 'value1',
        'other-field': 'value2',
      };
      const editor = createMockEditor();
      editor.state.units = [originalUnit];
      const result = duplicateUnit(editor, 'original-id');
      expect(result.units).toHaveLength(2);

      const duplicatedUnit = result.units[1];
      if (typeof duplicatedUnit.attributes !== 'string') {
        expect(duplicatedUnit.attributes['other-field']).toBe('value2');
        expect(duplicatedUnit.attributes[`field-${duplicatedUnit.id}`]).toBe('value1');
        expect(duplicatedUnit.attributes['field-original-id']).toBeUndefined();
      }
    });

    it('should not duplicate unit when supports.duplicate is function returning false', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '1' })];
      editor.registerUnitDefinition('mock', createMockUnitDef({ supports: { duplicate: false } }));
      const result = duplicateUnit(editor, '1');
      expect(result.units).toEqual(editor.state.units); // Should not change when validation fails
    });

    it('should duplicate unit when supports.duplicate is function returning true', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '1' })];
      editor.registerUnitDefinition('mock', createMockUnitDef({ supports: { duplicate: true } }));
      const result = duplicateUnit(editor, '1');
      expect(result.units).toHaveLength(2);
      expect(result.units[0].id).toBe('1');
      expect(result.units[1].id).not.toBe('1');
    });

    it('should duplicate unit when supports.duplicate is function that checks unit properties', () => {
      const unitWithSpecialAttribute = createMockUnit({ id: '1' });
      unitWithSpecialAttribute.attributes = { special: 'value' };

      const editor = createMockEditor();
      editor.state.units = [unitWithSpecialAttribute, createMockUnit({ id: '2' })];
      editor.registerUnitDefinition(
        'mock',
        createMockUnitDef({
          supports: {
            duplicate: (unit: UnitTreeNode) => {
              return unit.attributes && typeof unit.attributes === 'object' && 'special' in unit.attributes;
            },
          },
        })
      );

      // Unit with special attribute should be duplicatable
      const result1 = duplicateUnit(editor, '1');
      expect(result1.units).toHaveLength(3);

      // Unit without special attribute should not be duplicatable
      const result2 = duplicateUnit(editor, '2');
      expect(result2.units).toEqual(editor.state.units);
    });

    it('should not duplicate unit when child has supports.duplicate as function returning false', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '1', children: [createMockUnit({ id: '2' })] })];
      editor.registerUnitDefinition('mock', createMockUnitDef({ supports: { duplicate: false } }));
      const result = duplicateUnit(editor, '1');
      expect(result.units).toEqual(editor.state.units); // Should not change when validation fails
    });

    it('should duplicate unit when child has supports.duplicate as function returning true', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '1', children: [createMockUnit({ id: '2' })] })];
      editor.registerUnitDefinition('mock', createMockUnitDef({ supports: { duplicate: true } }));
      const result = duplicateUnit(editor, '1');
      expect(result.units).toHaveLength(2);
      expect(result.units[0].children).toHaveLength(1);
      expect(result.units[1].children).toHaveLength(1);
    });
  });

  describe('moveUnitToPosition with moveHierarchy support', () => {
    it('should not allow moving unit to different hierarchy when supports.moveHierarchy is false', () => {
      const editor = createMockEditor();
      editor.state.units = [
        createMockUnit({ id: '1' }),
        createMockUnit({ id: '2', children: [createMockUnit({ id: '3' })] }),
      ];
      editor.registerUnitDefinition('mock', createMockUnitDef({ supports: { moveHierarchy: false } }));
      const newPosition: UnitPosition = { index: 1, rootId: '2' };

      const result = moveUnitToPosition(editor, '1', newPosition);
      expect(result.units).toEqual(editor.state.units); // Should not change when validation fails
    });

    it('should allow moving unit within same hierarchy when supports.moveHierarchy is false', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '1' }), createMockUnit({ id: '2' })];
      editor.registerUnitDefinition('mock', createMockUnitDef({ supports: { moveHierarchy: false } }));
      const newPosition: UnitPosition = { index: 1 };

      const result = moveUnitToPosition(editor, '1', newPosition);
      expect(result.units[0].id).toBe('2');
      expect(result.units[1].id).toBe('1');
    });

    it('should allow moving nested unit within same parent when supports.moveHierarchy is false', () => {
      const editor = createMockEditor();
      editor.state.units = [
        createMockUnit({
          id: '1',
          children: [createMockUnit({ id: '2' }), createMockUnit({ id: '3' }), createMockUnit({ id: '4' })],
        }),
      ];
      editor.registerUnitDefinition('mock', createMockUnitDef({ supports: { moveHierarchy: false } }));
      const newPosition: UnitPosition = { index: 2, rootId: '1' };

      const result = moveUnitToPosition(editor, '2', newPosition);
      expect(result.units[0].children?.[0].id).toBe('3');
      expect(result.units[0].children?.[1].id).toBe('4');
      expect(result.units[0].children?.[2].id).toBe('2');
    });

    it('should not allow moving nested unit to root when supports.moveHierarchy is false', () => {
      const editor = createMockEditor();
      editor.state.units = [
        createMockUnit({ id: '1', children: [createMockUnit({ id: '2' })] }),
        createMockUnit({ id: '3' }),
      ];
      editor.registerUnitDefinition('mock', createMockUnitDef({ supports: { moveHierarchy: false } }));
      const newPosition: UnitPosition = { index: 1 };

      const result = moveUnitToPosition(editor, '2', newPosition);
      expect(result.units).toEqual(editor.state.units); // Should not change when validation fails
    });

    it('should not allow moving unit from one parent to another when supports.moveHierarchy is false', () => {
      const editor = createMockEditor();
      editor.state.units = [
        createMockUnit({ id: '1', children: [createMockUnit({ id: '2' })] }),
        createMockUnit({ id: '3', children: [createMockUnit({ id: '4' })] }),
      ];
      editor.registerUnitDefinition('mock', createMockUnitDef({ supports: { moveHierarchy: false } }));
      const newPosition: UnitPosition = { index: 1, rootId: '3' };

      const result = moveUnitToPosition(editor, '2', newPosition);
      expect(result.units).toEqual(editor.state.units); // Should not change when validation fails
    });

    it('should not allow moving unit when child has supports.moveHierarchy is false', () => {
      const editor = createMockEditor();
      editor.state.units = [
        createMockUnit({ id: '1', children: [createMockUnit({ id: '2' })] }),
        createMockUnit({ id: '3' }),
      ];
      editor.registerUnitDefinition('mock', createMockUnitDef({ supports: { moveHierarchy: false } }));
      const newPosition: UnitPosition = { index: 1, rootId: '3' };

      const result = moveUnitToPosition(editor, '1', newPosition);
      expect(result.units).toEqual(editor.state.units); // Should not change when validation fails
    });

    it('should allow moving unit to different hierarchy when supports.moveHierarchy is true (default)', () => {
      const editor = createMockEditor();
      editor.state.units = [
        createMockUnit({ id: '1' }),
        createMockUnit({ id: '2', children: [createMockUnit({ id: '3' })] }),
      ];
      editor.registerUnitDefinition('mock', createMockUnitDef({ supports: { moveHierarchy: true } }));
      const newPosition: UnitPosition = { index: 1, rootId: '2' };

      const result = moveUnitToPosition(editor, '1', newPosition);
      expect(result.units[0].children).toHaveLength(2);
      expect(result.units[0].children?.[1].id).toBe('1');
    });

    it('should not allow moving unit when supports.moveHierarchy is function returning false', () => {
      const editor = createMockEditor();
      editor.state.units = [
        createMockUnit({ id: '1' }),
        createMockUnit({ id: '2', children: [createMockUnit({ id: '3' })] }),
      ];
      editor.registerUnitDefinition('mock', createMockUnitDef({ supports: { moveHierarchy: false } }));
      const newPosition: UnitPosition = { index: 1, rootId: '2' };

      const result = moveUnitToPosition(editor, '1', newPosition);
      expect(result.units).toEqual(editor.state.units); // Should not change when validation fails
    });

    it('should allow moving unit when supports.moveHierarchy is function returning true', () => {
      const editor = createMockEditor();
      editor.state.units = [
        createMockUnit({ id: '1' }),
        createMockUnit({ id: '2', children: [createMockUnit({ id: '3' })] }),
      ];
      editor.registerUnitDefinition('mock', createMockUnitDef({ supports: { moveHierarchy: true } }));
      const newPosition: UnitPosition = { index: 1, rootId: '2' };

      const result = moveUnitToPosition(editor, '1', newPosition);
      expect(result.units[0].children).toHaveLength(2);
      expect(result.units[0].children?.[1].id).toBe('1');
    });

    it('should allow moving unit when supports.moveHierarchy is function that checks unit properties', () => {
      const unitWithSpecialAttribute = createMockUnit({ id: '1' });
      unitWithSpecialAttribute.attributes = { special: 'value' };

      const editor = createMockEditor();
      editor.state.units = [
        unitWithSpecialAttribute,
        createMockUnit({ id: '2' }),
        createMockUnit({
          id: '3',
          children: [createMockUnit({ id: '4' })],
        }),
      ];

      editor.registerUnitDefinition(
        'mock',
        createMockUnitDef({
          supports: {
            moveHierarchy: (unit: UnitTreeNode) => {
              return unit.attributes && typeof unit.attributes === 'object' && 'special' in unit.attributes;
            },
          },
        })
      );

      // Unit with special attribute should be movable to different hierarchy
      const newPosition1: UnitPosition = { index: 1, rootId: '3' };
      const result1 = moveUnitToPosition(editor, '1', newPosition1);
      expect(result1.units[1].children).toHaveLength(2);
      expect(result1.units[1].children?.[1].id).toBe('1');

      // Unit without special attribute should not be movable to different hierarchy
      const newPosition2: UnitPosition = { index: 1, rootId: '3' };
      const result2 = moveUnitToPosition(editor, '2', newPosition2);
      expect(result2.units).toEqual(editor.state.units);
    });

    it('should not allow moving unit when child has supports.moveHierarchy as function returning false', () => {
      const editor = createMockEditor();
      editor.state.units = [
        createMockUnit({ id: '1', children: [createMockUnit({ id: '2' })] }),
        createMockUnit({ id: '3' }),
      ];
      editor.registerUnitDefinition('mock', createMockUnitDef({ supports: { moveHierarchy: false } }));
      const newPosition: UnitPosition = { index: 1, rootId: '3' };

      const result = moveUnitToPosition(editor, '1', newPosition);
      expect(result.units).toEqual(editor.state.units); // Should not change when validation fails
    });

    it('should allow moving unit within same hierarchy when supports.moveHierarchy is function returning false', () => {
      const editor = createMockEditor();
      editor.state.units = [createMockUnit({ id: '1' }), createMockUnit({ id: '2' })];
      editor.registerUnitDefinition('mock', createMockUnitDef({ supports: { moveHierarchy: false } }));
      const newPosition: UnitPosition = { index: 1 };

      const result = moveUnitToPosition(editor, '1', newPosition);
      expect(result.units[0].id).toBe('2');
      expect(result.units[1].id).toBe('1');
    });
  });
  describe('selection commands', () => {
    describe('selectUnit', () => {
      it('should return selected unit ID array', () => {
        const editor = createMockEditor({
          units: [createMockUnit({ id: '1' }), createMockUnit({ id: '2' })],
        });

        const result = selectUnit(editor, '1');

        expect(result.selectedUnitIds).toEqual(['1']);
      });

      it('should return new selection when selecting a different unit', () => {
        const editor = createMockEditor({
          units: [createMockUnit({ id: '1' }), createMockUnit({ id: '2' })],
        });

        const result1 = selectUnit(editor, '1');
        const result2 = selectUnit(editor, '2');

        expect(result1.selectedUnitIds).toEqual(['1']);
        expect(result2.selectedUnitIds).toEqual(['2']);
      });
    });

    describe('deselectUnit', () => {
      it('should return empty array when deselecting selected unit', () => {
        const editor = createMockEditor({
          units: [createMockUnit({ id: '1' }), createMockUnit({ id: '2' })],
        });

        editor.state.selectedUnitIds = ['1'];
        const result = deselectUnit(editor, '1');

        expect(result.selectedUnitIds).toEqual([]);
      });

      it('should return current selection when deselecting non-selected unit', () => {
        const editor = createMockEditor({
          units: [createMockUnit({ id: '1' }), createMockUnit({ id: '2' })],
        });

        editor.state.selectedUnitIds = ['1'];
        const result = deselectUnit(editor, '2'); // 選択されていないユニットを解除

        expect(result.selectedUnitIds).toEqual(['1']);
      });
    });

    describe('deselectAll', () => {
      it('should return empty array', () => {
        const editor = createMockEditor({
          units: [createMockUnit({ id: '1' }), createMockUnit({ id: '2' })],
        });

        editor.state.selectedUnitIds = ['1'];
        const result = deselectAll(editor);

        expect(result.selectedUnitIds).toEqual([]);
      });
    });
  });

  describe('keyboard navigation commands', () => {
    describe('selectNextUnit', () => {
      it('should select first unit when no unit is selected', () => {
        const editor = createMockEditor({
          units: [createMockUnit({ id: '1' }), createMockUnit({ id: '2' })],
        });

        const result = selectNextUnit(editor);

        expect(result.selectedUnitIds).toEqual(['1']);
      });

      it('should select next unit when a unit is selected', () => {
        const editor = createMockEditor({
          units: [createMockUnit({ id: '1' }), createMockUnit({ id: '2' })],
        });

        editor.state.selectedUnitIds = ['1'];
        const result = selectNextUnit(editor);

        expect(result.selectedUnitIds).toEqual(['2']);
      });

      it('should select first unit when last unit is selected', () => {
        const editor = createMockEditor({
          units: [createMockUnit({ id: '1' }), createMockUnit({ id: '2' })],
        });

        editor.state.selectedUnitIds = ['2'];
        const result = selectNextUnit(editor);

        expect(result.selectedUnitIds).toEqual(['1']);
      });

      it('should return empty array when no units exist', () => {
        const editor = createMockEditor({
          units: [],
        });

        const result = selectNextUnit(editor);

        expect(result.selectedUnitIds).toEqual([]);
      });
    });

    describe('selectPreviousUnit', () => {
      it('should select last unit when no unit is selected', () => {
        const editor = createMockEditor({
          units: [createMockUnit({ id: '1' }), createMockUnit({ id: '2' })],
        });

        const result = selectPreviousUnit(editor);

        expect(result.selectedUnitIds).toEqual(['2']);
      });

      it('should select previous unit when a unit is selected', () => {
        const editor = createMockEditor({
          units: [createMockUnit({ id: '1' }), createMockUnit({ id: '2' })],
        });

        editor.state.selectedUnitIds = ['2'];
        const result = selectPreviousUnit(editor);

        expect(result.selectedUnitIds).toEqual(['1']);
      });

      it('should select last unit when first unit is selected', () => {
        const editor = createMockEditor({
          units: [createMockUnit({ id: '1' }), createMockUnit({ id: '2' })],
        });

        editor.state.selectedUnitIds = ['1'];
        const result = selectPreviousUnit(editor);

        expect(result.selectedUnitIds).toEqual(['2']);
      });

      it('should return empty array when no units exist', () => {
        const editor = createMockEditor({
          units: [],
        });

        const result = selectPreviousUnit(editor);

        expect(result.selectedUnitIds).toEqual([]);
      });
    });
  });

  describe('wrapUnits', () => {
    it('should wrap consecutive units in a group', () => {
      const editor = createMockEditor({
        units: [createMockUnit({ id: '1' }), createMockUnit({ id: '2' }), createMockUnit({ id: '3' })],
      });

      const unit = createMockUnit({ id: 'group' });
      const result = wrapUnits(editor, unit, ['1', '2']);

      expect(result.units).toHaveLength(2);
      expect(result.units[0].children).toHaveLength(2);
      expect(result.units[0].children?.[0].id).toBe('1');
      expect(result.units[0].children?.[1].id).toBe('2');
      expect(result.units[1].id).toBe('3');
      expect(result.selectedUnitIds).toEqual([result.units[0].id]);
    });

    it('should not wrap units with different parents', () => {
      const editor = createMockEditor({
        units: [createMockUnit({ id: '1', children: [createMockUnit({ id: '2' })] }), createMockUnit({ id: '3' })],
      });

      const unit = createMockUnit({ id: 'group' });
      const result = wrapUnits(editor, unit, ['2', '3']);

      expect(result.units).toEqual(editor.state.units);
    });

    it('should not wrap non-consecutive units', () => {
      const editor = createMockEditor({
        units: [createMockUnit({ id: '1' }), createMockUnit({ id: '2' }), createMockUnit({ id: '3' })],
      });

      const unit = createMockUnit({ id: 'group' });
      const result = wrapUnits(editor, unit, ['1', '3']);

      expect(result.units).toEqual(editor.state.units);
    });

    it('should not wrap empty unit array', () => {
      const editor = createMockEditor({
        units: [createMockUnit({ id: '1' })],
      });

      const unit = createMockUnit({ id: 'group' });
      const result = wrapUnits(editor, unit, []);

      expect(result.units).toEqual(editor.state.units);
    });

    it('should wrap units in nested structure', () => {
      const editor = createMockEditor({
        units: [
          createMockUnit({
            id: '1',
            children: [createMockUnit({ id: '2' }), createMockUnit({ id: '3' }), createMockUnit({ id: '4' })],
          }),
        ],
      });

      const unit = createMockUnit({ id: 'group' });
      const result = wrapUnits(editor, unit, ['2', '3']);

      expect(result.units[0].children).toHaveLength(2);
      expect(result.units[0].children?.[0].children).toHaveLength(2);
      expect(result.units[0].children?.[0].children?.[0].id).toBe('2');
      expect(result.units[0].children?.[0].children?.[1].id).toBe('3');
      expect(result.units[0].children?.[1].id).toBe('4');
    });

    it('should handle single unit wrapping', () => {
      const editor = createMockEditor({
        units: [createMockUnit({ id: '1' })],
      });

      const unit = createMockUnit({ id: 'group' });
      const result = wrapUnits(editor, unit, ['1']);

      // 単一ユニットのラップでは、元のユニットが削除されて新しいグループが挿入される
      expect(result.units).toHaveLength(1);
      expect(result.units[0].children).toHaveLength(1);
      expect(result.units[0].children?.[0].id).toBe('1');
    });

    it('should not wrap when unit does not support nesting', () => {
      const editor = createMockEditor({
        units: [createMockUnit({ id: '1' }), createMockUnit({ id: '2' })],
        unitDefs: {
          mock: createMockUnitDef({ supports: { nested: false } }),
        },
      });

      const unit = createMockUnit({ id: 'group' });
      const result = wrapUnits(editor, unit, ['1', '2']);

      expect(result.units).toEqual(editor.state.units);
    });

    it('should not wrap when unit does not support hierarchy movement', () => {
      const editor = createMockEditor({
        units: [createMockUnit({ id: '1' }), createMockUnit({ id: '2' })],
        unitDefs: {
          mock: createMockUnitDef({ supports: { moveHierarchy: false } }),
        },
      });

      const unit = createMockUnit({ id: 'group' });
      const result = wrapUnits(editor, unit, ['1', '2']);

      expect(result.units).toEqual(editor.state.units);
    });

    it('should not wrap when child unit does not support nesting', () => {
      const editor = createMockEditor({
        units: [createMockUnit({ id: '1', children: [createMockUnit({ id: '2' })] }), createMockUnit({ id: '3' })],
        unitDefs: {
          mock: createMockUnitDef({ supports: { nested: false } }),
        },
      });

      const unit = createMockUnit({ id: 'group2' });
      const result = wrapUnits(editor, unit, ['2', '3']);

      expect(result.units).toEqual(editor.state.units);
    });

    it('should not wrap when child unit does not support hierarchy movement', () => {
      const editor = createMockEditor({
        units: [createMockUnit({ id: '1', children: [createMockUnit({ id: '2' })] }), createMockUnit({ id: '3' })],
        unitDefs: {
          mock: createMockUnitDef({ supports: { moveHierarchy: false } }),
        },
      });

      const unit = createMockUnit({ id: 'group2' });
      const result = wrapUnits(editor, unit, ['2', '3']);

      expect(result.units).toEqual(editor.state.units);
    });

    it('should wrap when unit supports nesting and hierarchy movement', () => {
      const editor = createMockEditor({
        units: [createMockUnit({ id: '1' }), createMockUnit({ id: '2' })],
        unitDefs: {
          mock: createMockUnitDef({ supports: { nested: true, moveHierarchy: true } }),
        },
      });

      const unit = createMockUnit({ id: 'group' });
      const result = wrapUnits(editor, unit, ['1', '2']);

      // バリデーションが通る場合、ユニットがラップされる
      expect(result.units).toHaveLength(1);
      expect(result.units[0].children).toHaveLength(2);
    });

    it('should not wrap when unit definition has function-based validation returning false', () => {
      const editor = createMockEditor({
        units: [createMockUnit({ id: '1' }), createMockUnit({ id: '2' })],
        unitDefs: {
          text: createMockUnitDef({
            type: 'text',
            supports: {
              moveHierarchy: false,
            },
          }),
        },
      });

      const unit = createMockUnit({ id: 'group' });
      const result = wrapUnits(editor, unit, ['1', '2']);

      expect(result.units).toEqual(editor.state.units);
    });

    it('should wrap when unit definition has function-based validation returning true', () => {
      const editor = createMockEditor({
        units: [createMockUnit({ id: '1' }), createMockUnit({ id: '2' })],
        unitDefs: {
          mock: createMockUnitDef({
            supports: {
              moveHierarchy: true,
            },
          }),
        },
      });

      const unit = createMockUnit({ id: 'group' });
      const result = wrapUnits(editor, unit, ['1', '2']);

      // バリデーションが通る場合、ユニットがラップされる
      expect(result.units).toHaveLength(1);
      expect(result.units[0].id).toBe('group');
      expect(result.units[0].children).toHaveLength(2);
    });
  });

  describe('unwrapUnit', () => {
    it('should unwrap group and move children to parent level', () => {
      const editor = createMockEditor({
        units: [
          createMockUnit({
            id: '1',
            children: [createMockUnit({ id: '2' }), createMockUnit({ id: '3' })],
          }),
        ],
      });

      const result = unwrapUnit(editor, '1');

      expect(result.units).toHaveLength(2);
      expect(result.units[0].id).toBe('2');
      expect(result.units[1].id).toBe('3');
    });

    it('should unwrap group in nested structure', () => {
      const editor = createMockEditor({
        units: [
          createMockUnit({
            id: '1',
            children: [
              createMockUnit({
                id: '2',
                children: [createMockUnit({ id: '3' }), createMockUnit({ id: '4' })],
              }),
            ],
          }),
        ],
      });

      const result = unwrapUnit(editor, '2');

      expect(result.units[0].children).toHaveLength(2);
      expect(result.units[0].children?.[0].id).toBe('3');
      expect(result.units[0].children?.[1].id).toBe('4');
    });

    it('should remove empty group', () => {
      const editor = createMockEditor({
        units: [createMockUnit({ id: '1', children: [] })],
      });

      const result = unwrapUnit(editor, '1');

      expect(result.units).toHaveLength(1);
    });

    it('should not unwrap non-group unit', () => {
      const editor = createMockEditor({
        units: [createMockUnit({ id: '1' })],
      });

      const result = unwrapUnit(editor, '1');

      expect(result.units).toEqual(editor.state.units);
    });

    it('should not unwrap non-existent unit', () => {
      const editor = createMockEditor({
        units: [createMockUnit({ id: '1', children: [createMockUnit({ id: '2' })] })],
      });

      const result = unwrapUnit(editor, 'non-existent');

      expect(result.units).toEqual(editor.state.units);
    });

    it('should preserve unit order when unwrapping', () => {
      const editor = createMockEditor({
        units: [
          createMockUnit({
            id: '1',
            children: [createMockUnit({ id: '2' }), createMockUnit({ id: '3' }), createMockUnit({ id: '4' })],
          }),
        ],
      });

      const result = unwrapUnit(editor, '1');

      // グループをアンラップすると、子ユニットが順序を保って親レベルに移動する
      expect(result.units).toHaveLength(3);
      expect(result.units[0].id).toBe('2');
      expect(result.units[1].id).toBe('3');
      expect(result.units[2].id).toBe('4');
    });

    it('should unwrap group with mixed content', () => {
      const editor = createMockEditor({
        units: [
          createMockUnit({
            id: '1',
            children: [
              createMockUnit({ id: '2' }),
              createMockUnit({ id: '3', children: [createMockUnit({ id: '4' })] }),
              createMockUnit({ id: '5' }),
            ],
          }),
        ],
      });

      const result = unwrapUnit(editor, '1');

      // 混合コンテンツのグループをアンラップすると、すべての子ユニットが親レベルに移動する
      expect(result.units).toHaveLength(3);
      expect(result.units[0].id).toBe('2');
      expect(result.units[1].id).toBe('3');
      expect(result.units[2].id).toBe('5');
      expect(result.units[1].children).toHaveLength(1);
      expect(result.units[1].children?.[0].id).toBe('4');
    });

    it('should not unwrap when unit does not support hierarchy movement', () => {
      const editor = createMockEditor({
        units: [createMockUnit({ id: '1', children: [createMockUnit({ id: '2' })] })],
        unitDefs: {
          text: createMockUnitDef({ type: 'text', supports: { moveHierarchy: false } }),
        },
      });

      const result = unwrapUnit(editor, '1');

      expect(result.units).toEqual(editor.state.units);
    });

    it('should not unwrap when child unit does not support hierarchy movement', () => {
      const editor = createMockEditor({
        units: [
          createMockUnit({
            id: '1',
            children: [createMockUnit({ id: '2', children: [createMockUnit({ id: '3' })] })],
          }),
        ],
        unitDefs: {
          text: createMockUnitDef({ type: 'text', supports: { moveHierarchy: false } }),
        },
      });

      const result = unwrapUnit(editor, '1');

      expect(result.units).toEqual(editor.state.units);
    });

    it('should unwrap when unit supports hierarchy movement', () => {
      const editor = createMockEditor({
        units: [createMockUnit({ id: '1', children: [createMockUnit({ id: '2' })] })],
        unitDefs: {
          mock: createMockUnitDef({ supports: { moveHierarchy: true } }),
        },
      });

      const result = unwrapUnit(editor, '1');

      expect(result.units).toHaveLength(1);
      expect(result.units[0].id).toBe('2');
    });

    it('should not unwrap when unit definition has function-based validation returning false', () => {
      const editor = createMockEditor({
        units: [createMockUnit({ id: '1', children: [createMockUnit({ id: '2' })] })],
        unitDefs: {
          mock: createMockUnitDef({
            supports: {
              moveHierarchy: false,
            },
          }),
        },
      });

      const result = unwrapUnit(editor, '1');

      expect(result.units).toEqual(editor.state.units);
    });

    it('should unwrap when unit definition has function-based validation returning true', () => {
      const editor = createMockEditor({
        units: [createMockUnit({ id: '1', children: [createMockUnit({ id: '2' })] })],
        unitDefs: {
          mock: createMockUnitDef({
            supports: {
              moveHierarchy: true,
            },
          }),
        },
      });

      const result = unwrapUnit(editor, '1');

      expect(result.units).toHaveLength(1);
      expect(result.units[0].id).toBe('2');
    });

    it('should not unwrap when child unit has function-based validation returning false', () => {
      const editor = createMockEditor({
        units: [
          createMockUnit({
            id: '1',
            children: [createMockUnit({ id: '2', children: [createMockUnit({ id: '3' })] })],
          }),
        ],
        unitDefs: {
          mock: createMockUnitDef({
            supports: {
              moveHierarchy: false,
            },
          }),
        },
      });

      const result = unwrapUnit(editor, '1');

      expect(result.units).toEqual(editor.state.units);
    });

    it('should unwrap when child unit has function-based validation returning true', () => {
      const editor = createMockEditor({
        units: [
          createMockUnit({
            id: '1',
            children: [createMockUnit({ id: '2', children: [createMockUnit({ id: '3' })] })],
          }),
        ],
        unitDefs: {
          mock: createMockUnitDef({
            supports: {
              moveHierarchy: true,
            },
          }),
        },
      });

      const result = unwrapUnit(editor, '1');

      // バリデーションが通る場合、グループがアンラップされる
      expect(result.units).toHaveLength(1);
      expect(result.units[0].children).toHaveLength(1);
      expect(result.units[0].children?.[0].id).toBe('3');
    });
  });
});
