import axiosLib from '../../../lib/axios';

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

  await axiosLib({
    method: 'POST',
    url: window.location.href,
    data: params,
  });
};

/**
 * エントリーがロック状態か確認する
 */
export const checkEntryLock = async () => {
  const params = new URLSearchParams();
  params.append('ACMS_POST_Entry_Lock_Check', 'true');
  params.append('rvid', ACMS.Config.rvid || '0');
  params.append('eid', ACMS.Config.eid || '0');
  params.append('formToken', window.csrfToken);

  const response = await axiosLib({
    method: 'POST',
    url: window.location.href,
    responseType: 'json',
    data: params,
  });
  const json = response.data;
  if (response.status === 200) {
    return json;
  }
  return {
    locked: false,
  };
};
