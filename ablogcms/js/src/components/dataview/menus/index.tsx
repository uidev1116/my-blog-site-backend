import { Icon } from '@components/icon';
import { ColumnConfigDrawer } from '../components/column-config-drawer';
import ColumnServiceProvider from '../stores/service/provider';
import { RowData, type ColumnServiceInterface, type DrawerMenu } from '../types';

export function createColumnConfigMenu<T extends RowData>(
  service: ColumnServiceInterface<T>,
  {
    customColumnModalProps,
    ...props
  }: Partial<Omit<React.ComponentPropsWithoutRef<typeof ColumnConfigDrawer>, 'isOpen'>> = {}
): DrawerMenu<T> {
  return {
    id: 'column-visibility',
    label: (
      <>
        <Icon name="table_chart" />
        {ACMS.i18n('dataview.menu.column_visibility')}
      </>
    ),
    renderDrawer: ({ isOpen, close }) => {
      return (
        <ColumnServiceProvider<T> service={service}>
          <ColumnConfigDrawer
            {...props}
            isOpen={isOpen}
            onClose={() => {
              close();
              props.onClose?.();
            }}
            customColumnModalProps={customColumnModalProps}
          />
        </ColumnServiceProvider>
      );
    },
    condition: () => ACMS.Config.auth === 'administrator',
  };
}
