import { useCallback, useMemo, useState } from 'react';
import { Table } from '@tanstack/react-table';
import VisuallyHidden from '@components/visually-hidden';
import type { BulkAction } from '../../types';

interface BulkActionsProps<T> {
  actions: BulkAction<T>[];
  data: T[];
  table: Table<T>;
}

const BulkActions = <T,>({ actions = [], data, table }: BulkActionsProps<T>) => {
  const [selectedActionId, setSelectedActionId] = useState<BulkAction<T>['id']>('');
  const activeActions = useMemo(
    () => actions.filter((action) => action.condition === undefined || action.condition(data)),
    [actions, data]
  );

  const handleChange = useCallback(
    (event: React.ChangeEvent<HTMLSelectElement>) => {
      setSelectedActionId(event.target.value as BulkAction<T>['id']);
    },
    [setSelectedActionId]
  );

  const handleSubmit = useCallback(
    async (event: React.FormEvent<HTMLFormElement>) => {
      event.preventDefault();
      const action = activeActions.find((a) => a.id === selectedActionId);
      if (action) {
        const formData = new FormData(event.currentTarget, (event.nativeEvent as SubmitEvent).submitter);
        await action.onAction?.(formData, data);
        table.resetRowSelection();
      }
    },
    [selectedActionId, activeActions, data, table]
  );
  return (
    <form
      onSubmit={handleSubmit}
      id="bulk-action-form"
      method="post"
      className="acms-admin-form acms-admin-dataview-bulk-action-form"
    >
      <div>
        <VisuallyHidden asChild>
          <label htmlFor="bulk-action-select">{ACMS.i18n('dataview.bulk_actions')}</label>
        </VisuallyHidden>
        <select id="bulk-action-select" name="action-entry" onChange={handleChange}>
          <option value="">{ACMS.i18n('dataview.bulk_actions')}</option>
          {activeActions.map((action) => (
            <option key={action.id} value={action.id}>
              {typeof action.label === 'function' ? action.label(data) : action.label}
            </option>
          ))}
        </select>
      </div>
      {selectedActionId !== '' && (
        <div>{activeActions.find((action) => action.id === selectedActionId)?.renderForm?.(data)}</div>
      )}
      <input type="hidden" name="formToken" value={window.csrfToken} />
    </form>
  );
};

export default BulkActions;
