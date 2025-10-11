import type { UnitDefInterface } from '../../types/unit';
import Edit from './edit';
import Config from './config';
import InplaceEdit from './inplace-edit';
import type { WysiwygAttributes } from './types';

const Wysiwyg: UnitDefInterface<WysiwygAttributes> = {
  type: 'wysiwyg',
  name: 'WYSIWYGエディター',
  icon: 'wysiwyg',
  edit: Edit,
  inplaceEdit: InplaceEdit,
  config: Config,
};

export default Wysiwyg;
