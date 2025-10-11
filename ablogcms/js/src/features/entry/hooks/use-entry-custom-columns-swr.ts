import useSWR, { preload } from 'swr';
import { useCallback } from 'react';
import { isAxiosError } from 'axios';
import { CustomColumnsFailedResponse, fetchCustomColumns, saveCustomColumns } from '../api';
import {
  CustomAccessorColumn,
  CustomColumnMutationErrors,
  ColumnServiceInterface,
} from '../../../components/dataview/types';
import { EntryType } from '../types';

async function fetcher() {
  const data = await fetchCustomColumns();
  return data;
}

const transformErrors = (errors: CustomColumnsFailedResponse['errors']): CustomColumnMutationErrors =>
  errors.reduce((acc: CustomColumnMutationErrors, error: CustomColumnsFailedResponse['errors'][0]) => {
    if (error.field && error.option) {
      if (!acc[error.field]) {
        acc[error.field] = {};
      }
      acc[error.field][error.option] = true;
    }
    return acc;
  }, {});

export function getCashKey() {
  return 'entry-custom-culumns';
}

export function preloadCustomColumns() {
  return preload(getCashKey(), fetcher);
}

export default function useEntryCustomColumnsSWR() {
  const { data: customColumns, error, isLoading, mutate: _mutate } = useSWR(getCashKey(), fetcher);

  const mutate: ColumnServiceInterface<EntryType>['mutateCustomColumns'] = useCallback(
    async (formData: FormData) => {
      let data: CustomAccessorColumn<EntryType>[] = [];
      try {
        data = (await _mutate(saveCustomColumns(formData))) || [];
      } catch (error) {
        if (isAxiosError<CustomColumnsFailedResponse>(error)) {
          if (error.response?.data.errors) {
            const errors = transformErrors(error.response.data.errors);
            return { errors };
          }
        }
        console.error(error); // eslint-disable-line no-console
      }
      return { data };
    },
    [_mutate]
  );

  const invalidate = useCallback(async () => {
    await _mutate();
  }, [_mutate]);

  return {
    customColumns,
    isLoading,
    error,
    mutate,
    invalidate,
  };
}
