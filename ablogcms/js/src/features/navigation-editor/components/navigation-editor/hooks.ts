import { useState, useCallback, useMemo } from 'react';
import clone from 'clone';
import type { Item as NestableItem } from 'react-nestable';
import { nestify, flatify } from 'nestify';
import { v4 as uuidv4 } from 'uuid';

export interface BaseNestableItem extends NestableItem {
  id: number;
  uuid: string;
  parent: number | null;
}

type DefineDefaultItem<T> = () => Omit<T, keyof BaseNestableItem>;

interface UseNestableEditReturn<T> {
  items: T[];
  setItems: React.Dispatch<React.SetStateAction<T[]>>;
  updateItem: <K extends keyof T>(id: number, key: K, value: T[K]) => void;
  addChild: (item: T) => void;
  removeItem: (id: number, confirmMessage: string) => void;
  nested: T[];
  reIndex: T[];
  handleNestableChange: (options: { items: T[]; dragItem: T; targetPath: number[] }) => void;
}

export const useNestableEdit = <T extends NestableItem>(
  initialItems: T[],
  defineDefaultItem: DefineDefaultItem<T>
): UseNestableEditReturn<T> => {
  const setup = useCallback(
    (items: T[]): T[] => {
      const def = defineDefaultItem();
      if (items.length === 0) {
        items.push({ parent: null, ...def } as unknown as T);
      }
      return items.map((item, i) => ({
        ...item,
        id: i + 1,
        uuid: item.uuid ?? uuidv4(),
        parent: item.parent ?? null,
      }));
    },
    [defineDefaultItem]
  );

  const [items, setItems] = useState<T[]>(setup(initialItems || []));

  const nested = useMemo(() => {
    // itemsの中に以前のchildren情報が残っている可能性があるので、一旦childrenを削除してリセット
    const itemsWithoutChildren = items.map((item) => {
      const rest = { ...item };
      delete rest.children;
      return rest;
    });
    return nestify({ id: 'id', parentId: 'parent', children: 'children' }, itemsWithoutChildren) as T[];
  }, [items]);

  const reIndex = useMemo(() => {
    const newItems = items.map((item) => {
      const index = items.findIndex(({ id }) => id === item.parent); // 親のインデックスを取得
      return { ...item, parent: index >= 0 ? index + 1 : null }; // 親のインデックスを1から始まるように調整
    });
    return newItems.map((item, i) => ({ ...item, id: i + 1 })); // インデックスを1から始まるように調整
  }, [items]);

  const updateItem = useCallback(
    <K extends keyof T>(id: number, key: K, value: T[K]) => {
      const newItems = items.map((item) => (item.id === id ? { ...item, [key]: value } : item));
      setItems(newItems);
    },
    [items]
  );

  const removeItem = useCallback(
    (id: number, confirmMessage: string) => {
      if (window.confirm(confirmMessage)) {
        setItems((prev) => {
          const target = prev.find((item) => item.id === id);
          if (!target) return prev;

          // 削除対象の子要素を親要素に移動
          const updated = prev.map((item) => (item.parent === id ? { ...item, parent: target.parent } : item));
          return updated.filter((item) => item.id !== id);
        });
      }
    },
    [setItems]
  );

  const addChild = useCallback(
    (item: T) => {
      const def = defineDefaultItem();
      const newItem = {
        parent: item.parent,
        id: items.length + 1,
        uuid: uuidv4(),
        ...def,
      } as unknown as T;
      const index = items.findIndex((target) => target.id === item.id);
      const inserted = [...items.slice(0, index + 1), newItem, ...items.slice(index + 1)];
      setItems(inserted);
    },
    [items, defineDefaultItem]
  );

  const handleNestableChange = useCallback(
    (options: { items: T[]; dragItem: T; targetPath: number[] }) => {
      const newItems = options.items.map((item) => ({ ...item, parent: null }));
      const flatifiedItems = flatify({ id: 'id', parentId: 'parent', children: 'children' }, clone(newItems)) as T[];
      setItems(flatifiedItems);
    },
    [setItems]
  );

  return {
    items,
    setItems,
    updateItem,
    addChild,
    removeItem,
    handleNestableChange,
    nested,
    reIndex,
  };
};

export default useNestableEdit;
