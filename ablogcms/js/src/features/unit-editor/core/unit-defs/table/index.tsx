import type { UnitDefInterface } from '../../types/unit';
import Edit from './edit';
import Config from './config';
import InplaceEdit from './inplace-edit';

// 多言語ユニット対応のため、完全にReact化できていません。
const Table: UnitDefInterface = {
  type: 'table',
  name: 'テーブル',
  icon: 'table_chart',
  edit: Edit,
  inplaceEdit: InplaceEdit,
  config: Config,
};

export default Table;
