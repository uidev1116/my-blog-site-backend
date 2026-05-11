import { fetchClient } from '../../../lib/fetch-client';
import type { UserOption } from '../types';

export interface FetchUserOptionsParams {
  keyword?: string;
  currentUid?: number | null;
}

export async function fetchUserOptions(params?: FetchUserOptionsParams): Promise<UserOption[]> {
  const endpoint = ACMS.Library.acmsLink(
    {
      bid: ACMS.Config.bid,
      tpl: 'ajax/edit/user-assist.json',
      searchParams: {
        keyword: params?.keyword,
        currentUid: params?.currentUid,
      },
    },
    false
  );
  const { data: options = [] } = await fetchClient.get<UserOption[]>(endpoint);
  return options;
}
