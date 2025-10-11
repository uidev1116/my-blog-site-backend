import { useMemo } from 'react';
import { Icon } from '@components/icon';
import type { Action } from '../../../components/dataview/types';
import { pending } from '../../../lib/pending';
import type { EntryType } from '../types';
import { createEntryEditLink } from '../utils';
import { duplicateEntry, trashEntry, unlockEntry } from '../api';
import { notify } from '../../../lib/notify';
import { Tooltip } from '../../../components/tooltip';

export interface GetActionsContext {
  invalidate: () => void;
}

export interface UseEntryAdminActionsOptions {
  getActions: (actions: Action<EntryType>[], context: GetActionsContext) => Action<EntryType>[];
  context: GetActionsContext;
}

export default function useEntryAdminActions({ getActions, context }: UseEntryAdminActionsOptions) {
  const actions = useMemo<Action<EntryType>[]>(() => {
    const actions: Action<EntryType>[] = [
      {
        id: 'edit',
        label: ACMS.i18n('entry_index.action.edit'),
        getHref: (data) => {
          return createEntryEditLink(data, 'entry_editor');
        },
        type: 'primary',
        linkProps: {
          className: 'acms-admin-btn-admin',
        },
        condition: (data) => data.actions.includes('edit'),
      },
      {
        id: 'approval-history',
        label: ACMS.i18n('entry_index.action.approval-history'),
        getHref: (data) => {
          return ACMS.Library.acmsLink({
            admin: 'entry_approval-history',
            eid: data.id,
          });
        },
        condition: (data) => data.actions.includes('approval-history'),
      },
      {
        id: 'duplicate',
        label: ACMS.i18n('entry_index.action.duplicate'),
        onAction: async (data) => {
          const end = pending.splash(ACMS.i18n('entry_index.action.feedback.duplicate.pending'));
          try {
            await duplicateEntry(data);
            context.invalidate();
            notify.info(ACMS.i18n('entry_index.action.feedback.duplicate.success'));
          } catch (error) {
            notify.danger(ACMS.i18n('entry_index.action.feedback.duplicate.error'));
            // eslint-disable-next-line no-console
            console.error(error);
          } finally {
            end();
          }
        },
        condition: (data) => data.actions.includes('duplicate'),
      },
      {
        id: 'trash',
        label: ACMS.i18n('entry_index.action.trash'),
        onAction: async (data) => {
          if (!window.confirm(ACMS.i18n('edit.message1'))) {
            return;
          }
          const end = pending.splash(ACMS.i18n('entry_index.action.feedback.trash.pending'));
          try {
            await trashEntry(data);
            context.invalidate();
            notify.info(ACMS.i18n('entry_index.action.feedback.trash.success'));
          } catch (error) {
            notify.danger(ACMS.i18n('entry_index.action.feedback.trash.error'));
            // eslint-disable-next-line no-console
            console.error(error);
          } finally {
            end();
          }
        },
        condition: (data) => data.actions.includes('trash'),
      },
      {
        id: 'unlock',
        label: (
          <>
            <Icon name="lock_open" />
            <Tooltip id="entry-unlock-tooltip" />
          </>
        ),
        onAction: async (data) => {
          if (!window.confirm(ACMS.i18n('entry_index.action.feedback.unlock.confirm'))) {
            return;
          }
          const end = pending.splash(ACMS.i18n('entry_index.action.feedback.unlock.pending'));
          try {
            await unlockEntry(data);
            context.invalidate();
            notify.info(ACMS.i18n('entry_index.action.feedback.unlock.success', { title: data.title }));
          } catch (error) {
            notify.danger(ACMS.i18n('entry_index.action.feedback.unlock.error', { title: data.title }));
            // eslint-disable-next-line no-console
            console.error(error);
          } finally {
            end();
          }
        },
        type: 'tertiary',
        buttonProps: {
          className: 'acms-admin-btn-icon acms-admin-btn-unstyled',
          'data-tooltip-id': 'entry-unlock-tooltip',
          'data-tooltip-content': ACMS.i18n('entry_index.action.unlock'),
          'data-tooltip-place': 'top-start',
          'aria-label': ACMS.i18n('entry_index.action.unlock'),
        },
        // 自分が編集ロックしている場合のみ表示
        condition: (data) => data.lockUser != null && data.lockUser.id === parseInt(ACMS.Config.suid as string, 10),
      },
      {
        id: 'lock',
        label: (
          <>
            <Icon name="lock" />
            <Tooltip id="entry-lock-tooltip" />
          </>
        ),
        type: 'tertiary',
        buttonProps: (data) => ({
          className: 'acms-admin-btn-icon acms-admin-btn-unstyled',
          'data-tooltip-id': 'entry-lock-tooltip',
          'data-tooltip-content': ACMS.i18n('entry_index.action.lock', { name: data.lockUser?.name }),
          'data-tooltip-place': 'top-start',
          'aria-label': ACMS.i18n('entry_index.action.lock', { name: data.lockUser?.name }),
        }),
        // 自分以外のユーザーが編集ロックしている場合のみ表示
        condition: (data) => data.lockUser != null && data.lockUser.id !== parseInt(ACMS.Config.suid as string, 10),
      },
    ];
    return getActions(actions, context);
  }, [context, getActions]);

  return actions;
}
