import type { UnitDefInterface } from '../../types/unit';
import Edit from './edit';

const More: UnitDefInterface = {
  type: 'more',
  name: '続きを読む',
  icon: 'more_horiz',
  edit: Edit,
  supports: {
    multiple: false,
    nested: false,
  },
};

export default More;
