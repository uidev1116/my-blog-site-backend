import { Grid, GridItem } from '@components/grid';
import VStack from '@components/stack/v-stack';
import { useSettings } from '@features/unit-editor/components/config-editor/stores/settings';
import type { UnitAlign, UnitConfigEditProps } from '../../types';

const Config = ({ config, editor }: UnitConfigEditProps) => {
  const { textTagOptions, align, unitGroup } = useSettings();

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
      {textTagOptions.length > 0 && (
        <Grid>
          <GridItem col={{ xs: 12, sm: 2 }}>
            <label htmlFor={`${config.id}-field_2`} className="acms-admin-col-form-label">
              タグ
            </label>
          </GridItem>
          <GridItem col={{ xs: 12, sm: 10 }}>
            <select
              id={`${config.id}-field_2`}
              value={config.field_2}
              onChange={(e) => editor.update(config.id, (config) => ({ ...config, field_2: e.target.value }))}
            >
              {textTagOptions.map((option) => (
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
          <label htmlFor={`${config.id}-field_1`} className="acms-admin-col-form-label">
            本文
          </label>
        </GridItem>
        <GridItem col={{ xs: 12, sm: 10 }}>
          <textarea
            id={`${config.id}-field_1`}
            className="acms-admin-form-width-full"
            rows={3}
            value={config.field_1}
            onChange={(e) => editor.update(config.id, (config) => ({ ...config, field_1: e.target.value }))}
          />
        </GridItem>
      </Grid>
    </VStack>
  );
};

export default Config;
