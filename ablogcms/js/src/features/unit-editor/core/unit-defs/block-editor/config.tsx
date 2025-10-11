import { Grid, GridItem } from '@components/grid';
import VStack from '@components/stack/v-stack';
import { useSettings } from '@features/unit-editor/components/config-editor/stores/settings';
import { lazy, Suspense } from 'react';
import type { UnitConfigEditProps } from '../../types';

const BlockEditor = lazy(
  () => import(/* webpackChunkName: "block-editor" */ '@features/block-editor/components/BlockEditor/BlockEditor')
);

const Config = ({ config, editor }: UnitConfigEditProps) => {
  const { unitGroup } = useSettings();

  return (
    <VStack spacing="1rem" align="stretch">
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
            本文
          </span>
        </GridItem>
        <GridItem col={{ xs: 12, sm: 10 }}>
          <Suspense fallback={null}>
            <BlockEditor
              {...ACMS.Config.blockEditorConfig.editorProps}
              editorProps={{
                ...ACMS.Config.blockEditorConfig.editorProps.editorProps,
                attributes: {
                  ...ACMS.Config.blockEditorConfig.editorProps.editorProps.attributes,
                  'aria-labelledby': `${config.id}-field_1`,
                },
              }}
              defaultValue={config.field_1}
              onUpdate={(value) => editor.update(config.id, (config) => ({ ...config, field_1: value }))}
            />
          </Suspense>
        </GridItem>
      </Grid>
    </VStack>
  );
};

export default Config;
