import { UnitConfigList, UnitConfigTree, UnitConfigTreeNode } from '@features/unit-editor/core/types';

export function insertConfig(tree: UnitConfigTree, config: UnitConfigTreeNode) {
  return [...tree, config];
}

export function removeConfig(tree: UnitConfigTree, id: UnitConfigTreeNode['id']) {
  const newTree: UnitConfigTree = [];

  for (const item of tree) {
    if (item.id === id) {
      continue;
    }

    if (item.children.length) {
      item.children = removeConfig(item.children, id);
    }

    newTree.push(item);
  }

  return newTree;
}

export function findConfig(tree: UnitConfigTree, id: UnitConfigTreeNode['id']): UnitConfigTreeNode | null {
  for (const item of tree) {
    if (item.id === id) {
      return item;
    }
  }

  for (const item of tree) {
    const found = findConfig(item.children, id);
    if (found) {
      return found;
    }
  }

  return null;
}

export function updateConfig(
  tree: UnitConfigTree,
  id: UnitConfigTreeNode['id'],
  data: UnitConfigTreeNode | ((config: UnitConfigTreeNode) => UnitConfigTreeNode)
) {
  const newTree: UnitConfigTree = [];

  for (const item of tree) {
    if (item.id === id) {
      const newItem = typeof data === 'function' ? data(item) : data;
      newTree.push(newItem);
      continue;
    }

    if (item.children.length) {
      item.children = updateConfig(item.children, id, data);
    }

    newTree.push(item);
  }

  return newTree;
}

export function flatten(items: UnitConfigTree, parentId: UnitConfigTreeNode['id'] | null = null) {
  return items.reduce((acc, item): UnitConfigList => {
    const { children, ...itemWithoutChildren } = item;
    return [...acc, { ...itemWithoutChildren, parentId }, ...flatten(children ?? [], item.id)];
  }, [] as UnitConfigList);
}

export function nestify(unitList: UnitConfigList): UnitConfigTree {
  const root = { id: 'root', children: [] };
  const nodes: Record<string, UnitConfigTreeNode | Pick<UnitConfigTreeNode, 'id' | 'children'>> = { [root.id]: root };
  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  const units = unitList.map(({ parentId: _, ...unit }) => ({ ...unit, children: [] as UnitConfigTree }));

  for (const unit of units) {
    const { id, children } = unit;
    const parentId = unitList.find((u) => u.id === id)?.parentId ?? root.id;
    const parent = nodes[parentId] ?? units.find((unit) => unit.id === parentId);

    nodes[id] = { id, children };
    if (!Array.isArray(parent.children)) {
      parent.children = [];
    }
    parent.children.push(unit);
  }

  return root.children;
}
