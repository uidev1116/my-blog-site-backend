import useSWR, { preload } from 'swr';
import { ModulesResponse } from '../types';
import { AcmsContext } from '../../../lib/acmsPath/types';
import axiosClient from '../../../lib/axios';

async function fetcher(url: string): Promise<ModulesResponse> {
  const { data } = await axiosClient.get(url);

  const { modules = [], bulkActions = [] } = data;

  return {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    modules: modules.map((module: any) => ({
      ...module,
      created_datetime: module.created_datetime ? new Date(module.created_datetime) : null,
      updated_datetime: module.updated_datetime ? new Date(module.updated_datetime) : null,
      actions: module.actions.map((action: { id: string }) => action.id),
    })),
    bulkActions,
  };
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
function getCashKey(acmsContext: AcmsContext, searchParams: URLSearchParams) {
  return ACMS.Library.acmsLink(
    {
      ...acmsContext,
      tpl: 'ajax/modules.json',
      searchParams,
    },
    { ajaxCacheBusting: false }
  );
}

export function preloadModules(acmsContext: AcmsContext, searchParams: URLSearchParams) {
  return preload(getCashKey(acmsContext, searchParams), fetcher);
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export default function useModulesSWR(acmsContext: AcmsContext, searchParams: URLSearchParams) {
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
