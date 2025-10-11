import useSWR from 'swr';
import { useMemo } from 'react';
import { fetchOptionData } from '../api';
import { CustomAccessorColumn, OptionData } from '../../../components/dataview/types';
import { ENTRY_OPTION_DATA } from '../constants';
import { EntryType } from '../types';
import { getColumnAccessorKey } from '../../../components/dataview/utils';

async function fetcher(keys: string[]) {
  const data = await fetchOptionData(keys);
  return data;
}

export default function useOptionDataSWR(columns: CustomAccessorColumn<EntryType>[]) {
  const keys = useMemo(() => columns.map((column) => getColumnAccessorKey(column)), [columns]);
  const {
    data: apiOptionData,
    error,
    isLoading,
  } = useSWR(keys.length > 0 ? ['entry-option-data', keys] : null, ([, keys]) => fetcher(keys));

  const optionData: OptionData<EntryType> = useMemo(() => {
    if (apiOptionData === undefined) {
      return ENTRY_OPTION_DATA;
    }

    return {
      ...ENTRY_OPTION_DATA,
      ...apiOptionData,
    };
  }, [apiOptionData]);

  return {
    optionData,
    isLoading,
    error,
  };
}
