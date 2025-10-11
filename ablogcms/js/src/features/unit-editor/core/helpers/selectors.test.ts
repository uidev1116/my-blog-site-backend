import { describe, it, expect } from 'vitest';
import type { UnitTree } from '../types/unit';
import {
  findUnitById,
  findUnitPosition,
  findUnitIndex,
  findParentUnit,
  isFirstIndexUnit,
  isLastIndexUnit,
  isFirstPositionUnit,
  isLastPositionUnit,
  getSelectedUnitIds,
  isSelectedUnit,
  getSelectedUnits,
  getNextUnit,
  getPreviousUnit,
} from './selectors';
import { createMockEditor, createMockUnit } from './test-utils';

describe('selectors', () => {
  const mockUnits: UnitTree = [
    createMockUnit({
      id: '1',
      children: [
        createMockUnit({
          id: '1-1',
          children: [createMockUnit({ id: '1-1-1' })],
        }),
        createMockUnit({
          id: '1-2',
        }),
      ],
    }),
    createMockUnit({
      id: '2',
      children: [createMockUnit({ id: '2-1' })],
    }),
  ];

  describe('findUnitById', () => {
    it('should find a unit at the root level', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      const result = findUnitById(editor, '1');
      expect(result).toEqual(mockUnits[0]);
    });

    it('should find a unit in the first level of children', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      const result = findUnitById(editor, '1-1');
      expect(result).toEqual(mockUnits[0].children![0]);
    });

    it('should find a unit in the second level of children', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      const result = findUnitById(editor, '1-1-1');
      expect(result).toEqual(mockUnits[0].children![0].children![0]);
    });

    it('should return null when unit is not found', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      const result = findUnitById(editor, 'non-existent');
      expect(result).toBeNull();
    });
  });

  describe('findUnitPosition', () => {
    it('should find position of a unit at the root level', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      const result = findUnitPosition(editor, '1');
      expect(result).toEqual({ index: 0, rootId: undefined });
    });

    it('should find position of a unit in the first level of children', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      const result = findUnitPosition(editor, '1-1');
      expect(result).toEqual({ index: 0, rootId: '1' });
    });

    it('should find position of a unit in the second level of children', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      const result = findUnitPosition(editor, '1-1-1');
      expect(result).toEqual({ index: 0, rootId: '1-1' });
    });

    it('should return null when unit is not found', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      const result = findUnitPosition(editor, 'non-existent');
      expect(result).toBeNull();
    });
  });

  describe('findUnitIndex', () => {
    it('should find index of a unit at the root level', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      const result = findUnitIndex(editor, '1');
      expect(result).toBe(0);
    });

    it('should find index of a unit in the first level of children', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      const result = findUnitIndex(editor, '1-1');
      expect(result).toBe(0);
    });

    it('should throw error when unit is not found', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      expect(() => findUnitIndex(editor, 'non-existent')).toThrow('Unit with id non-existent not found');
    });
  });

  describe('findParentUnit', () => {
    it('should find parent of a unit in the first level of children', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      const result = findParentUnit(editor, '1-1');
      expect(result).toEqual(mockUnits[0]);
    });

    it('should find parent of a unit in the second level of children', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      const result = findParentUnit(editor, '1-1-1');
      expect(result).toEqual(mockUnits[0].children![0]);
    });

    it('should return null when parent is not found', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      const result = findParentUnit(editor, '1');
      expect(result).toBeNull();
    });

    it('should return null when unit is not found', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      const result = findParentUnit(editor, 'non-existent');
      expect(result).toBeNull();
    });
  });

  describe('isFirstPositionUnit', () => {
    it('should return true for first unit at root level', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      const result = isFirstPositionUnit(editor, '1');
      expect(result).toBe(true);
    });

    it('should return false for non-first unit at root level', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      const result = isFirstPositionUnit(editor, '2');
      expect(result).toBe(false);
    });

    it('should return false for first unit in children', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      const result = isFirstPositionUnit(editor, '1-1');
      expect(result).toBe(false);
    });

    it('should return false for last unit in children', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      const result = isFirstPositionUnit(editor, '1-2');
      expect(result).toBe(false);
    });

    it('should return false for non-first unit in children', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      const result = isFirstPositionUnit(editor, '1-1-1');
      expect(result).toBe(false);
    });

    it('should throw error when unit is not found', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      expect(() => isFirstPositionUnit(editor, 'non-existent')).toThrow('Unit with id non-existent not found');
    });
  });

  describe('isLastPositionUnit', () => {
    it('should return true for last unit at root level', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      const result = isLastPositionUnit(editor, '2');
      expect(result).toBe(true);
    });

    it('should return false for non-last unit at root level', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      const result = isLastPositionUnit(editor, '1');
      expect(result).toBe(false);
    });

    it('should return false for first unit in children', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      const result = isLastPositionUnit(editor, '1-1');
      expect(result).toBe(false);
    });

    it('should return false for last unit in children', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      const result = isLastPositionUnit(editor, '1-2');
      expect(result).toBe(false);
    });

    it('should return false for non-last unit in children', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      const result = isLastPositionUnit(editor, '1-1-1');
      expect(result).toBe(false);
    });

    it('should throw error when unit is not found', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      expect(() => isLastPositionUnit(editor, 'non-existent')).toThrow('Unit with id non-existent not found');
    });
  });

  describe('isFirstIndexUnit', () => {
    it('should return true for first unit at root level', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      const result = isFirstIndexUnit(editor, '1');
      expect(result).toBe(true);
    });

    it('should return true for first unit in children', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      const result = isFirstIndexUnit(editor, '1-1');
      expect(result).toBe(true);
    });

    it('should return false for non-first unit at root level', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      const result = isFirstIndexUnit(editor, '2');
      expect(result).toBe(false);
    });

    it('should return false for non-first unit in children', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      const result = isFirstIndexUnit(editor, '1-2');
      expect(result).toBe(false);
    });
  });

  describe('isLastIndexUnit', () => {
    it('should return true for last unit at root level', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      const result = isLastIndexUnit(editor, '2');
      expect(result).toBe(true);
    });

    it('should return true for last unit in children', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      const result = isLastIndexUnit(editor, '1-2');
      expect(result).toBe(true);
    });

    it('should return false for non-last unit at root level', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      const result = isLastIndexUnit(editor, '1');
      expect(result).toBe(false);
    });

    it('should return false for non-last unit in children', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      const result = isLastIndexUnit(editor, '1-1');
      expect(result).toBe(false);
    });
  });

  describe('getSelectedUnitId', () => {
    it('should return selected unit ID when a unit is selected', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      editor.state.selectedUnitIds = ['1'];

      const result = getSelectedUnitIds(editor);

      expect(result).toEqual(['1']);
    });

    it('should return null when no unit is selected', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      editor.state.selectedUnitIds = [];

      const result = getSelectedUnitIds(editor);

      expect(result).toEqual([]);
    });

    it('should return first selected unit ID when multiple units are selected', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      editor.state.selectedUnitIds = ['1', '2'];

      const result = getSelectedUnitIds(editor);

      expect(result).toEqual(['1', '2']);
    });
  });

  describe('isSelectedUnit', () => {
    it('should return true when unit is selected', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      editor.state.selectedUnitIds = ['1'];

      const result = isSelectedUnit(editor, '1');

      expect(result).toBe(true);
    });

    it('should return false when unit is not selected', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      editor.state.selectedUnitIds = ['1'];

      const result = isSelectedUnit(editor, '2');

      expect(result).toBe(false);
    });

    it('should return false when no unit is selected', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      editor.state.selectedUnitIds = [];

      const result = isSelectedUnit(editor, '1');

      expect(result).toBe(false);
    });

    it('should return true when unit is in multiple selections', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      editor.state.selectedUnitIds = ['1', '2'];

      const result = isSelectedUnit(editor, '2');

      expect(result).toBe(true);
    });
  });

  describe('getSelectedUnit', () => {
    it('should return selected unit when a unit is selected', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      editor.state.selectedUnitIds = ['1'];

      const result = getSelectedUnits(editor);

      expect(result).toEqual([mockUnits[0]]);
    });

    it('should return null when no unit is selected', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      editor.state.selectedUnitIds = [];

      const result = getSelectedUnits(editor);

      expect(result).toEqual([]);
    });

    it('should return empty array when selected unit ID does not exist', () => {
      const editor = createMockEditor({
        units: mockUnits,
      });
      editor.state.selectedUnitIds = ['non-existent'];

      const result = getSelectedUnits(editor);

      expect(result).toEqual([]);
    });
  });

  describe('keyboard navigation selectors', () => {
    describe('getNextUnit', () => {
      it('should return first unit when no unit is selected', () => {
        const editor = createMockEditor({
          units: mockUnits,
        });

        const result = getNextUnit(editor);

        expect(result).toEqual(expect.objectContaining({ id: '1' }));
      });

      it('should return next unit when a unit is selected', () => {
        const editor = createMockEditor({
          units: mockUnits,
        });

        editor.state.selectedUnitIds = ['1'];
        const result = getNextUnit(editor);

        expect(result).toEqual(expect.objectContaining({ id: '1-1' }));
      });

      it('should return first unit when last unit is selected', () => {
        const editor = createMockEditor({
          units: mockUnits,
        });

        editor.state.selectedUnitIds = ['2'];
        const result = getNextUnit(editor);

        expect(result).toEqual(expect.objectContaining({ id: '2-1' }));
      });

      it('should return null when no units exist', () => {
        const editor = createMockEditor({
          units: [],
        });

        const result = getNextUnit(editor);

        expect(result).toBeNull();
      });
    });

    describe('getPreviousUnit', () => {
      it('should return last unit when no unit is selected', () => {
        const editor = createMockEditor({
          units: mockUnits,
        });

        const result = getPreviousUnit(editor);

        expect(result).toEqual(expect.objectContaining({ id: '2-1' }));
      });

      it('should return previous unit when a unit is selected', () => {
        const editor = createMockEditor({
          units: mockUnits,
        });
        editor.state.selectedUnitIds = ['2'];
        const result = getPreviousUnit(editor);

        expect(result).toEqual(expect.objectContaining({ id: '1-2' }));
      });

      it('should return last unit when first unit is selected', () => {
        const editor = createMockEditor({
          units: mockUnits,
        });

        editor.state.selectedUnitIds = ['1'];
        const result = getPreviousUnit(editor);

        expect(result).toEqual(expect.objectContaining({ id: '2-1' }));
      });

      it('should return null when no units exist', () => {
        const editor = createMockEditor({
          units: [],
        });

        const result = getPreviousUnit(editor);

        expect(result).toBeNull();
      });
    });
  });
});
