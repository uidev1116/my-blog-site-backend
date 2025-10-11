import { describe, it, expect } from 'vitest';
import type { UnitTree, UnitList } from '@features/unit-editor/core';
import { flatten, nestify } from './index';

describe('flatten', () => {
  it('should flatten a nested tree structure into a flat list', () => {
    const input: UnitTree = [
      {
        id: '1',
        name: 'Unit 1',
        type: 'text',
        status: 'open',
        collapsed: false,
        attributes: {},
        children: [
          {
            id: '1-1',
            name: 'Unit 1-1',
            type: 'text',
            status: 'open',
            collapsed: false,
            attributes: {},
            children: [],
          },
          {
            id: '1-2',
            name: 'Unit 1-2',
            type: 'text',
            status: 'open',
            collapsed: false,
            attributes: {},
            children: [],
          },
        ],
      },
      {
        id: '2',
        name: 'Unit 2',
        type: 'text',
        status: 'open',
        collapsed: false,
        attributes: {},
        children: [],
      },
    ];

    const expected: UnitList = [
      { id: '1', name: 'Unit 1', type: 'text', status: 'open', collapsed: false, attributes: {}, parentId: null },
      { id: '1-1', name: 'Unit 1-1', type: 'text', status: 'open', collapsed: false, attributes: {}, parentId: '1' },
      { id: '1-2', name: 'Unit 1-2', type: 'text', status: 'open', collapsed: false, attributes: {}, parentId: '1' },
      { id: '2', name: 'Unit 2', type: 'text', status: 'open', collapsed: false, attributes: {}, parentId: null },
    ];

    expect(flatten(input)).toEqual(expected);
  });

  it('should handle empty tree', () => {
    const input: UnitTree = [];
    expect(flatten(input)).toEqual([]);
  });
});

describe('nestify', () => {
  it('should convert a flat list into a nested tree structure', () => {
    const input: UnitList = [
      { id: '1', name: 'Unit 1', type: 'text', status: 'open', collapsed: false, attributes: {}, parentId: null },
      { id: '1-1', name: 'Unit 1-1', type: 'text', status: 'open', collapsed: false, attributes: {}, parentId: '1' },
      { id: '1-2', name: 'Unit 1-2', type: 'text', status: 'open', collapsed: false, attributes: {}, parentId: '1' },
      { id: '2', name: 'Unit 2', type: 'text', status: 'open', collapsed: false, attributes: {}, parentId: null },
    ];

    const expected: UnitTree = [
      {
        id: '1',
        name: 'Unit 1',
        type: 'text',
        status: 'open',
        collapsed: false,
        attributes: {},
        children: [
          {
            id: '1-1',
            name: 'Unit 1-1',
            type: 'text',
            status: 'open',
            collapsed: false,
            attributes: {},
            children: [],
          },
          {
            id: '1-2',
            name: 'Unit 1-2',
            type: 'text',
            status: 'open',
            collapsed: false,
            attributes: {},
            children: [],
          },
        ],
      },
      {
        id: '2',
        name: 'Unit 2',
        type: 'text',
        status: 'open',
        collapsed: false,
        attributes: {},
        children: [],
      },
    ];

    expect(nestify(input)).toMatchObject(expected);
  });

  it('should handle empty list', () => {
    const input: UnitList = [];
    expect(nestify(input)).toEqual([]);
  });

  it('should handle items with missing parentId', () => {
    const input: UnitList = [
      { id: '1', name: 'Unit 1', type: 'text', status: 'open', collapsed: false, attributes: {}, parentId: null },
      { id: '2', name: 'Unit 2', type: 'text', status: 'open', collapsed: false, attributes: {}, parentId: '1' },
    ];

    const expected: UnitTree = [
      {
        id: '1',
        name: 'Unit 1',
        type: 'text',
        status: 'open',
        collapsed: false,
        attributes: {},
        children: [
          {
            id: '2',
            name: 'Unit 2',
            type: 'text',
            status: 'open',
            collapsed: false,
            attributes: {},
            children: [],
          },
        ],
      },
    ];

    expect(nestify(input)).toEqual(expected);
  });
});
