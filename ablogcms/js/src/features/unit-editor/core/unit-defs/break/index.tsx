import type { UnitDefInterface } from '../../types/unit';
import Edit from './edit';
import Config from './config';
import InplaceEdit from './inplace-edit';

// 多言語ユニット対応のため、完全にReact化できていません。
const Break: UnitDefInterface = {
  type: 'break',
  name: '改ページ',
  icon: 'last_page',
  edit: Edit,
  inplaceEdit: InplaceEdit,
  config: Config,
  supports: {
    nested: false,
  },
};

export default Break;
