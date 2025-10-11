import { Grid, GridItem } from '@components/grid';
import VStack from '@components/stack/v-stack';
import { useSettings } from '@features/unit-editor/components/config-editor/stores/settings';
import type { UnitAlign, UnitConfigEditProps } from '../../types';

const Config = ({ config, editor }: UnitConfigEditProps) => {
  const { align, unitGroup, sizeOptions } = useSettings();

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
          <label htmlFor={`${config.id}-size`} className="acms-admin-col-form-label">
            画像の大きさ
          </label>
        </GridItem>
        <GridItem col={{ xs: 12, sm: 10 }}>
          <select
            id={`${config.id}-size`}
            value={config.size}
            onChange={(e) => editor.update(config.id, (config) => ({ ...config, size: e.target.value }))}
          >
            {sizeOptions.image.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </GridItem>
      </Grid>
      <Grid>
        <GridItem col={{ xs: 12, sm: 2 }}>
          <label htmlFor={`${config.id}-edit`} className="acms-admin-col-form-label">
            画像の編集
          </label>
        </GridItem>
        <GridItem col={{ xs: 12, sm: 10 }}>
          <select
            id={`${config.id}-edit`}
            value={config.edit}
            onChange={(e) => editor.update(config.id, (config) => ({ ...config, edit: e.target.value }))}
          >
            <option value="none">編集しない</option>
            <option value="deleteLarge">拡大表示させない</option>
          </select>
        </GridItem>
      </Grid>
      <Grid>
        <GridItem col={{ xs: 12, sm: 2 }}>
          <label htmlFor={`${config.id}-field_1`} className="acms-admin-col-form-label">
            キャプション
          </label>
        </GridItem>
        <GridItem col={{ xs: 12, sm: 10 }}>
          <input
            id={`${config.id}-field_1`}
            type="text"
            className="acms-admin-form-width-full"
            value={config.field_1}
            onChange={(e) => editor.update(config.id, (config) => ({ ...config, field_1: e.target.value }))}
          />
        </GridItem>
      </Grid>
      <Grid>
        <GridItem col={{ xs: 12, sm: 2 }}>
          <label htmlFor={`${config.id}-field_3`} className="acms-admin-col-form-label">
            リンク
          </label>
        </GridItem>
        <GridItem col={{ xs: 12, sm: 10 }}>
          <input
            id={`${config.id}-field_3`}
            type="text"
            className="acms-admin-form-width-full"
            value={config.field_3}
            onChange={(e) => editor.update(config.id, (config) => ({ ...config, field_3: e.target.value }))}
          />
        </GridItem>
      </Grid>
      <Grid>
        <GridItem col={{ xs: 12, sm: 2 }}>
          <label htmlFor={`${config.id}-field_4`} className="acms-admin-col-form-label">
            代替テキスト
          </label>
        </GridItem>
        <GridItem col={{ xs: 12, sm: 10 }}>
          <textarea
            id={`${config.id}-field_4`}
            className="acms-admin-form-width-full acms-admin-form-auto-size"
            style={{ '--acms-admin-form-control-min-height': '5em' } as React.CSSProperties}
            value={config.field_4}
            onChange={(e) => editor.update(config.id, (config) => ({ ...config, field_4: e.target.value }))}
          />
        </GridItem>
      </Grid>
    </VStack>
  );
};

export default Config;
