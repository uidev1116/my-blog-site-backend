import { fetchClient } from '../../../lib/fetch-client';
import type { RelatedEntryOption } from '../types';

export interface FetchRelatedEntryOptionsParams {
  keyword: string;
  moduleId: string;
  ctx: string;
  thumbnail?: string;
}

export async function fetchRelatedEntryOptions(params: FetchRelatedEntryOptionsParams): Promise<RelatedEntryOption[]> {
  const endpoint = ACMS.Library.acmsLink(
    {
      tpl: 'ajax/edit/autocomplete.json',
      bid: ACMS.Config.bid,
    },
    false
  );
  const { data: options = [] } = await fetchClient.get<RelatedEntryOption[]>(endpoint, {
    params: { ...params },
  });
  return options.map((options) => ({
    ...options,
    value: options.id.toString(),
  }));
}
