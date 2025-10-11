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

const OpenStreetMap: UnitDefInterface = {
  type: 'osmap',
  name: 'OpenStreetMap',
  icon: 'map',
  edit: Edit,
  inplaceEdit: InplaceEdit,
  config: Config,
  supports: {
    align: (version) => alignOptions[version],
  },
};

export default OpenStreetMap;
