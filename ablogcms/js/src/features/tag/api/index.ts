import { fetchClient } from '../../../lib/fetch-client';
import type { TagOption } from '../types';

export async function fetchTagOptions(): Promise<TagOption[]> {
  const endpoint = ACMS.Library.acmsLink(
    {
      bid: ACMS.Config.bid,
      tpl: 'ajax/edit/tag-assist.json',
    },
    false
  );
  const { data: options = [] } = await fetchClient.get<TagOption[]>(endpoint);
  return options;
}
