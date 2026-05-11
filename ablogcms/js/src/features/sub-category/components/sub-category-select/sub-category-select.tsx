import { useCallback, useMemo, useState } from 'react';
import RichSelect from '../../../../components/rich-select/rich-select';
import useSubCategoryOptionsSWR from '../../hooks/use-sub-category-options-swr';
import type { SubCategoryOption } from '../../types';

const EMPTY_SUB_CATEGORY_OPTIONS: SubCategoryOption[] = [];

interface SubCategorySelectProps
  extends Partial<
    Pick<
      React.ComponentPropsWithoutRef<typeof RichSelect>,
      'id' | 'inputId' | 'isDisabled' | 'form' | 'name' | 'menuPortalTarget'
    >
  > {
  mainCategoryId?: number;
  defaultValue?: SubCategoryOption[];
  onChange?: (value: SubCategoryOption[]) => void;
  limit?: number;
}

const SubCategorySelect = ({
  mainCategoryId,
  onChange,
  limit,
  defaultValue = EMPTY_SUB_CATEGORY_OPTIONS,
  ...props
}: SubCategorySelectProps) => {
  const { options: apiOptions, isLoading } = useSubCategoryOptionsSWR();

  const options = useMemo(() => {
    if (!apiOptions) {
      return defaultValue;
    }

    const mergedValues = new Set([...defaultValue, ...apiOptions].map((option) => option.value));

    return Array.from(mergedValues).map((value) => {
      const apiOption = apiOptions.find((option) => option.value === value);
      const defaultOption = defaultValue.find((option) => option.value === value);
      return apiOption || (defaultOption as SubCategoryOption);
    });
  }, [apiOptions, defaultValue]);

  const [value, setValue] = useState<SubCategoryOption[]>(defaultValue);

  const handleChange = useCallback(
    (newValue: readonly SubCategoryOption[]) => {
      if (limit && newValue.length > limit) {
        alert(ACMS.i18n('subcategory.select_limit_message', { limit }));
        return;
      }
      setValue([...newValue]);
      onChange?.([...newValue]);
    },
    [limit, onChange]
  );

  const filteredOptions = useMemo(
    () => options?.filter((option) => option.value !== mainCategoryId?.toString()),
    [mainCategoryId, options]
  );

  return (
    <RichSelect<SubCategoryOption, true>
      isClearable
      value={value}
      onChange={handleChange}
      options={filteredOptions}
      isLoading={isLoading}
      isMulti
      isSearchable
      closeMenuOnSelect={false}
      placeholder={ACMS.i18n('subcategory.select_placeholder')}
      noOptionsMessage={() => ACMS.i18n('subcategory.select_notfound')}
      hideSelectedOptions
      {...props}
    />
  );
};

export default SubCategorySelect;
