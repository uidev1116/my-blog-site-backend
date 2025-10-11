import { forwardRef, useCallback, useEffect, useId, useMemo, useRef, useState } from 'react';
import { ControlProps, type InputActionMeta, type SelectInstance, components } from 'react-select';
import { debounce } from 'throttle-debounce';
import { FetchBlogOptionsParams } from '@features/blog/api';
import RichSelect from '../../../../components/rich-select/rich-select';
import useBlogOptionsSWR from '../../hooks/use-blog-options-swr';
import type { BlogOption } from '../../types';
import useMergeRefs from '../../../../hooks/use-merge-refs';
import { Tooltip } from '../../../../components/tooltip';

interface BlogSelectProps
  extends Partial<
      Pick<
        React.ComponentPropsWithoutRef<typeof RichSelect>,
        | 'id'
        | 'inputId'
        | 'isDisabled'
        | 'form'
        | 'name'
        | 'isClearable'
        | 'menuPortalTarget'
        | 'className'
        | 'aria-label'
        | 'aria-labelledby'
        | 'placeholder'
      >
    >,
    Pick<FetchBlogOptionsParams, 'scope'> {
  defaultValue?: BlogOption | BlogOption['value'];
  onChange?: (value: BlogOption | null) => void;
}

const BlogSelectControl = (props: ControlProps<BlogOption, false>) => {
  const id = useId();
  return (
    <div id={`${id}-blog-select-control`}>
      <components.Control {...props} />
      <Tooltip
        anchorSelect={`#${CSS.escape(id)}-blog-select-control`}
        content={props.getValue()?.[0]?.label}
        delayShow={500}
        openEvents={{
          mouseover: true,
          focus: false,
        }}
        closeEvents={{
          mouseout: true,
          blur: false,
        }}
      />
    </div>
  );
};

const BlogSelectWithoutRef = (
  { defaultValue: defaultValueProp = ACMS.Config.bid, isClearable = false, onChange, scope, ...props }: BlogSelectProps,
  ref: React.ForwardedRef<SelectInstance<BlogOption, false>>
) => {
  const selectRef = useRef<SelectInstance<BlogOption, false>>(null);
  const [inputValue, setInputValue] = useState<string>('');
  const [keyword, setKeyword] = useState<string>('');

  const [value, setValue] = useState<BlogOption | null>(null);
  const [defaultValue, setDefaultValue] = useState<BlogOption | null>(null);

  const currentBid = useMemo(() => {
    if (value !== null) {
      return parseInt(value.value, 10);
    }
    if (defaultValueProp != null) {
      return typeof defaultValueProp === 'string'
        ? parseInt(defaultValueProp, 10)
        : parseInt(defaultValueProp.value, 10);
    }
    return null;
  }, [value, defaultValueProp]);

  const { options: apiOptions, isLoading } = useBlogOptionsSWR({ keyword, currentBid, scope });

  const options = useMemo(() => {
    return apiOptions || [];
  }, [apiOptions]);

  const handleChange = useCallback(
    (newValue: BlogOption | null) => {
      const value = options.find((option) => option.value === newValue?.value) || newValue;
      setValue(value);
      onChange?.(value);
    },
    [onChange, options]
  );

  useEffect(() => {
    if (defaultValue === null && options && options.length > 0) {
      const defaultValueString =
        typeof defaultValueProp === 'string' ? defaultValueProp : defaultValueProp?.value.toString();

      setDefaultValue(options.find((option) => option.value === defaultValueString) || null);
    }
  }, [options, defaultValue, defaultValueProp]);

  useEffect(() => {
    setValue(defaultValue);
  }, [defaultValue]);

  const setKeywordDebounced = useRef(debounce(800, (keyword) => setKeyword(keyword))).current;

  const handleInputChange = useCallback(
    (newValue: string, meta: InputActionMeta) => {
      if (!['input-blur', 'menu-close'].includes(meta.action)) {
        setInputValue(newValue);
        setKeywordDebounced(newValue);
      }
    },
    [setKeywordDebounced]
  );

  return (
    <RichSelect<BlogOption, false>
      ref={useMergeRefs(selectRef, ref)}
      value={value}
      inputValue={inputValue}
      isSearchable
      isClearable={isClearable}
      options={options}
      placeholder={ACMS.i18n('blog.select.placeholder')}
      noOptionsMessage={() => ACMS.i18n('blog.select.notfound')}
      isLoading={!!keyword && isLoading}
      onInputChange={handleInputChange}
      onChange={handleChange}
      aria-label={ACMS.i18n('blog.select.label')}
      components={{
        Control: BlogSelectControl,
      }}
      filterOption={null}
      {...props}
    />
  );
};

BlogSelectWithoutRef.displayName = 'BlogSelect';

export default forwardRef(BlogSelectWithoutRef);
