import { fetchClient } from '../../../lib/fetch-client';
import type { WebhookEventOption } from '../types';

export async function fetchWebhookEventOptions(type: string): Promise<WebhookEventOption[]> {
  const endpoint = ACMS.Library.acmsLink(
    {
      bid: ACMS.Config.bid,
      tpl: 'ajax/edit/webhook-event.json',
      Query: {
        type,
      },
    },
    false
  );
  const { data } = await fetchClient.get<WebhookEventOption[]>(endpoint);
  return data;
}
