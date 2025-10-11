import type { UnitAlignOption, UnitAlignVersion, UnitDefInterface } from '../../types/unit';
import Edit from './edit';
import Config from './config';
import InplaceEdit from './inplace-edit';

const alignOptions: Record<UnitAlignVersion, UnitAlignOption[]> = {
  v1: [
    { value: 'center', label: ACMS.i18n('unit_editor.align.center') },
    { value: 'left', label: ACMS.i18n('unit_editor.align.left') },
    { value: 'right', label: ACMS.i18n('unit_editor.align.right') },
    { value: 'auto', label: ACMS.i18n('unit_editor.align.auto') },
  ],
  v2: [
    { value: 'center', label: ACMS.i18n('unit_editor.align.center') },
    { value: 'left', label: ACMS.i18n('unit_editor.align.left') },
    { value: 'right', label: ACMS.i18n('unit_editor.align.right') },
  ],
};

// 多言語ユニット対応のため、完全にReact化できていません。
const Media: UnitDefInterface = {
  type: 'media',
  name: '画像・ファイル',
  icon: 'photo',
  edit: Edit,
  inplaceEdit: InplaceEdit,
  config: Config,
  supports: {
    align: (version) => alignOptions[version],
  },
};

export default Media;
