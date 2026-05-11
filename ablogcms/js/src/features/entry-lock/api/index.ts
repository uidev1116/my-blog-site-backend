import { fetchClient } from '../../../lib/fetch-client';

/**
 * エントリーの排他制御
 * エントリーが編集中であることをPOSTする
 */
export const lockEntry = async () => {
  const params = new URLSearchParams();
  params.append('ACMS_POST_Entry_Lock_Exec', 'true');
  params.append('rvid', ACMS.Config.rvid || '0');
  params.append('eid', ACMS.Config.eid || '0');
  params.append('formToken', window.csrfToken);

  await fetchClient.post(window.location.href, params);
};

/**
 * エントリーがロック状態か確認する
 */
interface EntryLockInfo {
  locked: true;
  name: string;
  icon: string;
  datetime: string;
  expire: string;
  viewLink: string;
  alertOnly: boolean;
}

interface EntryNotLocked {
  locked: false;
}

type EntryLockCheckResponse = EntryLockInfo | EntryNotLocked;

export const checkEntryLock = async (): Promise<EntryLockCheckResponse> => {
  const params = new URLSearchParams();
  params.append('ACMS_POST_Entry_Lock_Check', 'true');
  params.append('rvid', ACMS.Config.rvid || '0');
  params.append('eid', ACMS.Config.eid || '0');
  params.append('formToken', window.csrfToken);

  const response = await fetchClient.post<EntryLockCheckResponse>(window.location.href, params);
  const json = response.data;
  if (response.status === 200) {
    return json;
  }
  return {
    locked: false,
  };
};
