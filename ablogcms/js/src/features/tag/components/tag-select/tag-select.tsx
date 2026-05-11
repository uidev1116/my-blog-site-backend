import { useCallback, useMemo, useRef, useState } from 'react';
import { SelectInstance } from 'react-select';
import CreatableSelect from '../../../../components/rich-select/creatable-select';
import useTagOptionsSWR from '../../hooks/use-tag-options-swr';
import { TagOption } from '../../types';

const EMPTY_TAG_OPTIONS: TagOption[] = [];

interface TagSelectProps
  extends Partial<
    Pick<
      React.ComponentPropsWithoutRef<typeof CreatableSelect>,
      'id' | 'inputId' | 'isDisabled' | 'form' | 'name' | 'menuPortalTarget'
    >
  > {
  defaultValue?: TagOption[];
  onChange?: (value: TagOption[]) => void;
}

const TagSelect = ({ onChange, defaultValue = EMPTY_TAG_OPTIONS, ...props }: TagSelectProps) => {
  const ref = useRef<SelectInstance<TagOption, true>>(null);
  const { options: apiOptions, isLoading } = useTagOptionsSWR();

  const options = useMemo(() => {
    if (!apiOptions) {
      return defaultValue;
    }

    const mergedValues = new Set([...apiOptions, ...defaultValue].map((option) => option.value));

    return Array.from(mergedValues).map((value) => {
      const apiOption = apiOptions.find((option) => option.value === value);
      const defaultOption = defaultValue.find((option) => option.value === value);
      return apiOption || (defaultOption as TagOption);
    });
  }, [apiOptions, defaultValue]);

  const [value, setValue] = useState<TagOption[]>(defaultValue);

  const handleChange = useCallback(
    (newValue: readonly TagOption[]) => {
      setValue([...newValue]);
      onChange?.([...newValue]);
    },
    [onChange]
  );

  return (
    <CreatableSelect<TagOption, true>
      ref={ref}
      isClearable
      value={value}
      onChange={handleChange}
      options={options}
      isLoading={isLoading}
      isMulti
      closeMenuOnSelect={false}
      placeholder={ACMS.i18n('tag.select_placeholder')}
      noOptionsMessage={() => ACMS.i18n('tag.select_notfound')}
      formatCreateLabel={(inputValue) => ACMS.i18n('tag.add_tag', { name: inputValue })}
      isValidNewOption={(inputValue) => inputValue.trim().length > 0}
      hideSelectedOptions
      {...props}
    />
  );
};

export default TagSelect;
