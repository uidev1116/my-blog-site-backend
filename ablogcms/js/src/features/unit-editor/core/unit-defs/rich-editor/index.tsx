import type { UnitDefInterface } from '../../types/unit';
import Edit from './edit';
import Config from './config';
import InplaceEdit from './inplace-edit';
import type { RichEditorAttributes } from './types';

const RichEditor: UnitDefInterface<RichEditorAttributes> = {
  type: 'rich-editor',
  name: 'リッチエディター',
  icon: 'edit',
  edit: Edit,
  inplaceEdit: InplaceEdit,
  config: Config,
};

export default RichEditor;
