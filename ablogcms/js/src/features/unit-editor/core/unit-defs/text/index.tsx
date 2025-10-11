import type { UnitAlignOption, UnitAlignVersion, UnitDefInterface } from '../../types/unit';
import Edit from './edit';
import Config from './config';
import InplaceEdit from './inplace-edit';

const alignOptions: Record<UnitAlignVersion, UnitAlignOption[]> = {
  v1: [
    { value: 'center', label: ACMS.i18n('unit_editor.align.full') },
    { value: 'auto', label: ACMS.i18n('unit_editor.align.auto') },
  ],
  v2: [],
};

// 多言語ユニット対応のため、完全にReact化できていません。
const Text: UnitDefInterface = {
  type: 'text',
  name: 'テキスト',
  icon: 'title',
  edit: Edit,
  inplaceEdit: InplaceEdit,
  config: Config,
  supports: {
    align: (version) => alignOptions[version],
  },
};

export default Text;
