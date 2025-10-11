import type { UnitDefInterface } from '../../types/unit';
import Edit from './edit';
import Config from './config';
import InplaceEdit from './inplace-edit';
import type { CodeAttributes } from './types';

const Code: UnitDefInterface<CodeAttributes> = {
  type: 'code',
  name: 'コード',
  icon: 'code_blocks',
  edit: Edit,
  config: Config,
  inplaceEdit: InplaceEdit,
};

export default Code;
