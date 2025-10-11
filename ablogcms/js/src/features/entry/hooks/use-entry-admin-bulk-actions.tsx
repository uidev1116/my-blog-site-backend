import { useMemo } from 'react';
import UserSelect from '@features/user/components/user-select/user-select';
import CategorySelect from '@features/category/components/category-select/category-select';
import BlogSelect from '@features/blog/components/blog-select/blog-select';
import { isAxiosError } from 'axios';
import type { BulkAction } from '../../../components/dataview/types';
import type { EntrySort, EntrySortType, EntryType } from '../types';
import { notify } from '../../../lib/notify';
import { pending } from '../../../lib/pending';
import {
  bulkChangeBlog,
  bulkChangeCategory,
  bulkChangeStatus,
  bulkChangeUser,
  bulkDuplicate,
  bulkTrash,
  changeCategorySort,
  changeSort,
  changeUserSort,
  exportEntries,
} from '../api';
import { ENTRY_STATUSES } from '../constants';
import VisuallyHidden from '../../../components/visually-hidden';
import HStack from '../../../components/stack/h-stack';

export interface GetBulkActionsContext {
  invalidate: () => void;
  sort?: EntrySort;
  bulkActions: string[];
}

export interface UseEntryAdminBulkActionsOptions {
  getBulkActions: (bulkActions: BulkAction<EntryType>[], context: GetBulkActionsContext) => BulkAction<EntryType>[];
  context: GetBulkActionsContext;
}

export default function useEntryAdminBulkActions({ getBulkActions, context }: UseEntryAdminBulkActionsOptions) {
  const bulkActions = useMemo<BulkAction<EntryType>[]>(() => {
    const bulkActions: BulkAction<EntryType>[] = [
      {
        id: 'order',
        label: ACMS.i18n('entry_index.bulk_action.order'),
        onAction: async (formData: FormData) => {
          const intent = formData.get('intent') as EntrySortType | null;
          if (!intent) {
            return;
          }

          const end = pending.splash(ACMS.i18n('entry_index.bulk_action.feedback.order.pending'));
          try {
            await {
              entry: changeSort,
              user: changeUserSort,
              category: changeCategorySort,
            }[intent](formData);
            context.invalidate();
            notify.info(ACMS.i18n('entry_index.bulk_action.feedback.order.success'));
          } catch (error) {
            notify.danger(ACMS.i18n('entry_index.bulk_action.feedback.order.error'));
            console.error(error); // eslint-disable-line no-console
          } finally {
            end();
          }
        },
        renderForm: () => (
          <button
            type="submit"
            name="intent"
            value={context.sort?.type}
            className="acms-admin-btn-admin acms-admin-btn-admin-primary"
          >
            {ACMS.i18n('entry_index.bulk_change')}
          </button>
        ),
        condition: () => context.bulkActions.includes('order'),
      },
      {
        id: 'status',
        label: ACMS.i18n('entry_index.bulk_action.status'),
        onAction: async (formData: FormData) => {
          const end = pending.splash(ACMS.i18n('entry_index.bulk_action.feedback.status.pending'));
          try {
            await bulkChangeStatus(formData);
            context.invalidate();
            notify.info(ACMS.i18n('entry_index.bulk_action.feedback.status.success'));
          } catch (error) {
            notify.danger(ACMS.i18n('entry_index.bulk_action.feedback.status.error'));
            console.error(error); // eslint-disable-line no-console
          } finally {
            end();
          }
        },
        renderForm: () => (
          <>
            <label>
              <VisuallyHidden>{ACMS.i18n('entry_index.bulk_action.status')}</VisuallyHidden>
              <select name="status">
                {ENTRY_STATUSES.map((status) => (
                  <option key={status.value} value={status.value}>
                    {status.label}
                  </option>
                ))}
              </select>
            </label>
            <button
              type="submit"
              name="ACMS_POST_Entry_Index_Status"
              className="acms-admin-btn-admin acms-admin-btn-admin-primary acms-admin-margin-left-mini"
            >
              {ACMS.i18n('entry_index.bulk_change')}
            </button>
          </>
        ),
        condition: () => context.bulkActions.includes('status'),
      },
      {
        id: 'user',
        label: ACMS.i18n('entry_index.bulk_action.user'),
        onAction: async (formData: FormData) => {
          const end = pending.splash(ACMS.i18n('entry_index.bulk_action.feedback.user.pending'));
          try {
            await bulkChangeUser(formData);
            context.invalidate();
            notify.info(ACMS.i18n('entry_index.bulk_action.feedback.user.success'));
          } catch (error) {
            notify.danger(ACMS.i18n('entry_index.bulk_action.feedback.user.error'));
            console.error(error); // eslint-disable-line no-console
          } finally {
            end();
          }
        },
        renderForm: () => (
          <HStack spacing="0.25rem">
            <label style={{ maxWidth: '100%', width: '220px' }}>
              <VisuallyHidden>{ACMS.i18n('entry_index.bulk_action.user')}</VisuallyHidden>
              <UserSelect name="uid" isClearable={false} />
            </label>
            <button
              type="submit"
              name="ACMS_POST_Entry_Index_User"
              className="acms-admin-btn-admin acms-admin-btn-admin-primary acms-admin-margin-left-mini"
            >
              {ACMS.i18n('entry_index.bulk_change')}
            </button>
          </HStack>
        ),
        condition: () => context.bulkActions.includes('user'),
      },
      {
        id: 'category',
        label: ACMS.i18n('entry_index.bulk_action.category'),
        onAction: async (formData: FormData) => {
          const end = pending.splash(ACMS.i18n('entry_index.bulk_action.feedback.category.pending'));
          try {
            await bulkChangeCategory(formData);
            context.invalidate();
            notify.info(ACMS.i18n('entry_index.bulk_action.feedback.category.success'));
          } catch (error) {
            notify.danger(ACMS.i18n('entry_index.bulk_action.feedback.category.error'));
            console.error(error); // eslint-disable-line no-console
          } finally {
            end();
          }
        },
        renderForm: () => (
          <HStack spacing="0.25rem">
            <label style={{ maxWidth: '100%', width: '220px' }}>
              <VisuallyHidden>{ACMS.i18n('entry_index.bulk_action.category')}</VisuallyHidden>
              <CategorySelect name="cid" isClearable={false} />
            </label>
            <div>
              <button
                type="submit"
                name="ACMS_POST_Entry_Index_Category"
                className="acms-admin-btn-admin acms-admin-btn-admin-primary"
              >
                {ACMS.i18n('entry_index.bulk_change')}
              </button>
            </div>
          </HStack>
        ),
        condition: () => context.bulkActions.includes('category'),
      },
      {
        id: 'blog',
        label: ACMS.i18n('entry_index.bulk_action.blog'),
        onAction: async (formData: FormData) => {
          const end = pending.splash(ACMS.i18n('entry_index.bulk_action.feedback.blog.pending'));
          try {
            await bulkChangeBlog(formData);
            context.invalidate();
            notify.info(ACMS.i18n('entry_index.bulk_action.feedback.blog.success'));
          } catch (error) {
            notify.danger(ACMS.i18n('entry_index.bulk_action.feedback.blog.error'));
            console.error(error); // eslint-disable-line no-console
          } finally {
            end();
          }
        },
        renderForm: () => (
          <div>
            <HStack spacing="0.25rem">
              <div style={{ maxWidth: '100%', width: '220px' }}>
                <BlogSelect name="bid" isClearable={false} scope="loggedInUserBlog" />
              </div>
              <div>
                <button
                  type="submit"
                  name="ACMS_POST_Entry_Index_Blog"
                  className="acms-admin-btn-admin acms-admin-btn-admin-primary acms-admin-margin-left-mini"
                >
                  {ACMS.i18n('entry_index.bulk_change')}
                </button>
              </div>
            </HStack>
            <div>
              <span className="acms-admin-tab-text">{ACMS.i18n('entry_index.bulk_blog_note')}</span>
            </div>
          </div>
        ),
        condition: () => context.bulkActions.includes('blog'),
      },
      {
        id: 'duplicate',
        label: ACMS.i18n('entry_index.bulk_action.duplicate'),
        onAction: async (formData: FormData) => {
          const end = pending.splash(ACMS.i18n('entry_index.bulk_action.feedback.duplicate.pending'));
          try {
            await bulkDuplicate(formData);
            context.invalidate();
            notify.info(ACMS.i18n('entry_index.bulk_action.feedback.duplicate.success'));
          } catch (error) {
            notify.danger(ACMS.i18n('entry_index.bulk_action.feedback.duplicate.error'));
            console.error(error); // eslint-disable-line no-console
          } finally {
            end();
          }
        },
        renderForm: () => (
          <div>
            <div>
              <button
                type="submit"
                name="ACMS_POST_Entry_Index_Duplicate"
                className="acms-admin-btn-admin acms-admin-btn-admin-primary"
              >
                {ACMS.i18n('entry_index.exec')}
              </button>
            </div>
            <div>
              <span className="acms-admin-tab-text">{ACMS.i18n('entry_index.bulk_duplicate_note')}</span>
            </div>
          </div>
        ),
        condition: () => context.bulkActions.includes('duplicate'),
      },
      {
        id: 'export',
        label: ACMS.i18n('entry_index.bulk_action.export'),
        onAction: async (formData: FormData) => {
          const end = pending.splash(ACMS.i18n('entry_index.bulk_action.feedback.export.pending'));
          try {
            await exportEntries(formData);
            notify.info(ACMS.i18n('entry_index.bulk_action.feedback.export.success'));
          } catch (error) {
            if (isAxiosError(error)) {
              const text = await error.response?.data.text();
              const data = JSON.parse(text);
              notify.danger(data.messages[0]);
            } else {
              notify.danger(ACMS.i18n('entry_index.bulk_action.feedback.export.error'));
            }
            console.error(error); // eslint-disable-line no-console
          } finally {
            end();
          }
        },
        renderForm: () => (
          <button
            type="submit"
            name="ACMS_POST_Entry_Index_Export"
            className="acms-admin-btn-admin acms-admin-btn-admin-primary"
          >
            {ACMS.i18n('entry_index.exec')}
          </button>
        ),
        condition: () => context.bulkActions.includes('export'),
      },
      {
        id: 'trash',
        label: ACMS.i18n('entry_index.bulk_action.trash'),
        onAction: async (formData: FormData) => {
          const end = pending.splash(ACMS.i18n('entry_index.bulk_action.feedback.trash.pending'));
          try {
            await bulkTrash(formData);
            context.invalidate();
            notify.info(ACMS.i18n('entry_index.bulk_action.feedback.trash.success'));
          } catch (error) {
            notify.danger(ACMS.i18n('entry_index.bulk_action.feedback.trash.error'));
            console.error(error); // eslint-disable-line no-console
          } finally {
            end();
          }
        },
        renderForm: () => (
          <button
            type="submit"
            name="ACMS_POST_Entry_Index_Trash"
            className="acms-admin-btn-admin acms-admin-btn-admin-danger"
            onClick={() => window.confirm(ACMS.i18n('entry_index.bulk_action.feedback.trash.confirm'))}
          >
            {ACMS.i18n('entry_index.exec')}
          </button>
        ),
        condition: () => context.bulkActions.includes('trash'),
      },
    ];
    return getBulkActions(bulkActions, context);
  }, [context, getBulkActions]);
  return bulkActions;
}
