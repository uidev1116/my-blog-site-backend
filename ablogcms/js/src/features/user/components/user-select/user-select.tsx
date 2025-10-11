import { forwardRef, useCallback, useEffect, useId, useMemo, useRef, useState } from 'react';
import { ControlProps, type InputActionMeta, type SelectInstance, components } from 'react-select';
import { debounce } from 'throttle-debounce';
import RichSelect from '../../../../components/rich-select/rich-select';
import useUserOptionsSWR from '../../hooks/use-user-options-swr';
import type { UserOption } from '../../types';
import useMergeRefs from '../../../../hooks/use-merge-refs';
import { Tooltip } from '../../../../components/tooltip';

interface UserSelectProps
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
  > {
  defaultValue?: UserOption | UserOption['value'];
  onChange?: (value: UserOption | null) => void;
}

const UserSelectControl = (props: ControlProps<UserOption, false>) => {
  const id = useId();
  return (
    <div id={`${id}-user-select-control`}>
      <components.Control {...props} />
      <Tooltip
        anchorSelect={`#${CSS.escape(id)}-user-select-control`}
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

const UserSelectWithoutRef = (
  { defaultValue: defaultValueProp, isClearable = true, onChange, ...props }: UserSelectProps,
  ref: React.ForwardedRef<SelectInstance<UserOption, false>>
) => {
  const selectRef = useRef<SelectInstance<UserOption, false>>(null);
  const [inputValue, setInputValue] = useState<string>('');
  const [keyword, setKeyword] = useState<string>('');

  const [value, setValue] = useState<UserOption | null>(null);
  const [defaultValue, setDefaultValue] = useState<UserOption | null>(null);

  const currentUid = useMemo(() => {
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

  const { options: apiOptions, isLoading } = useUserOptionsSWR({ keyword, currentUid });

  const options = useMemo(() => {
    return apiOptions || [];
  }, [apiOptions]);

  const handleChange = useCallback(
    (newValue: UserOption | null) => {
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
    <RichSelect<UserOption, false>
      ref={useMergeRefs(selectRef, ref)}
      value={value}
      inputValue={inputValue}
      isSearchable
      isClearable={isClearable}
      options={options}
      placeholder={ACMS.i18n('user.select.placeholder')}
      noOptionsMessage={() => ACMS.i18n('user.select.notfound')}
      isLoading={!!keyword && isLoading}
      onInputChange={handleInputChange}
      onChange={handleChange}
      aria-label={ACMS.i18n('user.select.label')}
      components={{
        Control: UserSelectControl,
      }}
      filterOption={null}
      {...props}
    />
  );
};

UserSelectWithoutRef.displayName = 'UserSelect';

export default forwardRef(UserSelectWithoutRef);
