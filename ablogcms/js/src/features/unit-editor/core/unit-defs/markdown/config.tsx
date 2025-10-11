import { Grid, GridItem } from '@components/grid';
import VStack from '@components/stack/v-stack';
import { useSettings } from '@features/unit-editor/components/config-editor/stores/settings';
import type { UnitConfigEditProps } from '../../types';

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
          <label htmlFor={`${config.id}-field_1`} className="acms-admin-col-form-label">
            マークダウン
          </label>
        </GridItem>
        <GridItem col={{ xs: 12, sm: 10 }}>
          <textarea
            id={`${config.id}-field_1`}
            className="acms-admin-form-width-full acms-admin-form-auto-size"
            style={{ '--acms-admin-form-control-min-height': '5em' } as React.CSSProperties}
            value={config.field_1}
            onChange={(e) => editor.update(config.id, (config) => ({ ...config, field_1: e.target.value }))}
            placeholder="マークダウンを入力してください"
          />
        </GridItem>
      </Grid>
    </VStack>
  );
};

export default Config;
