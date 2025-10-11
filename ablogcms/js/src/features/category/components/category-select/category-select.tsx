import { forwardRef, useCallback, useEffect, useId, useMemo, useRef, useState } from 'react';
import { ControlProps, type InputActionMeta, type SelectInstance, components } from 'react-select';
import { debounce } from 'throttle-debounce';
import styled from 'styled-components';
import RichSelect from '../../../../components/rich-select/rich-select';
import useCategoryOptionsSWR from '../../hooks/use-category-options-swr';
import type { CategoryOption, CreatedCategoryDTO } from '../../types';
import CategoryCreateModal from '../category-create-modal/category-create-modal';
import useMergeRefs from '../../../../hooks/use-merge-refs';
import { Tooltip } from '../../../../components/tooltip';
import { isString } from '../../../../utils/typeGuard';

interface CategorySelectProps
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
  defaultValue?: CategoryOption | CategoryOption['value'];
  narrowDown?: boolean;
  isCreatable?: boolean;
  onChange?: (value: CategoryOption | null) => void;
  noOption?: boolean;
  mtOption?: boolean;
}

const CategorySelectContainer = styled.div`
  display: flex;
  gap: 4px;
  align-items: center;
  width: 100%;
`;

const CategorySelectFormArea = styled.div`
  flex: 1;
`;

const CategorySelectControl = (props: ControlProps<CategoryOption, false>) => {
  const id = useId();
  return (
    <div id={`${id}-category-select-control`}>
      <components.Control {...props} />
      <Tooltip
        anchorSelect={`#${CSS.escape(id)}-category-select-control`}
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

const CategorySelectWithoutRef = (
  {
    defaultValue: defaultValueProp,
    narrowDown = false,
    isCreatable = false,
    isClearable = true,
    onChange,
    noOption = false,
    mtOption = false,
    ...props
  }: CategorySelectProps,
  ref: React.ForwardedRef<SelectInstance<CategoryOption, false>>
) => {
  const selectRef = useRef<SelectInstance<CategoryOption, false>>(null);
  const [inputValue, setInputValue] = useState<string>('');
  const [keyword, setKeyword] = useState<string>('');

  const [value, setValue] = useState<CategoryOption | null>(null);
  const [defaultValue, setDefaultValue] = useState<CategoryOption | null>(null);

  const currentCid = useMemo(() => {
    if (value !== null) {
      return parseInt(value.value, 10);
    }
    if (defaultValueProp != null) {
      return isString(defaultValueProp) ? parseInt(defaultValueProp, 10) : parseInt(defaultValueProp.value, 10);
    }
    return null;
  }, [value, defaultValueProp]);
  const { options: apiOptions, isLoading } = useCategoryOptionsSWR({ keyword, narrowDown, currentCid });

  const options = useMemo(() => {
    return [
      ...(mtOption ? [{ value: '-1', label: ACMS.i18n('category.select_mt_option_label') }] : []),
      ...(noOption ? [{ value: '0', label: ACMS.i18n('category.select_no_option_label') }] : []),
      ...(apiOptions || []),
    ];
  }, [apiOptions, mtOption, noOption]);

  const handleChange = useCallback(
    (newValue: CategoryOption | null) => {
      const value = options.find((option) => option.value === newValue?.value) || newValue;
      setValue(value);
      onChange?.(value);
    },
    [onChange, options]
  );

  useEffect(() => {
    if (defaultValue === null && options && options.length > 0) {
      // カテゴリーのデフォルト値を設定
      // カテゴリーのデータ（option）は、サーバーから現在選択しているカテゴリーが含まれていることが保証されている前提
      const defaultValueString = isString(defaultValueProp) ? defaultValueProp : defaultValueProp?.value.toString();

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

  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);

  const handleCreateModalControlButtonClick = useCallback(() => {
    setIsCreateModalOpen(true);
  }, []);

  const handleCloseCreateModal = useCallback(() => {
    setIsCreateModalOpen(false);
  }, []);

  const handleCreate = useCallback((category: CreatedCategoryDTO) => {
    if (selectRef.current) {
      selectRef.current.setValue(
        {
          label: category.name,
          value: category.id.toString(),
        },
        'select-option'
      );
      setIsCreateModalOpen(false);
    }
  }, []);

  return (
    <CategorySelectContainer>
      <CategorySelectFormArea>
        <RichSelect<CategoryOption, false>
          ref={useMergeRefs(selectRef, ref)}
          value={value}
          inputValue={inputValue}
          isSearchable
          isClearable={isClearable}
          options={options}
          placeholder={ACMS.i18n('category.select_placeholder')}
          noOptionsMessage={() => ACMS.i18n('category.select_notfound')}
          isLoading={!!keyword && isLoading}
          onInputChange={handleInputChange}
          onChange={handleChange}
          aria-label={ACMS.i18n('category.select_label')}
          components={{
            Control: CategorySelectControl,
          }}
          filterOption={null}
          {...props}
        />
      </CategorySelectFormArea>
      {isCreatable && (
        <>
          <div id="entry-create-category-display">
            <button type="button" className="acms-admin-btn-admin" onClick={handleCreateModalControlButtonClick}>
              {ACMS.i18n('category.add')}
            </button>
          </div>
          <CategoryCreateModal isOpen={isCreateModalOpen} onClose={handleCloseCreateModal} onCreate={handleCreate} />
        </>
      )}
    </CategorySelectContainer>
  );
};

CategorySelectWithoutRef.displayName = 'CategorySelect';

export default forwardRef(CategorySelectWithoutRef);
