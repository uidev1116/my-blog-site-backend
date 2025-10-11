import useSWR, { preload } from 'swr';
import { EntriesResponse } from '../types';
import { AcmsContext } from '../../../lib/acmsPath/types';
import axiosLib from '../../../lib/axios';

// eslint-disable-next-line @typescript-eslint/no-explicit-any
async function fetcher(url: string): Promise<EntriesResponse> {
  const { data } = await axiosLib.get(url);
  const {
    ignoredFilters = [],
    entry: entries = [],
    itemsAmount: totalItems = 0,
    bulkActions = [],
    sort = { enabled: false, type: 'entry' },
  } = data;

  return {
    ignoredFilters,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    entries: entries.map((entry: any) => ({
      ...entry,
      datetime: new Date(entry.datetime),
      updated_datetime: new Date(entry.updated_datetime),
      posted_datetime: new Date(entry.posted_datetime),
      start_datetime: new Date(entry.start_datetime),
      end_datetime: new Date(entry.end_datetime),
      actions: entry.actions.map((action: { id: string }) => action.id),
    })),
    totalItems,
    bulkActions,
    sort,
  };
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
function getCashKey(acmsContext: AcmsContext, searchParams: URLSearchParams) {
  return ACMS.Library.acmsLink(
    {
      ...acmsContext,
      tpl: 'ajax/entries.json',
      searchParams,
    },
    { ajaxCacheBusting: false }
  );
}

export function preloadEntries(acmsContext: AcmsContext, searchParams: URLSearchParams) {
  return preload(getCashKey(acmsContext, searchParams), fetcher);
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export default function useEntriesSWR(acmsContext: AcmsContext, searchParams: URLSearchParams) {
  const { data, error, isLoading, mutate } = useSWR(getCashKey(acmsContext, searchParams), fetcher, {
    keepPreviousData: true,
  });

  const invalidate = async () => {
    await mutate();
  };

  return {
    data,
    isLoading,
    error,
    invalidate,
    mutate,
  };
}
