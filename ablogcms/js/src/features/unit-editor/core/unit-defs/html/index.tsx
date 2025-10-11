import type { UnitDefInterface } from '../../types/unit';
import Edit from './edit';
import Config from './config';
import InplaceEdit from './inplace-edit';
import type { HtmlAttributes } from './types';

const Html: UnitDefInterface<HtmlAttributes> = {
  type: 'html',
  name: '自由入力',
  icon: 'html',
  edit: Edit,
  config: Config,
  inplaceEdit: InplaceEdit,
};

export default Html;
