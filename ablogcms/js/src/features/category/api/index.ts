import { fetchClient, FetchError } from '../../../lib/fetch-client';
import type { CategoryOption, CreatedCategoryDTO } from '../types';

export interface FetchCategoryOptionsParams {
  keyword: string;
  narrowDown: boolean;
  currentCid?: number | null;
}

export async function fetchCategoryOptions(params: FetchCategoryOptionsParams): Promise<CategoryOption[]> {
  const endpoint = ACMS.Library.acmsLink(
    {
      bid: ACMS.Config.bid,
      cid: ACMS.Config.cid,
      keyword: params.keyword,
      tpl: 'ajax/edit/category-assist.json',
      Query: {
        narrowDown: params.narrowDown ? 'true' : 'false',
        ...(params.currentCid && { currentCid: params.currentCid.toString() }),
      },
    },
    false
  );
  const { data: options = [] } = await fetchClient.get<CategoryOption[]>(endpoint);
  return options;
}

export interface CreateCategorySuccessResponse {
  status: 'success';
  category: CreatedCategoryDTO;
}

export interface CreateCategoryFailedResponse {
  status: 'failure';
  errors: {
    field: string;
    option: string;
  }[];
}

export type CreateCategoryResponse = CreateCategorySuccessResponse | CreateCategoryFailedResponse;

export async function createCategory(formData: FormData) {
  formData.append('ACMS_POST_Category_Insert', 'exec');
  const endpoint = ACMS.Library.acmsLink(
    {
      tpl: 'ajax/edit/category-add-response.json',
    },
    true
  );
  const response = await fetchClient.post<CreateCategoryResponse>(endpoint, formData);
  if (response.data.status === 'failure') {
    throw new FetchError('Failed to create category.', response.status, response.statusText, response);
  }
  return response.data.category;
}
