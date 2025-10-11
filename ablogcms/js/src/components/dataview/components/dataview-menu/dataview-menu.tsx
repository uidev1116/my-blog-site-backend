import { Table } from '@tanstack/react-table';
import type { DataviewComponents, Menu } from '../../types';

interface DataviewMenuProps<T> {
  data: T[]; // components オプションでdataを参照できるようにするために未使用だがpropsとして受け取る
  menus?: Menu<T>[];
  className?: string;
  table: Table<T>;
  components: DataviewComponents<T>;
}

const DataviewMenu = <T,>({
  data, // eslint-disable-line @typescript-eslint/no-unused-vars
  menus = [],
  className = 'acms-admin-dataview-menu',
  table,
  components,
}: DataviewMenuProps<T>) => {
  const selectedData = table.getSelectedRowModel().rows.map((row) => row.original);

  if (selectedData.length > 0) {
    return null;
  }

  return (
    <div className={className}>
      <components.MenuList menus={menus} data={selectedData} />
    </div>
  );
};

export default DataviewMenu;
