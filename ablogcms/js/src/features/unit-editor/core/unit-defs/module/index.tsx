import type { UnitAlignOption, UnitAlignVersion, UnitDefInterface } from '../../types/unit';
import Edit from './edit';
import Config from './config';
import InplaceEdit from './inplace-edit';
import type { ModuleAttributes } from './types';

const alignOptions: Record<UnitAlignVersion, UnitAlignOption[]> = {
  v1: [
    { value: 'center', label: ACMS.i18n('unit_editor.align.full') },
    { value: 'auto', label: ACMS.i18n('unit_editor.align.auto') },
  ],
  v2: [],
};

const Module: UnitDefInterface<ModuleAttributes> = {
  type: 'module',
  name: 'モジュール',
  icon: 'widgets',
  edit: Edit,
  inplaceEdit: InplaceEdit,
  config: Config,
  supports: {
    align: (version) => alignOptions[version],
  },
};

export default Module;
