import { useState, useCallback, Fragment } from 'react';

import { DndContext, DragEndEvent, KeyboardSensor, PointerSensor, useSensor, useSensors } from '@dnd-kit/core';
import {
  arrayMove,
  SortableContext,
  sortableKeyboardCoordinates,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { restrictToVerticalAxis, restrictToParentElement } from '@dnd-kit/modifiers';
import EntryView from '../entry-view/entry-view';
import type { RelatedEntryOption, RelatedEntryType } from '../../types';
import RelatedEntrySelect from '../related-entry-select/related-entry-select';
import useUpdateEffect from '../../../../hooks/use-update-effect';

interface RelatedEntriesProps {
  entries: RelatedEntryType[];
  moduleId: string;
  ctx: string;
  thumbnail?: string;
  maxItem: number;
  type: string;
  title: string;
  onChange?: (entries: RelatedEntryType[]) => void;
}

const toOptions = (entries: RelatedEntryType[]): RelatedEntryOption[] =>
  entries.map((entry) => ({ ...entry, value: entry.id.toString(), label: entry.title }));

const RelatedEntries = ({
  entries: entriesProp = [],
  moduleId,
  ctx,
  thumbnail,
  maxItem,
  type,
  title,
  onChange,
}: RelatedEntriesProps) => {
  const [entries, setEntries] = useState(entriesProp);

  const handleChange = useCallback(
    (newEntry: RelatedEntryOption | null) => {
      if (newEntry === null) {
        return;
      }
      const registeredEntry = entries.find((entry) => entry.id === newEntry.id);
      if (registeredEntry) {
        alert(ACMS.i18n('related_entry.already_registered')); // eslint-disable-line no-alert, no-console
        return;
      }
      if (maxItem > 0 && entries.length >= maxItem) {
        alert(ACMS.i18n('related_entry.max_items')); // eslint-disable-line no-alert, no-console
        return;
      }
      setEntries((prevEntries) => [...prevEntries, newEntry]);
    },
    [entries, maxItem]
  );

  const sensors = useSensors(
    useSensor(PointerSensor),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates,
    })
  );

  const handleDragEnd = useCallback(
    ({ active, over }: DragEndEvent) => {
      if (over && active.id !== over.id) {
        setEntries((prevEntries) =>
          arrayMove(
            prevEntries,
            prevEntries.findIndex((entry) => entry.id === active.id),
            prevEntries.findIndex((entry) => entry.id === over.id)
          )
        );
      }
    },
    [setEntries]
  );

  const removeEntry = useCallback(
    (entryId: number) => {
      if (!confirm(ACMS.i18n('related_entry.confirm_remove'))) {
        // eslint-disable-line no-alert, no-console
        return;
      }
      setEntries((prevEntries) => prevEntries.filter((entry) => entry.id !== entryId));
    },
    [setEntries]
  );

  const handleRemove = useCallback((entryId: number) => removeEntry(entryId), [removeEntry]);

  useUpdateEffect(() => {
    onChange?.(entries);
    // entries が変更されたら onChange を呼び出す
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [entries]);

  return (
    <>
      <table className="acms-admin-related-table">
        <tbody>
          <tr className="acms-admin-related-table-item">
            <th>
              <label htmlFor={`related-entry-select-${type || 'default'}`}>{title}</label>
            </th>
            <td>
              <div className="acms-admin-related-list-wrap">
                <RelatedEntrySelect
                  inputId={`related-entry-select-${type || 'default'}`}
                  value={toOptions(entries)}
                  moduleId={moduleId}
                  ctx={ctx}
                  thumbnail={thumbnail}
                  onChange={handleChange}
                />
              </div>
            </td>
          </tr>
          <tr className="acms-admin-related-table-item">
            {/* eslint-disable-next-line jsx-a11y/control-has-associated-label */}
            <th />
            <td>
              {entries.length > 0 && (
                <>
                  <DndContext
                    sensors={sensors}
                    onDragEnd={handleDragEnd}
                    modifiers={[restrictToVerticalAxis, restrictToParentElement]}
                  >
                    <ul className="acms-admin-related-entry-list" style={{ marginTop: '10px', marginBottom: '0' }}>
                      <SortableContext items={entries} strategy={verticalListSortingStrategy}>
                        {entries.map((entry) => (
                          <EntryView key={entry.id} entry={entry} onRemove={handleRemove} />
                        ))}
                      </SortableContext>
                    </ul>
                  </DndContext>
                  {entries.map((entry) => (
                    <Fragment key={entry.id}>
                      <input type="hidden" name="related[]" value={entry.id} />
                      <input type="hidden" name="related_type[]" value={type} />
                    </Fragment>
                  ))}
                </>
              )}
            </td>
          </tr>
        </tbody>
      </table>
      <input type="hidden" name="loaded_realted_entries[]" value={type || 'default'} />
    </>
  );
};

export default RelatedEntries;
