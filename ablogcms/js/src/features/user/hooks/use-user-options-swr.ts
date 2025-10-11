import useSWRImuutable from 'swr/immutable';
import { type FetchUserOptionsParams, fetchUserOptions } from '../api';

async function fetcher({ params }: { params: FetchUserOptionsParams }) {
  const data = await fetchUserOptions(params);
  return data;
}

const getCacheKey = (params: FetchUserOptionsParams) => ({ id: 'user-options', params });

export default function useUserOptionsSWR(params: FetchUserOptionsParams | null) {
  const { data: options, error, isLoading } = useSWRImuutable(params ? getCacheKey(params) : null, fetcher);

  return {
    options,
    isLoading,
    error,
  };
}
