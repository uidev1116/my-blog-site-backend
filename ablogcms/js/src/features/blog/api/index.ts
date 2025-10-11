import axiosClient from '../../../lib/axios';
import type { BlogOption } from '../types';

export interface FetchBlogOptionsParams {
  scope?: 'currentBlog' | 'loggedInUserBlog';
  keyword?: string;
  currentBid?: number | null;
}

export async function fetchBlogOptions(params?: FetchBlogOptionsParams): Promise<BlogOption[]> {
  const endpoint = ACMS.Library.acmsLink(
    {
      bid: params?.scope === 'loggedInUserBlog' ? ACMS.Config.sbid : ACMS.Config.bid,
      tpl: 'ajax/edit/blog-assist.json',
      searchParams: {
        keyword: params?.keyword,
        currentBid: params?.currentBid,
      },
    },
    false
  );
  const { data: options = [] } = await axiosClient.get<BlogOption[]>(endpoint);
  return options;
}
