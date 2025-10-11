import { Grid, GridItem } from '@components/grid';
import VStack from '@components/stack/v-stack';
import { useSettings } from '@features/unit-editor/components/config-editor/stores/settings';
import { useCallback, useRef } from 'react';
import useEffectOnce from '@hooks/use-effect-once';
import type { UnitAlign, UnitConfigEditProps } from '../../types';
import setupAtable from '../../../../../lib/a-table';

interface TableEditorProps {
  value?: string;
  onChange: (value: string) => void;
}

const defaultValue = `<table><tr><td></td><td></td><td></td></tr><tr><td></td><td></td><td></td></tr><tr><td></td><td></td><td></td></tr></table>`;

const TableEditor = ({ value = defaultValue, onChange }: TableEditorProps) => {
  const containerRef = useRef<HTMLDivElement>(null);
  const aTableRef = useRef<ReturnType<typeof setupAtable>>(null);
  const createDom = useCallback(() => {
    if (!containerRef.current) {
      return;
    }
    while (containerRef.current.firstChild) {
      containerRef.current.removeChild(containerRef.current.firstChild);
    }
    const div = document.createElement('div');
    const editableTable = document.createElement('div');
    editableTable.className = 'js-editable-table table';

    editableTable.innerHTML = value;

    div.appendChild(editableTable);
    containerRef.current.appendChild(div);
    return div;
  }, [value]);

  const initATable = useCallback(() => {
    if (!containerRef.current) {
      return;
    }
    const dom = createDom();
    if (!dom) {
      return;
    }
    aTableRef.current = setupAtable(dom, {
      onChange,
    });
  }, [onChange, createDom]);

  const destroyATable = useCallback(() => {
    if (aTableRef.current) {
      aTableRef.current = null;
    }
  }, []);

  useEffectOnce(() => {
    initATable();
    return () => {
      destroyATable();
    };
  });

  return <div ref={containerRef} />;
};

const Config = ({ config, editor }: UnitConfigEditProps) => {
  const { align, unitGroup } = useSettings();

  return (
    <VStack spacing="1rem" align="stretch">
      {editor.editor.selectors.canAlignUnit(config.type, align.version) && (
        <Grid>
          <GridItem col={{ xs: 12, sm: 2 }}>
            <label htmlFor={`${config.id}-align`} className="acms-admin-col-form-label">
              配置
            </label>
          </GridItem>
          <GridItem col={{ xs: 12, sm: 10 }}>
            <select
              id={`${config.id}-align`}
              value={config.align}
              onChange={(e) =>
                editor.update(config.id, (config) => ({ ...config, align: e.target.value as UnitAlign }))
              }
            >
              {editor.editor.selectors.getAlignOptions(config.type, align.version).map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </GridItem>
        </Grid>
      )}
      {unitGroup.enable && (
        <Grid>
          <GridItem col={{ xs: 12, sm: 2 }}>
            <label htmlFor={`${config.id}-group`} className="acms-admin-col-form-label">
              グループ
            </label>
          </GridItem>
          <GridItem col={{ xs: 12, sm: 10 }}>
            <select
              id={`${config.id}-group`}
              value={config.group}
              onChange={(e) => editor.update(config.id, (config) => ({ ...config, group: e.target.value }))}
            >
              {unitGroup.options.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </GridItem>
        </Grid>
      )}
      <Grid>
        <GridItem col={{ xs: 12, sm: 2 }}>
          <span id={`${config.id}-field_1`} className="acms-admin-col-form-label">
            テーブル
          </span>
        </GridItem>
        <GridItem col={{ xs: 12, sm: 10 }}>
          <TableEditor
            value={config.field_1}
            onChange={(value) => editor.update(config.id, (config) => ({ ...config, field_1: value }))}
          />
        </GridItem>
      </Grid>
    </VStack>
  );
};

export default Config;
