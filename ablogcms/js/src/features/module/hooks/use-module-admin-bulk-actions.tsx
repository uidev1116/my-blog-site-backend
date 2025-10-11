import { useMemo } from 'react';
import BlogSelect from '@features/blog/components/blog-select/blog-select';
import type { BulkAction } from '../../../components/dataview/types';
import type { ModuleType } from '../types';
import { pending } from '../../../lib/pending';
import { bulkChangeBlog, bulkChangeStatus, bulkDelete, exportModules } from '../api';
import { notify } from '../../../lib/notify';
import { MODULE_STATUSES } from '../constants';
import HStack from '../../../components/stack/h-stack';

export interface GetBulkActionsContext {
  invalidate: () => void;
  searchParams: URLSearchParams;
  bulkActions: string[];
}

export interface UseModuleAdminBulkActionsOptions {
  getBulkActions: (bulkActions: BulkAction<ModuleType>[], context: GetBulkActionsContext) => BulkAction<ModuleType>[];
  context: GetBulkActionsContext;
}

export default function useModuleAdminBulkActions({ getBulkActions, context }: UseModuleAdminBulkActionsOptions) {
  const bulkActions = useMemo<BulkAction<ModuleType>[]>(() => {
    const bulkActions: BulkAction<ModuleType>[] = [
      {
        id: 'status',
        label: ACMS.i18n('module_index.bulk_action.status'),
        onAction: async (formData: FormData) => {
          const end = pending.splash(ACMS.i18n('module_index.bulk_action.feedback.status.pending'));
          try {
            await bulkChangeStatus(formData);
            context.invalidate();
            notify.info(ACMS.i18n('module_index.bulk_action.feedback.status.success'));
          } catch (error) {
            notify.danger(ACMS.i18n('module_index.bulk_action.feedback.status.error'));
            console.error(error); // eslint-disable-line no-console
          } finally {
            end();
          }
        },
        renderForm: () => (
          <>
            <select name="status">
              {MODULE_STATUSES.map((status) => (
                <option key={status.value} value={status.value}>
                  {status.label}
                </option>
              ))}
            </select>
            <button
              type="submit"
              name="ACMS_POST_Module_Index_Status"
              className="acms-admin-btn-admin acms-admin-btn-admin-primary acms-admin-margin-left-mini"
            >
              {ACMS.i18n('module_index.bulk_change')}
            </button>
          </>
        ),
        condition: () => context.bulkActions.includes('status'),
      },
      {
        id: 'blog',
        label: ACMS.i18n('module_index.bulk_action.blog'),
        onAction: async (formData: FormData) => {
          const end = pending.splash(ACMS.i18n('module_index.bulk_action.feedback.blog.pending'));
          try {
            await bulkChangeBlog(formData);
            context.invalidate();
            notify.info(ACMS.i18n('module_index.bulk_action.feedback.blog.success'));
          } catch (error) {
            notify.danger(ACMS.i18n('module_index.bulk_action.feedback.blog.error'));
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
                  name="ACMS_POST_Module_Index_Blog"
                  className="acms-admin-btn-admin acms-admin-btn-admin-primary acms-admin-margin-left-mini"
                >
                  {ACMS.i18n('module_index.bulk_change')}
                </button>
              </div>
            </HStack>
          </div>
        ),
        condition: () => context.bulkActions.includes('status'),
      },
      {
        id: 'export',
        label: ACMS.i18n('module_index.bulk_action.export'),
        onAction: async (formData: FormData) => {
          const end = pending.splash(ACMS.i18n('module_index.bulk_action.feedback.export.pending'));
          try {
            await exportModules(formData);
            notify.info(ACMS.i18n('module_index.bulk_action.feedback.export.success'));
          } catch (error) {
            notify.danger(ACMS.i18n('module_index.bulk_action.feedback.export.error'));
            console.error(error); // eslint-disable-line no-console
          } finally {
            end();
          }
        },
        renderForm: () => (
          <button
            type="submit"
            name="ACMS_POST_Module_Index_Export"
            className="acms-admin-btn-admin acms-admin-btn-admin-primary"
          >
            {ACMS.i18n('module_index.exec')}
          </button>
        ),
        condition: () => context.bulkActions.includes('export'),
      },
      {
        id: 'delete',
        label: ACMS.i18n('module_index.bulk_action.delete'),
        onAction: async (formData: FormData) => {
          if (!window.confirm(ACMS.i18n('module_index.bulk_action.feedback.delete.confirm'))) {
            return;
          }
          const end = pending.splash(ACMS.i18n('module_index.bulk_action.feedback.delete.pending'));
          try {
            await bulkDelete(formData);
            context.invalidate();
            notify.info(ACMS.i18n('module_index.bulk_action.feedback.delete.success'));
          } catch (error) {
            notify.danger(ACMS.i18n('module_index.bulk_action.feedback.delete.error'));
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
                name="ACMS_POST_Module_Index_Delete"
                className="acms-admin-btn-admin acms-admin-btn-admin-danger"
              >
                {ACMS.i18n('module_index.exec')}
              </button>
            </div>
          </div>
        ),
        condition: () => context.bulkActions.includes('delete'),
      },
    ];
    return getBulkActions(bulkActions, context);
  }, [getBulkActions, context]);

  return bulkActions;
}
