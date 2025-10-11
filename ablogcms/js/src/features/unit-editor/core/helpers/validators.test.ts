import { describe, it, expect } from 'vitest';
import type { UnitPosition } from '../types';
import { validateUnitNesting, validateUnitMultiple, validateUnitInsert } from './validators';
import { createMockEditor, createMockUnitDef } from './test-utils';

describe('validators', () => {
  describe('validateUnitNesting', () => {
    it('should return valid when supports is not defined', () => {
      const Mock = createMockUnitDef();
      const editor = createMockEditor({
        unitDefs: {
          mock: Mock,
        },
      });
      const unit = editor.createUnit('mock');

      const result = validateUnitNesting(editor, unit);
      expect(result.valid).toBe(true);
    });

    it('should return valid when nested is allowed (default)', () => {
      const Mock = createMockUnitDef({ supports: { nested: true } });
      const editor = createMockEditor({
        unitDefs: {
          mock: Mock,
        },
      });
      const unit = editor.createUnit('mock');

      const result = validateUnitNesting(editor, unit);
      expect(result.valid).toBe(true);
    });

    it('should return invalid when nested is not allowed', () => {
      const Mock = createMockUnitDef({ supports: { nested: false } });
      const editor = createMockEditor({
        unitDefs: {
          mock: Mock,
        },
      });
      const unit = editor.createUnit('mock');

      const result = validateUnitNesting(editor, unit);
      expect(result.valid).toBe(false);
    });
  });

  describe('validateUnitMultiple', () => {
    it('should return valid when supports is not defined', () => {
      const Mock = createMockUnitDef();
      const editor = createMockEditor({
        unitDefs: {
          mock: Mock,
        },
      });
      const unit = editor.createUnit('mock');

      const result = validateUnitMultiple(editor, unit);
      expect(result.valid).toBe(true);
    });

    it('should return valid when multiple is allowed (default)', () => {
      const Mock = createMockUnitDef({ supports: { multiple: true } });
      const editor = createMockEditor({
        unitDefs: {
          mock: Mock,
        },
      });
      const unit = editor.createUnit('mock');

      const result = validateUnitMultiple(editor, unit);
      expect(result.valid).toBe(true);
    });

    it('should return invalid when multiple is not allowed and unit type exists', () => {
      const Mock = createMockUnitDef({ supports: { multiple: false } });
      const editor = createMockEditor({
        unitDefs: {
          mock: Mock,
        },
      });
      const unit = editor.createUnit('mock');
      editor.state.units.push(unit);

      const result = validateUnitMultiple(editor, unit);
      expect(result.valid).toBe(false);
      if (!result.valid) {
        expect(result.reason).toBeDefined();
      }
    });

    it('should return valid when multiple is not allowed but unit type does not exist', () => {
      const Mock = createMockUnitDef({ supports: { multiple: false } });
      const editor = createMockEditor({
        unitDefs: {
          mock: Mock,
        },
      });
      const unit = editor.createUnit('mock');

      const result = validateUnitMultiple(editor, unit);
      expect(result.valid).toBe(true);
    });
  });

  describe('validateUnitInsert', () => {
    it('should return invalid when nesting validation fails', () => {
      const Mock = createMockUnitDef({ supports: { nested: false } });
      const editor = createMockEditor({
        unitDefs: {
          mock: Mock,
        },
      });
      const unit = editor.createUnit('mock');
      const position: UnitPosition = { index: 0, rootId: 'parent' };

      const result = validateUnitInsert(editor, unit, position);
      expect(result.valid).toBe(false);
      if (!result.valid) {
        expect(result.reason).toBeDefined();
      }
    });

    it('should return invalid when multiple validation fails', () => {
      const Mock = createMockUnitDef({ supports: { multiple: false } });
      const editor = createMockEditor({
        unitDefs: {
          mock: Mock,
        },
      });
      const unit = editor.createUnit('mock');
      editor.state.units.push(unit);

      const result = validateUnitInsert(editor, unit);
      expect(result.valid).toBe(false);
      if (!result.valid) {
        expect(result.reason).toBeDefined();
      }
    });

    it('should return valid when both validations pass', () => {
      const Mock = createMockUnitDef({ supports: { nested: true, multiple: true } });
      const editor = createMockEditor({
        unitDefs: {
          mock: Mock,
        },
      });
      const unit = editor.createUnit('mock');
      const position: UnitPosition = { index: 0, rootId: 'parent' };

      const result = validateUnitInsert(editor, unit, position);
      expect(result.valid).toBe(true);
    });
  });
});
