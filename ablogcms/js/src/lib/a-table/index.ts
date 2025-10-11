// @ts-expect-error no types
import Atable from 'a-table';
import 'a-table/css/a-table.css';

export interface ATableOptions {
  onChange?: (value: string) => void;
  [key: string]: any; // eslint-disable-line @typescript-eslint/no-explicit-any
}

export default function setupAtable(element: HTMLElement, options: ATableOptions = {}) {
  const { onChange, ...rest } = options;
  const root = element.querySelector<HTMLElement & { aTable?: Atable }>(ACMS.Config.aTableMark);
  if (root === null) {
    throw new Error('a-table root element not found');
  }
  if (root.aTable instanceof Atable) {
    return root.aTable;
  }
  const dest = root.querySelector<HTMLInputElement>(ACMS.Config.aTableDestMark);
  const table = root.querySelector<HTMLTableElement>('table');
  if (table === null) {
    throw new Error('a-table table element not found');
  }
  const aTable = new Atable(table, {
    mark: ACMS.Config.aTableConf,
    selector: {
      option: ACMS.Config.aTableSelector,
    },
    tableOption: ACMS.Config.aTableOption,
    message: ACMS.Config.aTableMessage,
    ...rest,
  });
  aTable.afterRendered = () => {
    const value = aTable.getTable();
    if (dest !== null) {
      dest.value = value;
    }
    onChange?.(value);
  };
  aTable.afterEntered = () => {
    const value = aTable.getTable();
    if (dest !== null) {
      dest.value = value;
    }
    onChange?.(value);
  };
  aTable.afterRendered();
  root.aTable = aTable;

  return aTable;
}
