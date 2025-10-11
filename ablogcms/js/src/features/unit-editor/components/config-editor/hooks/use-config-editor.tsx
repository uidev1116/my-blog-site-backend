import { useState, useCallback, useMemo } from 'react';
import type {
  ConfigEditor,
  UnitConfigList,
  UnitConfigTree,
  UnitConfigTreeNode,
} from '@features/unit-editor/core/types';
import { coreCommands, coreSelectors, coreUnitDefs } from '@features/unit-editor/core';
import useUnitEditor from '@features/unit-editor/hooks/use-unit-editor';
import { v4 as uuidv4 } from 'uuid';
import * as utils from '../utlis';

interface UseConfigEditorOptions {
  id: string;
  defaultValue?: UnitConfigList;
}

function generateId() {
  return uuidv4();
}

export default function useConfigEditor({ id, defaultValue = [] }: UseConfigEditorOptions): ConfigEditor | null {
  const [configs, setConfigs] = useState<UnitConfigTree>(() => {
    const list = defaultValue.map((config) => ({
      ...config,
      id: generateId(),
    }));
    return utils.nestify(list);
  });
  const editor = useUnitEditor({
    commands: coreCommands,
    selectors: coreSelectors,
    unitDefs: coreUnitDefs,
  });

  const insert = useCallback((config: UnitConfigTreeNode) => {
    setConfigs((prev) => utils.insertConfig(prev, config));
  }, []);

  const remove = useCallback((id: UnitConfigTreeNode['id']) => {
    setConfigs((prev) => utils.removeConfig(prev, id));
  }, []);

  const move = useCallback((id: UnitConfigTreeNode['id'], newIndex: number) => {
    setConfigs((prev) => {
      const unitIndex = prev.findIndex((unit) => unit.id === id);
      if (unitIndex === -1) {
        return prev;
      }

      const newUnits = [...prev];
      const [movedUnit] = newUnits.splice(unitIndex, 1);
      newUnits.splice(newIndex, 0, movedUnit);
      return newUnits;
    });
  }, []);

  const update = useCallback(
    (id: UnitConfigTreeNode['id'], data: UnitConfigTreeNode | ((config: UnitConfigTreeNode) => UnitConfigTreeNode)) => {
      setConfigs((prev) => utils.updateConfig(prev, id, data));
    },
    []
  );

  const find = useCallback(
    (id: UnitConfigTreeNode['id']) => {
      return utils.findConfig(configs, id);
    },
    [configs]
  );

  const create = useCallback(
    (name: string, options: { name?: string } = {}) => {
      if (!editor) {
        throw new Error('Editor not found');
      }

      const unitDef = editor?.findUnitDef(name);
      if (!unitDef) {
        throw new Error(`Unit definition not found for name: ${name}`);
      }

      const config: UnitConfigTreeNode = {
        id: generateId(),
        type: name,
        collapsed: true,
        children: [],
        name: options.name || unitDef.name,
      };

      return config;
    },
    [editor]
  );

  const namePrefix = useMemo(() => {
    return `column_def_${id}_`;
  }, [id]);

  if (!editor) {
    return null;
  }

  return {
    id,
    configs,
    namePrefix,
    insert,
    remove,
    move,
    update,
    find,
    create,
    flatten: utils.flatten,
    nestify: utils.nestify,
    editor,
  };
}
