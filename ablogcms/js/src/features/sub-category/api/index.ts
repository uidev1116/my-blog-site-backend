import { fetchClient } from '../../../lib/fetch-client';
import type { SubCategoryOption } from '../types';

export async function fetchSubCategoryOptions(): Promise<SubCategoryOption[]> {
  const endpoint = ACMS.Library.acmsLink(
    {
      bid: ACMS.Config.bid,
      cid: ACMS.Config.cid,
      tpl: 'ajax/edit/sub-category-assist.json',
    },
    false
  );
  const { data: options = [] } = await fetchClient.get<SubCategoryOption[]>(endpoint);
  return options;
}
