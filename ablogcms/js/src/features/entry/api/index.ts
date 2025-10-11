import { AxiosError } from 'axios';
import dayjs from 'dayjs';
import { type CustomAccessorColumn, type OptionData, type ColumnConfig } from '../../../components/dataview/types';
import axiosLib from '../../../lib/axios';
import { type EntryType } from '../types';
import { getFileNameFromContentDisposition } from '../../../utils';

export interface CustomColumnsSuccessResponse {
  status: 'success';
  columns: CustomAccessorColumn<EntryType>[];
}

export interface CustomColumnsFailedResponse {
  status: 'failure';
  errors: {
    field: string;
    option: string;
  }[];
}

export type CustomColumnsResponse = CustomColumnsSuccessResponse | CustomColumnsFailedResponse;

export async function fetchCustomColumns(): Promise<CustomAccessorColumn<EntryType>[]> {
  const url = ACMS.Library.acmsLink({
    bid: ACMS.Config.bid,
    admin: 'entry_custom_column', // 監査ログ用
    tpl: 'ajax/config/entry/custom-column.json',
  });

  const response = await axiosLib.get<CustomColumnsResponse>(url);

  if (response.data.status === 'failure') {
    throw new AxiosError(
      'Failed to fetch custom columns.',
      `${response.status} ${response.statusText}`,
      response.config,
      response.request,
      response
    );
  }

  return response.data.columns;
}

export async function saveCustomColumns(formData: FormData): Promise<CustomAccessorColumn<EntryType>[]> {
  const url = ACMS.Library.acmsLink({
    bid: ACMS.Config.bid,
    admin: 'entry_custom_column', // 監査ログ用
    tpl: 'ajax/config/entry/custom-column.json',
  });

  formData.append('ACMS_POST_Config', 'exec');
  const response = await axiosLib.post<CustomColumnsResponse>(url, formData);

  if (response.data.status === 'failure') {
    throw new AxiosError(
      'Failed to save custom columns.',
      `${response.status} ${response.statusText}`,
      response.config,
      response.request,
      response
    );
  }

  return response.data.columns;
}

export interface ColumnConfigSuccessResponse {
  status: 'success';
  config: ColumnConfig;
}

export interface ColumnConfigFailedResponse {
  status: 'failure';
  errors: {
    field: string;
    option: string;
  }[];
}

export type ColumnConfigResponse = ColumnConfigSuccessResponse | ColumnConfigFailedResponse;

export async function fetchColumnConfig(): Promise<ColumnConfig> {
  const url = ACMS.Library.acmsLink({
    bid: ACMS.Config.bid,
    admin: 'entry_column', // 監査ログ用
    tpl: 'ajax/config/entry/column-config.json',
  });

  const response = await axiosLib.get<ColumnConfigResponse>(url);

  if (response.data.status === 'failure') {
    throw new AxiosError(
      'Failed to fetch column config.',
      `${response.status} ${response.statusText}`,
      response.config,
      response.request,
      response
    );
  }

  return response.data.config;
}

export async function saveColumnConfig(formData: FormData): Promise<ColumnConfig> {
  const url = ACMS.Library.acmsLink({
    bid: ACMS.Config.bid,
    admin: 'entry_column', // 監査ログ用
    tpl: 'ajax/config/entry/column-config.json',
  });

  formData.append('ACMS_POST_Config', 'exec');
  const response = await axiosLib.post<ColumnConfigResponse>(url, formData);

  if (response.data.status === 'failure') {
    throw new AxiosError(
      'Failed to save column config.',
      `${response.status} ${response.statusText}`,
      response.config,
      response.request,
      response
    );
  }

  return response.data.config;
}

interface FieldValuesGroupSuccessResponse {
  status: 'success';
  data: Record<string, string[]>;
}

interface FieldValuesGroupFailedResponse {
  status: 'failure';
  message: string;
}

export type FieldValuesGroupResponse = FieldValuesGroupSuccessResponse | FieldValuesGroupFailedResponse;

export async function fetchOptionData(keys: string[] = []): Promise<OptionData<EntryType>> {
  const url = ACMS.Library.acmsLink({
    bid: ACMS.Config.bid,
    tpl: 'ajax/field-values-group.json',
    searchParams: {
      type: 'entry',
      key: keys,
    },
  });

  const response = await axiosLib.get<FieldValuesGroupResponse>(url);

  if (response.data.status === 'failure') {
    throw new AxiosError(
      'Failed to fetch option data.',
      `${response.status} ${response.statusText}`,
      response.config,
      response.request,
      response
    );
  }

  return Object.entries(response.data.data).reduce((acc, [key, values]) => {
    const options = values.map((value) => ({ value, label: value }));
    return { ...acc, [key]: options };
  }, {});
}

interface ActionResponse {
  status: 'success' | 'error';
}

export async function duplicateEntry(entry: EntryType): Promise<void> {
  const url = new URL(ACMS.Library.acmsPath({ tpl: 'ajax/action.json' }), window.location.href);

  const formData = new FormData();
  formData.append('ACMS_POST_Entry_Duplicate', 'exec');
  formData.append('eid', entry.id.toString());
  formData.append('formToken', window.csrfToken);

  const response = await axiosLib.post<ActionResponse>(url.toString(), formData);

  if (response.data.status === 'error') {
    throw new AxiosError(
      'Failed to duplicate entry.',
      `${response.status} ${response.statusText}`,
      response.config,
      response.request,
      response
    );
  }
}

export async function trashEntry(entry: EntryType): Promise<void> {
  const url = new URL(ACMS.Library.acmsPath({ tpl: 'ajax/action.json' }), window.location.href);

  const formData = new FormData();
  formData.append('ACMS_POST_Entry_Trash', 'exec');
  formData.append('eid', entry.id.toString());
  formData.append('formToken', window.csrfToken);

  const response = await axiosLib.post<ActionResponse>(url.toString(), formData);

  if (response.data.status === 'error') {
    throw new AxiosError(
      'Failed to trash entry.',
      `${response.status} ${response.statusText}`,
      response.config,
      response.request,
      response
    );
  }
}

export async function unlockEntry(entry: EntryType): Promise<void> {
  const url = new URL(ACMS.Library.acmsPath({ tpl: 'ajax/action.json' }), window.location.href);

  const formData = new FormData();
  formData.append('ACMS_POST_Entry_Lock_Unlock', 'exec');
  formData.append('eid', entry.id.toString());
  formData.append('formToken', window.csrfToken);

  const response = await axiosLib.post<ActionResponse>(url.toString(), formData);

  if (response.data.status === 'error') {
    throw new AxiosError(
      'Failed to unlock entry.',
      `${response.status} ${response.statusText}`,
      response.config,
      response.request,
      response
    );
  }
}

export async function changeSort(formData: FormData): Promise<void> {
  const url = new URL(ACMS.Library.acmsPath({ tpl: 'ajax/action.json' }), window.location.href);

  formData.append('ACMS_POST_Entry_Index_Sort_Entry', 'exec');
  formData.append('formToken', window.csrfToken);

  const response = await axiosLib.post<ActionResponse>(url.toString(), formData);

  if (response.data.status === 'error') {
    throw new AxiosError(
      'Failed to change sort.',
      `${response.status} ${response.statusText}`,
      response.config,
      response.request,
      response
    );
  }
}

export async function changeCategorySort(formData: FormData): Promise<void> {
  const url = new URL(ACMS.Library.acmsPath({ tpl: 'ajax/action.json' }), window.location.href);

  formData.append('ACMS_POST_Entry_Index_Sort_Category', 'exec');
  formData.append('formToken', window.csrfToken);

  const response = await axiosLib.post<ActionResponse>(url.toString(), formData);

  if (response.data.status === 'error') {
    throw new AxiosError(
      'Failed to change category sort.',
      `${response.status} ${response.statusText}`,
      response.config,
      response.request,
      response
    );
  }
}

export async function changeUserSort(formData: FormData): Promise<void> {
  const url = new URL(ACMS.Library.acmsPath({ tpl: 'ajax/action.json' }), window.location.href);

  formData.append('ACMS_POST_Entry_Index_Sort_User', 'exec');
  formData.append('formToken', window.csrfToken);

  const response = await axiosLib.post<ActionResponse>(url.toString(), formData);

  if (response.data.status === 'error') {
    throw new AxiosError(
      'Failed to change user sort.',
      `${response.status} ${response.statusText}`,
      response.config,
      response.request,
      response
    );
  }
}

export async function bulkChangeStatus(formData: FormData): Promise<void> {
  const url = new URL(ACMS.Library.acmsPath({ tpl: 'ajax/action.json' }), window.location.href);
  formData.append('ACMS_POST_Entry_Index_Status', 'exec');
  formData.append('formToken', window.csrfToken);

  const response = await axiosLib.post<ActionResponse>(url.toString(), formData);

  if (response.data.status === 'error') {
    throw new AxiosError(
      'Failed to bulk change status.',
      `${response.status} ${response.statusText}`,
      response.config,
      response.request,
      response
    );
  }
}

export async function bulkChangeCategory(formData: FormData): Promise<void> {
  const url = new URL(ACMS.Library.acmsPath({ tpl: 'ajax/action.json' }), window.location.href);
  formData.append('ACMS_POST_Entry_Index_Category', 'exec');
  formData.append('formToken', window.csrfToken);

  const response = await axiosLib.post<ActionResponse>(url.toString(), formData);

  if (response.data.status === 'error') {
    throw new AxiosError(
      'Failed to bulk change category.',
      `${response.status} ${response.statusText}`,
      response.config,
      response.request,
      response
    );
  }
}

export async function bulkChangeUser(formData: FormData): Promise<void> {
  const url = new URL(ACMS.Library.acmsPath({ tpl: 'ajax/action.json' }), window.location.href);
  formData.append('ACMS_POST_Entry_Index_User', 'exec');
  formData.append('formToken', window.csrfToken);

  const response = await axiosLib.post<ActionResponse>(url.toString(), formData);

  if (response.data.status === 'error') {
    throw new AxiosError(
      'Failed to bulk change user.',
      `${response.status} ${response.statusText}`,
      response.config,
      response.request,
      response
    );
  }
}

export async function bulkChangeBlog(formData: FormData): Promise<void> {
  const url = new URL(ACMS.Library.acmsPath({ tpl: 'ajax/action.json' }), window.location.href);
  formData.append('ACMS_POST_Entry_Index_Blog', 'exec');
  formData.append('formToken', window.csrfToken);

  const response = await axiosLib.post<ActionResponse>(url.toString(), formData);

  if (response.data.status === 'error') {
    throw new AxiosError(
      'Failed to bulk change blog.',
      `${response.status} ${response.statusText}`,
      response.config,
      response.request,
      response
    );
  }
}

export async function bulkDuplicate(formData: FormData): Promise<void> {
  const url = new URL(ACMS.Library.acmsPath({ tpl: 'ajax/action.json' }), window.location.href);
  formData.append('ACMS_POST_Entry_Index_Duplicate', 'exec');
  formData.append('formToken', window.csrfToken);

  const response = await axiosLib.post<ActionResponse>(url.toString(), formData);

  if (response.data.status === 'error') {
    throw new AxiosError(
      'Failed to bulk duplicate.',
      `${response.status} ${response.statusText}`,
      response.config,
      response.request,
      response
    );
  }
}

export async function bulkTrash(formData: FormData): Promise<void> {
  const url = new URL(ACMS.Library.acmsPath({ tpl: 'ajax/action.json' }), window.location.href);
  formData.append('ACMS_POST_Entry_Index_Trash', 'exec');
  formData.append('formToken', window.csrfToken);

  const response = await axiosLib.post<ActionResponse>(url.toString(), formData);

  if (response.data.status === 'error') {
    throw new AxiosError(
      'Failed to bulk trash.',
      `${response.status} ${response.statusText}`,
      response.config,
      response.request,
      response
    );
  }
}

export async function exportEntries(formData: FormData): Promise<void> {
  const url = new URL(ACMS.Library.acmsPath({ tpl: 'ajax/action.json' }), window.location.href);
  formData.append('ACMS_POST_Entry_Index_Export', 'exec');
  formData.append('formToken', window.csrfToken);

  const response = await axiosLib.post<BlobPart>(url.toString(), formData, {
    responseType: 'blob',
  });

  const contentType = response.headers['content-type'];
  if (contentType.includes('application/json')) {
    throw new AxiosError(
      'Failed to export entries.',
      `${response.status} ${response.statusText}`,
      response.config,
      response.request,
      response
    );
  }

  // サーバーから送信されたContent-Dispositionヘッダーからファイル名を取得
  const contentDisposition = response.headers['content-disposition'];
  const defaultFileName = `entries_${dayjs().format('YYYYMMDD_HHmmss')}.zip`;
  const fileName = getFileNameFromContentDisposition(contentDisposition, defaultFileName);

  const blob = new Blob([response.data], { type: 'application/zip' });

  const href = window.URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = href;
  a.download = fileName;
  a.click();
  window.URL.revokeObjectURL(href);
}
