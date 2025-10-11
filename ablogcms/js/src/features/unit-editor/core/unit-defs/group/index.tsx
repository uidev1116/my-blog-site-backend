import type { UnitDefInterface } from '../../types/unit';
import { GroupAttributes } from './types';
import Edit from './edit';

const Group: UnitDefInterface<GroupAttributes> = {
  type: 'group',
  name: 'グループ',
  icon: 'folder',
  edit: Edit,
};

export default Group;
