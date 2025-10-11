import useSWR, { preload } from 'swr';
import { useCallback, useMemo } from 'react';
import { isAxiosError } from 'axios';
import { ColumnConfigFailedResponse, fetchColumnConfig, saveColumnConfig } from '../api';
import {
  type ColumnConfig,
  type ColumnConfigMutationErrors,
  type ColumnServiceInterface,
} from '../../../components/dataview/types';
import { type EntryType } from '../types';
import { ENTRY_COLUMN_VISIBILITY, ENTRY_COLUMN_ORDER } from '../constants';

import { isEmptyObject } from '../../../utils/typeGuard';

async function fetcher() {
  const data = await fetchColumnConfig();
  return data;
}

const transformErrors = (errors: ColumnConfigFailedResponse['errors']): ColumnConfigMutationErrors =>
  errors.reduce((acc: ColumnConfigMutationErrors, error: ColumnConfigFailedResponse['errors'][0]) => {
    if (error.field && error.option) {
      if (!acc[error.field]) {
        acc[error.field] = {};
      }
      acc[error.field][error.option] = true;
    }
    return acc;
  }, {});

function getCashKey() {
  return 'entry-column-config';
}

export function preloadColumnConfig() {
  preload(getCashKey(), fetcher);
}

export default function useColumnConfigSWR() {
  const { data, error, isLoading, mutate: _mutate } = useSWR(getCashKey(), fetcher);

  const mutate: ColumnServiceInterface<EntryType>['mutateConfig'] = useCallback(
    async (formData: FormData) => {
      let data: ColumnConfig | null = null;
      try {
        data = (await _mutate<ColumnConfig>(saveColumnConfig(formData))) as ColumnConfig;
      } catch (error) {
        if (isAxiosError<ColumnConfigFailedResponse>(error)) {
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

  const columnVisibility = useMemo<ColumnConfig['visibility']>(() => {
    if (data === undefined) {
      return {};
    }
    if (isEmptyObject(data.visibility)) {
      return ENTRY_COLUMN_VISIBILITY;
    }

    return { ...ENTRY_COLUMN_VISIBILITY, ...data.visibility };
  }, [data]);

  const columnOrder = useMemo<ColumnConfig['order']>(() => {
    if (data === undefined) {
      return [];
    }
    if (data.order.length > 0) {
      return Array.from(new Set([...data.order, ...ENTRY_COLUMN_ORDER]));
    }

    return ENTRY_COLUMN_ORDER;
  }, [data]);

  const config = useMemo<ColumnConfig>(() => {
    return {
      visibility: columnVisibility,
      order: columnOrder,
    };
  }, [columnVisibility, columnOrder]);

  const invalidate = useCallback(async () => {
    await _mutate();
  }, [_mutate]);

  return {
    config,
    isLoading,
    error,
    mutate,
    invalidate,
  };
}
