import type { UnitDefInterface } from '../../types/unit';
import Edit from './edit';
import Config from './config';
import InplaceEdit from './inplace-edit';
import type { MarkdownAttributes } from './types';

const Markdown: UnitDefInterface<MarkdownAttributes> = {
  type: 'markdown',
  name: 'Markdown',
  icon: 'code',
  edit: Edit,
  inplaceEdit: InplaceEdit,
  config: Config,
};

export default Markdown;
