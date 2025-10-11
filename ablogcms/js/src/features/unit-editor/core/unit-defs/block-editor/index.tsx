import type { UnitDefInterface } from '../../types/unit';
import Edit from './edit';
import Config from './config';
import InplaceEdit from './inplace-edit';
import type { BlockEditorAttributes } from './types';

const BlockEditor: UnitDefInterface<BlockEditorAttributes> = {
  type: 'block-editor',
  name: 'ブロックエディター',
  icon: 'edit',
  edit: Edit,
  inplaceEdit: InplaceEdit,
  config: Config,
};

export default BlockEditor;
