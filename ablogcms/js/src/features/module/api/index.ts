import dayjs from 'dayjs';
import { fetchClient, FetchError } from '../../../lib/fetch-client';
import { getFileNameFromContentDisposition } from '../../../utils';

interface ActionResponse {
  status: 'success' | 'error';
}

export async function bulkChangeStatus(formData: FormData): Promise<void> {
  const url = new URL(ACMS.Library.acmsPath({ tpl: 'ajax/action.json' }), window.location.href);
  formData.append('ACMS_POST_Module_Index_Status', 'exec');
  formData.append('formToken', window.csrfToken);

  const response = await fetchClient.post<ActionResponse>(url.toString(), formData);

  if (response.data.status === 'error') {
    throw new FetchError('Failed to bulk change status.', response.status, response.statusText, response);
  }
}

export async function bulkChangeBlog(formData: FormData): Promise<void> {
  const url = new URL(ACMS.Library.acmsPath({ tpl: 'ajax/action.json' }), window.location.href);
  formData.append('ACMS_POST_Module_Index_Blog', 'exec');
  formData.append('formToken', window.csrfToken);

  const response = await fetchClient.post<ActionResponse>(url.toString(), formData);

  if (response.data.status === 'error') {
    throw new FetchError('Failed to bulk change blog.', response.status, response.statusText, response);
  }
}

export async function bulkDelete(formData: FormData): Promise<void> {
  const url = new URL(ACMS.Library.acmsPath({ tpl: 'ajax/action.json' }), window.location.href);
  formData.append('ACMS_POST_Module_Index_Delete', 'exec');
  formData.append('formToken', window.csrfToken);

  const response = await fetchClient.post<ActionResponse>(url.toString(), formData);

  if (response.data.status === 'error') {
    throw new FetchError('Failed to bulk delete.', response.status, response.statusText, response);
  }
}

export async function exportModules(formData: FormData): Promise<void> {
  formData.append('ACMS_POST_Module_Index_Export', 'exec');
  formData.append('formToken', window.csrfToken);

  const response = await fetchClient.post<Blob>(window.location.href, formData, {
    responseType: 'blob',
  });

  // サーバーから送信されたContent-Dispositionヘッダーからファイル名を取得
  const contentDisposition = response.headers.get('content-disposition') || '';
  const defaultFileName = `config_${dayjs().format('YYYYMMDD_HHmm')}.yaml`;
  const fileName = getFileNameFromContentDisposition(contentDisposition, defaultFileName);

  const blob = new Blob([response.data], { type: 'application/x-yaml' });

  const url = window.URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = fileName;
  a.click();
  window.URL.revokeObjectURL(url);
}
