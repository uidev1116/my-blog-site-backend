import useSWRImuutable from 'swr/immutable';
import { type FetchBlogOptionsParams, fetchBlogOptions } from '../api';

async function fetcher({ params }: { params: FetchBlogOptionsParams }) {
  const data = await fetchBlogOptions(params);
  return data;
}

const getCacheKey = (params: FetchBlogOptionsParams) => ({ id: 'blog-options', params });

export default function useBlogOptionsSWR(params: FetchBlogOptionsParams | null) {
  const { data: options, error, isLoading } = useSWRImuutable(params ? getCacheKey(params) : null, fetcher);

  return {
    options,
    isLoading,
    error,
  };
}
