import { forwardRef, useCallback, useMemo, useRef, useState } from 'react';
import type { InputActionMeta, SelectInstance } from 'react-select';
import { debounce } from 'throttle-debounce';
import RichSelect from '../../../../components/rich-select/rich-select';
import useRelatedEntryOptionsSWR from '../../hooks/use-related-entry-options-swr';
import type { RelatedEntryOption } from '../../types';

interface RelatedEntrySelectProps
  extends Partial<
    Pick<React.ComponentPropsWithoutRef<typeof RichSelect>, 'id' | 'inputId' | 'isDisabled' | 'form' | 'name'>
  > {
  value: RelatedEntryOption[];
  moduleId: string;
  ctx: string;
  thumbnail?: string;
  onChange?: (value: RelatedEntryOption | null) => void;
}

const RelatedEntrySelect = (
  { value, moduleId, ctx, thumbnail, onChange, ...props }: RelatedEntrySelectProps,
  ref: React.ForwardedRef<SelectInstance<RelatedEntryOption, false>>
) => {
  const [inputValue, setInputValue] = useState<string>('');
  const [keyword, setKeyword] = useState<string>('');
  const { options, isLoading } = useRelatedEntryOptionsSWR(keyword ? { moduleId, ctx, keyword, thumbnail } : null);

  const handleChange = useCallback(
    (newValue: RelatedEntryOption | null) => {
      onChange?.(newValue);
    },
    [onChange]
  );

  const setKeywordDebounced = useRef(debounce(800, (keyword) => setKeyword(keyword))).current;

  const handleInputChange = useCallback(
    (newValue: string, meta: InputActionMeta) => {
      if (!['input-blur', 'menu-close', 'set-value'].includes(meta.action)) {
        // ↑’set-value’ がない場合、値が選択されたときに inputValue がクリアされてしまう
        setInputValue(newValue);
        setKeywordDebounced(newValue);
      }
    },
    [setKeywordDebounced]
  );

  const filteredOptions = useMemo(
    () => options?.filter((option) => !value.some((entry) => entry.id === option.id)),
    [options, value]
  );

  return (
    <RichSelect<RelatedEntryOption, false>
      ref={ref}
      inputValue={inputValue}
      controlShouldRenderValue={false}
      isSearchable
      closeMenuOnSelect={false}
      closeMenuOnScroll={false}
      options={filteredOptions}
      placeholder={ACMS.i18n('related_entry.placeholder')}
      filterOption={null}
      noOptionsMessage={() =>
        keyword ? ACMS.i18n('related_entry.no_options_message') : ACMS.i18n('related_entry.type_to_search')
      }
      isLoading={!!keyword && isLoading}
      onInputChange={handleInputChange}
      onChange={handleChange}
      {...props}
    />
  );
};

RelatedEntrySelect.displayName = 'RelatedEntrySelect';

export default forwardRef(RelatedEntrySelect);
